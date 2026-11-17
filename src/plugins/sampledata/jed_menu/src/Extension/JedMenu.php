<?php

/**
 * @package     JED
 * @subpackage  Sampledata.jed_menu
 *
 * @copyright   (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\SampleData\JedMenu\Extension;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Event\SampleData\GetOverviewEvent;
use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Component\Menus\Administrator\Table\MenuTypeTable;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Sampledata - JED Menu Plugin
 *
 * Builds the JED front-end menu (and optionally a mod_menu module to display it) from a JSON definition.
 *
 * The definition is read from `menu.json` in this plugin's folder, so the menu can be changed without
 * touching PHP. If that file is missing, the built-in default (self::DEFAULT_MENU_JSON) is used instead.
 *
 * JSON structure:
 *
 * {
 *   "schema_version": 1,
 *   "menu": {
 *     "menutype":         "jedmainmenu",     // Unique machine name, max 24 chars (required)
 *     "title":            "JED Main Menu",   // Shown in Menus > Manage (required)
 *     "description":      "...",             // Optional
 *     "replace_existing": true               // What to do when the menu already exists (a second run):
 *                                            //   true (default) = remove all of its menu items and
 *                                            //        recreate them from this file; the menu itself and
 *                                            //        its menu module are kept and updated in place
 *                                            //   false = stop with an error and change nothing
 *   },
 *   "module": {                              // Optional. Omit to create the menu without a module.
 *     "title":     "JED Main Menu",
 *     "position":  "position-1",             // Template position. position-1 is the menu bar of the
 *                                            // "joomla" template.
 *     "showtitle": 0,
 *     "access":    "public",
 *     "ordering":  1,
 *     "menu_class": "nav-pills",            // "Menu Class" (Advanced tab): added to the menu <ul> next to "nav".
 *                                            // Space separated CSS classes, "" for none. Default "nav-pills"
 *     "params":    { ... }                   // Any mod_menu params; menutype is filled in automatically.
 *                                            // "layout": "joomla:dropdown" opens submenus as dropdowns
 *                                            // (templates/joomla/html/mod_menu/dropdown.php); it needs
 *                                            // "showAllChildren": 1
 *   },
 *   "items": [                               // Menu items, in display order
 *     {
 *       "title":        "Search",            // Required
 *       "alias":        "search",            // Optional, generated from the title when omitted
 *       "type":         "component",         // component | url | alias | heading | separator
 *                                            // Optional, inferred from "link" when omitted (see below)
 *       "link":         "index.php?option=com_jed&view=search",
 *       "alias_target": "home",              // type=alias only: "home" or a menu item id
 *       "access":       "public",            // Viewing access level title (public, guest, registered,
 *                                            // special, super users, ...) or its numeric id
 *       "published":    1,                   // 1 = published, 0 = unpublished
 *       "new_window":   false,               // Open link in a new browser window
 *       "note":         "",                  // Admin note
 *       "params":       { ... },             // Extra menu item params (merged over the defaults)
 *       "children":     [ ... ]              // Sub-items, same structure, any depth
 *     }
 *   ]
 * }
 *
 * Type inference when "type" is omitted: empty link => heading, "index.php?option=..." => component,
 * anything else => url. For backwards compatibility "url" is accepted as a synonym for "link",
 * a top-level "menu" array is accepted as the item list, and SEF style links such as
 * "index.php/component/jed/categories?id=0" are converted to "index.php?option=com_jed&view=categories&id=0".
 *
 * Running the sample data again:
 * The whole definition is validated before anything is changed. If the menu already exists, every item in it
 * (published, unpublished and trashed) is removed through com_menus' own item model, so delete events fire,
 * multilingual associations are cleaned up and the nested set stays intact, and the items are then recreated
 * from the JSON. The menu type keeps its id (and permissions) and step 2 updates the existing menu module
 * rather than adding a second copy.
 *
 * @since  4.0.0
 */
