<?php

use GlpiPlugin\Passvault\Credential;
use GlpiPlugin\Passvault\Credential_Item;
use Html;
use Session;

include('../../../inc/includes.php');

Session::checkRight('passvault', READ);

$credential = new Credential();

if (isset($_POST['add'])) {
    Session::checkRight('passvault', CREATE);

    $newID = $credential->add($_POST);
    if ($newID === false) {
        Html::back();
    }

    if (!empty($_SESSION['glpibackcreated'])) {
        Html::redirect(Credential::getFormURLWithID($newID));
    }
    Html::redirect(Credential::getSearchURL());
} elseif (isset($_POST['update'])) {
    Session::checkRight('passvault', UPDATE);

    if ($credential->update($_POST)) {
        Session::addMessageAfterRedirect(
            __('Credential updated.', 'passvault'),
            false,
            INFO
        );
    }
    Html::back();
} elseif (isset($_POST['delete'])) {
    Session::checkRight('passvault', DELETE);
    $credential->delete($_POST);
    $credential->redirectToList();
} elseif (isset($_POST['purge'])) {
    Session::checkRight('passvault', PURGE);
    $credential->delete($_POST, true);
    $credential->redirectToList();
} elseif (isset($_POST['restore'])) {
    Session::checkRight('passvault', UPDATE);
    $credential->restore($_POST);
    $credential->redirectToList();
} else {
    $ID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    Html::header(
        Credential::getTypeName(2),
        $_SERVER['PHP_SELF'],
        'plugins',
        Credential::class,
        'credential'
    );

    $credential->display(['id' => $ID]);
    Html::footer();
}
