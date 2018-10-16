<?php

namespace Icinga\Module\Reporting\Controllers;

use Icinga\Module\Reporting\Forms\ReportForm;
use Icinga\Module\Reporting\Timeframes;
use Icinga\Module\Reporting\Web\Controller;

class ReportController extends Controller
{
    public function showAction()
    {
        $form = $this->view->reportForm = $this->loadForm('report')->handleRequest();
        if ($this->sendDownloads($form)) {
            return;
        }

        $this->view->report = $form->getReport();

        $this->view->tabs->add('reporting', array(
            'label' => 'Reporting',
            'url'   => $this->getRequest()->getUrl()
        ))->activate('reporting');
    }

    protected function sendDownloads(ReportForm $form)
    {
        $url = clone($this->getRequest()->getUrl());
        if (! $url->shift('download')) {
            return false;
        }

        $urlParams = $url->getParams();
        $params = array();
        foreach (array_keys($urlParams->toArray(false)) as $key) {
            if ($key === 'timeframes') {
                $params[$key] = $urlParams->getValues($key);
            } else {
                $params[$key] = $urlParams->get($key);
            }
        }

        $report = $form->loadReportByName(
            $params['report']
        )->setValues($params);

        $report->getCsv()->send($this);

        return true;
    }
}
