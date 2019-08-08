<?php

namespace Icinga\Module\Reporting\Forms;

use Icinga\Application\Config;
use Icinga\Module\Reporting\MonitoringDb;
use Icinga\Module\Reporting\Report\Report;
use Icinga\Module\Reporting\Web\Form\QuickForm;
use Icinga\Web\Hook;
use Icinga\Web\Url;

class ReportForm extends QuickForm
{
    private $report;

    public function setup()
    {
        $availableReports = $this->enumReports();

        $this->addElement('select', 'report', array(
            'label'        => $this->translate('Report Type'),
            'multiOptions' => $this->optionalEnum($availableReports),
            'class'        => 'autosubmit',
        ));

        $this->setSubmitLabel($this->translate('Next'));
        
        if (! $this->hasBeenSent()) {
            return;
        }
        $post = $this->getRequest()->getPost();

        if (! $this->isValidPartial($post)) {
            return;
        }
        $class = $this->getValue('report');
        if (! $class) {
            return;
        }

        $report = new $class;
        $report->addFormElements($this);
        if ($this->isValidPartial($this->getRequest()->getPost())) {
            foreach ($this->getElements() as $el) {
                if ($el->isRequired() && ! isset($post[$el->getName()])) {
                    return;
                }
            }
            $this->report = $report->setValues($this->getValues());
        }

        $this->setSubmitLabel($this->translate('Generate'));
    }

    public function getDownloadUrl()
    {
        $values = $this->getValues();
        if (array_key_exists('report', $values)) {
            $values['report'] = $this->getReportNameForClass($values['report']);
        }

        if (array_key_exists('timeframes', $values)) {
            $timeframes = $values['timeframes'];
            unset($values['timeframes']);
        } else {
            $timeframes = null;
        }

        $url = Url::fromPath($this->getAction(), $values);

        if ($timeframes) {
            $url->getParams()
                ->addValues('timeframes', $timeframes)
                ->add('download');
        }

        return $url;
    }

    public function getReport()
    {
        return $this->report;
    }

    public function onSuccess()
    {
        // No redirect
    }

    protected function getReportNameForClass($classname)
    {
        $enum = $this->enumReports();
        return $enum[$classname];
    }

    /**
     * @param $name
     * @return \Icinga\Module\Reporting\Web\Hook\ReportHook
     */
    public function loadReportByName($name)
    {
        $enum = $this->enumReports();
        $enum = array_flip($enum);
        return new $enum[$name];
    }

    protected function enumReports()
    {
        $hooks = Hook::all('Reporting\\Report');
        $enum = array();
        foreach ($hooks as $hook) {
            $enum[get_class($hook)] = $hook->getName();
        }

        return $enum;
    }

    protected function availableReports()
    {
        return array(
            'hostsla' => 'Host Availability',
            'graphs'  => 'KPI Graphs',
        );
    }

    protected function enumHostgroups()
    {
        return $this->db->enumHostgroups();
    }
}
