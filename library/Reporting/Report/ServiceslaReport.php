<?php

namespace Icinga\Module\Reporting\Report;

use Icinga\Module\Reporting\Web\Form\QuickForm;

class ServiceslaReport extends SlaReport
{
    public function getName()
    {
        return 'Service SLA';
    }

    public function getViewScript()
    {
        return 'reports/service-sla.phtml';
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    public function addFormElements(QuickForm $form)
    {
        $this->addTimeframesElement($form);
        $form->addElement('select', 'hostgroup', [
            'label'        => $form->translate('Hostgroup'),
            'multiOptions' => $form->optionalEnum($this->ido()->enumHostgroups()),
            'class'        => 'autosubmit',
            'required'     => false
        ]);
        $form->addElement('select', 'servicegroup', [
            'label'        => $form->translate('Servicegroup'),
            'multiOptions' => $form->optionalEnum($this->ido()->enumServicegroups()),
            'class'        => 'autosubmit',
            'required'     => false
        ]);
        $form->addElement('select', 'limit', [
            'label'        => $form->translate('Limit Result'),
            'multiOptions' => [
                '50'    => '50',
                '100'   => '100',
                '500'   => '500',
                '2000'  => '2000',
                '10000' => '10000 (expensive)',
            ],
            'class'        => 'autosubmit',
            'required'     => false
        ]);
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->ido()->db()->fetchAll($this->getQuery());
    }

    public function getQuery()
    {
        $hostgroup = $this->getValue('hostgroup');
        $servicegroup = $this->getValue('servicegroup');
        $db = $this->ido()->db();

        $query = $db->select()->from(
            ['so' => 'icinga_objects'],
            []
        )->join(
            ['s' => 'icinga_services'],
            's.service_object_id = so.object_id',
            []
        )->where('so.is_active = 1')
            ->where('so.objecttype_id = 2')
            ->limit((int) $this->getValue('limit'))
            ->order('hostname ASC')
            ->order('servicename ASC');

        if ($hostgroup) {
            $query->join(
                ['hgm' => 'icinga_hostgroup_members'],
                's.host_object_id = hgm.host_object_id',
                []
            )->join(
                ['hg' => 'icinga_hostgroups'],
                'hg.hostgroup_id = hgm.hostgroup_id',
                []
            )->join(
                ['hgo' => 'icinga_objects'],
                'hgo.object_id = hg.hostgroup_object_id AND hgo.is_active = 1',
                []
            )->where('hgo.name1 = ?', $hostgroup);
        }

        if ($servicegroup) {
            $query->join(
                ['sgm' => 'icinga_servicegroup_members'],
                's.service_object_id = sgm.service_object_id',
                []
            )->join(
                ['sg' => 'icinga_servicegroups'],
                'sg.servicegroup_id = sgm.servicegroup_id',
                []
            )->join(
                ['sgo' => 'icinga_objects'],
                'sgo.object_id = sg.servicegroup_object_id AND sgo.is_active = 1',
                []
            )->where('sgo.name1 = ?', $servicegroup);
        }

        $columns = [
            'hostname' => 'so.name1',
            'servicename' => 'so.name2',
        ];
        $this->addSlaColumnsToQuery($query, 'so.object_id', $columns);

        return $query;
    }

    protected function getMainCsvHeaders()
    {
        return ['Host', 'Service'];
    }
}
