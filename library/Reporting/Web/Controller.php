<?php

namespace Icinga\Module\Reporting\Web;

use Icinga\Module\Reporting\Ido;
use Icinga\Module\Reporting\Web\Form\FormLoader;
use Icinga\Web\Controller as ActionController;

class Controller extends ActionController
{
    protected $ido;

    protected function ido()
    {
        if ($this->ido === null) {
            $this->ido = new Ido();
        }

        return $this->ido;
    }

    public function loadForm($name)
    {
        return FormLoader::load($name, $this->Module());
    }
}
