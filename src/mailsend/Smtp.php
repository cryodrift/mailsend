<?php

//declare(strict_types=1);

namespace cryodrift\mailsend;

use Exception;
use cryodrift\user\AccountStorage;
use cryodrift\fw\Core;

class Smtp
{
    private $conn;

    const string FROM = 'From';
    const string TO = 'To';
    const string SUBJECT = 'Subject';
    const string DATE = 'Date';
    const string DATE_FORMAT = 'D, j M Y H:i:s O';
    protected string $host;
    protected string $username;
    protected string $password;


    public function __construct(protected AccountStorage $accounts)
    {
    }

    public function connect(string $from): void
    {
        $domain = Core::pop(explode('@', $from));
        $data = $this->accounts->load();

        foreach ($data as $host => $account) {
            Core::echo(__METHOD__, Core::pop(explode('.', $host, 2)), $account, $host);
            if ($domain === Core::pop(explode('.', $host, 2)) && ($account['type'] === 'smtp' || $account['type'] === 'pop3smtp')) {
                $this->host = $host;
                $this->username = $account['name'];
                $this->password = $account['password'];
            }
        }

        $this->conn = fsockopen($this->host, 25, $errno, $errstr, 10);

        if (!$this->conn) {
            throw new Exception("Verbindung zum SMTP-Server fehlgeschlagen: $errstr ($errno)");
        }

        $response = fgets($this->conn, 1024);
        if (!str_starts_with($response, '220')) {
            throw new Exception('Fehler beim Verbinden mit dem SMTP-Server: ' . $response);
        }

        $this->command('HELO ' . $this->host);

        $this->authenticate();
    }

    public function disconnect(): void
    {
        $this->command('QUIT', ['221']);
        fclose($this->conn);
    }

    private function command($command, $expectedResponses = ['250']): string
    {
        Core::echo(__METHOD__, 'command:', $command);
        fwrite($this->conn, $command . "\r\n");
        $response = fgets($this->conn, 1024);
        Core::echo(__METHOD__, 'response:', $response);
        $responseCode = substr($response, 0, 3);
        if (!in_array($responseCode, $expectedResponses)) {
            throw new Exception('Fehler bei SMTP-Befehl: ' . $command . ' - Antwort: ' . $response);
        }
        return $response;
    }

    private function authenticate(): void
    {
        $this->command('AUTH LOGIN', ['334']);
        $this->command(base64_encode($this->username), ['334']);
        $this->command(base64_encode($this->password), ['235']);
    }

    public function send(string $from, string $to, Mime $mime): void
    {
        $this->command('MAIL FROM: <' . $from . '>');
        $this->command('RCPT TO: <' . $to . '>');
        $this->command('DATA', ['354']);
        $build = $mime->build();
        Core::echo(__METHOD__, 'build-len:', strlen($build));
        fwrite($this->conn, $build . "\r\n.\r\n");
        $response = fgets($this->conn, 1024);
        Core::echo(__METHOD__, 'response:', $response);
        if (!str_starts_with($response, '250')) {
            throw new Exception('Fehler beim Senden der E-Mail: ' . $response);
        }
    }
}


