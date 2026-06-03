<?php

namespace GlpiPlugin\Passvault;

use CommonDBTM;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Profile as Glpi_Profile;
use Session;

/**
 * Adds a "Pass Vault" tab on each core {@see \Profile} page so administrators
 * can grant the plugin's rights on a per-profile basis.
 */
class Profile extends CommonDBTM
{
    public static $rightname = 'profile';

    public static function getTypeName($nb = 0)
    {
        return __('Pass Vault', 'passvault');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (
            $item instanceof Glpi_Profile
            && $item->getField('id')
        ) {
            return self::createTabEntry(self::getTypeName());
        }
        return '';
    }

    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        if (
            $item instanceof Glpi_Profile
            && $item->getField('id')
        ) {
            return self::showForProfile($item->getID());
        }
        return true;
    }

    /**
     * Rights defined by this plugin, indexed by `field`.  Used by
     * {@see \ProfileRight::addProfileRights()} on install and
     * {@see \ProfileRight::deleteProfileRights()} on uninstall.
     */
    public static function getAllRights($all = false): array
    {
        $rights = [
            [
                'itemtype' => Credential::class,
                'label'    => Credential::getTypeName(2),
                'field'    => 'passvault',
            ],
        ];

        return $rights;
    }

    /**
     * Render the per-profile rights matrix for the plugin.
     */
    public static function showForProfile($profiles_id = 0)
    {
        $profile = new Glpi_Profile();
        $profile->getFromDB($profiles_id);

        $current_rights = \ProfileRight::getProfileRights(
            $profiles_id,
            ['passvault']
        );
        $current = $current_rights['passvault'] ?? 0;

        TemplateRenderer::getInstance()->display(
            '@passvault/profile.html.twig',
            [
                'can_edit'       => self::canUpdate(),
                'profile'        => $profile,
                'current_rights' => $current,
                'csrf_token'     => Session::getNewCSRFToken(),
            ]
        );
    }
}
