<?php

use Icinga\Web\Url;

class Zend_View_Helper_SlaValue extends Zend_View_Helper_Abstract
{
    /**
     * @param $value
     * @param $timeframe
     * @param $host
     * @param null $service
     * @return mixed
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function slaValue($value, \Icinga\Module\Reporting\Timeframe $timeframe, $host, $service = null)
    {
        $url = Url::fromPath('monitoring/list/eventhistory');
        $url->setQueryString($timeframe->toFilter()->toQueryString());
        $params = $url->getParams();
        if ($service === null) {
            $params->unshift('object_type', 'host');
            $params->unshift('host_name', $host);
        } else {
            $params->unshift('service_description', $service);
            $params->unshift('host_name', $host);
        }

        $params->add('limit', 100);

        return $this->view->qlink(
            sprintf('%.3f %%', $value),
            $url,
            null,
            array(
               'title' => sprintf('%f', $value)
            )
        );
    }
}
