<?php

$this->registerHook(
    'Reporting\\Report', '\\Icinga\\Module\\Reporting\\Report\\HostslaReport', 'hostsla'
);

$this->provideHook('monitoring/dataviewExtension');
$this->provideHook('monitoring/idoQueryExtension');

