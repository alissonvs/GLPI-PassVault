<?php

namespace GlpiPlugin\Passvault;

use CommonDBTM;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Log;
use Notepad;
use Search;
use Session;
use Toolbox;

/**
 * Pass Vault credential itemtype.
 *
 * Stores access credentials (server, database, application, service, wifi,
 * other) with a username, an encrypted password and a URL.  Credentials can
 * be linked to one or many GLPI assets (or any other itemtype) through the
 * {@see Credential_Item} polymorphic table.
 */
class Credential extends CommonDBTM
{
    public static $rightname       = 'passvault';
    public $dohistory             = true;
    public static $can_be_translated = false;

    /**
     * Type labels keyed by the database value.
     */
    public const TYPES = [
        'server'      => 'Server',
        'database'    => 'Database',
        'application' => 'Application',
        'service'     => 'Service',
        'wifi'        => 'Wi-Fi',
        'other'       => 'Other',
    ];

    public static function getTypeName($nb = 0)
    {
        return _n('Credential', 'Credentials', $nb, 'passvault');
    }

    /**
     * The database table is the plural form of the class name.
     */
    public static function getTable($classname = null)
    {
        return 'glpi_plugin_passvault_credentials';
    }

    public static function getMenuName($nb = 0)
    {
        return self::getTypeName($nb);
    }

    /**
     * Menu structure rendered in the GLPI sidebar.
     */
    public static function getMenuContent()
    {
        $title  = self::getMenuName(Session::getPluralNumber());
        $search = self::getSearchURL(false);
        $form   = self::getFormURL(false);

        $menu = [
            'title'   => __('Pass Vault', 'passvault'),
            'page'    => $search,
            'icon'    => 'ti ti-key',
            'options' => [
                'credential' => [
                    'title' => $title,
                    'page'  => $search,
                    'links' => [
                        'search' => $search,
                        'add'    => $form,
                    ],
                ],
            ],
        ];

        return $menu;
    }

    /**
     * Return the localized type labels, suitable for the `options` argument
     * of a dropdown Twig macro.
     */
    public static function getTypeOptions(): array
    {
        $options = [];
        foreach (self::TYPES as $key => $label) {
            $options[$key] = __($label, 'passvault');
        }
        return $options;
    }

    /**
     * Localized label for a stored type key.
     */
    public static function getTypeLabel(?string $key): string
    {
        if ($key === null || $key === '') {
            return '';
        }
        $label = self::TYPES[$key] ?? null;
        return $label !== null ? __($label, 'passvault') : $key;
    }

