<?php

namespace Icinga\Module\Reporting;

use Icinga\Data\ConfigObject;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\NotImplementedError;

class Timeframe
{
    const RAW = 0x01;

    const UNIX = 0x02;

    const HUMAN = 0x04;

    const SHORT = 0x08;

    protected $name;

    protected $title;

    protected $start;

    protected $end;

    protected function __construct($name, $title, $start, $end)
    {
        $this->name  = $name;
        $this->title = $title;
        $this->start = $start;
        $this->end   = $end;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getTitle()
    {
        $title = $this->title;
        if (strpos($title, '{') !== false) {
            $start = $this->getStart();
            $end = $this->getEnd();
            $title = preg_replace('/{START_MONTHNAME}/', date('F', $start), $title);
            $title = preg_replace('/{START_MONTH}/', date('m', $start), $title);
            $title = preg_replace('/{START_YEAR}/', date('Y', $start), $title);
            $title = preg_replace('/{START_WEEK}/', date('W', $start), $title);
            $title = preg_replace('/{END_MONTHNAME}/', date('F', $end), $title);
            $title = preg_replace('/{END_MONTH}/', date('m', $end), $title);
            $title = preg_replace('/{END_YEAR}/', date('Y', $end), $title);
            $title = preg_replace('/{END_WEEK}/', date('W', $end), $title);
        }
        return $title;
    }

    public function getIntervalDescription()
    {
        return sprintf('%s - %s', $this->getStart(self::HUMAN), $this->getEnd(self::HUMAN));
    }

    public function getStart($format = self::UNIX)
    {
        return $this->format($this->start, $format);
    }

    public function getEnd($format = self::UNIX)
    {
        return $this->format($this->end, $format);
    }

    public function toFilter($column = 'timestamp', $format = self::UNIX)
    {
        return Filter::matchAll(
            Filter::expression($column, '>=', $this->getStart($format)),
            Filter::expression($column, '<=', $this->getEnd($format))
        );
    }

    protected function format($time, $format)
    {
        if ($format === self::RAW) {
            return $time;
        } elseif ($format === self::UNIX) {
            return strtotime($time);
        } elseif ($format === self::HUMAN) {
            return date('Y-m-d H:i:s', strtotime($time));
        } else {
            throw new NotImplementedError('No known time format has been requested');
        }
    }

    public static function fromConfigSection($name, ConfigObject $config)
    {
        return new static(
            $name,
            $config->get('title', $name),
            $config->get('start'),
            $config->get('end')
        );
    }
}
