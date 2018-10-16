<?php

namespace Icinga\Module\Reporting\Report;

use Icinga\Module\Reporting\Ido;
use Icinga\Module\Reporting\Web\Hook\ReportHook;

abstract class IdoReport extends ReportHook
{
    private $ido;

    /**
     * @return Ido
     */
    protected function ido()
    {
        if ($this->ido === null) {
            $this->ido = new Ido();
        }

        return $this->ido;
    }
}