    public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0)
    {
        return '';
    }

    public static function displayTabContentForItem(
        \CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        return true;
    }

    /**
     * Standard tabs: form + linked items + notes + history.
     */
    public function defineTabs($options = [])
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs)
            ->addStandardTab(Credential_Item::class, $tabs, $options)
            ->addStandardTab(Notepad::class, $tabs, $options)
            ->addStandardTab(Log::class, $tabs, $options);

        return $tabs;
    }

    /**
     * Remove every link that points at this credential when it is purged.
     */
    public function cleanDBOnPurge()
    {
        $link = new Credential_Item();
        $link->deleteByCriteria(
            ['plugin_passvault_credentials_id' => $this->getID()],
            false,
            false
        );
    }

    /**
     * Render the form via a Twig template that extends the generic one.
     */
    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);

        $type_options = self::getTypeOptions();
        $current_type = $this->fields['type'] ?? 'other';
        if (!array_key_exists($current_type, $type_options)) {
            $current_type = 'other';
        }

        $decrypted = '';
        if ($ID > 0) {
            $decrypted = $this->getDecryptedPassword();
        }

        TemplateRenderer::getInstance()->display(
            '@passvault/credential.form.html.twig',
            [
                'item'               => $this,
                'params'             => $options,
                'type_options'       => $type_options,
                'current_type'       => $current_type,
                'decrypted_password' => $decrypted,
            ]
        );

        return true;
    }

    /**
     * Encrypt the password before persisting a new credential.
     */
    public function prepareInputForAdd($input)
    {
        if (isset($input['password']) && $input['password'] !== '') {
            $input['password'] = self::encryptSecret((string) $input['password']);
        } else {
            $input['password'] = '';
        }

        if (empty($input['name'])) {
            Session::addMessageAfterRedirect(
                __('A name is required for the credential.', 'passvault'),
                false,
                ERROR
            );
            return false;
        }

        if (!array_key_exists($input['type'] ?? '', self::TYPES)) {
            $input['type'] = 'other';
        }

        return $input;
    }

    /**
     * Encrypt the password before persisting an update.  An empty value
     * means "do not change the existing password".
     */
    public function prepareInputForUpdate($input)
    {
        if (array_key_exists('password', $input)) {
            if ($input['password'] === '' || $input['password'] === null) {
                unset($input['password']);
            } else {
                $input['password'] = self::encryptSecret((string) $input['password']);
            }
        }

        if (isset($input['type']) && !array_key_exists($input['type'], self::TYPES)) {
            $input['type'] = 'other';
        }

        if (isset($input['name']) && $input['name'] === '') {
            Session::addMessageAfterRedirect(
                __('A name is required for the credential.', 'passvault'),
                false,
                ERROR
            );
            return false;
        }

        return $input;
    }

    /**
     * Return the decrypted password, or an empty string if not set.
     */
    public function getDecryptedPassword(): string
    {
        if (empty($this->fields['password'])) {
            return '';
        }

        $key = self::getGlpiKey();
        if ($key === null) {
            return '';
        }

        $decrypted = Toolbox::decrypt((string) $this->fields['password'], $key);
        return $decrypted !== false ? (string) $decrypted : '';
    }

    /**
     * Encrypt a plaintext password using the GLPI key file.
     */
    private static function encryptSecret(string $plaintext): string
    {
        $key = self::getGlpiKey();
        if ($key === null) {
            return $plaintext;
        }
        return Toolbox::encrypt($plaintext, $key);
    }

    /**
     * Resolve the GLPI encryption key.
     */
    private static function getGlpiKey(): ?string
    {
        if (defined('GLPI_KEY_FILE')) {
            $candidate = constant('GLPI_KEY_FILE');
            if (is_string($candidate) && is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        $dir = defined('GLPI_FILES_DIR') ? constant('GLPI_FILES_DIR') : '';
        if ($dir !== '' && is_file($dir . '/key/glpi.key')) {
            return $dir . '/key/glpi.key';
        }

        $config_dir = defined('GLPI_CONFIG_DIR') ? constant('GLPI_CONFIG_DIR') : '';
        if ($config_dir !== '' && is_file($config_dir . '/glpi.key')) {
            return $config_dir . '/glpi.key';
        }

        return null;
    }

    /**
     * Search options exposed in the credentials list view.
     */
    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => self::getTypeName(2),
        ];

        $tab[] = [
            'id'       => 1,
            'table'    => self::getTable(),
            'field'    => 'name',
            'name'     => __('Name'),
            'datatype' => 'itemlink',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => 2,
            'table'    => self::getTable(),
            'field'    => 'id',
            'name'     => __('ID'),
        ];

        $tab[] = [
            'id'       => 3,
            'table'    => self::getTable(),
            'field'    => 'description',
            'name'     => __('Description'),
            'datatype' => 'string',
        ];

        $tab[] = [
            'id'       => 4,
            'table'    => self::getTable(),
            'field'    => 'type',
            'name'     => __('Type', 'passvault'),
            'datatype' => 'string',
        ];

        $tab[] = [
            'id'       => 5,
            'table'    => self::getTable(),
            'field'    => 'username',
            'name'     => __('Username'),
            'datatype' => 'string',
        ];

        $tab[] = [
            'id'       => 6,
            'table'    => self::getTable(),
            'field'    => 'url',
            'name'     => __('URL'),
            'datatype' => 'string',
        ];

        $tab[] = [
            'id'       => 7,
            'table'    => self::getTable(),
            'field'    => 'entities_id',
            'name'     => __('Entity'),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id'       => 8,
            'table'    => self::getTable(),
            'field'    => 'is_recursive',
            'name'     => __('Child entities'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id'       => 9,
            'table'    => self::getTable(),
            'field'    => 'date_creation',
            'name'     => __('Creation date'),
            'datatype' => 'datetime',
        ];

        $tab[] = [
            'id'       => 10,
            'table'    => self::getTable(),
            'field'    => 'date_mod',
            'name'     => __('Last update'),
            'datatype' => 'datetime',
        ];

        $tab[] = [
            'id'       => 11,
            'table'    => self::getTable(),
            'field'    => 'users_id',
            'name'     => __('Creator'),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id'           => 12,
            'table'        => Credential_Item::getTable(),
            'field'        => 'id',
            'name'         => __('Number of linked items', 'passvault'),
            'datatype'     => 'count',
            'forcegroupby' => true,
            'usehaving'    => true,
            'joinparams'   => [
                'jointype' => 'child',
            ],
        ];

        return $tab;
    }
}
