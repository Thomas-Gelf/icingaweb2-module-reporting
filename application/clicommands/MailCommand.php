<?php

namespace Icinga\Module\Reporting\Clicommands;

use Icinga\Cli\Command;
use Icinga\Application\Icinga;
use Icinga\Module\Reporting\Mail;
use Icinga\Module\Reporting\Web\Form\FormLoader;
use Icinga\Web\View;
use Zend_Controller_Action_HelperBroker;

class MailCommand extends Command
{
    public function testAction()
    {
        $form = $this->loadForm('report');
        $form->handleValues(array(
            'report'     => 'Icinga\\Module\\Reporting\\Report\\HostslaReport',
            'timeframes' => array('current_week', 'last_week', 'minus2_week', 'minus3_week'),
            'hostgroup' => 'Some group',
            'Submit' => 'Submit'
        ));

        $report = $form->getReport();
        $from = $this->params->shift('from');
        $to = $this->params->shift('to');

        Mail::create()
            ->setFrom($from)
            ->addTo($to)
            ->setSubject('Report "' . $report->getName() . '"')
            ->addHtmlImage('/tmp/graph.png')
            ->setBodyText("Irgendwas\n========\n\nNix zu sagen\n")
            ->setBodyHtml(
                '<h1>Irgendwas - Servus</h1>'
                . '<p>Mailst mir bitte einen Screenshot dieser Mail?</p>'
                . $this->renderReport($report)
                . '<img src="graph.png" alt="" border="0" />'
                . '<p>Vielen Dank - Tom</p>'
            )
            ->send();
    }

    protected function renderReport($report)
    {
        return $this->fixHtml($report->render($this->viewRenderer()->view));
    }

    protected function viewRenderer()
    {
        $app = Icinga::app();
        $view = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
        $view->setView(new View());
        $view->view->addHelperPath($app->getApplicationDir('/views/helpers'));
        $view->view->addHelperPath($this->Module()->getApplicationDir() .'/views/helpers');
        $view->view->addBasePath($this->Module()->getApplicationDir() .'/views');
        $view->view->setEncoding('UTF-8');
        return $view;
    }

    protected function fixHtml($html)
    {
        $html = preg_replace('/ class="ok"/', ' class="ok" style="background-color: #44BB77; color: white;"', $html);
        $html = '<html><head><style> .ok { background-color: #44BB77; color: #fff; } </style></head><body style="font-family: Verdana, Helvetica, sans-serif;"><table width="100%"><tr><td width="10%"></td><td width="80%">'
              . $html
              . '</td><td width="10%"></td></tr></table></body></html>';

        // $html = preg_replace('/\<a\s/', '<a style="color: inherit; text-decoration: none;" ', $html);
        $html = preg_replace('/\<a\s.+?\>([^<]+)<\/a\>/', '\1', $html);
        return $html;
    }

    protected function loadForm($name)
    {
        return FormLoader::load($name, $this->Module());
    }

    protected function Module()
    {
        return Icinga::app()->getModuleManager()->getModule('reporting');
    }
}
