<?php

namespace Icinga\Module\Reporting\File;

use Icinga\Web\Controller;

class Csv
{
    protected $data;
    
    protected $filename = 'data.csv';

    protected function __construct($data)
    {
        $this->data = $data;
    }

    public static function create($data)
    {
        return new static($data);
    }

    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this;
    }

    public function send(Controller $controller)
    {
        $controller->getHelper('layout')->disableLayout();
        $controller->getHelper('viewRenderer')->setNoRender(true);
        $response = $controller->getResponse();
        $out = fopen('php://output', 'w');
        $response->setHeader(
            'Content-Disposition',
            'attachment; filename="' . $this->filename . '"'
        );

        $response->setHeader('Content-type', 'text/csv');
        foreach ($this->data as $row) {
            fputcsv($out, (array) $row, ';');
        }
        fclose($out);
    }
}
