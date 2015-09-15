<?php

namespace Icinga\Module\Reporting\Report;

use Icinga\Module\Reporting\Web\Form\QuickForm;

class HostslaReport extends SlaReport
{
    public function getName()
    {
        return 'Host SLA';
    }

    public function addFormElements(QuickForm $form)
    {
        $this->addTimeframesElement($form);
        $form->addElement('select', 'hostgroup', array(
            'label'        => $form->translate('Hostgroup'),
            'multiOptions' => $form->optionalEnum($this->ido()->enumHostgroups()),
            'class'        => 'autosubmit',
            'required'     => true
        ));
    }

    public function getResult()
    {
        return $this->fetchHostSlasForHostgroup($this->getValue('hostgroup'));
    }

    protected function fetchHostSlasForHostgroup($hostgroup)
    {
        $db = $this->ido()->db();

        $query = $db->select()->from(
            array('ho' => 'icinga_objects'),
            array()
        )->join(
            array('hgm' => 'icinga_hostgroup_members'),
            'ho.object_id = hgm.host_object_id',
            array()
        )->join(
            array('hg' => 'icinga_hostgroups'),
            'hg.hostgroup_id = hgm.hostgroup_id',
            array()
        )->join(
            array('hgo' => 'icinga_objects'),
            'hgo.object_id = hg.hostgroup_object_id AND hgo.is_active = 1',
            array()
        );

        $columns = array('hostname' => 'ho.name1');
        $this->addSlaColumnsToQuery($query, 'ho.object_id', $columns);

        $query->where('hgo.name1 = ?', $hostgroup)
              ->where('ho.is_active = 1');

        $query->order('hostname', 'ASC');

        return $db->fetchAll($query);
    }
}