final class JedMenu extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Name of the JSON file (in this plugin's folder) that defines the menu.
     *
     * @since  4.0.0
     */
    private const MENU_FILE = 'menu.json';

    /**
     * Maximum length of the menutype column in #__menu_types.
     *
     * @since  4.0.0
     */
    private const MENUTYPE_MAX_LENGTH = 24;

    /**
     * Menu Class (mod_menu "class_sfx" option) used when "module.menu_class" is not in the definition.
     *
     * @since  4.0.0
     */
    private const DEFAULT_MENU_CLASS = 'nav-pills';

    /**
     * Supported menu item types.
     *
     * @since  4.0.0
     */
    private const ITEM_TYPES = ['component', 'url', 'alias', 'heading', 'separator'];

    /**
     * Core viewing access levels, used when a level title cannot be found in #__viewlevels.
     *
     * @since  4.0.0
     */
    private const CORE_ACCESS_LEVELS = [
        'public'      => 1,
        'registered'  => 2,
        'special'     => 3,
        'guest'       => 5,
        'super users' => 6,
    ];

    /**
     * Fallback menu definition, used only when menu.json is missing from the plugin folder.
     *
     * @since  4.0.0
     */
    private const DEFAULT_MENU_JSON = <<<'JSON'
{
  "schema_version": 1,
  "menu": {
    "menutype": "jedmainmenu",
    "title": "JED Main Menu",
    "description": "Main navigation for the Joomla! Extensions Directory",
    "replace_existing": true
  },
  "module": {
    "title": "JED Main Menu",
    "position": "position-1",
    "showtitle": 0,
    "access": "public",
    "menu_class": "nav-pills",
    "params": {
      "layout": "joomla:dropdown",
      "startLevel": 1,
      "endLevel": 0,
      "showAllChildren": 1,
      "module_tag": "nav"
    }
  },
  "items": [
    {
      "title": "Home",
      "alias": "jed-home",
      "type": "alias",
      "alias_target": "home"
    },
    {
      "title": "Browse Extensions",
      "type": "heading",
      "children": [
        { "title": "Top Rated", "link": "index.php?option=com_jed&view=extensions", "params": { "browse_list": "top-rated" } },
        { "title": "Most Reviewed", "link": "index.php?option=com_jed&view=extensions", "params": { "browse_list": "most-reviewed" } },
        { "title": "New", "link": "index.php?option=com_jed&view=extensions", "params": { "browse_list": "recently-added" } },
        { "title": "Recently Updated", "link": "index.php?option=com_jed&view=extensions", "params": { "browse_list": "recently-updated" } },
        { "title": "Compatible with J4", "link": "index.php?option=com_jed&view=extensions", "params": { "joomla_version": "40" } },
        { "title": "Compatible with J5", "link": "index.php?option=com_jed&view=extensions", "params": { "joomla_version": "50" } },
        { "title": "Compatible with J5 (with b/c plugin)", "link": "index.php?option=com_jed&view=extensions", "params": { "joomla_version": "51" } },
        { "title": "Compatible with J6", "link": "index.php?option=com_jed&view=extensions", "params": { "joomla_version": "60" } }
      ]
    },
    {
      "title": "Search",
      "link": "index.php?option=com_jed&view=search"
    },
    {
      "title": "Community",
      "type": "heading",
      "children": [
        { "title": "Meet the JED Team", "link": "index.php?option=com_jed&view=categories&id=0" },
        { "title": "Blog", "link": "index.php?option=com_jed&view=categories&id=0" },
        { "title": "JED Newsletter", "link": "index.php?option=com_jed&view=categories&id=0" },
        { "title": "Terms of Service", "link": "index.php?option=com_jed&view=categories&id=0" },
        { "title": "Help Joomla!", "link": "index.php?option=com_jed&view=categories&id=0" }
      ]
    },
    {
      "title": "Support",
      "type": "heading",
      "children": [
        { "title": "Knowledgebase", "link": "index.php?option=com_jed&view=categories&id=0" },
        { "title": "Sponsor Joomla!", "link": "index.php?option=com_jed&view=categories&id=0" }
      ]
    },
    {
      "title": "Vulnerable Extensions",
      "alias": "vulnerable-extensions",
      "link": "index.php?option=com_vel&view=advisories",
      "params": { "default_status": "live" },
      "children": [
        { "title": "About", "link": "index.php?option=com_jed&view=velliveitems" },
        { "title": "Live", "link": "index.php?option=com_vel&view=advisories", "params": { "default_status": "live", "page_title": "Vulnerable Extensions: Live" } },
        { "title": "Patched", "link": "index.php?option=com_vel&view=advisories", "params": { "default_status": "patched", "page_title": "Vulnerable Extensions: Patched" } },
        { "title": "Abandoned", "link": "index.php?option=com_vel&view=advisories", "params": { "default_status": "abandoned", "page_title": "Vulnerable Extensions: Abandoned" } },
        { "title": "Submit a Report", "link": "index.php?option=com_jed&view=velreportform" },
        { "title": "Submit an Update", "link": "index.php?option=com_jed&view=veldeveloperupdateform" },
        { "title": "Submit AbandonWare", "link": "index.php?option=com_jed&view=velabandonwareform" },
        { "title": "JSON Feed", "link": "index.php?option=com_jed&view=categories&id=0" }
      ]
    },
    {
      "title": "Login",
      "link": "index.php?option=com_users&view=login",
      "access": "guest"
    },
    {
      "title": "Register",
      "link": "index.php?option=com_users&view=registration",
      "access": "guest"
    },
    {
      "title": "Debug",
      "type": "heading",
      "children": [
        { "title": "Live VEL Items", "link": "index.php?option=com_vel&view=advisories" },
        { "title": "VEL Report a Vulnerability", "link": "index.php?option=com_vel&view=report" },
        { "title": "VEL Fix Notice", "link": "index.php?option=com_vel&view=fixnotice" },
        { "title": "VEL Single Advisory", "link": "index.php?option=com_vel&view=advisory" },
        { "title": "My Tickets", "link": "index.php?option=com_jed&view=tickets" },
        { "title": "Show Extension Categories", "link": "index.php?option=com_jed&view=categories&id=0" },
        { "title": "Submit Extension", "link": "index.php?option=com_jed&view=extensionform" },
        { "title": "User Control Panel", "link": "index.php?option=com_jed&view=controlpanel" }
      ]
    }
  ]
}
JSON;

    /**
     * Affects constructor behavior. If true, language files will be loaded automatically.
     *
     * @var    boolean
     * @since  4.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * The com_menus item model.
     *
     * @var    \Joomla\Component\Menus\Administrator\Model\ItemModel
     * @since  4.0.0
     */
    private $menuItemModel;

    /**
     * Cache of component records, keyed by option.
     *
     * @var    array
     * @since  4.0.0
     */
    private $componentIds = [];

    /**
     * Cache of viewing access levels, keyed by lower case title.
     *
     * @var    array|null
     * @since  4.0.0
     */
    private $accessLevels;

    /**
     * Non-fatal problems found while creating items, reported back to the user.
     *
     * @var    string[]
     * @since  4.0.0
     */
    private $warnings = [];

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onSampledataGetOverview'    => 'onSampledataGetOverview',
            'onAjaxSampledataApplyStep1' => 'onAjaxSampledataApplyStep1',
            'onAjaxSampledataApplyStep2' => 'onAjaxSampledataApplyStep2',
        ];
    }

    /**
     * Get an overview of the proposed sampledata.
     *
     * @param   GetOverviewEvent  $event  The event.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function onSampledataGetOverview(GetOverviewEvent $event): void
    {
        if (!$this->getApplication()->getIdentity()->authorise('core.create', 'com_menus')) {
            return;
        }

        $data              = new \stdClass();
        $data->name        = $this->_name;
        $data->title       = $this->text('PLG_SAMPLEDATA_JED_MENU_OVERVIEW_TITLE', 'JED Menu');
        $data->description = $this->text(
            'PLG_SAMPLEDATA_JED_MENU_OVERVIEW_DESC',
            'Creates the JED front-end menu (and its menu module) from plugins/sampledata/jed_menu/menu.json.'
        );
        $data->icon        = 'bars';
        $data->steps       = 2;

        $event->addResult($data);
    }

    /**
     * Step 1: create the menu and its menu items.
     *
     * @param   AjaxEvent  $event  The event.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function onAjaxSampledataApplyStep1(AjaxEvent $event): void
    {
        if (!$this->isOurRequest(true)) {
            return;
        }

        $app = $this->getApplication();

        if (!ComponentHelper::isEnabled('com_menus') || !$app->getIdentity()->authorise('core.create', 'com_menus')) {
            $event->addResult($this->response(true, $this->stepSkipped(1, 'com_menus')));

            return;
        }

        // Disable language debug to prevent debug_lang_const being added to the strings.
        $app->getLanguage()->setDebug(false);

        try {
            // Everything is validated here, before anything in the database is touched, so a broken
            // menu.json can never leave the site with the old items removed and no new ones.
            $definition = $this->loadDefinition($usedFallback);
            $menuTable  = $this->findMenuType($definition['menu']);

            $this->menuItemModel = $app->bootComponent('com_menus')->getMVCFactory()
                ->createModel('Item', 'Administrator', ['ignore_request' => true]);

            // Store the menu type first: on an existing menu this refuses when its items or menu module are
            // checked out by someone else, and it must do so before any of the old items are removed.
            $existed  = (int) $menuTable->id > 0;
            $menuType = $this->saveMenuType($menuTable, $definition['menu']);

            // Second run: clear out the items created last time, so they can be rebuilt from the JSON.
            $removed = $existed ? $this->removeMenuItems($menuTable) : 0;

            $count = $this->addMenuItems($definition['items'], $menuType, 1, 1);
        } catch (\Exception $e) {
            $event->addResult($this->response(false, $this->stepFailed(1, $e->getMessage())));

            return;
        }

        // Remember what we created for step 2.
        $app->setUserState('sampledata.jed_menu.menutype', $menuType);
        $app->setUserState('sampledata.jed_menu.module', $definition['module']);

        if ($removed > 0) {
            $message = $this->text(
                'PLG_SAMPLEDATA_JED_MENU_STEP1_REPLACED',
                'Menu "%1$s" already existed: removed %3$d old menu items and created %2$d new menu items.'
            );
        } else {
            $message = $this->text('PLG_SAMPLEDATA_JED_MENU_STEP1_SUCCESS', 'Created menu "%1$s" with %2$d menu items.');
        }

        $message = \sprintf($message, $menuType, $count, $removed);

        if ($usedFallback) {
            $this->warnings[] = \sprintf(
                $this->text('PLG_SAMPLEDATA_JED_MENU_WARNING_DEFAULT_USED', '%s was not found, the built-in default menu was used.'),
                self::MENU_FILE
            );
        }

        if ($this->warnings) {
            $message .= '<br>' . implode('<br>', array_map('htmlspecialchars', $this->warnings));
        }

        $event->addResult($this->response(true, $message));
    }

    /**
     * Step 2: create the mod_menu module that displays the new menu.
     *
     * @param   AjaxEvent  $event  The event.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function onAjaxSampledataApplyStep2(AjaxEvent $event): void
    {
        if (!$this->isOurRequest(true)) {
            return;
        }

        $app      = $this->getApplication();
        $menuType = (string) $app->getUserState('sampledata.jed_menu.menutype', '');
        $module   = $app->getUserState('sampledata.jed_menu.module');

        // Clear the state so a later run starts clean.
        $app->setUserState('sampledata.jed_menu.menutype', null);
        $app->setUserState('sampledata.jed_menu.module', null);

        if (empty($module)) {
            $event->addResult($this->response(true, $this->text('PLG_SAMPLEDATA_JED_MENU_STEP2_NO_MODULE', 'No module defined in the menu definition, so no menu module was created.')));

            return;
        }

        if ($menuType === '') {
            $event->addResult($this->response(false, $this->stepFailed(2, 'Menu type from step 1 not found.')));

            return;
        }

        if (!ComponentHelper::isEnabled('com_modules') || !$app->getIdentity()->authorise('core.create', 'com_modules')) {
            $event->addResult($this->response(true, $this->stepSkipped(2, 'com_modules')));

            return;
        }

        try {
            $updated = $this->saveMenuModule($module, $menuType);
        } catch (\Exception $e) {
            $event->addResult($this->response(false, $this->stepFailed(2, $e->getMessage())));

            return;
        }

        $message = $updated
            ? $this->text('PLG_SAMPLEDATA_JED_MENU_STEP2_UPDATED', 'Updated the existing menu module "%s" in position "%s".')
            : $this->text('PLG_SAMPLEDATA_JED_MENU_STEP2_SUCCESS', 'Created menu module "%s" in position "%s".');

        $event->addResult($this->response(true, \sprintf($message, $module['title'], $module['position'])));
    }

    /**
     * Load, decode and validate the menu definition.
     *
     * @param   boolean  $usedFallback  Set to true when the built-in default definition was used.
     *
     * @return  array  Normalised definition with keys menu, module and items.
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function loadDefinition(&$usedFallback = false): array
    {
        $file         = JPATH_PLUGINS . '/sampledata/' . $this->_name . '/' . self::MENU_FILE;
        $usedFallback = !is_file($file);

        if ($usedFallback) {
            $json = self::DEFAULT_MENU_JSON;
        } else {
            $json = file_get_contents($file);

            if ($json === false) {
                throw new \RuntimeException(\sprintf('Unable to read %s', $file));
            }

            // Strip a UTF-8 byte order mark, which some editors add and json_decode() rejects.
            $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);
        }

        $data = json_decode($json, true);

        if (!\is_array($data)) {
            throw new \RuntimeException(\sprintf('%s is not valid JSON: %s', self::MENU_FILE, json_last_error_msg()));
        }

        // Backwards compatibility: { "menu": [ ...items... ] }
        if (isset($data['menu']) && \is_array($data['menu']) && array_is_list($data['menu'])) {
            $data['items'] = $data['menu'];
            $data['menu']  = [];
        }

        $menu = isset($data['menu']) && \is_array($data['menu']) ? $data['menu'] : [];

        $menu += [
            'menutype'         => 'jedmainmenu',
            'title'            => 'JED Main Menu',
            'description'      => '',
            'replace_existing' => true,
        ];

        if (empty($data['items']) || !\is_array($data['items'])) {
            throw new \RuntimeException(\sprintf('%s does not contain any menu items ("items" array).', self::MENU_FILE));
        }

        $this->validateItems($data['items'], 'items');

        $module = null;

        if (!empty($data['module']) && \is_array($data['module'])) {
            $module = $data['module'] + [
                'title'     => $menu['title'],
                'position'  => 'menu',
                'showtitle' => 0,
                'access'    => 'public',
                'ordering'  => 1,
                'params'    => [],
            ];

            if (!\is_array($module['params'])) {
                throw new \RuntimeException('"module.params" must be an object.');
            }

            // Menu Class: "menu_class" wins, then a "class_sfx" given in params, then the default.
            if (!\array_key_exists('menu_class', $module)) {
                $module['menu_class'] = $module['params']['class_sfx'] ?? self::DEFAULT_MENU_CLASS;
            }

            if ($module['menu_class'] !== null && !\is_string($module['menu_class'])) {
                throw new \RuntimeException('"module.menu_class" must be a string.');
            }

            // Collapse whitespace: "a  b " => "a b".
            $module['menu_class'] = trim(preg_replace('/\s+/', ' ', (string) $module['menu_class']));

            // Saving through the model skips form validation, so apply the field's CssIdentifier rule here.
            if (!self::isValidCssClassList($module['menu_class'])) {
                throw new \RuntimeException(\sprintf(
                    '"module.menu_class" contains an invalid CSS class name: "%s".',
                    $module['menu_class']
                ));
            }
        }

        return [
            'menu'   => $menu,
            'module' => $module,
            'items'  => $data['items'],
        ];
    }

    /**
     * Validate the item tree before anything is written, so a typo does not leave a half built menu behind.
     *
     * @param   array   $items  Items to check.
     * @param   string  $path   JSON path of the items, used in error messages.
     *
     * @return  void
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function validateItems(array $items, string $path): void
    {
        foreach ($items as $index => $item) {
            $where = $path . '[' . $index . ']';

            if (!\is_array($item)) {
                throw new \RuntimeException(\sprintf('%s must be an object.', $where));
            }

            if (!isset($item['title']) || !\is_string($item['title']) || trim($item['title']) === '') {
                throw new \RuntimeException(\sprintf('%s has no "title".', $where));
            }

            $where .= ' ("' . $item['title'] . '")';

            if (isset($item['type']) && !\in_array($item['type'], self::ITEM_TYPES, true)) {
                throw new \RuntimeException(\sprintf('%s has unknown type "%s". Allowed: %s', $where, $item['type'], implode(', ', self::ITEM_TYPES)));
            }

            if (isset($item['params']) && !\is_array($item['params'])) {
                throw new \RuntimeException(\sprintf('%s "params" must be an object.', $where));
            }

            // Resolve link and type now, so errors surface before anything is saved.
            $type = $this->resolveType($item, $this->normaliseLink($this->rawLink($item), $where));

            if ($type === 'alias') {
                if (!isset($item['alias_target'])) {
                    throw new \RuntimeException(\sprintf('%s is of type "alias" but has no "alias_target".', $where));
                }

                // Resolve it now as well: on a second run the old items are removed before the new ones are
                // created, so an unresolvable target must be caught before that happens.
                $this->resolveAliasTarget($item['alias_target'], $this->languageInfo()[0]);
            }

            if (isset($item['access'])) {
                $this->resolveAccess($item['access'], $where);
            }

            if (isset($item['children'])) {
                if (!\is_array($item['children'])) {
                    throw new \RuntimeException(\sprintf('%s "children" must be an array.', $where));
                }

                $this->validateItems($item['children'], $where . '.children');
            }
        }
    }

    /**
     * Find the menu type (the menu itself) named in the definition.
     *
     * Returns the loaded table when the menu already exists (a second run), or an empty table when it does not.
     * Nothing is written here.
     *
     * @param   array  $menu  The "menu" section of the definition.
     *
     * @return  MenuTypeTable
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function findMenuType(array $menu): MenuTypeTable
    {
        [$language] = $this->languageInfo();

        // Keep menus for different languages apart on multilingual sites.
        $menuType = (string) $menu['menutype'];

        if ($language !== '*') {
            $menuType .= '-' . strtolower($language);
        }

        $menuType = substr(ApplicationHelper::stringURLSafe($menuType), 0, self::MENUTYPE_MAX_LENGTH);

        if ($menuType === '') {
            throw new \RuntimeException('"menu.menutype" is empty or contains no usable characters.');
        }

        $table = new MenuTypeTable($this->getDatabase());
        $table->setCurrentUser($this->getApplication()->getIdentity());

        if (!$table->load(['menutype' => $menuType])) {
            // A new menu: hand back an empty table that already carries the menutype to create.
            $table->reset();
            $table->id       = 0;
            $table->menutype = $menuType;

            return $table;
        }

        if ((int) $table->client_id !== 0) {
            throw new \RuntimeException(\sprintf('An administrator menu with menutype "%s" already exists. Choose another menutype.', $menuType));
        }

        if (empty($menu['replace_existing'])) {
            throw new \RuntimeException(\sprintf(
                'A menu with menutype "%s" already exists. Delete it first, or set "replace_existing": true in %s to have its menu items removed and recreated.',
                $menuType,
                self::MENU_FILE
            ));
        }

        return $table;
    }

    /**
     * Remove every menu item (any published state, trashed ones included) from an existing menu, so the items
     * can be recreated from the definition.
     *
     * The com_menus item model is used rather than a plain DELETE, so the nested set stays consistent,
     * onContentBeforeDelete/onContentAfterDelete fire, multilingual associations are removed and the menu caches
     * are cleaned. Items are deleted deepest first, so every item is a leaf by the time it is deleted.
     *
     * @param   MenuTypeTable  $menuTable  The existing menu.
     *
     * @return  integer  Number of menu items removed.
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function removeMenuItems(MenuTypeTable $menuTable): int
    {
        $db       = $this->getDatabase();
        $user     = $this->getApplication()->getIdentity();
        $menuType = (string) $menuTable->menutype;
        $asset    = 'com_menus.menu.' . (int) $menuTable->id;

        $query = $db->createQuery();
        $query->select($db->quoteName(['id', 'title', 'home', 'checked_out']))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('menutype') . ' = :menutype')
            ->where($db->quoteName('client_id') . ' = 0')
            ->order($db->quoteName('level') . ' DESC')
            ->order($db->quoteName('lft') . ' DESC')
            ->bind(':menutype', $menuType);

        $items = $db->setQuery($query)->loadObjectList();

        if (!$items) {
            return 0;
        }

        // Check everything that could stop part way first, so a failure leaves the old menu as it was.
        if (!$user->authorise('core.edit.state', $asset) || !$user->authorise('core.delete', $asset)) {
            throw new \RuntimeException(\sprintf(
                'The menu "%s" already exists and you are not allowed to trash and delete its menu items, so they cannot be replaced.',
                $menuType
            ));
        }

        foreach ($items as $item) {
            if ((int) $item->home === 1) {
                throw new \RuntimeException(\sprintf(
                    'The menu "%s" contains "%s", a default home page. Make another menu item the home page, then run this sample data again.',
                    $menuType,
                    $item->title
                ));
            }

            if (!empty($item->checked_out) && (int) $item->checked_out !== (int) $user->id) {
                throw new \RuntimeException(\sprintf(
                    'The menu item "%s" in menu "%s" is checked out by another user. Check it in, then run this sample data again.',
                    $item->title,
                    $menuType
                ));
            }
        }

        $ids = array_map('intval', array_column($items, 'id'));

        // com_menus only deletes trashed items, so trash them all first.
        $pks = $ids;

        if (!$this->menuItemModel->publish($pks, -2)) {
            throw new \RuntimeException(\sprintf(
                'Unable to trash the existing menu items of "%s": %s',
                $menuType,
                $this->menuItemModel->getError() ?: 'unknown error'
            ));
        }

        $pks = $ids;

        if (!$this->menuItemModel->delete($pks)) {
            throw new \RuntimeException(\sprintf(
                'Unable to delete the existing menu items of "%s": %s',
                $menuType,
                $this->menuItemModel->getError() ?: 'unknown error'
            ));
        }

        // Belt and braces: the models report some refusals only to the log, so make sure the menu really is empty
        // before new items (with the same aliases) are added to it.
        $query = $db->createQuery();
        $query->select('COUNT(*)')
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('menutype') . ' = :menutype')
            ->where($db->quoteName('client_id') . ' = 0')
            ->bind(':menutype', $menuType);

        $left = (int) $db->setQuery($query)->loadResult();

        if ($left > 0) {
            throw new \RuntimeException(\sprintf('%d of the existing menu items of "%s" could not be removed.', $left, $menuType));
        }

        return \count($ids);
    }

    /**
     * Create the menu type, or update the title and description of the existing one.
     *
     * @param   MenuTypeTable  $table  Table from findMenuType(): loaded (existing menu) or empty (new menu).
     * @param   array          $menu   The "menu" section of the definition.
     *
     * @return  string  The menutype.
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function saveMenuType(MenuTypeTable $table, array $menu): string
    {
        [, $langSuffix] = $this->languageInfo();

        $data = [
            'id'          => (int) $table->id,
            'menutype'    => (string) $table->menutype,
            'title'       => $menu['title'] . $langSuffix,
            'description' => (string) $menu['description'],
            'client_id'   => 0,
        ];

        if (!$table->bind($data) || !$table->check() || !$table->store()) {
            $this->getApplication()->getLanguage()->load('com_menus');

            throw new \RuntimeException(Text::_($table->getError()));
        }

        return $table->menutype;
    }

    /**
     * Recursively add menu items.
     *
     * @param   array    $items     Item definitions.
     * @param   string   $menuType  The menutype to add the items to.
     * @param   integer  $parentId  Parent menu item id (1 = root).
     * @param   integer  $level     Level in the menu tree.
     *
     * @return  integer  Number of items created.
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function addMenuItems(array $items, string $menuType, int $parentId, int $level): int
    {
        $app   = $this->getApplication();
        $user  = $app->getIdentity();
        $count = 0;

        [$language, $langSuffix] = $this->languageInfo();

        foreach ($items as $item) {
            $link = $this->normaliseLink($this->rawLink($item), $item['title']);
            $type = $this->resolveType($item, $link);

            $params = [
                'menu-anchor_title' => '',
                'menu-anchor_css'   => '',
                'menu_image'        => '',
                'menu_image_css'    => '',
                'menu_text'         => 1,
                'menu_show'         => 1,
            ];

            $componentId = 0;

            switch ($type) {
                case 'component':
                    $componentId = $this->componentId($link);

                    if ($componentId === 0) {
                        $this->warnings[] = \sprintf('Skipped "%s" (and any sub-items): component for "%s" is not installed.', $item['title'], $link);

                        continue 2;
                    }

                    $params['secure'] = 0;
                    break;

                case 'alias':
                    $link                     = 'index.php?Itemid=';
                    $params['aliasoptions']   = $this->resolveAliasTarget($item['alias_target'], $language);
                    $params['alias_redirect'] = 0;
                    break;

                case 'heading':
                case 'separator':
                    $link = '';
                    break;

                case 'url':
                default:
                    break;
            }

            if (!empty($item['params'])) {
                $params = array_merge($params, $item['params']);
            }

            $alias = isset($item['alias']) && trim((string) $item['alias']) !== ''
                ? ApplicationHelper::stringURLSafe($item['alias'], $language)
                : $this->aliasFromTitle($item['title'], $language);

            $data = [
                'id'                => 0,
                'menutype'          => $menuType,
                'title'             => $item['title'] . $langSuffix,
                'alias'             => $alias,
                'note'              => isset($item['note']) ? (string) $item['note'] : '',
                'link'              => $link,
                'type'              => $type,
                'component_id'      => $componentId,
                'parent_id'         => $parentId,
                'level'             => $level,
                'published'         => isset($item['published']) ? (int) $item['published'] : 1,
                'access'            => isset($item['access']) ? $this->resolveAccess($item['access'], $item['title']) : (int) $app->get('access', 1),
                'browserNav'        => !empty($item['new_window']) ? 1 : 0,
                'language'          => $language,
                'template_style_id' => 0,
                'home'              => 0,
                'img'               => '',
                'client_id'         => 0,
                'created_user_id'   => $user->id,
                'associations'      => [],
                'params'            => $params,
            ];

            $itemId = $this->saveMenuItem($data);
            $count++;

            if (!empty($item['children'])) {
                $count += $this->addMenuItems($item['children'], $menuType, $itemId, $level + 1);
            }
        }

        return $count;
    }

    /**
     * Save one menu item. Retries with "-1" / "-2" alias suffixes on alias clashes, as top level aliases
     * must be unique across all site menus (e.g. "login" may already exist in the Main Menu).
     *
     * @param   array  $data  Menu item data.
     *
     * @return  integer  The new menu item id.
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function saveMenuItem(array $data): int
    {
        $baseAlias = $data['alias'];

        foreach (['', '-1', '-2'] as $suffix) {
            $this->menuItemModel->setState('item.id', 0);

            $data['alias'] = $baseAlias . $suffix;

            if ($this->menuItemModel->save($data)) {
                return (int) $this->menuItemModel->getState('item.id');
            }
        }

        throw new \RuntimeException($data['title'] . ' => ' . $data['alias'] . ' : ' . $this->menuItemModel->getError());
    }

    /**
     * Create the mod_menu module for the menu, or update the existing one when the sample data is run again.
     *
     * @param   array   $module    The "module" section of the definition.
     * @param   string  $menuType  The menutype created in step 1.
     *
     * @return  boolean  True when an existing module was updated, false when a new one was created.
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function saveMenuModule(array $module, string $menuType): bool
    {
        $app = $this->getApplication();

        [$language] = $this->languageInfo();

        /** @var \Joomla\Component\Modules\Administrator\Model\ModuleModel $model */
        $model = $app->bootComponent('com_modules')->getMVCFactory()
            ->createModel('Module', 'Administrator', ['ignore_request' => true]);

        // On a second run, update the module created last time instead of adding a duplicate.
        $moduleId = $this->findMenuModule($menuType, (string) $module['title']);

        $params = array_merge(
            [
                'startLevel'      => 1,
                'endLevel'        => 0,
                'showAllChildren' => 1,
                'layout'          => '_:default',
                'cache'           => 1,
                'cache_time'      => 900,
                'cachemode'       => 'itemid',
                'module_tag'      => 'nav',
                'bootstrap_size'  => 0,
                'header_tag'      => 'h3',
                'style'           => 0,
            ],
            $module['params'],
            [
                // Always point the module at the menu we just created.
                'menutype'        => $menuType,
                // "Menu Class" on the module's Advanced tab, from "module.menu_class". mod_menu adds it to the
                // <ul class="mod-menu mod-list nav ..."> element, which is where Bootstrap classes such as nav-pills work.
                'class_sfx'       => (string) $module['menu_class'],
            ]
        );

        $data = [
            'id'         => $moduleId,
            'title'      => $module['title'],
            'note'       => '',
            'content'    => '',
            'position'   => $module['position'],
            'ordering'   => (int) $module['ordering'],
            'module'     => 'mod_menu',
            'access'     => $this->resolveAccess($module['access'], 'module'),
            'showtitle'  => (int) $module['showtitle'],
            'published'  => 1,
            'client_id'  => 0,
            'language'   => $language,
            'assignment' => 0,
            'assigned'   => [],
            'params'     => $params,
        ];

        if (!$model->save($data)) {
            $app->getLanguage()->load('com_modules');

            throw new \RuntimeException(Text::_($model->getError()));
        }

        return $moduleId > 0;
    }

    /**
     * Find the site mod_menu module that already shows the given menu (from an earlier run), ignoring trashed ones.
     *
     * When there are several, the one with the title from the definition wins, otherwise the oldest one. Any
     * other modules showing the same menu (e.g. a copy in another position made by hand) are left alone.
     *
     * @param   string  $menuType  The menutype.
     * @param   string  $title     The module title from the definition.
     *
     * @return  integer  Module id, or 0 when there is none.
     *
     * @since   4.0.0
     */
    private function findMenuModule(string $menuType, string $title): int
    {
        $db     = $this->getDatabase();
        $search = '%"menutype":' . json_encode($menuType) . '%';

        $query = $db->createQuery();
        $query->select($db->quoteName(['id', 'title', 'params']))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_menu'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('published') . ' <> -2')
            ->where($db->quoteName('params') . ' LIKE :search')
            ->order($db->quoteName('id') . ' ASC')
            ->bind(':search', $search);

        $found = 0;

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            // LIKE is only a pre-filter: make sure the module really shows this exact menu.
            $params = json_decode((string) $row->params, true);

            if (!\is_array($params) || ($params['menutype'] ?? null) !== $menuType) {
                continue;
            }

            if ($row->title === $title) {
                return (int) $row->id;
            }

            $found = $found ?: (int) $row->id;
        }

        return $found;
    }

    /**
     * Get the link of an item definition ("link", or the legacy "url" key).
     *
     * @param   array  $item  Item definition.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function rawLink(array $item): string
    {
        if (isset($item['link'])) {
            return trim((string) $item['link']);
        }

        return isset($item['url']) ? trim((string) $item['url']) : '';
    }

    /**
     * Convert SEF style JED links into the non-SEF form Joomla stores on menu items.
     *
     * "index.php/component/jed/categories?id=0" => "index.php?option=com_jed&view=categories&id=0"
     * "index.php/component/jed?view=controlpanel" => "index.php?option=com_jed&view=controlpanel"
     *
     * @param   string  $link   The link from the JSON.
     * @param   string  $where  Item description for error messages.
     *
     * @return  string
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function normaliseLink(string $link, string $where): string
    {
        if ($link === '' || strpos($link, 'index.php?') === 0) {
            return $link;
        }

        if (preg_match('#^/?index\.php/component/([a-z0-9_]+)(?:/([a-z0-9_]+))?/?(?:\?(.*))?$#i', $link, $m)) {
            $result = 'index.php?option=com_' . strtolower($m[1]);

            if (!empty($m[2])) {
                $result .= '&view=' . $m[2];
            }

            if (!empty($m[3])) {
                $result .= '&' . $m[3];
            }

            return $result;
        }

        // Anything else that looks like a relative Joomla SEF path cannot be converted reliably.
        if (strpos($link, 'index.php/') === 0) {
            throw new \RuntimeException(\sprintf(
                '%s: cannot convert SEF link "%s". Use the non-SEF form, e.g. "index.php?option=com_jed&view=...".',
                $where,
                $link
            ));
        }

        return $link;
    }

    /**
     * Work out the menu item type.
     *
     * @param   array   $item  Item definition.
     * @param   string  $link  Normalised link.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function resolveType(array $item, string $link): string
    {
        if (!empty($item['type'])) {
            return $item['type'];
        }

        if ($link === '') {
            return 'heading';
        }

        return strpos($link, 'index.php?option=') === 0 ? 'component' : 'url';
    }

    /**
     * Get the extension id of the component a link points to; 0 when it is not installed.
     *
     * @param   string  $link  Non-SEF link.
     *
     * @return  integer
     *
     * @since   4.0.0
     */
    private function componentId(string $link): int
    {
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        $option = isset($query['option']) ? (string) $query['option'] : '';

        if ($option === '') {
            return 0;
        }

        if (!\array_key_exists($option, $this->componentIds)) {
            $record = ExtensionHelper::getExtensionRecord($option, 'component');

            $this->componentIds[$option] = $record ? (int) $record->extension_id : 0;
        }

        return $this->componentIds[$option];
    }

    /**
     * Resolve an access level title (case insensitive) or id to an id.
     *
     * @param   mixed   $access  Title or id.
     * @param   string  $where   Item description for error messages.
     *
     * @return  integer
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function resolveAccess($access, string $where): int
    {
        if (\is_int($access) || ctype_digit((string) $access)) {
            return (int) $access;
        }

        if ($this->accessLevels === null) {
            $this->accessLevels = self::CORE_ACCESS_LEVELS;

            $db    = $this->getDatabase();
            $query = $db->createQuery();
            $query->select($db->quoteName(['id', 'title']))
                ->from($db->quoteName('#__viewlevels'));

            foreach ($db->setQuery($query)->loadObjectList() as $level) {
                $this->accessLevels[strtolower(trim($level->title))] = (int) $level->id;
            }
        }

        $key = strtolower(trim((string) $access));

        if (!isset($this->accessLevels[$key])) {
            throw new \RuntimeException(\sprintf('%s: unknown access level "%s".', $where, $access));
        }

        return $this->accessLevels[$key];
    }

    /**
     * Resolve the target of an alias menu item.
     *
     * @param   mixed   $target    "home" or a menu item id.
     * @param   string  $language  Language tag or "*".
     *
     * @return  integer
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function resolveAliasTarget($target, string $language): int
    {
        if (\is_int($target) || ctype_digit((string) $target)) {
            return (int) $target;
        }

        if (strtolower((string) $target) !== 'home') {
            throw new \RuntimeException(\sprintf('Unsupported alias_target "%s". Use "home" or a menu item id.', $target));
        }

        $home = $this->getApplication()->getMenu('site')->getDefault();
        $id   = $home ? (int) $home->id : 0;

        if (Multilanguage::isEnabled()) {
            $homes = Multilanguage::getSiteHomePages();

            if (isset($homes[$language])) {
                $id = (int) $homes[$language]->id;
            }
        }

        if ($id === 0) {
            throw new \RuntimeException('Could not find the site home page menu item for alias_target "home".');
        }

        return $id;
    }

    /**
     * Build an alias from a title, falling back to unicode slugs for titles with no latin characters.
     *
     * @param   string  $title     The title.
     * @param   string  $language  Language tag or "*".
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function aliasFromTitle(string $title, string $language): string
    {
        $alias = ApplicationHelper::stringURLSafe($title, $language);

        if (trim(str_replace('-', '', $alias)) === '') {
            $app     = $this->getApplication();
            $unicode = $app->set('unicodeslugs', 1);
            $alias   = ApplicationHelper::stringURLSafe($title, $language);
            $app->set('unicodeslugs', $unicode);
        }

        return $alias;
    }

    /**
     * Language tag to use and the suffix to add to titles on multilingual sites.
     *
     * @return  array  [language tag or '*', title suffix]
     *
     * @since   4.0.0
     */
    private function languageInfo(): array
    {
        $language = Multilanguage::isEnabled() ? $this->getApplication()->getLanguage()->getTag() : '*';

        return [$language, $language !== '*' ? ' (' . $language . ')' : ''];
    }

    /**
     * Check the request is a valid sample data request for this plugin.
     *
     * @param   boolean  $checkToken  Whether to verify the CSRF token.
     *
     * @return  boolean
     *
     * @since   4.0.0
     */
    private function isOurRequest(bool $checkToken): bool
    {
        if ($this->getApplication()->getInput()->get('type') !== $this->_name) {
            return false;
        }

        return !$checkToken || Session::checkToken('get');
    }

    /**
     * Build a sample data step response.
     *
     * @param   boolean  $success  Success flag.
     * @param   string   $message  Message.
     *
     * @return  array
     *
     * @since   4.0.0
     */
    private function response(bool $success, string $message): array
    {
        return ['success' => $success, 'message' => $message];
    }

    /**
     * Build a "step skipped" message.
     *
     * @param   integer  $step       Step number.
     * @param   string   $component  The component that is disabled or not permitted.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function stepSkipped(int $step, string $component): string
    {
        $format = $this->text('PLG_SAMPLEDATA_JED_MENU_STEP_SKIPPED', 'Step %1$d skipped: %2$s is disabled or you lack permission to use it.');

        return \sprintf($format, $step, $component);
    }

    /**
     * Build a "step failed" message.
     *
     * @param   integer  $step     Step number.
     * @param   string   $message  Error detail.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function stepFailed(int $step, string $message): string
    {
        $format = $this->text('PLG_SAMPLEDATA_JED_MENU_STEP_FAILED', 'Step %1$d failed: %2$s');

        return \sprintf($format, $step, $message);
    }

    /**
     * Translate a language key, falling back to English text if the key is not in the language files.
     *
     * @param   string  $key       Language key.
     * @param   string  $fallback  Fallback text.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function text(string $key, string $fallback): string
    {
        $language = $this->getApplication()->getLanguage();

        return $language->hasKey($key) ? $language->_($key) : $fallback;
    }

    /**
     * Check a space separated list of CSS class names, using the same rules as Joomla's CssIdentifier form rule
     * (which validates the Menu Class field when a module is saved from the backend).
     *
     * @param   string  $classes  Class list, e.g. "nav-pills my-menu". An empty string is valid.
     *
     * @return  boolean
     *
     * @since   4.0.0
     */
    private static function isValidCssClassList(string $classes): bool
    {
        if ($classes === '') {
            return true;
        }

        foreach (explode(' ', $classes) as $class) {
            // Allowed: - 0-9 : @ A-Z _ a-z and U+00A1 and higher.
            if (preg_match('/[^\x{002D}\x{0030}-\x{003A}\x{0040}-\x{005A}\x{005F}\x{0061}-\x{007A}\x{00A1}-\x{FFFF}]/u', $class)) {
                return false;
            }

            // Cannot start with a digit, two hyphens, or a hyphen followed by a digit.
            if (preg_match('/^[0-9]|^-[0-9]|^--/', $class)) {
                return false;
            }
        }

        return true;
    }
}
