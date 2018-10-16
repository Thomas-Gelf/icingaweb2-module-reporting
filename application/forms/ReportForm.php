<?php

namespace Icinga\Module\Reporting\Forms;

use Icinga\Application\Config;
use Icinga\Module\Reporting\MonitoringDb;
use Icinga\Module\Reporting\Report\Report;
use Icinga\Module\Reporting\Web\Form\QuickForm;
use Icinga\Web\Hook;

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

    public function getReport()
    {
        return $this->report;
    }

    public function onSuccess()
    {
        // No redirect
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
