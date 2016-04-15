<?php

namespace Icinga\Module\Reporting;

use Icinga\Exception\ProgrammingError;
use Zend_Mail;
use Zend_Mail_Transport_Smtp;
use Zend_Mime;

class Mail extends Zend_Mail
{
    private $htmlImages = array();

    public function __construct($charset = null)
    {
        if ($charset === null) {
            parent::__construct('utf-8');
        }
    }

    public static function create($charset = 'utf-8')
    {
        $mail = new Mail($charset);
        return $mail;
    }

    public function setBodyHtml(
        $html,
        $charset = null,
        $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE
    ) {
        if (count($this->htmlImages) > 0) {
            $pre = '/(\<img\s[^\>]*src=")';
            $post = '"/';
            foreach ($this->htmlImages as $name => $img) {
                $preg = $pre . preg_quote($name, '/') . $post;
                $html = preg_replace($preg, '$1cid:' . $img->id . '"', $html);
            }

            $this->setType(Zend_Mime::MULTIPART_RELATED);
        }

        if ($charset === null) {
            $charset = 'utf8';
        }

        return parent::setBodyHtml($html, $charset, $encoding);
    }

    public function addHtmlImage(
        $file,
        $isFilename = true,
        $mimetype = null,
        $name = null
    ) {
        $p_suff = '~\.(jpg|jpeg|png|gif)$~i';

        $name = $this->uniqueImageFilename($name, $file, $isFilename);

        if ($isFilename) {
            $this->assertFileExists($file);
            $file = file_get_contents($file);
        }

        if (preg_match($p_suff, $name, $match)) {
            if ($mimetype === null) {
                $mimetype = 'image/' . $match[1];
            }
        } else {
            if (preg_match($p_suff, $mimetype, $match)) {
                $name .= '.' . $match[1];
            }
        }

        if (isset($this->htmlImages[$name])) return false;
        $key = md5($file);
        $img = $this->createAttachment($file);
        $img->type        = $mimetype;
        $img->filename    = $name;
        $img->id          = $key;
        $img->disposition = Zend_Mime::DISPOSITION_INLINE;
        $img->encoding    = Zend_Mime::ENCODING_BASE64;
        $this->htmlImages[$name] = $img;

        return $this;
    }

    public function attachFile(
        $file,
        $isFilename = true,
        $name = null,
        $mimetype = 'application/octet-stream'
    ) {
        if ($name) {
            $filename = $name;
        } else {
            $filename = 'image' . (count($this->htmlImages) + 1);
        }

        if ($isFilename) {
            $this->assertFileExists($file);

            if ($name === null) {
                $filename = basename($file);
            }
            $file = file_get_contents($file);
        } else {
            if ($name === null) {
                throw new ProgrammingError(
                    'Filename is required when attaching blobs to mails'
                );
            }
        }

        $att = $this->createAttachment($file);
        $att->type     = $mimetype;
        $att->filename = $filename;

        return $this;
    }

    public function send($transport = null)
    {
        if ($transport === null) {
            $transport = new Zend_Mail_Transport_Smtp('localhost');
        }

        parent::send($transport);
    }

    protected function uniqueImageFileName($name, $filename, $isFilename)
    {
        if ($name === null) {
            if ($isFilename) {
                $filename = basename($filename);
            } else {
                $filename = 'image' . (count($this->htmlImages) + 1);
            }
        } else {
            $filename = $name;
        }

        return $filename;
    }

    protected function assertFileExists($file)
    {
        if (! is_readable($file)) {
            throw new ProgrammingError('Unable to open "%s"', $file);
        }

        return $this;
    }
}
