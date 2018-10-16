<?php

$this->provideHook(
    'reporting/Report',
    '\\Icinga\\Module\\Reporting\\Report\\HostslaReport'
);

$this->provideHook(
    'reporting/Report',
    '\\Icinga\\Module\\Reporting\\Report\\ServiceslaReport'
);

$this->provideHook('monitoring/dataviewExtension');
$this->provideHook('monitoring/idoQueryExtension');

