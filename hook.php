<?php

/**
 * Installation, uninstallation and migration routines for Pass Vault.
 */

use GlpiPlugin\Passvault\Credential;
use GlpiPlugin\Passvault\Credential_Item;
use GlpiPlugin\Passvault\Profile as PassvaultProfile;
use ProfileRight;

/**
 * Plugin installation — creates database tables, registers rights and
 * seeds default display preferences.
 */
function plugin_passvault_install()
{
    global $DB;

    $migration = new Migration(PLUGIN_PASSVAULT_VERSION);

    $table = Credential::getTable();
    if (!$DB->tableExists($table)) {
        $charset   = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();

        $query = "CREATE TABLE `$table` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `is_recursive` TINYINT NOT NULL DEFAULT 0,
            `name` VARCHAR(255) DEFAULT NULL,
            `description` TEXT,
            `type` VARCHAR(255) DEFAULT 'other',
            `username` VARCHAR(255) DEFAULT NULL,
            `password` TEXT,
            `url` VARCHAR(255) DEFAULT NULL,
            `users_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `groups_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `is_deleted` TINYINT NOT NULL DEFAULT 0,
            `date_creation` TIMESTAMP NULL DEFAULT NULL,
            `date_mod` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            INDEX `entities_id` (`entities_id`),
            INDEX `type` (`type`),
            INDEX `name` (`name`),
            INDEX `is_deleted` (`is_deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation;";
        $DB->doQuery($query);
    }

    $link_table = Credential_Item::getTable();
    if (!$DB->tableExists($link_table)) {
        $charset   = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();

        $query = "CREATE TABLE `$link_table` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `plugin_passvault_credentials_id` INT UNSIGNED NOT NULL,
            `itemtype` VARCHAR(100) NOT NULL,
            `items_id` INT UNSIGNED NOT NULL,
            `date_creation` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `unique_link` (`plugin_passvault_credentials_id`, `itemtype`, `items_id`),
            INDEX `item` (`itemtype`, `items_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation;";
        $DB->doQuery($query);
    }

    // Register the right on all profiles (only if not already present)
    $rights_list = [];
    foreach (PassvaultProfile::getAllRights() as $right) {
        $rights_list[] = $right['field'];
    }

    $has_right = countElementsInTable(
        'glpi_profilerights',
        ['name' => $rights_list[0]]
    );
    if ($has_right === 0) {
        ProfileRight::addProfileRights($rights_list);
    }

    // Grant full rights to Super-Admin (ID 4) and the profile performing the install
    $rights_to_grant = array_fill_keys($rights_list, ALLSTANDARDRIGHT);
    $profileRight = new ProfileRight();
    $profileRight->updateProfileRights(4, $rights_to_grant);
    if (isset($_SESSION['glpiactiveprofile']['id'])) {
        $profileRight->updateProfileRights(
            $_SESSION['glpiactiveprofile']['id'],
            $rights_to_grant
        );
    }

    $migration->executeMigration();

    // Seed default display preferences for the credentials list view.
    $existing = countElementsInTable(
        'glpi_displaypreferences',
        [
            'itemtype' => Credential::class,
            'users_id' => 0,
        ]
    );
    if ($existing === 0) {
        $displaypref = new DisplayPreference();
        // Search-option IDs 1 (name), 4 (type), 5 (username), 7 (url)
        foreach ([1, 4, 5, 7] as $rank => $num) {
            $displaypref->add([
                'itemtype' => Credential::class,
                'num'      => $num,
                'users_id' => 0,
                'rank'     => $rank + 1,
            ]);
        }
    }

    return true;
}

/**
 * Plugin uninstallation — drops tables and removes rights.
 */
function plugin_passvault_uninstall()
{
    global $DB;

    $tables = [
        Credential_Item::getTable(),
        Credential::getTable(),
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    foreach (PassvaultProfile::getAllRights() as $right) {
        ProfileRight::deleteProfileRights([$right['field']]);
    }

    return true;
}
