<?php

use GlpiPlugin\Passvault\Credential;
use GlpiPlugin\Passvault\Credential_Item;
use Html;
use Session;

include('../../../inc/includes.php');

Session::checkRight('passvault', READ);

if (isset($_POST['add_item'])) {
    Session::checkRight('passvault', UPDATE);
    Credential_Item::addFromRequest($_POST);
    Html::back();
} elseif (isset($_POST['delete_item'])) {
    Session::checkRight('passvault', UPDATE);
    Credential_Item::deleteFromRequest($_POST);
    Html::back();
} else {
    $ID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($ID > 0) {
        $cred = new Credential();
        if ($cred->getFromDB($ID)) {
            Html::header(
                Credential::getTypeName(1) . ' — ' . ($cred->fields['name'] ?? ''),
                $_SERVER['PHP_SELF'],
                'plugins',
                Credential::class,
                'credential'
            );
            $cred->display(['id' => $ID]);
            Html::footer();
            return;
        }
    }

    Html::back();
}
