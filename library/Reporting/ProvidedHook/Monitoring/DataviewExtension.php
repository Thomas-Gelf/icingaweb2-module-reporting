<?php

namespace Icinga\Module\Reporting\ProvidedHook\Monitoring;

use Icinga\Module\Monitoring\Hook\DataviewExtensionHook;

class DataviewExtension extends DataviewExtensionHook
{
    public function provideAdditionalQueryColumns($queryName)
    {
        if ($queryName === 'HostStatus') {
            return array('host_in_slatime');
        } elseif ($queryName === 'ServiceStatus') {
            return array('servicehost_in_slatime');
        }
    }
}
