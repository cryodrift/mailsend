<?php

//declare(strict_types=1);

namespace cryodrift\mailsend;

use cryodrift\fw\Core;

class Mime
{
    private string $boundary;
    private string $content = '';

    public function __construct(protected array $headers = [], protected array $mimes = [])
    {
        $this->boundary = uniqid('boundary_');
    }

    public function getBoundary(): string
    {
        return $this->boundary;
    }

    public static function newHeader(string $name, string $value): string
    {
        return $name . ': ' . $value;
    }

    public function addHeader(string $name, string $value): self
    {
        $this->headers[] = self::newHeader($name, $value);
        return $this;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function addMime(self $part): self
    {
        $this->mimes[] = $part;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * render mime message
     */
    public function build(): string
    {
        if (count($this->mimes) > 1) {
            $this->addHeader('Content-Type', 'multipart/mixed; boundary="' . $this->boundary . '"');
        }
        if (count($this->headers)) {
            $mimeMessage = implode("\r\n", $this->headers) . "\r\n";
        }

        if ($this->content) {
            $mimeMessage .= "\r\n";
            $mimeMessage .= $this->content;
        }

        foreach ($this->mimes as $part) {
            if (count($this->mimes) > 1) {
                $mimeMessage .= '--' . $this->boundary . "\r\n";
            }
            $mimeMessage .= $part->build();
        }

        if (count($this->mimes) > 1) {
            $mimeMessage .= "\r\n";
            $mimeMessage .= '--' . $this->boundary . '--' . "\r\n";
        }
        return $mimeMessage;
    }

    /**
     * only utf-8 and quoted
     */
    public function addText(string $content, string $type = 'plain'): self
    {
        $mime = new self();
        $mime->addHeader('Content-Type', 'text/' . $type . '; charset=UTF-8');
        $mime->addHeader('Content-Transfer-Encoding', 'quoted-printable');
        $content = mb_convert_encoding($content, 'UTF-8');
        $content = quoted_printable_encode($content);
        $mime->setContent($content);
        $this->addMime($mime);
        return $mime;
    }

    /**
     * only cid or attachment
     */
    public function addBinary(string $content, string $filename, string $type, string $cid = ''): self
    {
        $mime = new self();
        $mime->addHeader('Content-Type', $type);
        $mime->addHeader('Content-Transfer-Encoding', 'base64');
        if ($cid) {
            $mime->addHeader('Content-ID', '<' . $cid . '>');
        } else {
            $mime->addHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }
        $mime->setContent(base64_encode($content));
        $this->addMime($mime);
        return $mime;
    }

}
