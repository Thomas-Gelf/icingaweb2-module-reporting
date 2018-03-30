Files
=====
* slaperiod-schema.sql: creates time period tables
* refresh_slaperiods-procedure.sql: procedure prefilling those tables
* get_sladetail-procedure.sql: procedure able to retrieve SLA details for a single object

Created tables
--------------
* icinga_sla_periods: daily start/end events for each time period
* icinga_outofsla_periods: inverted sla_periods (events differ!)

Refreshing tables
-----------------
The procedure is called this way:

  CALL icinga_refresh_slaperiods();

It should be called after each change to timeperiod objects, ideally by IDO2DB once all config objects have been dumped.


Fetching SLA details
--------------------
This new procedure cannot be part of a SELECT query, there has to be a dedicated CALL for each desired object. For a reporting suite as Jasper this implies running subreports for each single object.

The procedure asks for four parameters:

* object_id BIGINT UNSIGNED: the monitored object you are interested in
* t_start DATETIME: interval start
* t_end DATETIME: interval end
* timeperiod_object_id BIGINT UNSIGNED: the SLA timeperiod object id

A successful CALL may look as follows:

  mysql> CALL icinga_get_sladetail(
    15373,
    '2013-08-01 00:00:00',
    '2013-08-31 23:59:59',
    67192)\G
  *************************** 1. row ***************************
  sla_state0: 0.5875898251156755
  sla_state1: 0
  sla_state2: 0.3911780881041249
  sla_state3: 0.021232086780199663
      state0: 0.4925580542704802
      state1: 0
      state2: 0.4829974921585619
      state3: 0.024444453570957876
  1 row in set (0.16 sec)

  Query OK, 0 rows affected (0.16 sec)

You see a service with 58.75% availability if you consider the given SLA time period, but just 49.25% of "real" 24x7 availability.


