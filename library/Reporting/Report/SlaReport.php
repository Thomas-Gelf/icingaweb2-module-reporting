<?php

namespace Icinga\Module\Reporting\Report;

use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Module\Reporting\File\Csv;
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

    abstract protected function getMainCsvHeaders();

    public function getCsv()
    {
        $filename = sprintf(
            '%s %s.csv',
            $this->getName(),
            date('(d.m.Y)')
        );

        $headers = $this->getMainCsvHeaders();
        foreach ($this->getSelectedTimeframes() as $timeFrame) {
            $headers[] = $timeFrame->getTitle();
        }
        $rows = array($headers);
        foreach ($this->getResult() as $row) {
            $props = (array) $row;
            foreach ($props as $key => & $value) {
                if ($key === 'hostname' || $key === 'servicename') {
                    continue;
                }

                $value = (float) $value;
            }
            $rows[] = array_values((array) $props);
        }
        return Csv::create($rows)->setFilename($filename);
    }

    protected function slaFunction($objectColumn, $timeframe)
    {
        return  sprintf(
            "icinga_availability_slatime(%s, '%s', '%s', NULL)",
            //"icinga_availability2(%s, '%s', '%s')",
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
