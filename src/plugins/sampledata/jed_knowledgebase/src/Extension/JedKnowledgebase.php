<?php

/**
 * @package     JED
 * @subpackage  Sampledata.jed_knowledgebase
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\SampleData\JedKnowledgebase\Extension;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Event\SampleData\GetOverviewEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Sample data - JED Knowledgebase.
 *
 * Builds a knowledgebase in com_content - one parent category, its subcategories and their articles - from
 * knowledgebase.json in this plugin's folder, the way plg_sampledata_jed_menu builds the JED menu from menu.json.
 *
 * JSON structure:
 *
 * {
 *   "schema_version": 1,
 *   "replace_existing": true,              // What a second run does when the parent category already exists:
 *                                          //   true (default) = keep the parent category (same id, so links and
 *                                          //        menu items to it keep working), update it, and remove and
 *                                          //        recreate EVERYTHING below it - subcategories and articles,
 *                                          //        including any added by hand since
 *                                          //   false = stop with an error and change nothing
 *   "parent_path": "",                     // Optional. Path of an existing com_content category to put the
 *                                          // knowledgebase under, e.g. "help". Empty = top level.
 *   "defaults": {                          // Optional. Applied to every category and article unless they say
 *     "access":    "public",               //   otherwise. Viewing access level title or id.
 *     "language":  "*",                    //   "*" or an installed content language, e.g. "en-GB"
 *     "published": 1,                      //   1 published, 0 unpublished, 2 archived
 *     "author_alias": ""                   //   Articles only: "Written by" name instead of the user running this
 *   },
 *   "category": {                          // The parent category (required)
 *     "title": "Knowledgebase",            // Required
 *     "alias": "knowledgebase",            // Optional, made from the title when omitted
 *     "description": "<p>...</p>",         // Optional HTML
 *     "note": "", "metadesc": "", "metakey": "",
 *     "params": { ... },                   // Optional category options, e.g. {"category_layout": "_:blog"}
 *     "access": ..., "language": ..., "published": ...,
 *     "articles": [ ... ],                 // Optional for the parent category
 *     "children": [                        // The subcategories. Same structure as a category, any depth.
 *       {
 *         "title": "Getting Started",
 *         "articles": [                    // REQUIRED, at least one: every subcategory has 1 or more articles
 *           {
 *             "title": "What is the JED?", // Required
 *             "alias": "...",              // Optional, made from the title when omitted
 *             "introtext": "<p>...</p>",   // The text: either introtext (plus an optional fulltext, shown
 *             "fulltext": "<p>...</p>",    //   after "Read more") ...
 *             "file": "articles/x.html",   //   ... or an HTML file in this plugin's folder. A line
 *                                          //   <hr id="system-readmore"> in it splits intro and full text.
 *             "featured": false,
 *             "tags": ["Getting started"], // Tag titles. Missing tags are created (needs core.create on com_tags).
 *             "author_alias": "", "note": "", "metadesc": "", "metakey": "",
 *             "attribs": {}, "images": {}, "urls": {}, "metadata": {},   // Passed to the article as they are
 *             "access": ..., "language": ..., "published": ...
 *           }
 *         ],
 *         "children": [ ... ]
 *       }
 *     ]
 *   }
 * }
 *
 * Keys starting with an underscore (e.g. "_comment") are ignored anywhere, so the file can carry notes. Any other
 * key that is not listed above is reported as an error, so a typo cannot silently drop part of the tree.
 *
 * The whole definition - including the article files - is read and checked before anything is changed, so a
 * mistake in it cannot leave the old knowledgebase removed and the new one half built. Everything is written
 * through com_categories' and com_content's own models, so versions, tags, workflows, custom field values,
 * associations and assets are handled as they would be from the administrator.
 *
 * @since  4.0.0
 */
