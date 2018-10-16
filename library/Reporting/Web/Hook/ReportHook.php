<?php

namespace Icinga\Module\Reporting\Web\Hook;

use Icinga\Application\Config;
use Icinga\Application\Icinga;
use Icinga\Module\Reporting\MonitoringDb;
use Icinga\Module\Reporting\Web\Form\QuickForm;
use Icinga\Module\Reporting\Timeframes;

abstract class ReportHook
{
    protected $values = array();

    abstract public function getViewData();

    abstract public function getViewScript();

    public function getName()
    {
        $parts = $this->splitClass();
        $class = preg_replace('/Report/', '', array_pop($parts));
        if (($module = $this->getModuleName()) !== 'Reporting') {
            return sprintf('%s (%s)', $class, $module);
        }

        return $class;
    }

    public function providesCsv()
    {
        return method_exists($this, 'getCsv');
    }

    public function getValue($key, $default = null)
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        return $default;
    }

    protected function getModuleName()
    {
        $parts = $this->splitClass();
        if (array_shift($parts) === 'Icinga' && array_shift($parts) === 'Module') {
            return strtolower(array_shift($parts));
        }

        return null;
    }

    protected function splitClass()
    {
        return explode('\\', get_class($this));
    }

    protected function addTimeframesElement(QuickForm $form)
    {
        $form->addElement('multiselect', 'timeframes', array(
            'label'        => $form->translate('Timeframe(s)'),
            'size'         => 10,
            'multiOptions' => $this->enumTimeframes(),
            'required'     => true,
        ));
    }

    protected function addTimeframeElement(QuickForm $form)
    {
        $form->addElement('select', 'timeframe', array(
            'label'        => $form->translate('Timeframe'),
            'multiOptions' => $this->enumTimeframes(),
            'required'     => true,
            'class'        => 'autosubmit',
        ));
    }

    public function setValues($values)
    {
        $this->values = $values;
        return $this;
    }

    public function addFormElements(QuickForm $form)
    {
    }

    public function render($view)
    {
        $data = $this->getViewData();
        $data['form'] = $view->reportForm;
        if (Icinga::app()->isCli()) {
            // CLI workaround
            return $view->partial(
                $this->getViewScript(),
                null,
                $data
            );
        } else {
            return $view->partial(
                $this->getViewScript(),
                $this->getModuleName(),
                $data
            );
        }
    }

    protected function enumTimeframes()
    {
        return $this->configuredTimeframes()->enumTimeframes();
    }

    protected function configuredTimeframes()
    {
        return Timeframes::fromConfig(
            Config::module('reporting', 'timeframes')
        );
    }
}
