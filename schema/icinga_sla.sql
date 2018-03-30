-- --------------------------------------------------- --
-- SLA function for Icinga/IDO                         --
--                                                     --
-- Author    : Icinga Developer Team <info@icinga.org> --
-- Copyright : 2012 Icinga Developer Team              --
-- License   : GPL 2.0                                 --
-- --------------------------------------------------- --

--
-- History
-- 
-- 2012-08-31: Added to Icinga
-- 2013-08-20: Simplified and improved
-- 2013-08-23: Refactored, added SLA time period support
--

DELIMITER |

DROP FUNCTION IF EXISTS icinga_availability_slatime|
CREATE FUNCTION icinga_availability_slatime (
  id BIGINT UNSIGNED,
  start DATETIME,
  end DATETIME,
  sla_timeperiod_id BIGINT UNSIGNED
) RETURNS DECIMAL(7, 4)
  READS SQL DATA
BEGIN
  DECLARE availability DECIMAL(7, 4);
  DECLARE dummy_id BIGINT UNSIGNED;

  SELECT @type_id := objecttype_id INTO dummy_id FROM icinga_objects WHERE object_id = id;

  IF @type_id NOT IN (1, 2) THEN
    RETURN NULL;
  END IF;

  -- We'll use @-Vars, this allows easy testing of subqueries without a function
  SET @former_id         := @id,
      @tp_lastday        := -1,
      @tp_lastend        := 0,
      @former_sla_timeperiod_id := @sla_timeperiod_id,
      @former_start      := @start,
      @former_end        := @end,
      @sla_timeperiod_id := sla_timeperiod_id,
      @id                := id,
      @start             := start,
      @end               := end,
      @day_offset        := -1,
      @last_state        := NULL,
      @last_ts           := NULL,
      @cnt_dt            := NULL,
      @cnt_tp            := NULL,
      @add_duration      := NULL;


SELECT
  CAST(SUM(
    (duration) -- why??
    /
    -- ... divided through the chosen time period duration...
    (UNIX_TIMESTAMP(@end) - UNIX_TIMESTAMP(@start))
    -- ...multiplying the result with 100 (%)...
    * 100
    -- ...ignoring all but OK, WARN or UP states...
    * IF (@type_id = 1, IF(current_state = 0, 1, 0), IF (current_state < 2, 1, 0))
  ) AS DECIMAL(7, 4))