final class JedKnowledgebase extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Name of the definition file in this plugin's folder.
     *
     * @since  4.0.0
     */
    private const DEFINITION_FILE = 'knowledgebase.json';

    /**
     * The schema version this code reads.
     *
     * @since  4.0.0
     */
    private const SCHEMA_VERSION = 1;

    /**
     * The marker Joomla splits intro and full text on.
     *
     * @since  4.0.0
     */
    private const READMORE = '<hr id="system-readmore">';

    /**
     * The keys each part of the definition may have. Anything else is reported, so a typo such as "childen"
     * cannot silently drop part of the knowledgebase. Keys starting with "_" are always allowed, for notes.
     *
     * @since  4.0.0
     */
    private const KEYS = [
        'file'     => ['schema_version', 'replace_existing', 'parent_path', 'defaults', 'category'],
        'defaults' => ['access', 'language', 'published', 'author_alias'],
        'category' => [
            'title', 'alias', 'description', 'note', 'metadesc', 'metakey', 'params', 'access', 'language',
            'published', 'articles', 'children',
        ],
        'article'  => [
            'title', 'alias', 'introtext', 'fulltext', 'file', 'featured', 'tags', 'author_alias', 'note',
            'metadesc', 'metakey', 'attribs', 'images', 'urls', 'metadata', 'access', 'language', 'published',
        ],
    ];

    /**
     * Allowed published states: unpublished, published, archived.
     *
     * @since  4.0.0
     */
    private const STATES = [0, 1, 2];

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
     * Affects constructor behavior. If true, language files will be loaded automatically.
     *
     * @var    boolean
     * @since  4.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * Cache of viewing access levels, keyed by lower case title.
     *
     * @var    array|null
     * @since  4.0.0
     */
    private ?array $accessLevels = null;

    /**
     * Cache of content language tags that exist on the site.
     *
     * @var    string[]|null
     * @since  4.0.0
     */
    private ?array $contentLanguages = null;

    /**
     * Non-fatal problems, reported back with the result.
     *
     * @var    string[]
     * @since  4.0.0
     */
    private array $warnings = [];

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
        ];
    }

    /**
     * Get an overview of the proposed sample data.
     *
     * @param   GetOverviewEvent  $event  The event.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function onSampledataGetOverview(GetOverviewEvent $event): void
    {
        if (!$this->getApplication()->getIdentity()->authorise('core.create', 'com_content')) {
            return;
        }

        $data              = new \stdClass();
        $data->name        = $this->_name;
        $data->title       = Text::_('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_OVERVIEW_TITLE');
        $data->description = Text::_('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_OVERVIEW_DESC');
        $data->icon        = 'book';
        $data->steps       = 1;

        $event->addResult($data);
    }

    /**
     * The only step: build (or rebuild) the knowledgebase.
     *
     * @param   AjaxEvent  $event  The event.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function onAjaxSampledataApplyStep1(AjaxEvent $event): void
    {
        $app = $this->getApplication();

        if (!Session::checkToken('get') || $app->getInput()->get('type') !== $this->_name) {
            return;
        }

        if (!ComponentHelper::isEnabled('com_content') || !$app->getIdentity()->authorise('core.create', 'com_content')) {
            $event->addResult($this->response(true, Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_STEP_SKIPPED', 1, 'com_content')));

            return;
        }

        // Language debug would wrap the stored texts in debug markers.
        $app->getLanguage()->setDebug(false);

        try {
            // 1. Read and check everything. Nothing has been changed yet.
            $definition = $this->loadDefinition();
            $parentId   = $this->resolveParentPath($definition['parent_path']);
            $existing   = $this->findExistingRoot($parentId, $definition['category']['alias']);

            if ($existing !== null && !$definition['replace_existing']) {
                throw new \RuntimeException(Text::sprintf(
                    'PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_EXISTS',
                    $existing->title,
                    $existing->path,
                    self::DEFINITION_FILE
                ));
            }

            // 2. A second run: check that everything below the old parent can go, then remove it.
            $removed = ['categories' => 0, 'articles' => 0];

            if ($existing !== null) {
                $plan    = $this->planRemoval($existing);
                $removed = $this->remove($plan);
            }

            // 3. Build the new tree. The parent keeps its id on a second run.
            $created = ['categories' => 0, 'articles' => 0];
            $rootId  = $this->saveCategory($definition['category'], $parentId, $existing !== null ? (int) $existing->id : 0);

            if ($existing === null) {
                $created['categories']++;
            }

            $this->buildCategory($definition['category'], $rootId, $created);
        } catch (\Throwable $e) {
            $event->addResult($this->response(false, Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_STEP_FAILED', 1, $e->getMessage())));

            return;
        }

        $title   = $definition['category']['title'];
        $message = $existing !== null
            ? Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_STEP1_REPLACED', $title, $created['categories'], $created['articles'], $removed['categories'], $removed['articles'])
            : Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_STEP1_SUCCESS', $title, $created['categories'], $created['articles']);

        if ($this->warnings) {
            $message .= '<br>' . implode('<br>', array_map('htmlspecialchars', array_unique($this->warnings)));
        }

        $event->addResult($this->response(true, $message));
    }

    /**
     * Read, check and normalise the definition file.
     *
     * @return  array  ['replace_existing' => bool, 'parent_path' => string, 'category' => array]
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function loadDefinition(): array
    {
        $file = $this->pluginPath() . '/' . self::DEFINITION_FILE;

        if (!is_file($file)) {
            throw new \RuntimeException(Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NO_FILE', self::DEFINITION_FILE));
        }

        $json = (string) file_get_contents($file);
        $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);
        $data = json_decode($json, true);

        if (!\is_array($data)) {
            throw new \RuntimeException(Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_INVALID_JSON', self::DEFINITION_FILE, json_last_error_msg()));
        }

        $version = (int) ($data['schema_version'] ?? self::SCHEMA_VERSION);

        if ($version !== self::SCHEMA_VERSION) {
            throw new \RuntimeException(Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_SCHEMA', $version, self::SCHEMA_VERSION));
        }

        if (!isset($data['category']) || !\is_array($data['category']) || array_is_list($data['category'])) {
            throw new \RuntimeException(Text::_('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NO_CATEGORY'));
        }

        $errors = [];

        $this->checkKeys($data, 'file', self::DEFINITION_FILE, $errors);

        $defaults = $data['defaults'] ?? [];

        if (!\is_array($defaults) || ($defaults !== [] && array_is_list($defaults))) {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NOT_AN_OBJECT', 'defaults');
            $defaults = [];
        }

        $this->checkKeys($defaults, 'defaults', 'defaults', $errors);

        $defaults += [
            'access'       => (int) $this->getApplication()->get('access', 1),
            'language'     => '*',
            'published'    => 1,
            'author_alias' => '',
        ];

        $category = $this->normaliseCategory($data['category'], $defaults, 'category', true, $errors);

        if ($errors) {
            // Report every problem at once, so the file can be fixed in one go.
            throw new \RuntimeException(implode('<br>', array_map('htmlspecialchars', $errors)));
        }

        return [
            'replace_existing' => !\array_key_exists('replace_existing', $data) || !empty($data['replace_existing']),
            'parent_path'      => trim((string) ($data['parent_path'] ?? ''), " /\t\n\r"),
            'category'         => $category,
        ];
    }

    /**
     * Check and normalise one category and everything below it.
     *
     * @param   array     $raw       The category from the JSON.
     * @param   array     $defaults  The defaults.
     * @param   string    $where     JSON path, for messages.
     * @param   boolean   $isRoot    Whether this is the parent category (articles are optional for it).
     * @param   string[]  $errors    Collected problems.
     *
     * @return  array
     *
     * @since   4.0.0
     */
    private function normaliseCategory(array $raw, array $defaults, string $where, bool $isRoot, array &$errors): array
    {
        $title = trim((string) ($raw['title'] ?? ''));

        if ($title === '') {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NO_TITLE', $where);
            $title    = '?';
        } else {
            $where .= ' ("' . $title . '")';
        }

        $this->checkKeys($raw, 'category', $where, $errors);

        $language = $this->checkLanguage($raw['language'] ?? $defaults['language'], $where, $errors);

        $category = [
            'title'       => $title,
            'alias'       => $this->makeAlias($raw['alias'] ?? '', $title, $language, $where, $errors),
            'description' => (string) ($raw['description'] ?? ''),
            'note'        => (string) ($raw['note'] ?? ''),
            'metadesc'    => (string) ($raw['metadesc'] ?? ''),
            'metakey'     => (string) ($raw['metakey'] ?? ''),
            'params'      => $this->checkObject($raw, 'params', $where, $errors),
            'access'      => $this->checkAccess($raw['access'] ?? $defaults['access'], $where, $errors),
            'language'    => $language,
            'published'   => $this->checkState($raw['published'] ?? $defaults['published'], $where, $errors),
            'articles'    => [],
            'children'    => [],
        ];

        // Articles. Every subcategory has at least one; for the parent category they are optional.
        $articles = $raw['articles'] ?? [];

        if (!\is_array($articles) || !array_is_list($articles)) {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NOT_A_LIST', $where, 'articles');
            $articles = [];
        }

        if (!$isRoot && $articles === []) {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NO_ARTICLES', $where);
        }

        $aliases = [];

        foreach ($articles as $i => $article) {
            $path = $where . '.articles[' . $i . ']';

            if (!\is_array($article) || array_is_list($article)) {
                $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NOT_AN_OBJECT', $path);

                continue;
            }

            $normalised = $this->normaliseArticle($article, $defaults, $path, $errors);

            // Aliases must be unique within a category, or the second save would fail half way through.
            if ($normalised['alias'] !== '' && isset($aliases[$normalised['alias']])) {
                $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_DUPLICATE_ALIAS', $path, $normalised['alias']);
            }

            $aliases[$normalised['alias']] = true;
            $category['articles'][]        = $normalised;
        }

        // Subcategories.
        $children = $raw['children'] ?? [];

        if (!\is_array($children) || !array_is_list($children)) {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NOT_A_LIST', $where, 'children');
            $children = [];
        }

        if ($isRoot && $children === []) {
            $errors[] = Text::_('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NO_CHILDREN');
        }

        $aliases = [];

        foreach ($children as $i => $child) {
            $path = $where . '.children[' . $i . ']';

            if (!\is_array($child) || array_is_list($child)) {
                $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NOT_AN_OBJECT', $path);

                continue;
            }

            $normalised = $this->normaliseCategory($child, $defaults, $path, false, $errors);

            if ($normalised['alias'] !== '' && isset($aliases[$normalised['alias']])) {
                $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_DUPLICATE_ALIAS', $path, $normalised['alias']);
            }

            $aliases[$normalised['alias']] = true;
            $category['children'][]        = $normalised;
        }

        return $category;
    }

    /**
     * Check and normalise one article, reading its text from a file when it names one.
     *
     * @param   array     $raw       The article from the JSON.
     * @param   array     $defaults  The defaults.
     * @param   string    $where     JSON path, for messages.
     * @param   string[]  $errors    Collected problems.
     *
     * @return  array
     *
     * @since   4.0.0
     */
    private function normaliseArticle(array $raw, array $defaults, string $where, array &$errors): array
    {
        $title = trim((string) ($raw['title'] ?? ''));

        if ($title === '') {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NO_TITLE', $where);
            $title    = '?';
        } else {
            $where .= ' ("' . $title . '")';
        }

        $this->checkKeys($raw, 'article', $where, $errors);

        $language = $this->checkLanguage($raw['language'] ?? $defaults['language'], $where, $errors);

        // The text: inline, or from a file - not both, and not neither.
        $hasInline = trim((string) ($raw['introtext'] ?? '')) !== '' || trim((string) ($raw['fulltext'] ?? '')) !== '';
        $hasFile   = trim((string) ($raw['file'] ?? '')) !== '';
        $text      = '';

        if ($hasInline && $hasFile) {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_TEXT_TWICE', $where);
        } elseif ($hasFile) {
            $text = $this->readArticleFile((string) $raw['file'], $where, $errors);
        } elseif ($hasInline) {
            $intro    = (string) ($raw['introtext'] ?? '');
            $fulltext = (string) ($raw['fulltext'] ?? '');
            $text     = trim($fulltext) !== '' ? $intro . self::READMORE . $fulltext : $intro;
        } else {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NO_TEXT', $where);
        }

        $tags = $raw['tags'] ?? [];

        if (!\is_array($tags) || !array_is_list($tags)) {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NOT_A_LIST', $where, 'tags');
            $tags     = [];
        }

        $tags = array_values(array_unique(array_filter(array_map(static fn ($t) => trim((string) $t), $tags), static fn ($t) => $t !== '')));

        return [
            'title'        => $title,
            'alias'        => $this->makeAlias($raw['alias'] ?? '', $title, $language, $where, $errors),
            'articletext'  => $text,
            'state'        => $this->checkState($raw['published'] ?? $defaults['published'], $where, $errors),
            'access'       => $this->checkAccess($raw['access'] ?? $defaults['access'], $where, $errors),
            'language'     => $language,
            'featured'     => !empty($raw['featured']) ? 1 : 0,
            'tags'         => $tags,
            'author_alias' => (string) ($raw['author_alias'] ?? $defaults['author_alias']),
            'note'         => (string) ($raw['note'] ?? ''),
            'metadesc'     => (string) ($raw['metadesc'] ?? ''),
            'metakey'      => (string) ($raw['metakey'] ?? ''),
            'attribs'      => $this->checkObject($raw, 'attribs', $where, $errors),
            'images'       => $this->checkObject($raw, 'images', $where, $errors),
            'urls'         => $this->checkObject($raw, 'urls', $where, $errors),
            'metadata'     => $this->checkObject($raw, 'metadata', $where, $errors),
        ];
    }

    /**
     * Read an article's HTML file. Only .html/.htm files inside this plugin's folder are accepted.
     *
     * @param   string    $relative  Path relative to the plugin folder.
     * @param   string    $where     JSON path, for messages.
     * @param   string[]  $errors    Collected problems.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function readArticleFile(string $relative, string $where, array &$errors): string
    {
        $base = realpath($this->pluginPath());
        $path = realpath($this->pluginPath() . '/' . ltrim($relative, '/\\'));

        // realpath() resolves "..", so a path that still starts with the plugin folder really is inside it.
        if ($base === false || $path === false || !is_file($path) || !str_starts_with($path, $base . \DIRECTORY_SEPARATOR)) {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_FILE_NOT_FOUND', $where, $relative);

            return '';
        }

        if (!\in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['html', 'htm'], true)) {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_FILE_TYPE', $where, $relative);

            return '';
        }

        $html = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path));

        if (trim($html) === '') {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_FILE_EMPTY', $where, $relative);
        }

        return $html;
    }

    /**
     * The alias to use: the given one or one made from the title, in Joomla's URL safe form.
     *
     * @param   mixed     $alias     Alias from the JSON, may be empty.
     * @param   string    $title     The title.
     * @param   string    $language  Language tag or "*".
     * @param   string    $where     JSON path, for messages.
     * @param   string[]  $errors    Collected problems.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function makeAlias($alias, string $title, string $language, string $where, array &$errors): string
    {
        $source = trim((string) $alias) !== '' ? (string) $alias : $title;
        $result = ApplicationHelper::stringURLSafe($source, $language);

        // Titles without latin characters: fall back to unicode aliases, as Joomla does.
        if (trim(str_replace('-', '', $result)) === '') {
            $app     = $this->getApplication();
            $unicode = $app->set('unicodeslugs', 1);
            $result  = ApplicationHelper::stringURLSafe($source, $language);
            $app->set('unicodeslugs', $unicode);
        }

        if (trim(str_replace('-', '', $result)) === '') {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NO_ALIAS', $where);

            return '';
        }

        return $result;
    }

    /**
     * Report keys that this part of the definition does not have.
     *
     * @param   array     $raw     The part of the JSON.
     * @param   string    $type    Which part: a key of self::KEYS.
     * @param   string    $where   JSON path, for messages.
     * @param   string[]  $errors  Collected problems.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    private function checkKeys(array $raw, string $type, string $where, array &$errors): void
    {
        foreach (array_keys($raw) as $key) {
            if (!str_starts_with((string) $key, '_') && !\in_array($key, self::KEYS[$type], true)) {
                $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_UNKNOWN_KEY', $where, $key, implode(', ', self::KEYS[$type]));
            }
        }
    }

    /**
     * An optional JSON object (e.g. "params"), as an array.
     *
     * @param   array     $raw     The item from the JSON.
     * @param   string    $key     The key.
     * @param   string    $where   JSON path, for messages.
     * @param   string[]  $errors  Collected problems.
     *
     * @return  array
     *
     * @since   4.0.0
     */
    private function checkObject(array $raw, string $key, string $where, array &$errors): array
    {
        if (!isset($raw[$key]) || $raw[$key] === []) {
            return [];
        }

        if (!\is_array($raw[$key]) || array_is_list($raw[$key])) {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NOT_AN_OBJECT', $where . '.' . $key);

            return [];
        }

        return $raw[$key];
    }

    /**
     * A published state: 0, 1 or 2.
     *
     * @param   mixed     $state   The value from the JSON.
     * @param   string    $where   JSON path, for messages.
     * @param   string[]  $errors  Collected problems.
     *
     * @return  integer
     *
     * @since   4.0.0
     */
    private function checkState($state, string $where, array &$errors): int
    {
        if (\is_bool($state)) {
            return (int) $state;
        }

        if (!is_numeric($state) || !\in_array((int) $state, self::STATES, true)) {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_STATE', $where, (string) $state);

            return 1;
        }

        return (int) $state;
    }

    /**
     * A viewing access level id, from a title (case insensitive) or an id.
     *
     * @param   mixed     $access  The value from the JSON.
     * @param   string    $where   JSON path, for messages.
     * @param   string[]  $errors  Collected problems.
     *
     * @return  integer
     *
     * @since   4.0.0
     */
    private function checkAccess($access, string $where, array &$errors): int
    {
        if ($this->accessLevels === null) {
            $this->accessLevels = self::CORE_ACCESS_LEVELS;

            $db    = $this->getDatabase();
            $query = $db->createQuery()
                ->select($db->quoteName(['id', 'title']))
                ->from($db->quoteName('#__viewlevels'));

            foreach ($db->setQuery($query)->loadObjectList() as $level) {
                $this->accessLevels[strtolower(trim($level->title))] = (int) $level->id;
            }
        }

        if (\is_int($access) || ctype_digit((string) $access)) {
            if (\in_array((int) $access, $this->accessLevels, true)) {
                return (int) $access;
            }
        } elseif (isset($this->accessLevels[strtolower(trim((string) $access))])) {
            return $this->accessLevels[strtolower(trim((string) $access))];
        }

        $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_ACCESS', $where, (string) $access);

        return 1;
    }

    /**
     * A language tag: "*" or a content language that exists on this site.
     *
     * @param   mixed     $language  The value from the JSON.
     * @param   string    $where     JSON path, for messages.
     * @param   string[]  $errors    Collected problems.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function checkLanguage($language, string $where, array &$errors): string
    {
        $language = trim((string) $language);

        if ($language === '' || $language === '*') {
            return '*';
        }

        if ($this->contentLanguages === null) {
            $db    = $this->getDatabase();
            $query = $db->createQuery()
                ->select($db->quoteName('lang_code'))
                ->from($db->quoteName('#__languages'));

            $this->contentLanguages = $db->setQuery($query)->loadColumn();
        }

        if (!\in_array($language, $this->contentLanguages, true)) {
            $errors[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_LANGUAGE', $where, $language);

            return '*';
        }

        return $language;
    }

    /**
     * The id of the category to put the knowledgebase under.
     *
     * @param   string  $path  Path of an existing com_content category, or "" for the top level.
     *
     * @return  integer
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function resolveParentPath(string $path): int
    {
        if ($path === '') {
            return 1;
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('path') . ' = :path')
            ->bind(':path', $path);

        $id = (int) $db->setQuery($query)->loadResult();

        if ($id === 0) {
            throw new \RuntimeException(Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NO_PARENT', $path));
        }

        return $id;
    }

    /**
     * The knowledgebase's parent category from an earlier run, if there is one (in any published state).
     *
     * @param   integer  $parentId  Where the knowledgebase goes.
     * @param   string   $alias     The parent category's alias.
     *
     * @return  object|null
     *
     * @since   4.0.0
     */
    private function findExistingRoot(int $parentId, string $alias): ?object
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'title', 'path', 'lft', 'rgt', 'checked_out']))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('parent_id') . ' = :parent')
            ->where($db->quoteName('alias') . ' = :alias')
            ->bind(':parent', $parentId, ParameterType::INTEGER)
            ->bind(':alias', $alias);

        return $db->setQuery($query)->loadObject() ?: null;
    }

    /**
     * Everything below an existing parent category, checked for whether it can be removed.
     *
     * All checks are made before anything is removed, so a refusal leaves the old knowledgebase as it was.
     *
     * @param   object  $root  The existing parent category.
     *
     * @return  array  ['categories' => int[] deepest first, 'articles' => int[]]
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function planRemoval(object $root): array
    {
        $db   = $this->getDatabase();
        $user = $this->getApplication()->getIdentity();
        $lft  = (int) $root->lft;
        $rgt  = (int) $root->rgt;

        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'title', 'checked_out']))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('lft') . ' > :lft')
            ->where($db->quoteName('rgt') . ' < :rgt')
            ->order($db->quoteName('level') . ' DESC')
            ->order($db->quoteName('lft') . ' DESC')
            ->bind(':lft', $lft, ParameterType::INTEGER)
            ->bind(':rgt', $rgt, ParameterType::INTEGER);

        $categories = $db->setQuery($query)->loadObjectList();
        $catIds     = array_merge([(int) $root->id], array_map(static fn ($c) => (int) $c->id, $categories));

        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'title', 'checked_out']))
            ->from($db->quoteName('#__content'))
            ->whereIn($db->quoteName('catid'), $catIds);

        $articles = $db->setQuery($query)->loadObjectList();

        // The parent category is kept and updated.
        if (!$user->authorise('core.edit', 'com_content.category.' . (int) $root->id)) {
            throw new \RuntimeException(Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NOT_ALLOWED', $root->title));
        }

        foreach ([[$root], $categories, $articles] as $rows) {
            foreach ($rows as $row) {
                if (!empty($row->checked_out) && (int) $row->checked_out !== (int) $user->id) {
                    throw new \RuntimeException(Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_CHECKED_OUT', $row->title));
                }
            }
        }

        foreach ($categories as $category) {
            if (!$user->authorise('core.delete', 'com_content.category.' . (int) $category->id)) {
                throw new \RuntimeException(Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NOT_ALLOWED', $category->title));
            }
        }

        foreach ($articles as $article) {
            if (!$user->authorise('core.delete', 'com_content.article.' . (int) $article->id)) {
                throw new \RuntimeException(Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_NOT_ALLOWED', $article->title));
            }
        }

        return [
            'categories' => array_map(static fn ($c) => (int) $c->id, $categories),
            'articles'   => array_map(static fn ($a) => (int) $a->id, $articles),
        ];
    }

    /**
     * Remove the old articles and subcategories.
     *
     * Both models only delete trashed items. They are trashed with a plain UPDATE rather than through publish():
     * with workflows enabled, the Publishing workflow plugin refuses any state change that is not a transition.
     * The delete itself goes through the models, so tags, versions, workflow associations, custom field values,
     * featured rows and assets are removed with the items. Categories go deepest first: Joomla refuses to
     * delete a category that still has articles or subcategories.
     *
     * @param   array  $plan  From planRemoval().
     *
     * @return  array  ['categories' => int, 'articles' => int] removed.
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function remove(array $plan): array
    {
        $db  = $this->getDatabase();
        $app = $this->getApplication();

        if ($plan['articles']) {
            $db->setQuery(
                $db->createQuery()
                    ->update($db->quoteName('#__content'))
                    ->set($db->quoteName('state') . ' = -2')
                    ->whereIn($db->quoteName('id'), $plan['articles'])
            )->execute();

            $pks   = $plan['articles'];
            $model = $this->createModel('com_content', 'Article');

            if (!$model->delete($pks)) {
                $app->getLanguage()->load('com_content');

                throw new \RuntimeException(Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_REMOVE', Text::_((string) $model->getError())));
            }

            $this->assertGone('#__content', $plan['articles']);
        }

        if ($plan['categories']) {
            $db->setQuery(
                $db->createQuery()
                    ->update($db->quoteName('#__categories'))
                    ->set($db->quoteName('published') . ' = -2')
                    ->whereIn($db->quoteName('id'), $plan['categories'])
            )->execute();

            $pks   = $plan['categories'];
            $model = $this->createModel('com_categories', 'Category');

            if (!$model->delete($pks)) {
                $app->getLanguage()->load('com_categories');

                throw new \RuntimeException(Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_REMOVE', Text::_((string) $model->getError())));
            }

            $this->assertGone('#__categories', $plan['categories']);
        }

        return ['categories' => \count($plan['categories']), 'articles' => \count($plan['articles'])];
    }

    /**
     * Make sure rows really were deleted. Some refusals of AdminModel::delete() are only logged.
     *
     * @param   string  $table  The table.
     * @param   int[]   $ids    The ids that should be gone.
     *
     * @return  void
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function assertGone(string $table, array $ids): void
    {
        $db   = $this->getDatabase();
        $left = (int) $db->setQuery(
            $db->createQuery()
                ->select('COUNT(*)')
                ->from($db->quoteName($table))
                ->whereIn($db->quoteName('id'), $ids)
        )->loadResult();

        if ($left > 0) {
            throw new \RuntimeException(Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_LEFT_OVER', $left));
        }
    }

    /**
     * Create the articles and subcategories of a category, recursively, in the order of the JSON.
     *
     * @param   array    $category  The normalised category.
     * @param   integer  $catId     Its id.
     * @param   array    $created   Counters.
     *
     * @return  void
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function buildCategory(array $category, int $catId, array &$created): void
    {
        $articleIds = [];

        foreach ($category['articles'] as $article) {
            $articleIds[] = $this->saveArticle($article, $catId);
            $created['articles']++;
        }

        $this->setOrdering($articleIds);

        foreach ($category['children'] as $child) {
            $childId = $this->saveCategory($child, $catId, 0);
            $created['categories']++;

            $this->buildCategory($child, $childId, $created);
        }
    }

    /**
     * Create a category, or update the existing parent category.
     *
     * @param   array    $category  The normalised category.
     * @param   integer  $parentId  Parent category id (1 = top level).
     * @param   integer  $id        Existing id, or 0 for a new category.
     *
     * @return  integer  The category id.
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function saveCategory(array $category, int $parentId, int $id): int
    {
        $data = [
            'id'           => $id,
            'parent_id'    => $parentId,
            'extension'    => 'com_content',
            'title'        => $category['title'],
            'alias'        => $category['alias'],
            'description'  => $category['description'],
            'note'         => $category['note'],
            'published'    => $category['published'],
            'access'       => $category['access'],
            'language'     => $category['language'],
            'params'       => $category['params'],
            'metadesc'     => $category['metadesc'],
            'metakey'      => $category['metakey'],
            'associations' => [],
        ];

        if ($id === 0) {
            $data['created_user_id'] = (int) $this->getApplication()->getIdentity()->id;
        }

        // A new model for every save: AdminModel::save() falls back to the id in the model state when the data
        // has none, so a reused model would overwrite the category it saved before.
        $model = $this->createModel('com_categories', 'Category');

        if (!$model->save($data)) {
            $this->getApplication()->getLanguage()->load('com_categories');

            throw new \RuntimeException(Text::sprintf(
                'PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_SAVE',
                $category['title'],
                Text::_((string) $model->getError())
            ));
        }

        return (int) $model->getState('category.id');
    }

    /**
     * Create one article.
     *
     * @param   array    $article  The normalised article.
     * @param   integer  $catId    Its category.
     *
     * @return  integer  The article id.
     *
     * @since   4.0.0
     * @throws  \RuntimeException
     */
    private function saveArticle(array $article, int $catId): int
    {
        $data = [
            'id'               => 0,
            'catid'            => $catId,
            'title'            => $article['title'],
            'alias'            => $article['alias'],
            // ContentTable::bind() splits this into intro and full text at the read more line.
            'articletext'      => $article['articletext'],
            'state'            => $article['state'],
            'access'           => $article['access'],
            'language'         => $article['language'],
            'featured'         => $article['featured'],
            'created_by_alias' => $article['author_alias'],
            'note'             => $article['note'],
            'metadesc'         => $article['metadesc'],
            'metakey'          => $article['metakey'],
            'attribs'          => $article['attribs'],
            'images'           => $article['images'],
            'urls'             => $article['urls'],
            'metadata'         => $article['metadata'],
            'tags'             => $this->resolveTags($article['tags']),
            'associations'     => [],
        ];

        $model = $this->createModel('com_content', 'Article');

        if (!$model->save($data)) {
            $this->getApplication()->getLanguage()->load('com_content');

            throw new \RuntimeException(Text::sprintf(
                'PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_ERROR_SAVE',
                $article['title'],
                Text::_((string) $model->getError())
            ));
        }

        $id = (int) $model->getState('article.id');

        // With workflows managing "featured", the model leaves it alone; set it the way core's Blog sample data does.
        if (
            $article['featured']
            && ComponentHelper::isEnabled('com_workflow')
            && PluginHelper::isEnabled('workflow', 'featuring')
            && ComponentHelper::getParams('com_content')->get('workflow_enabled')
        ) {
            $db = $this->getDatabase();

            $db->insertObject('#__content_frontpage', (object) [
                'content_id'    => $id,
                'ordering'      => 0,
                'featured_up'   => null,
                'featured_down' => null,
            ]);
        }

        return $id;
    }

    /**
     * Tag titles to what the article model expects: the id of an existing tag, or "#new#Title" for one that the
     * model should create.
     *
     * @param   string[]  $titles  Tag titles.
     *
     * @return  string[]
     *
     * @since   4.0.0
     */
    private function resolveTags(array $titles): array
    {
        if ($titles === []) {
            return [];
        }

        $app = $this->getApplication();

        if (!ComponentHelper::isEnabled('com_tags')) {
            $this->warnings[] = Text::_('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_WARNING_NO_TAGS');

            return [];
        }

        $db       = $this->getDatabase();
        $canMake  = $app->getIdentity()->authorise('core.create', 'com_tags');
        $resolved = [];

        foreach ($titles as $title) {
            // Looked up for every article, so a tag created by the previous article is found and not made twice.
            $lower = mb_strtolower($title);
            $query = $db->createQuery()
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__tags'))
                ->where('LOWER(' . $db->quoteName('title') . ') = :title')
                ->where($db->quoteName('published') . ' >= 0')
                ->where($db->quoteName('id') . ' > 1')
                ->order($db->quoteName('id') . ' ASC')
                ->bind(':title', $lower);

            $id = (int) $db->setQuery($query, 0, 1)->loadResult();

            if ($id > 0) {
                $resolved[] = (string) $id;
            } elseif ($canMake) {
                $resolved[] = '#new#' . $title;
            } else {
                $this->warnings[] = Text::sprintf('PLG_SAMPLEDATA_JED_KNOWLEDGEBASE_WARNING_TAG_NOT_CREATED', $title);
            }
        }

        return $resolved;
    }

    /**
     * Put a category's new articles in the order of the JSON.
     *
     * ArticleModel puts every new article first in its category, so saving them in order leaves them reversed.
     *
     * @param   int[]  $ids  Article ids, in the wanted order.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    private function setOrdering(array $ids): void
    {
        $db = $this->getDatabase();

        foreach ($ids as $position => $id) {
            $ordering = $position + 1;

            $db->setQuery(
                $db->createQuery()
                    ->update($db->quoteName('#__content'))
                    ->set($db->quoteName('ordering') . ' = :ordering')
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':ordering', $ordering, ParameterType::INTEGER)
                    ->bind(':id', $id, ParameterType::INTEGER)
            )->execute();
        }
    }

    /**
     * A fresh administrator model of a component, not tied to the request.
     *
     * @param   string  $component  com_content or com_categories.
     * @param   string  $name       Model name.
     *
     * @return  AdminModel
     *
     * @since   4.0.0
     */
    private function createModel(string $component, string $name): AdminModel
    {
        $model = $this->getApplication()->bootComponent($component)->getMVCFactory()
            ->createModel($name, 'Administrator', ['ignore_request' => true]);

        if ($component === 'com_categories') {
            // What populateState() would have read from the request.
            $model->setState('category.extension', 'com_content');
            $model->setState('category.component', 'com_content');
            $model->setState('category.section', null);
            $model->setState('category.id', 0);
        }

        return $model;
    }

    /**
     * This plugin's folder.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function pluginPath(): string
    {
        return JPATH_PLUGINS . '/sampledata/' . $this->_name;
    }

    /**
     * A sample data step response.
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
}
