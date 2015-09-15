<?php

namespace Icinga\Module\Reporting;

use Icinga\Application\Config;
use Icinga\Exception\NotFoundError;

class Timeframes
{
    protected $timeframes;

    protected function __construct()
    {
    }

    public function enumTimeframes()
    {
        $enum = array();

        foreach ($this->timeframes as $key => $timeframe) {
            $enum[$key] = $timeframe->getTitle();
        }

        return $enum;
    }

    public function get($name)
    {
        if (is_array($name)) {
            $timeframes = array();
            foreach ($name as $key) {
                $timeframes[$key] = $this->get($key);
            }
            return $timeframes;
        }

        if (array_key_exists($name, $this->timeframes)) {
            return $this->timeframes[$name];
        }

        throw new NotFoundError('No such timeframe defined: "%s"', $name);
    }

    public static function fromConfig(Config $config)
    {
        $self = new static;
        $self->timeframes = array();

        foreach ($config as $name => $section) {
            $self->timeframes[$name] = Timeframe::fromConfigSection($name, $section);
        }

        return $self;
    }
}
