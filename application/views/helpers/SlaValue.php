<?php

use Icinga\Web\Url;

class Zend_View_Helper_SlaValue extends Zend_View_Helper_Abstract
{
    public function slaValue($value, $timeframe, $host, $service = null)
    {
        $url = Url::fromPath('monitoring/list/eventhistory');
        $url->setQueryString($timeframe->toFilter()->toQueryString());
        $params = $url->getParams();
        if ($service === null) {
            $params->unshift('object_type', 'host');
            $params->unshift('host', $host);
        } else {
            $params->unshift('service', $service);
            $params->unshift('host', $host);
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
