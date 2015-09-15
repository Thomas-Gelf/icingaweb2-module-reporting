<?php

namespace Icinga\Module\Reporting\Report;

use Icinga\Module\Reporting\Timeframe;

abstract class SlaReport extends IdoReport
{
    protected $timeframes = array();

    public function getViewScript()
    {
        return 'reports/sla.phtml';
    }

    abstract public function getResult();

    public function getViewData()
    {
        return array(
            'result'     => $this->getResult(),
            'timeframes' => $this->getSelectedTimeframes()
        );
    }

    protected function slaFunction($objectColumn, $timeframe)
    {
        return  sprintf(
            // "icinga_availability_slatime(%s, '%s', '%s', NULL)",
            "icinga_availability2(%s, '%s', '%s')",
            $objectColumn,
            $timeframe->getStart(Timeframe::HUMAN),
            $timeframe->getEnd(Timeframe::HUMAN)
        );
    }

    protected function getSelectedTimeframes()
    {
        return $this->configuredTimeframes()->get($this->getValue('timeframes'));
    }

    protected function addSlaColumnsToQuery($query, $objectColumn, $columns)
    {
        $slaColumns = $this->prepareSlaColumnsForTimeframes($objectColumn);
        $query->columns($columns + $slaColumns);
        reset($slaColumns);
        $query->order(key($slaColumns), 'ASC');
        return $query;
    }

    protected function prepareSlaColumnsForTimeframes($objectColumn)
    {
        $columns = array();
        $timeframes = $this->getSelectedTimeframes();
        foreach ($timeframes as $alias => $timeframe) {
            $columns[$alias] = $this->slaFunction($objectColumn, $timeframe);
        }

        return $columns;
    }
}
