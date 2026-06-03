<?php

namespace GlpiPlugin\Passvault;

use CommonDBTM;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Session;

/**
 * Polymorphic link between a {@see Credential} and any other GLPI itemtype
 * (Computer, NetworkEquipment, Phone, Printer, Monitor, Peripheral, User,
 * Group, Location, Supplier, etc.).
 */
class Credential_Item extends CommonDBTM
{
    public static $rightname = 'passvault';
    public static $can_be_translated = false;

    public static function getTypeName($nb = 0)
    {
        return _n('Linked item', 'Linked items', $nb, 'passvault');
    }

    /**
     * The database table is the plural form of the class name.
     */
    public static function getTable($classname = null)
    {
        return 'glpi_plugin_passvault_credentials_items';
    }

    /**
     * Tab title on the credential form.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!($item instanceof Credential)) {
            return '';
        }

        $nb = countElementsInTable(
            self::getTable(),
            ['plugin_passvault_credentials_id' => $item->getID()]
        );
        return self::createTabEntry(self::getTypeName($nb), $nb);
    }

    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        if (!($item instanceof Credential)) {
            return true;
        }
        return self::showForCredential($item);
    }

    /**
     * Render the form to add new links and the list of existing ones.
     */
    public static function showForCredential(Credential $credential)
    {
        $credential_id = (int) $credential->getID();
        $linked_items  = self::getCredentialLinkedItems($credential_id);

        TemplateRenderer::getInstance()->display(
            '@passvault/credential_item.html.twig',
            [
                'credential'   => $credential,
                'linked_items' => $linked_items,
                'csrf_token'   => Session::getNewCSRFToken(),
            ]
        );

        return true;
    }

    /**
     * Build the list of items linked to the given credential.
     *
     * @return array<int,array{link_id:int,itemtype:string,items_id:int,name:string,url:string}>
     */
    public static function getCredentialLinkedItems(int $credentials_id): array
    {
        global $DB;

        if ($credentials_id <= 0) {
            return [];
        }

        $link_table = self::getTable();
        $rows = $DB->request(
            [
                'FROM'  => $link_table,
                'WHERE' => ['plugin_passvault_credentials_id' => $credentials_id],
                'ORDER' => 'itemtype ASC',
            ]
        );

        $items = [];
        foreach ($rows as $row) {
            $itemtype = $row['itemtype'];
            $items_id = (int) $row['items_id'];
            $name     = '';
            $url      = '';

            if (is_string($itemtype) && class_exists($itemtype)) {
                /** @var CommonDBTM $instance */
                $instance = new $itemtype();
                if ($instance->getFromDB($items_id)) {
                    $name = (string) ($instance->fields['name'] ?? '');
                    $url  = $instance->getFormURLWithID($items_id);
                }
            }

            $items[] = [
                'link_id'  => (int) $row['id'],
                'itemtype' => $itemtype,
                'items_id' => $items_id,
                'name'     => $name,
                'url'      => $url,
            ];
        }

        return $items;
    }

    /**
     * Validate and add a new link from a generic POST request.
     */
    public static function addFromRequest(array $post): bool
    {
        $credentials_id = (int) ($post['plugin_passvault_credentials_id'] ?? 0);
        if ($credentials_id <= 0) {
            Session::addMessageAfterRedirect(
                __('Missing credential reference.', 'passvault'),
                false,
                ERROR
            );
            return false;
        }

        $raw = (string) ($post['itemtype_items_id'] ?? '');
        if (!preg_match('/^(.+)\$(\d+)$/', $raw, $m)) {
            Session::addMessageAfterRedirect(
                __('You must select a device to link.', 'passvault'),
                false,
                ERROR
            );
            return false;
        }

        $itemtype = $m[1];
        $items_id = (int) $m[2];

        if (!class_exists($itemtype)) {
            Session::addMessageAfterRedirect(
                __('Invalid itemtype.', 'passvault'),
                false,
                ERROR
            );
            return false;
        }

        $link = new self();
        $existing = $link->find(
            [
                'plugin_passvault_credentials_id' => $credentials_id,
                'itemtype'                        => $itemtype,
                'items_id'                        => $items_id,
            ]
        );
        if (count($existing) > 0) {
            Session::addMessageAfterRedirect(
                __('This device is already linked to the credential.', 'passvault'),
                false,
                WARNING
            );
            return false;
        }

        $ok = $link->add(
            [
                'plugin_passvault_credentials_id' => $credentials_id,
                'itemtype'                        => $itemtype,
                'items_id'                        => $items_id,
            ]
        );

        if ($ok) {
            Session::addMessageAfterRedirect(
                __('Device linked.', 'passvault'),
                false,
                INFO
            );
        } else {
            Session::addMessageAfterRedirect(
                __('Failed to link the device.', 'passvault'),
                false,
                ERROR
            );
        }

        return (bool) $ok;
    }

    /**
     * Remove a link by its primary key.
     */
    public static function deleteFromRequest(array $post): bool
    {
        $id = (int) ($post['id'] ?? 0);
        if ($id <= 0) {
            return false;
        }
        $link = new self();
        $ok = $link->delete(['id' => $id]);
        if ($ok) {
            Session::addMessageAfterRedirect(
                __('Link removed.', 'passvault'),
                false,
                INFO
            );
        }
        return $ok;
    }

    public function prepareInputForAdd($input)
    {
        if (empty($input['plugin_passvault_credentials_id'])
            || empty($input['itemtype'])
            || empty($input['items_id'])
        ) {
            return false;
        }
        return $input;
    }
}
