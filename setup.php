<?php

/**
 * Pass Vault — credential vault plugin for GLPI 11.0
 */

define('PLUGIN_PASSVAULT_VERSION', '0.1.0');

// Minimal GLPI version, inclusive
define('PLUGIN_PASSVAULT_MIN_GLPI_VERSION', '11.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_PASSVAULT_MAX_GLPI_VERSION', '11.0.99');

// Minimal PHP version, inclusive
define('PLUGIN_PASSVAULT_MIN_PHP_VERSION', '8.2');

/**
 * Plugin version metadata shown in Setup > Plugins.
 */
function plugin_version_passvault()
{
    return [
        'name'         => 'Pass Vault',
        'version'      => PLUGIN_PASSVAULT_VERSION,
        'author'       => 'Pass Vault contributors',
        'license'      => 'GPL-3.0-or-later',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_PASSVAULT_MIN_GLPI_VERSION,
                'max' => PLUGIN_PASSVAULT_MAX_GLPI_VERSION,
            ],
            'php' => [
                'min' => PLUGIN_PASSVAULT_MIN_PHP_VERSION,
            ],
        ],
    ];
}

/**
 * Plugin instantiation function — called on every GLPI page once prerequisites
 * and configuration checks pass. Declare all hooks here.
 */
function plugin_init_passvault()
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::CSRF_COMPLIANT]['passvault'] = true;

    // Register the main itemtype so GLPI knows about it
    \Plugin::registerClass(\GlpiPlugin\Passvault\Credential::class);
    \Plugin::registerClass(\GlpiPlugin\Passvault\Credential_Item::class);
    \Plugin::registerClass(\GlpiPlugin\Passvault\Profile::class, [
        'addtabon' => \Profile::class,
    ]);

    // Menu entry under Plugins
    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::MENU_TOADD]['passvault'] = [
        'tools' => \GlpiPlugin\Passvault\Credential::class,
    ];

    // Assets & JS
    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_CSS]['passvault']        = 'css/passvault.css';
    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['passvault'] = 'js/passvault.js';

    // Register the password field as a "secured" field so that
    // `php bin/console glpi:security:changekey` re-encrypts it
    // when the GLPI key file is rotated.
    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::SECURED_FIELDS]['passvault'] = [
        'glpi_plugin_passvault_credentials.password',
    ];
}

/**
 * Called on every GLPI page. Returning `false` deactivates the plugin
 * automatically when criteria are not met.
 */
function plugin_passvault_check_config($verbose = false)
{
    if (true) {
        return true;
    }

    if ($verbose) {
        echo __('Installed / not configured', 'passvault');
    }

    return false;
}