-- START fetching the whole normalized eventhistory with durations
INTO availability FROM ( SELECT

  CASE WHEN @last_ts IS NULL THEN
    -- ...remember the duration and return 0...
    (@add_duration := COALESCE(@add_duration, 0)
      + UNIX_TIMESTAMP(state_time)
      - UNIX_TIMESTAMP(COALESCE(@last_ts, @start)) + 1
    ) - 1
  ELSE
    -- ...otherwise return a correct duration...
    UNIX_TIMESTAMP(state_time)
      - UNIX_TIMESTAMP(COALESCE(@last_ts, @start))
      -- ...and don't forget to add what we remembered 'til now:
      + COALESCE(CASE @cnt_dt + @cnt_tp WHEN 0 THEN @add_duration ELSE NULL END, 0)
  END AS duration,

  -- current_state is the state from the last state change until now:
  CASE WHEN @cnt_dt + @cnt_tp >= 1 THEN 0 ELSE COALESCE(@last_state, last_state) END AS current_state,

  -- next_state is the state from now on, so it replaces @last_state:
  CASE
    -- Set our next @last_state if we have a hard state change
    WHEN type IN ('hard_state', 'former_state', 'current_state') THEN @last_state := state
    -- ...or if there is a soft_state and no @last_state has been seen before
    WHEN type = 'soft_state' THEN
      -- If we don't have a @last_state...
      CASE WHEN @last_state IS NULL
      -- ...use and set our own last_hard_state (last_state is an alias here)...
      THEN @last_state := last_state
      -- ...and return @last_state otherwise, as soft states shall have no
      -- impact on availability
      ELSE @last_state END

    WHEN type IN ('dt_start', 'sla_end') THEN 0
    WHEN type IN ('dt_end', 'sla_start') THEN @last_state
  END AS next_state,

  -- Then set @add_duration to NULL in case we got a new @last_ts
  COALESCE(
    CASE WHEN @add_duration IS NOT NULL AND @cnt_dt = 0 AND @cnt_tp = 0
      THEN @add_duration
      ELSE @add_duration := null
    END,
    0
  ) AS addd,

  -- First raise or lower our downtime counter
  -- TODO: Distinct counters for sla and dt, they are not related to each other
  CASE type
    WHEN 'dt_start' THEN @cnt_dt := COALESCE(@cnt_dt, 0) + 1
    WHEN 'dt_end' THEN @cnt_dt := GREATEST(@cnt_dt - 1, 0)
    WHEN 'sla_end' THEN @cnt_tp := COALESCE(@cnt_tp, 0) + 1
    WHEN 'sla_start' THEN @cnt_tp := GREATEST(@cnt_tp - 1, 0)
    ELSE @cnt_dt + @cnt_tp -- UGLY
  END AS dt_depth,

  -- Also fetch the event type
  type,

  -- Our start_time is either the last end_time or @start...
  COALESCE(@last_ts, @start) AS start_time,

  -- ...end when setting the new end_time we remember it in @last_ts:
  @last_ts := state_time AS end_time

FROM (

  -- START fetching statehistory events
  SELECT
     state_time,
     CASE state_type WHEN 1 THEN 'hard_state' ELSE 'soft_state' END AS type,
     state,
     -- Workaround for a nasty Icinga issue. In case a hard state is reached
     -- before max_check_attempts, the last_hard_state value is wrong. As of
     -- this we are stepping through all single events, even soft ones. Of
     -- course soft states do not have an influence on the availability:
     CASE state_type WHEN 1 THEN last_state ELSE last_hard_state END AS last_state
  FROM icinga_statehistory
  WHERE object_id = @id
    AND state_time >= @start
    AND state_time <= @end
  -- STOP fetching statehistory events

  -- START fetching last state BEFORE the given interval as an event
  UNION SELECT * FROM (
    SELECT
      @start AS state_time,
      'former_state' AS type,
      CASE state_type WHEN 1 THEN state ELSE last_hard_state END AS state,
      CASE state_type WHEN 1 THEN last_state ELSE last_hard_state END AS last_state
    FROM icinga_statehistory h
    WHERE object_id = @id
      AND state_time < @start
    ORDER BY h.state_time DESC LIMIT 1
  ) formerstate
  -- END fetching last state BEFORE the given interval as an event

  -- START fetching first state AFTER the given interval as an event
  UNION SELECT * FROM (
    SELECT
      @end AS state_time,
      'future_state' AS type,
      CASE state_type WHEN 1 THEN last_state ELSE last_hard_state END AS state,
      CASE state_type WHEN 1 THEN state ELSE last_hard_state END AS last_state
    FROM icinga_statehistory h
    WHERE object_id = @id
      AND state_time > @end
    ORDER BY h.state_time ASC LIMIT 1
  ) futurestate
  -- END fetching first state AFTER the given interval as an event

  -- START ADDING a fake end
  UNION SELECT
    @end AS state_time,
    'dt_start' AS type,
    NULL AS state,
    NULL AS last_state
  FROM DUAL
  -- END ADDING a fake end

  -- START fetching current host state as an event
  -- TODO: This is not 100% correct. state should be find, last_state sometimes isn't.
  UNION SELECT 
    GREATEST(
      @start,
      CASE state_type WHEN 1 THEN last_state_change ELSE last_hard_state_change END
    ) AS state_time,
    'current_state' AS type,
    CASE state_type WHEN 1 THEN current_state ELSE last_hard_state END AS state,
    last_hard_state AS last_state
  FROM icinga_hoststatus
  WHERE CASE state_type WHEN 1 THEN last_state_change ELSE last_hard_state_change END < @start
    AND host_object_id = @id
    AND CASE state_type WHEN 1 THEN last_state_change ELSE last_hard_state_change END <= @end
    AND status_update_time > @start
  -- END fetching current host state as an event

  -- START fetching current service state as an event
  UNION SELECT 
    GREATEST(
      @start,
      CASE state_type WHEN 1 THEN last_state_change ELSE last_hard_state_change END
    ) AS state_time,
    'current_state' AS type,
    CASE state_type WHEN 1 THEN current_state ELSE last_hard_state END AS state,
    last_hard_state AS last_state
  FROM icinga_servicestatus
  WHERE CASE state_type WHEN 1 THEN last_state_change ELSE last_hard_state_change END < @start
    AND service_object_id = @id
    AND CASE state_type WHEN 1 THEN last_state_change ELSE last_hard_state_change END <= @end
    AND status_update_time > @start
  -- END fetching current service state as an event

  -- START adding add all related downtime start times
  -- TODO: Handling downtimes still being active would be nice.
  --       But pay attention: they could be completely outdated
  UNION SELECT
    GREATEST(actual_start_time, @start) AS state_time,
    'dt_start' AS type,
    NULL AS state,
    NULL AS last_state
  FROM icinga_downtimehistory
  WHERE object_id = @id
    AND actual_start_time < @end
    AND actual_end_time > @start
  -- STOP adding add all related downtime start times

  -- START adding add all related downtime end times
  UNION SELECT
    LEAST(actual_end_time, @end) AS state_time,
    'dt_end' AS type,
    NULL AS state,
    NULL AS last_state
  FROM icinga_downtimehistory
  WHERE object_id = @id
    AND actual_start_time < @end
    AND actual_end_time > @start
  -- STOP adding add all related downtime end times

  -- START fetching SLA time period start times ---
  -- TODO: * reset @tp_*??
  --       * find better sub table aliases
  UNION SELECT
    DATE_ADD(CAST(monthly.date AS DATETIME), INTERVAL finaltps.start_sec SECOND) AS state_time,
    'sla_start' AS type,
    NULL AS state,
    NULL AS last_state
  FROM (
    -- there is no generate_series or similar in MySQL
    SELECT
      DATE_ADD(DATE(@start), INTERVAL @day_offset := @day_offset + 1 DAY) AS date,
      DAYOFWEEK(DATE_ADD(DATE(@start), INTERVAL @day_offset DAY)) - 1 AS weekday
    FROM icinga_objects
    JOIN (SELECT @day_offset := -1) day_offset
    LIMIT 1461
  ) monthly JOIN (
    SELECT
      NULL AS day,
      NULL as start_sec,
      NULL AS end_sec
    FROM DUAL
    WHERE (@tp_lastday := NULL) IS NOT NULL
      AND ((@tp_lastend := 0) + (@day_offset := -1)) = 1

    UNION SELECT
      cleantps.day,
      cleantps.start_sec,
      cleantps.end_sec
    FROM (
      SELECT
        @tp_lastday AS last_day,
        sortedtps.day AS day,
        CASE sortedtps.day WHEN @tp_lastday THEN @tp_lastend ELSE (@tp_lastday := sortedtps.day) - sortedtps.day END start_sec,
        sortedtps.start_sec + (@tp_lastend := sortedtps.end_sec) * 0 AS end_sec
      FROM (
        SELECT
          singletps.day,
          singletps.start_sec,
          singletps.end_sec
        FROM (
          SELECT
            @day_offset := CASE
              WHEN @day_offset < 6 AND @day_offset >= 0 AND @day_offset IS NOT NULL
              THEN @day_offset + 1
              ELSE 0
            END AS day,
            0 AS start_sec,
            0 AS end_sec
          FROM icinga_objects LIMIT 7
          UNION SELECT
            @day_offset := CASE
              WHEN @day_offset < 6 AND @day_offset >= 0 AND @day_offset IS NOT NULL 
              THEN @day_offset + 1
              ELSE 0
            END AS day,
            86400 AS start_sec,
            86400 AS end_sec
          FROM icinga_objects LIMIT 7
          UNION SELECT
            day,
            start_sec AS start_sec,
            end_sec AS end_sec
          FROM icinga_timeperiod_timeranges tpr
          WHERE tpr.timeperiod_id = @sla_timeperiod_id
        ) singletps
        ORDER BY singletps.day, singletps.start_sec, singletps.end_sec
      ) sortedtps
    ) cleantps
    WHERE cleantps.end_sec > cleantps.start_sec
  ) finaltps ON finaltps.day = monthly.weekday
  WHERE DATE_ADD(CAST(monthly.date AS DATETIME), INTERVAL finaltps.end_sec - 1 SECOND) <= @end
  -- STOP fetching SLA time period start times ---

  -- START fetching SLA time period end times ---
  UNION SELECT
    DATE_ADD(CAST(monthly.date AS DATETIME), INTERVAL finaltps.end_sec SECOND) AS state_time,
    'sla_end' AS type,
    NULL AS state,
    NULL AS last_state
  FROM (
    SELECT
      DATE_ADD(DATE(@start), INTERVAL @day_offset := @day_offset + 1 DAY) AS date,
      DAYOFWEEK(DATE_ADD(DATE(@start), INTERVAL @day_offset DAY)) - 1 AS weekday
    FROM icinga_objects
    JOIN (SELECT @day_offset := -1) day_offset
    LIMIT 1461
  ) monthly JOIN (
    SELECT
      NULL AS day,
      NULL as start_sec,
      NULL AS end_sec
    FROM DUAL
    WHERE (@tp_lastday := NULL) IS NOT NULL
      AND ((@tp_lastend := 0) + (@day_offset := -1)) = 1

    UNION SELECT
      cleantps.day,
      cleantps.start_sec,
      cleantps.end_sec
    FROM (
      SELECT
        @tp_lastday AS last_day,
        sortedtps.day AS day,
        CASE sortedtps.day WHEN @tp_lastday THEN @tp_lastend ELSE (@tp_lastday := sortedtps.day) - sortedtps.day END start_sec,
        sortedtps.start_sec + (@tp_lastend := sortedtps.end_sec) * 0 AS end_sec
      FROM (
        SELECT
          singletps.day,
          singletps.start_sec,
          singletps.end_sec
        FROM (
          SELECT
            @day_offset := CASE
              WHEN @day_offset < 6 AND @day_offset >= 0 AND @day_offset IS NOT NULL
              THEN @day_offset + 1
              ELSE 0
            END AS day,
            0 AS start_sec,
            0 AS end_sec
          FROM icinga_objects LIMIT 7
          UNION SELECT
            @day_offset := CASE
              WHEN @day_offset < 6 AND @day_offset >= 0 AND @day_offset IS NOT NULL
              THEN @day_offset + 1
              ELSE 0
            END AS day,
            86400 AS start_sec,
            86400 AS end_sec
          FROM icinga_objects LIMIT 7
          UNION SELECT
            day,
            start_sec AS start_sec,
            end_sec AS end_sec
          FROM icinga_timeperiod_timeranges tpr
          WHERE tpr.timeperiod_id = @sla_timeperiod_id
        ) singletps
        ORDER BY singletps.day, singletps.start_sec, singletps.end_sec
      ) sortedtps
    ) cleantps
    WHERE end_sec > start_sec
  ) finaltps ON finaltps.day = monthly.weekday
  WHERE DATE_ADD(CAST(monthly.date AS DATETIME), INTERVAL finaltps.end_sec SECOND) <= @end

  -- STOP fetching SLA time period end times ---

) events
  ORDER BY events.state_time ASC,
    CASE events.type 
      WHEN 'former_state' THEN 0
      WHEN 'soft_state' THEN 1
      WHEN 'hard_state' THEN 2
      WHEN 'current_state' THEN 3
      WHEN 'future_state' THEN 4
      WHEN 'sla_end' THEN 5
      WHEN 'sla_start' THEN 6
      WHEN 'dt_start' THEN 7
      WHEN 'dt_end' THEN 8
      ELSE 9
    END ASC
) events_with_duration;
-- END fetching the whole normalized eventhistory with durations

  -- Restore other vars
  SET @id         := @former_id,
      @start      := @former_start,
      @end        := @former_end,
      @sla_timeperiod_id := @former_sla_timeperiod_id,
      @last_state   := NULL,
      @last_ts      := NULL,
      @cnt_dt       := NULL,
      @cnt_tp       := NULL,
      @add_duration := NULL,
      @type_id      := NULL,
      @tp_lastday   := NULL,
      @tp_lastend   := NULL,
      @day_offset   := NULL;
  
  RETURN availability;
END|

DELIMITER ;
