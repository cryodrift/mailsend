<?php

//declare(strict_types=1);

namespace cryodrift\mailsend;


use cryodrift\fw\cli\ParamFile;
use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\trait\CliHandler;

class Cli implements Handler
{
    use CliHandler;

    public function __construct(protected Smtp $conn, protected Config $config)
    {
    }

    public function handle(Context $ctx): Context
    {
        return $this->handleCli($ctx);
    }

    /**
     * @cli send simple text email
     * @cli param: [-send]
     * @cli param: -from
     * @cli param: -to
     * @cli param: -content (filename or piped)
     * @cli param: [-subject]
     */
    protected function text(string $from, array|string $to, ParamFile $content, string $subject = '', bool $send = false): string
    {
        return $this->send($from, $to, $content, $subject, $send, 'plain');
    }

    /**
     * @cli send simple html email
     * @cli param: [-send]
     * @cli param: -from
     * @cli param: -to
     * @cli param: -content (filename or piped)
     * @cli param: [-subject]
     */
    protected function html(string $from, string $to, ParamFile $content, string $subject = '', bool $send = false): string
    {
        return $this->send($from, $to, $content, $subject, $send, 'html');
    }

    private function send(string $from, string|array $to, ParamFile $content, string $subject, bool $send, string $type): string
    {
        $out = '';

        if (!is_array($to)) {
            $to = [$to];
        }

        foreach ($to as $rcv) {
            $mime = $this->getMail($from, $rcv, $subject);
            $mime->addText((string)$content, $type);
            $out .= $mime->build() . PHP_EOL . PHP_EOL;

            if ($send) {
                $this->sendMail($mime, $from, $rcv);
            } else {
                $out .= Core::toLog('Test MODE no Sending! from:', $from,'to:', $rcv);
            }
        }


        return $out;
    }

    /**
     * @cli test quoted printable decode
     */
    protected function testquoted(ParamFile $content): string
    {
        return quoted_printable_decode((string)$content);
    }

    private function sendMail(Mime $mime, string $from, string $to): void
    {
        $this->conn->connect($from);
        $this->conn->send($from, $to, $mime);
        $this->conn->disconnect();
    }

    private function getMail(string $from, string $to, string $subject): Mime
    {
        $mime = new Mime([
          Mime::newHeader(Smtp::FROM, $from),
          Mime::newHeader(Smtp::TO, $to),
          Mime::newHeader(Smtp::SUBJECT, mb_encode_mimeheader($subject)),
          Mime::newHeader(Smtp::DATE, date(Smtp::DATE_FORMAT)),
        ]);
        return $mime;
    }

}
