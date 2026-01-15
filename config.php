<?php

//declare(strict_types=1);

/**
 *
 */

use cryodrift\fw\Core;

if (!isset($ctx)) {
    $ctx = Core::newContext(new \cryodrift\fw\Config());
}

$cfg = $ctx->config();

$cfg[\cryodrift\mailsend\Smtp::class] = [
  'accounts' => \cryodrift\user\AccountStorage::class,
];

\cryodrift\fw\Router::addConfigs($ctx, [
  'mailsend/cli' => \cryodrift\mailsend\Cli::class,
], \cryodrift\fw\Router::TYP_CLI);
