<?php

use GlpiPlugin\Passvault\Credential;
use Html;
use Search;

include('../../../inc/includes.php');

Session::checkRight('passvault', READ);

Html::header(
    Credential::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'plugins',
    Credential::class,
    'credential'
);

Search::show(Credential::class);

Html::footer();
