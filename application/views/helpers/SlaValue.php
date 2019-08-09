<?php

use Icinga\Application\Config;
use Icinga\Web\Url;

class Zend_View_Helper_SlaValue extends Zend_View_Helper_Abstract
{
    protected static $linkToHistory;

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

        if ($this->linkToHistory()) {
            return $this->view->qlink(
                sprintf('%.3f %%', $value),
                $url,
                null,
                ['title' => sprintf('%f', $value)]
            );
        } else {
            return $this->view->qlink(
                sprintf('%.3f %%', $value),
                '#',
                null,
                ['title' => sprintf('%f', $value)]
            );
        }
    }

    protected static function linkToHistory()
    {
        if (self::$linkToHistory === null) {
            self::$linkToHistory = Config::module('reporting')
                ->get('ui', 'link_to_history', 'yes') === 'yes';
        }

        return self::$linkToHistory;
    }
}
