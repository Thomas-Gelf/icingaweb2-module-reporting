<?php

namespace Icinga\Module\Reporting\ProvidedHook\Monitoring;

use Icinga\Module\Monitoring\Hook\IdoQueryExtensionHook;
use Icinga\Module\Monitoring\Backend\Ido\Query\IdoQuery;
use Icinga\Module\Monitoring\Backend\Ido\Query\HoststatusQuery;
use Icinga\Module\Monitoring\Backend\Ido\Query\ServicestatusQuery;

class IdoQueryExtension extends IdoQueryExtensionHook
{
// TODO: Wrap im Hook
    public function extendColumnMap(IdoQuery $query)
    {
        if ($query instanceof HoststatusQuery || $query instanceof ServicestatusQuery) {
            return array(
                'hostsla' => array(
                    'sla_timeperiod_name' => 'hostsla.sla_timeperiod_name',
                    'sla_last_seen'       => 'hostsla.sla_last_seen',
                    'host_in_slatime'     => 'CASE WHEN (hostsla.sla_last_seen IS NULL) OR (hostsla.sla_last_seen > hs.last_hard_state_change) THEN 1 ELSE 0 END',
            'servicehost_in_slatime'      => 'CASE WHEN (hostsla.sla_last_seen IS NULL) OR (hostsla.sla_last_seen > ss.last_hard_state_change) THEN 1 ELSE 0 END'
                )
            );
        }
    }

    public function joinVirtualTable(IdoQuery $query, $virtualTable)
    {
        if ($virtualTable === 'hostsla') {
            if ($query instanceof HoststatusQuery) {
                $this->joinHostsla($query, 'ho.object_id');
            } elseif ($query instanceof ServicestatusQuery) {
                $this->joinHostsla($query, 's.host_object_id');
            }
        }
    }

    protected function joinHostsla(IdoQuery $query, $joinOn)
    {
        $db = $query->getDatasource()->getDbAdapter();
        $prefix = 'icinga_';

        $sla_periods = $db->select()->from(
            array('cv' => $prefix . 'customvariablestatus'),
            array('tp_name' => "CONCAT('sla', cv.varvalue) COLLATE latin1_general_cs")
        )->join(
            array('o' => $prefix . 'objects'),
            "o.object_id = cv.object_id AND o.is_active = 1 AND o.objecttype_id = 1 AND LOWER(varname) = 'sla_id'",
            array()
        )->group('cv.varvalue');

        $hostsla = $db->select()->from(
            array('tpo' => $prefix . 'objects'),
            array(
                'sla_timeperiod_name' => 'tpcv.tp_name',
                'sla_last_seen'       => 'CASE WHEN MAX(slp.end_time) > NOW() THEN NOW() ELSE MAX(slp.end_time) END'
            )
        )->join(
          array('tpcv' => $sla_periods),
          'tpo.name1 = tpcv.tp_name AND tpo.objecttype_id = 9 AND tpo.is_active = 1',
          array()
        )->join(
            array('slp' => $prefix . 'sla_periods'),
            'slp.timeperiod_object_id = tpo.object_id',
            array()

        )->where('slp.start_time < NOW()')
        ->group('timeperiod_object_id');

        $query->joinLeft(
            array('sla_cv' => $prefix . 'customvariablestatus'),
            "sla_cv.object_id = $joinOn AND LOWER(sla_cv.varname) = 'sla_id'",
            array()
        )->joinLeft(
            array( 'hostsla' => $hostsla ),
            "hostsla.sla_timeperiod_name = CONCAT('sla', sla_cv.varvalue) COLLATE latin1_general_cs",
            array()
        );

    }
}
