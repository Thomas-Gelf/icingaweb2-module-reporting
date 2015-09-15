<?php

use Icinga\Module\Reporting\Timeframes;
use Icinga\Module\Reporting\Web\Controller;

class Reporting_ReportController extends Controller
{
    public function showAction()
    {
        $form = $this->view->reportForm = $this->loadForm('report')->handleRequest();

        $this->view->report = $form->getReport();
        $this->view->tabs->add('reporting', array(
            'label' => 'Reporting',
            'url'   => $this->getRequest()->getUrl()
        ))->activate('reporting');
    }
}
