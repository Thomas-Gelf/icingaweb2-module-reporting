DROP PROCEDURE IF EXISTS icinga_refresh_slaperiods;

DELIMITER $$

CREATE PROCEDURE icinga_refresh_slaperiods()
    SQL SECURITY INVOKER
BEGIN
  DECLARE t_start DATETIME;
  DECLARE t_end DATETIME;
  DECLARE tp_id, tpo_id BIGINT UNSIGNED;
  DECLARE ts_offset INT;
  DECLARE fake_result INT UNSIGNED;

  DECLARE done INT DEFAULT FALSE;

  DECLARE cursor_tp CURSOR FOR SELECT
          tpo.object_id,
          tp.timeperiod_object_id
        FROM icinga_timeperiods tp
        JOIN icinga_objects tpo ON tp.timeperiod_object_id = tpo.object_id
            AND tpo.is_active = 1;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;


  SET SESSION binlog_format = ROW;

  START TRANSACTION;

  TRUNCATE TABLE icinga_sla_periods;

  SELECT
    CAST(DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 4 YEAR), '%Y-01-01 00:00:00') AS DATETIME),
    CAST(DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR), '%Y-12-31 23:59:59') AS DATETIME),
    -- Icinga 2 writes seconds with timestamp offset into columns not aware of timezones
    -- This is an attempt to fix those values:
    CASE WHEN COALESCE(
        (SELECT CASE WHEN program_version LIKE 'v2%' THEN 1 ELSE 0 END
           FROM icinga_programstatus
           WHERE is_currently_running = 1
           ORDER BY status_update_time DESC
        ),
        1
     ) = 1 THEN TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) ELSE 0 END
    INTO t_start, t_end, ts_offset;

  OPEN cursor_tp;

  tp_loop: LOOP
    FETCH cursor_tp INTO tp_id, tpo_id;
    IF done THEN
      LEAVE tp_loop;
    END IF;

    SET @tp_lastend := NULL,
        @tp_lastday := NULL,
        @day_offset := NULL;

    INSERT
      INTO icinga_sla_periods SELECT
        tpo_id,
        DATE_ADD(CAST(monthly.date AS DATETIME), INTERVAL finaltps.start_sec SECOND) AS start_time,
        DATE_ADD(CAST(monthly.date AS DATETIME), INTERVAL finaltps.end_sec SECOND) AS end_time

      FROM (
        SELECT
          DATE_ADD(DATE(t_start), INTERVAL @day_offset := @day_offset + 1 DAY) AS date,
          DAYOFWEEK(DATE_ADD(DATE(t_start), INTERVAL @day_offset DAY)) - 1 AS weekday

        FROM icinga_objects o
        JOIN (SELECT @day_offset := -1) day_offset

        ORDER BY object_id
        LIMIT 2194
      ) monthly JOIN (
        SELECT
          NULL AS day,
          NULL as start_sec,
          NULL AS end_sec
        FROM DUAL
        WHERE (@tp_lastday := NULL) IS NOT NULL
          AND ((@tp_lastend := 0) + (@day_offset := -1)) = 1

        UNION

          SELECT
            day,
            (start_sec + ts_offset) % 86400 AS start_sec,
            CASE WHEN (end_sec + ts_offset) = 86400 THEN 86400 ELSE (end_sec + ts_offset) % 86400 END AS end_sec
          FROM icinga_timeperiod_timeranges tpr

          JOIN icinga_timeperiods tp ON tp.timeperiod_id = tpr.timeperiod_id
          WHERE tp.timeperiod_object_id = tpo_id

      ) finaltps ON finaltps.day = monthly.weekday
      WHERE DATE_ADD(CAST(monthly.date AS DATETIME), INTERVAL finaltps.end_sec - 1 SECOND) <= t_end
      ORDER BY monthly.date, finaltps.start_sec, finaltps.end_sec
      ;

  END LOOP tp_loop;

  CLOSE cursor_tp;

  COMMIT;
  SET SESSION binlog_format = STATEMENT;



  SELECT 0 INTO fake_result FROM icinga_objects LIMIT 1;
END;
$$
DELIMITER ;
