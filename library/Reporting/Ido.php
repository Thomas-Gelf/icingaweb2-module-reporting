<?php

namespace Icinga\Module\Reporting;

use Icinga\Application\Icinga;
use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Monitoring\Backend;

class Ido
{
    private $monitoring;

    private $db;

    public function db()
    {
        if ($this->db === null) {
            $this->db = $this->monitoring->getResource()->getDbAdapter();
        }
        return $this->db;
    }

    public function enumHostgroups(Filter $filter = null)
    {
        $query = $this->monitoring()->select()
            ->from('hostgroup', array('hostgroup_name', 'hostgroup_alias'))
            ->order('hostgroup_alias');

        $this->applyObjectRestrictions($query); 

        return array(null => '- please choose -') + $query->getQuery()->fetchPairs();
    }

    public function enumDistinctServicesForHostgroup($hostgroup)
    {
        $query = $this->monitoring()->select()
            ->from('serviceStatus', array('service_description', 'service_display_name'))
            ->where('hostgroup', $hostgroup)
            ->order('service_display_name');
        $this->applyObjectRestrictions($query);
        return array('_HOST_' => 'Host Check') + $query->getQuery()->distinct()->fetchPairs();
    }

    protected function applyObjectRestrictions($query)
    {
        if (Icinga::app()->isCli()) {
            return $query;
        }

        $restrictions = Filter::matchAny();
        foreach (Auth::getInstance()->getRestrictions('monitoring/filter/objects') as $filter) {
            $restrictions->addFilter(Filter::fromQueryString($filter));
        }

        return $query->applyFilter($restrictions);
    }

    public function monitoring()
    {
        if ($this->monitoring === null) {
            $this->monitoring = Backend::instance();
        }

        return $this->monitoring;
    }
}
