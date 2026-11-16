<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Sampledata.jed_migrate
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\SampleData\JedMigrate\Extension;

use Jed\Component\Jed\Administrator\Helper\ContentTypeHelper;
use Jed\Component\Jed\Administrator\Parser\VideoParser;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Event\SampleData\GetOverviewEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Sampledata - JED3 migration plugin.
 *
 * Runs the JED3 -> JED4 migration that used to live in
 * administrator/components/com_jed/sql/migrate_jed3.xml, driven by the com_jed admin view
 * Copyjed3data. That view executed every statement of every task inside a single request,
 * which does not survive the real data volume - one PHP timeout and the run is lost with no
 * way to resume.
 *
 * Here each <task> of the old file is one sampledata step, so each step is its own AJAX
 * request with its own time and memory budget, reports success or failure on its own, and
 * can be retried without redoing the steps before it. The SQL is unchanged and lives in
 * the plugin's sql folder.
 *
 * The connection to the JED3 database comes from the com_jed component options
 * (jed3_db_database_name and jed3_db_prefix). Both databases must live on the same server,
 * because the SQL reaches into the source with a <database>.<prefix>_table reference.
 *
 * @since  4.0.0
 */
final class JedMigrate extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Load language files automatically.
     *
     * @var    boolean
     *
     * @since  4.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * The placeholder prefix used inside the step SQL for the JED3 tables. It is rewritten
     * to "<jed3 database>.<jed3 prefix>_" before a statement runs - the same contract the
     * old JedmigrateHelper::doSql() used, so the SQL did not have to change.
     *
     * @var    string
     *
     * @since  4.0.0
     */
    private const SOURCE_PLACEHOLDER = 'wqyh6_';

    /**
     * How many steps the extension history import is spread over.
     *
     * The history import is by far the heaviest part of the migration: every revision in
     * wqyh6_ucm_history is parsed out of a JSON blob field by field. Raise this if a batch
     * step still exceeds the request time limit - it is the only value that needs changing,
     * the SQL and the step numbering follow from it.
     *
     * @var    integer
     *
     * @since  4.0.0
     */
    private const HISTORY_BATCHES = 16;

    /**
     * How many steps the #__ucm_content half of the tag import is spread over.
     *
     * Same reasoning as HISTORY_BATCHES, different table: the tag import has to write one
     * #__ucm_content row per tagged extension, and against the real data that is 17 MB of listing
     * descriptions across 13,860 rows into a core table with twelve secondary indexes - ~28
     * seconds as a single statement, which is not a safe size for one request on a host with a
     * 30 second limit. Six batches put it at roughly five seconds each. Raise this if a batch
     * still exceeds the limit; nothing else needs changing.
     *
     * @var    integer
     *
     * @since  4.0.0
     */
    private const TAG_UCM_BATCHES = 6;

    /**
     * How many steps the legacy status history import is spread over.
     *
     * Same reasoning as HISTORY_BATCHES again, and the largest of the three: the moderation log,
     * the extension half of the audit trail and the edit log together come to 165,518 events on
     * the real data, each written as one revision carrying a copy of the listing. Measured at
     * roughly 2.5 seconds per 6,900 rows, so 24 batches put a step well inside a 30 second limit.
     *
     * @var    integer
     *
     * @since  4.0.0
     */
    private const STATUS_BATCHES = 24;

    /**
     * Number of steps that always run.
     *
     * Twenty-two fixed steps - the ten original migration tasks, the two prepare steps, the
     * baseline revision, the hit aggregate, the RSForms staging, the two non-batched thirds of the
     * tag import, the abandonware import, the linked extensions, the user privileges, the tickets
     * and the cleanup - plus one step per history, status and tag-UCM batch.
     *
     * @var    integer
     *
     * @since  4.0.0
     */
    private const STEP_COUNT = 22 + self::HISTORY_BATCHES + self::STATUS_BATCHES + self::TAG_UCM_BATCHES;

    /**
     * Number of steps that only run when an optional sibling component is installed.
     *
     * Currently one: the VEL legacy report import, which needs com_vel's tables. The overview
     * reports the real number of steps for this installation (see stepPlan()); this only sizes the
     * list of AJAX events the plugin subscribes to, which has to be fixed up front.
     *
     * @var    integer
     *
     * @since  4.0.0
     */
    private const OPTIONAL_STEP_COUNT = 1;

    /**
     * The event every step is dispatched as, followed by the 1 based step number.
     *
     * @var    string
     *
     * @since  4.0.0
     */
    private const STEP_EVENT = 'onAjaxSampledataApplyStep';

    /**
     * How many surviving assignments a legacy tag needs before it is imported (`P1-16`).
     *
     * "A tag used once is noise" is the plan's wording; a tag used zero times is not a vocabulary
     * entry at all. On the real data this drops 20 of the 528 legacy tags and 10 of the 38,687
     * assignments, and it happens to remove every alias-less duplicate row in one go - see the
     * commentary in sql/tags.sql. The dropped tags are named on the run's report.
     *
     * @var    integer
     *
     * @since  4.0.0
     */
    private const TAG_MIN_USES = 2;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * The sampledata module dispatches step N as onAjaxSampledataApplyStepN, so there is one
     * event per step. They all go to the same handler, which reads the step number back out of
     * the event name. Enough events are subscribed for the largest plan - the overview tells the
     * module how many of them are actually used on this installation.
     *
     * @return  array
     *
     * @since   4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        $events = ['onSampledataGetOverview' => 'onSampledataGetOverview'];

        for ($step = 1; $step <= self::STEP_COUNT + self::OPTIONAL_STEP_COUNT; $step++) {
            $events[self::STEP_EVENT . $step] = 'onAjaxSampledataApplyStep';
        }

        return $events;
    }

    /**
     * Get an overview of the proposed sampledata.
     *
     * @param   GetOverviewEvent  $event  Event instance.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function onSampledataGetOverview(GetOverviewEvent $event): void
    {
        $data              = new \stdClass();
        $data->name        = $this->_name;
        $data->title       = Text::_('PLG_SAMPLEDATA_JED_MIGRATE_OVERVIEW_TITLE');
        $data->description = Text::_('PLG_SAMPLEDATA_JED_MIGRATE_OVERVIEW_DESC');
        $data->icon        = 'database';

        // Counted from the plan rather than taken from STEP_COUNT, because the plan has an
        // optional step: with com_vel installed there is one more, and a fixed count would stop
        // the run one step early - before the cleanup.
        $data->steps       = \count($this->stepPlan());

        $event->addResult($data);
    }

    /**
     * Run one migration step.
     *
     * Step 1 additionally checks that the source database is configured and reachable, so a
     * misconfiguration is reported before anything is written.
     *
     * @param   AjaxEvent  $event  Event instance, named onAjaxSampledataApplyStepN.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function onAjaxSampledataApplyStep(AjaxEvent $event): void
    {
        if (!$this->isOurRequest()) {
            return;
        }

        $step = (int) substr($event->getName(), \strlen(self::STEP_EVENT));

        $event->addResult($this->applyStep($step));
    }

    /**
     * Whether the current AJAX request is a step of this plugin that may run.
     *
     * Every sampledata plugin receives every step event, so the type check is what keeps this
     * plugin out of the other plugins' runs. The token check stops a forged GET link from
     * starting the migration, and only a Super User may run it at all.
     *
     * @return  bool
     *
     * @throws  \RuntimeException  When the request is ours but the user is not a Super User.
     *
     * @since   4.0.0
     */
    private function isOurRequest(): bool
    {
        if (!Session::checkToken('get') || $this->getApplication()->getInput()->get('type') !== $this->_name) {
            return false;
        }

        // The migration wipes and reloads the directory: Super Users only.
        if (!$this->getApplication()->getIdentity()->authorise('core.admin')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return true;
    }

    /**
     * Map every step number onto the SQL file that implements it.
     *
     * Steps 1-10 are the fixed migration tasks. Step 11 prepares the history import and the next
     * HISTORY_BATCHES steps each import one batch from the same parameterised file. Then come the
     * baseline revision and the RSForms staging, the tag import - one vocabulary step,
     * TAG_UCM_BATCHES batch steps and one assignment step - and finally the cleanup.
     *
     * @return  array<int, array{file: string, batch?: int, tagBatch?: int, label: string, before?: string|string[], after?: string|string[]}>
     *
     * @since   4.0.0
     */
    private function stepPlan(): array
    {
        $plan = [];

        for ($i = 1; $i <= 10; $i++) {
            $plan[$i] = ['file' => 'step' . $i . '.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_STEP' . $i . '_SUCCESS'];

            // Step 4 copies the categories in with JED3's own lft/rgt values, which do not
            // fit the target's shared nested set - rebuild it before anything reads the tree.
            if ($i === 4) {
                $plan[$i]['after'] = 'rebuildCategoryTree';
            }

            // Step 6 is where #__jed_extensions is filled, so the videos it brought in are
            // normalised straight afterwards - once, at import, never on render (8.4).
            if ($i === 6) {
                $plan[$i]['after'] = 'normaliseVideos';
            }
        }

        $plan[11] = ['file' => 'history_prepare.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_HISTORY_PREPARE_SUCCESS'];

        for ($batch = 1; $batch <= self::HISTORY_BATCHES; $batch++) {
            $plan[11 + $batch] = [
                'file'  => 'history_batch.sql',
                'batch' => $batch,
                'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_HISTORY_BATCH_SUCCESS',
            ];
        }

        $next = 11 + self::HISTORY_BATCHES;

        // The legacy status history (P1-24). It has to sit here, between the last content revision
        // and the baseline, and both halves of that are load-bearing:
        //
        //  - BEFORE history_baseline.sql, because the baseline appends each listing's current
        //    state as its ONE active revision and relies on that row carrying the highest id.
        //    ExtensionformModel::getItem() and the admin list both resolve "the current version"
        //    with MAX(id), not with active = 1, so a status revision written after the baseline
        //    would become what the developer edits.
        //  - AFTER the content revisions, because history_baseline.sql also normalises
        //    description and intro across the whole table, and these rows carry a raw description
        //    that has to go through that pass exactly once.
        $plan[++$next] = ['file' => 'status_prepare.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_STATUS_PREPARE_SUCCESS'];

        for ($batch = 1; $batch <= self::STATUS_BATCHES; $batch++) {
            $plan[++$next] = [
                'file'        => 'status_batch.sql',
                'statusBatch' => $batch,
                'label'       => 'PLG_SAMPLEDATA_JED_MIGRATE_STATUS_BATCH_SUCCESS',
            ];
        }

        $plan[++$next] = ['file' => 'history_baseline.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_HISTORY_BASELINE_SUCCESS'];
        // After the listings exist, because the aggregate joins them to drop hits for extensions
        // that never came across (P1-12 item 7).
        $plan[++$next] = ['file' => 'hits.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_HITS_SUCCESS'];
        $plan[++$next] = ['file' => 'rsforms.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_RSFORMS_SUCCESS'];

        // Tags come last before the cleanup because they depend on both the categories (step 4)
        // and the extensions (step 6) already being there. The content type row has to exist
        // before anything is written - it supplies the type_id every mapping row carries - and the
        // tag nested set has to be rebuilt once the records are in, for the same reason step 4
        // rebuilds the category tree.
        $plan[++$next] = [
            'file'   => 'tags_vocab.sql',
            'label'  => 'PLG_SAMPLEDATA_JED_MIGRATE_TAGS_VOCAB_SUCCESS',
            'before' => 'ensureExtensionContentType',
            'after'  => 'rebuildTagTree',
        ];

        for ($batch = 1; $batch <= self::TAG_UCM_BATCHES; $batch++) {
            $plan[++$next] = [
                'file'     => 'tags_ucm_batch.sql',
                'tagBatch' => $batch,
                'label'    => 'PLG_SAMPLEDATA_JED_MIGRATE_TAGS_UCM_BATCH_SUCCESS',
            ];
        }

        // The report is written here rather than after the vocabulary step, because until the
        // assignments exist there is nothing to count.
        $plan[++$next] = [
            'file'  => 'tags_map.sql',
            'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_TAGS_MAP_SUCCESS',
            'after' => 'reportTagCuration',
        ];

        // After rsforms.sql, which stages forms 9 and 14, and after the listings exist, because it
        // resolves each report's free-text extension name against #__jed_extensions (P1-19).
        $plan[++$next] = ['file' => 'abandonware.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_ABANDONWARE_SUCCESS'];

        // The VEL's legacy intake (forms 5/10/11 and 7/12), after rsforms.sql for the same reason
        // and after the listings for the same name resolution (VEL2-PRD M-02). Skipped cleanly
        // when com_vel is not installed: the step is a no-op without its tables.
        if ($this->tableExists('#__vel_reports')) {
            $plan[++$next] = ['file' => 'vel_reports.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_VEL_REPORTS_SUCCESS'];
        }

        // After history_baseline.sql, because it writes the two link columns onto the active
        // baseline revision and that revision does not exist until the baseline has run (P1-23).
        $plan[++$next] = ['file' => 'linked.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_LINKED_SUCCESS'];

        // Only needs the users from step 2 and the categories from step 4, so its position is free;
        // it sits with the other late data sets rather than being threaded into the middle.
        $plan[++$next] = ['file' => 'useraccess.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_USERACCESS_SUCCESS'];

        // Last of the data sets: the tickets reference extensions and reviews by id, so both have
        // to be in place, and nothing else references the tickets.
        $plan[++$next] = [
            'file'  => 'tickets.sql',
            'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_TICKETS_SUCCESS',
            'after' => 'readableTicketEvents',
        ];

        $plan[++$next] = ['file' => 'cleanup.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_CLEANUP_SUCCESS'];

        return $plan;
    }

    /**
     * Whether a table exists in this installation - for steps that belong to an optional
     * sibling component.
     *
     * @param   string  $table  The table name with the #__ prefix.
     *
     * @return  bool
     *
     * @since   4.0.0
     */
    private function tableExists(string $table): bool
    {
        try {
            $db     = $this->getDatabase();
            $tables = $db->getTableList();

            return \in_array($db->replacePrefix($table), $tables, true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Run one step of the plan. The request has already been checked by isOurRequest().
     *
     * @param   integer  $step  The step number, 1 based.
     *
     * @return  array{success: bool, message: string}  Will be converted into the JSON response to the module.
     *
     * @since   4.0.0
     */
    private function applyStep(int $step): array
    {
        if ($step === 1) {
            $configError = $this->checkSourceDatabase();

            if ($configError !== null) {
                return ['success' => false, 'message' => $configError];
            }
        }

        $plan = $this->stepPlan();

        if (!isset($plan[$step])) {
            return [
                'success' => false,
                'message' => Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_MISSING_FILE', 'step ' . $step),
            ];
        }

        $spec = $plan[$step];
        $file = JPATH_PLUGINS . '/sampledata/' . $this->_name . '/sql/' . $spec['file'];

        if (!is_file($file)) {
            return [
                'success' => false,
                'message' => Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_MISSING_FILE', basename($file)),
            ];
        }

        $sql = file_get_contents($file);

        if ($sql === false) {
            return [
                'success' => false,
                'message' => Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_MISSING_FILE', basename($file)),
            ];
        }

        // {{BATCH}} selects the slice of wqyh6_ucm_history this step imports and {{BATCHES}} tells
        // the prepare step how many slices to cut; {{STATUS_BATCH}}/{{STATUS_BATCHES}} and
        // {{TAG_BATCH}}/{{TAG_BATCHES}} do the same for the status history and the tag UCM import,
        // and {{TAG_MIN_USES}} is the tag import threshold. All of them are plugin constants or
        // step numbers, never user input.
        $sql = str_replace(
            ['{{BATCH}}', '{{BATCHES}}', '{{STATUS_BATCH}}', '{{STATUS_BATCHES}}', '{{TAG_BATCH}}', '{{TAG_BATCHES}}', '{{TAG_MIN_USES}}'],
            [
                (string) ($spec['batch'] ?? 0),
                (string) self::HISTORY_BATCHES,
                (string) ($spec['statusBatch'] ?? 0),
                (string) self::STATUS_BATCHES,
                // MOD(id, batches) yields 0..batches-1, but the batches are numbered from 1 so
                // that the progress message reads "1 of 6" rather than "0 of 6".
                (string) (($spec['tagBatch'] ?? 1) - 1),
                (string) self::TAG_UCM_BATCHES,
                (string) self::TAG_MIN_USES,
            ],
            $sql
        );

        // A "before" hook prepares state the SQL cannot create for itself. Unlike "after" it must
        // succeed, because the statements that follow depend on what it wrote.
        foreach ((array) ($spec['before'] ?? []) as $hook) {
            $error = $this->{$hook}();

            if ($error !== null) {
                return ['success' => false, 'message' => $error];
            }
        }

        $db      = $this->getDatabase();
        $queries = $this->splitQueries($this->applySourcePrefix($sql));
        $count   = 0;

        foreach ($queries as $query) {
            $db->setQuery($query);

            try {
                $db->execute();
                $count++;
            } catch (\RuntimeException $e) {
                // Report which statement failed. Unlike the old runner this does not kill
                // the request with exit(), so the step can be corrected and retried on its
                // own instead of restarting the whole migration.
                return [
                    'success' => false,
                    'message' => Text::sprintf(
                        'PLG_SAMPLEDATA_JED_MIGRATE_ERROR_STEP',
                        $step,
                        $count + 1,
                        $e->getMessage(),
                        $this->shorten($query)
                    ),
                ];
            }
        }

        foreach ((array) ($spec['after'] ?? []) as $hook) {
            $error = $this->{$hook}();

            if ($error !== null) {
                return ['success' => false, 'message' => $error];
            }
        }

        if (isset($spec['batch'])) {
            $message = Text::sprintf($spec['label'], $spec['batch'], self::HISTORY_BATCHES, $count);
        } elseif (isset($spec['statusBatch'])) {
            $message = Text::sprintf($spec['label'], $spec['statusBatch'], self::STATUS_BATCHES, $count);
        } elseif (isset($spec['tagBatch'])) {
            $message = Text::sprintf($spec['label'], $spec['tagBatch'], self::TAG_UCM_BATCHES, $count);
        } else {
            $message = Text::sprintf($spec['label'], $count);
        }

        return ['success' => true, 'message' => $message];
    }

    /**
     * Rebuild the category nested set after the JED3 categories have been copied in.
     *
     * The copy takes the JED3 lft/rgt values verbatim, but Joomla keeps ONE nested set across
     * every extension in #__categories - com_content, com_contact and com_jed all live in the
     * same tree under a single ROOT row. The imported numbering therefore does not fit inside
     * the target's existing ROOT, and any category landing outside its lft/rgt range becomes
     * unreachable by tree traversal: no subcategory listing, no breadcrumb, no SEF path.
     *
     * Recomputing lft/rgt/level from parent_id puts every row back inside the tree. This has to
     * happen in PHP - it is a recursive walk that the plain SQL steps cannot express.
     *
     * @return string|null  An error message, or null on success.
     *
     * @since 4.0.0
     */
    private function rebuildCategoryTree(): ?string
    {
        try {
            // com_categories owns the table class, and its namespace is only registered once
            // the component has been booted - instantiating the class directly fails outside a
            // request that already loaded it.
            $table = $this->getApplication()
                ->bootComponent('com_categories')
                ->getMVCFactory()
                ->createTable('Category', 'Administrator', ['dbo' => $this->getDatabase()]);

            if (!$table->rebuild()) {
                return Text::sprintf(
                    'PLG_SAMPLEDATA_JED_MIGRATE_ERROR_CATEGORY_REBUILD',
                    $table->getError() ?: 'unknown error'
                );
            }
        } catch (\Throwable $e) {
            return Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_CATEGORY_REBUILD', $e->getMessage());
        }

        return null;
    }

    /**
     * Turn the RSTicketsPro events among the imported ticket messages into text.
     *
     * RSTicketsPro logs its own events - status, department and staff changes, auto-close - as
     * ticket messages with user_id -1, and their text is a serialized PHP array such as
     * `a:4:{s:4:"type";s:6:"status";s:4:"from";s:1:"1";s:2:"to";i:2;s:7:"user_id";s:5:"12345";}`.
     * tickets.sql copies that text as it is, so the ticket view would show the array. This writes
     * a sentence instead - "Status changed from Open to Closed by Jane Doe." - with the names of
     * the RSTicketsPro statuses and departments and of the (migrated) users. A PHP step because
     * the text is serialized PHP; the sentences are English, like the rest of the ticket history.
     *
     * @return  string|null  An error message, or null on success.
     *
     * @since   4.0.0
     */
    private function readableTicketEvents(): ?string
    {
        try {
            $db = $this->getDatabase();

            // The RSTicketsPro messages are the ones above the 100000 offset of tickets.sql
            $rows = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'message']))
                    ->from($db->quoteName('#__jed_ticket_messages'))
                    ->where($db->quoteName('id') . ' > 100000')
                    ->where($db->quoteName('message') . ' LIKE ' . $db->quote('a:%'))
            )->loadAssocList('id', 'message');

            $statuses = $db->setQuery($this->applySourcePrefix(
                'SELECT id, name FROM ' . self::SOURCE_PLACEHOLDER . 'rsticketspro_statuses'
            ))->loadAssocList('id', 'name');
            $departments = $db->setQuery($this->applySourcePrefix(
                'SELECT id, name FROM ' . self::SOURCE_PLACEHOLDER . 'rsticketspro_departments'
            ))->loadAssocList('id', 'name');

            $events  = [];
            $userIds = [];

            foreach ((array) $rows as $id => $message) {
                $event = @unserialize((string) $message, ['allowed_classes' => false]);

                if (!\is_array($event) || !isset($event['type'])) {
                    continue;
                }

                $events[$id] = $event;
                $userIds[]   = (int) ($event['user_id'] ?? 0);

                if ($event['type'] === 'staff') {
                    $userIds[] = (int) ($event['from'] ?? 0);
                    $userIds[] = (int) ($event['to'] ?? 0);
                }
            }

            $userIds = array_values(array_filter(array_unique($userIds)));
            $users   = $userIds ? $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'name']))
                    ->from($db->quoteName('#__users'))
                    ->whereIn($db->quoteName('id'), $userIds)
            )->loadAssocList('id', 'name') : [];

            $name = static fn (int $userId): string => $users[$userId] ?? 'user ' . $userId;
            $by   = static fn (array $event): string => (int) ($event['user_id'] ?? 0) ? ' by ' . $name((int) $event['user_id']) : '';
            $done = 0;

            foreach ($events as $id => $event) {
                $from = (int) ($event['from'] ?? 0);
                $to   = (int) ($event['to'] ?? 0);
                $text = match ($event['type']) {
                    'status'     => 'Status changed from ' . ($statuses[$from] ?? $from) . ' to ' . ($statuses[$to] ?? $to) . $by($event) . '.',
                    'department' => 'Department changed from ' . ($departments[$from] ?? $from) . ' to ' . ($departments[$to] ?? $to) . $by($event) . '.',
                    'staff'      => match (true) {
                        $to === 0   => 'Unassigned from ' . $name($from) . $by($event) . '.',
                        $from === 0 => 'Assigned to ' . $name($to) . $by($event) . '.',
                        default     => 'Reassigned from ' . $name($from) . ' to ' . $name($to) . $by($event) . '.',
                    },
                    'autoclose'  => 'Closed automatically after ' . (int) ($event['days'] ?? 0) . ' days without a reply.',
                    default      => null,
                };

                if ($text === null) {
                    continue;
                }

                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__jed_ticket_messages'))
                        ->set($db->quoteName('message') . ' = ' . $db->quote($text))
                        ->where($db->quoteName('id') . ' = ' . (int) $id)
                )->execute();

                $done++;
            }

            $this->getApplication()->enqueueMessage(
                Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_TICKET_EVENTS', $done, \count((array) $rows) - $done)
            );
        } catch (\Throwable $e) {
            return Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_TICKET_EVENTS', $e->getMessage());
        }

        return null;
    }

    /**
     * Normalise the imported `video` values into provider + id (`P1-11`).
     *
     * A PHP step rather than SQL because the parser is PHP: the patterns involved - a query
     * string where `v` is not the first parameter, `{youtube}id|650{/youtube}`, a scheme that is
     * simply missing - are not something a REGEXP_REPLACE should be asked to get right, and
     * getting one of them subtly wrong across 1,258 rows is how a catalogue ends up full of
     * embeds pointing at nothing.
     *
     * The raw column is left exactly as it was. It is what the developer typed, it is what the
     * clean-up report has to quote back, and overwriting it would destroy the only evidence of
     * what a value that failed to convert actually said.
     *
     * @return  string|null  An error message, or null on success.
     *
     * @since   4.0.0
     */
    private function normaliseVideos(): ?string
    {
        try {
            $db = $this->getDatabase();

            $rows = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'video']))
                    ->from($db->quoteName('#__jed_extensions'))
                    ->where('TRIM(IFNULL(' . $db->quoteName('video') . ", '')) <> ''")
            )->loadAssocList();

            $converted = 0;

            foreach ((array) $rows as $row) {
                $video = VideoParser::parse((string) $row['video']);

                if ($video === null) {
                    // Left NULL on purpose. The row stays on the clean-up report rather than
                    // being quietly stored as something it is not.
                    continue;
                }

                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__jed_extensions'))
                        ->set($db->quoteName('video_provider') . ' = ' . $db->quote($video->provider))
                        ->set($db->quoteName('video_id') . ' = ' . $db->quote($video->id))
                        ->where($db->quoteName('id') . ' = ' . (int) $row['id'])
                )->execute();

                $converted++;
            }

            $this->getApplication()->enqueueMessage(
                Text::sprintf(
                    'PLG_SAMPLEDATA_JED_MIGRATE_VIDEOS_NORMALISED',
                    $converted,
                    \count((array) $rows) - $converted
                )
            );
        } catch (\Throwable $e) {
            return Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_VIDEOS', $e->getMessage());
        }

        return null;
    }

    /**
     * Make sure the `com_jed.extension` content type exists before the tag import runs (`P1-16`).
     *
     * Every row of #__contentitem_tag_map carries the type_id of that content type, and
     * TagsHelper::getTagItemsQuery() INNER JOINs #__content_types - so without the row the import
     * would write mappings with type_id 0 and every tag page would be empty.
     *
     * The row is normally written by com_jed's installer. It is ensured again here because an
     * installation that predates the tags work (27 July) does not have it, and a migration that
     * fails on that is a migration the JED team has to debug rather than run. Both callers share
     * ContentTypeHelper, so there is still only one definition of the row.
     *
     * @return  string|null  An error message, or null on success.
     *
     * @since   4.0.0
     */
    private function ensureExtensionContentType(): ?string
    {
        try {
            // Booted for the same reason rebuildCategoryTree() boots com_categories: the helper
            // lives in com_jed's namespace, and that namespace is only guaranteed to resolve once
            // the component has been booted in this request.
            $this->getApplication()->bootComponent('com_jed');

            ContentTypeHelper::ensureExtensionContentType($this->getDatabase());
        } catch (\Throwable $e) {
            return Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_CONTENT_TYPE', $e->getMessage());
        }

        return null;
    }

    /**
     * Rebuild the tag nested set after the legacy tags have been copied in.
     *
     * Same problem as the categories in step 4, same reason: #__tags is ONE nested set shared by
     * every component, the import writes lft/rgt/level as zeroes, and a tag outside ROOT's
     * lft/rgt range is invisible to every tree query - including the one com_tags' router uses to
     * resolve a /tags/<slug> segment. Recomputing from parent_id also fills in `path`, which the
     * import cannot know before the tree is laid out.
     *
     * @return  string|null  An error message, or null on success.
     *
     * @since   4.0.0
     */
    private function rebuildTagTree(): ?string
    {
        try {
            $table = $this->getApplication()
                ->bootComponent('com_tags')
                ->getMVCFactory()
                ->createTable('Tag', 'Administrator', ['dbo' => $this->getDatabase()]);

            if (!$table->rebuild()) {
                return Text::sprintf(
                    'PLG_SAMPLEDATA_JED_MIGRATE_ERROR_TAG_REBUILD',
                    $table->getError() ?: 'unknown error'
                );
            }
        } catch (\Throwable $e) {
            return Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_TAG_REBUILD', $e->getMessage());
        }

        return null;
    }

    /**
     * Write the tag curation report the plan asks for, and summarise it on screen.
     *
     * The point of the report is that nothing about this import should have to be discovered later
     * in the tag cloud. Four things are worth knowing and none of them are visible from the result:
     *
     *  - which tags the usage threshold dropped, by name;
     *  - which imported tags duplicate a com_jed category title, because that is the curation
     *    decision the plan leaves to the JED team and it is 60 % of the vocabulary;
     *  - which tags arrived unpublished, because that is the JED3 state carried over verbatim and
     *    it is where the "function layer" vocabulary of 13.4/`P2-12` actually lives;
     *  - how many assignments were dropped because their extension or their tag did not survive.
     *
     * The full lists go to a file rather than the message queue: 300-odd titles in a Joomla alert
     * is not a report anyone reads.
     *
     * @return  string|null  An error message, or null on success. A report that cannot be written
     *                       is not a reason to fail the step - the import itself is done by then.
     *
     * @since   4.0.0
     */
    private function reportTagCuration(): ?string
    {
        try {
            $db = $this->getDatabase();

            // These read the source as well as the target, so they go through the same placeholder
            // rewrite the step files get - "wqyh6_" is not a table name anywhere but in this file.
            $imported = $db->setQuery($this->applySourcePrefix(
                'SELECT t.id, t.title, t.alias, t.published,'
                . ' (SELECT COUNT(*) FROM ' . $db->quoteName('#__contentitem_tag_map') . ' m'
                . '  WHERE m.tag_id = t.id) AS uses,'
                . ' EXISTS (SELECT 1 FROM ' . $db->quoteName('#__categories') . ' c'
                . "  WHERE c.extension = 'com_jed' AND c.title = t.title) AS is_category_title"
                . ' FROM ' . $db->quoteName('#__tags') . ' t'
                // id 1 is ROOT, which exists in both tag tables and is not an imported tag.
                . ' WHERE t.id > 1 AND t.id IN (SELECT id FROM ' . self::SOURCE_PLACEHOLDER . 'tags)'
                . ' ORDER BY uses DESC, t.title'
            ))->loadAssocList();

            // Everything the source offered that did not end up in #__tags, with the reason.
            $skipped = $db->setQuery($this->applySourcePrefix(
                'SELECT s.id, s.title, s.alias,'
                . ' (SELECT COUNT(*) FROM ' . self::SOURCE_PLACEHOLDER . 'contentitem_tag_map m'
                . '  INNER JOIN ' . $db->quoteName('#__jed_extensions') . ' e ON e.id = m.content_item_id'
                . "  WHERE m.tag_id = s.id AND m.type_alias = 'com_jed.extension') AS uses"
                . ' FROM ' . self::SOURCE_PLACEHOLDER . 'tags s'
                . ' WHERE s.id > 1'
                . ' AND s.id NOT IN (SELECT id FROM ' . $db->quoteName('#__tags') . ')'
                . ' ORDER BY s.title'
            ))->loadAssocList();

            $droppedAssignments = $db->setQuery($this->applySourcePrefix(
                'SELECT'
                . ' SUM(e.id IS NULL) AS extension_gone,'
                . ' SUM(e.id IS NOT NULL AND t.id IS NULL) AS tag_not_imported'
                . ' FROM ' . self::SOURCE_PLACEHOLDER . 'contentitem_tag_map m'
                . ' LEFT JOIN ' . $db->quoteName('#__jed_extensions') . ' e ON e.id = m.content_item_id'
                . ' LEFT JOIN ' . $db->quoteName('#__tags') . ' t ON t.id = m.tag_id'
                . " WHERE m.type_alias = 'com_jed.extension'"
            ))->loadAssoc();

            $taggedItems = (int) $db->setQuery(
                'SELECT COUNT(*) FROM ' . $db->quoteName('#__ucm_content')
                . " WHERE core_type_alias = 'com_jed.extension'"
            )->loadResult();

            $categoryTitled = array_filter($imported, static fn ($t) => (int) $t['is_category_title'] === 1);
            $unpublished    = array_filter($imported, static fn ($t) => (int) $t['published'] !== 1);

            $path = $this->writeTagReport($imported, $skipped, $categoryTitled, $unpublished, $droppedAssignments, $taggedItems);

            $this->getApplication()->enqueueMessage(
                Text::sprintf(
                    'PLG_SAMPLEDATA_JED_MIGRATE_TAGS_REPORT',
                    \count($imported),
                    \count($imported) - \count($unpublished),
                    \count($categoryTitled),
                    \count($skipped),
                    (int) ($droppedAssignments['extension_gone'] ?? 0),
                    (int) ($droppedAssignments['tag_not_imported'] ?? 0),
                    $taggedItems,
                    $path
                )
            );
        } catch (\Throwable $e) {
            $this->getApplication()->enqueueMessage(
                Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_TAG_REPORT', $e->getMessage()),
                'warning'
            );
        }

        return null;
    }

    /**
     * Write the tag curation report to the site's log directory.
     *
     * @param   array  $imported            The imported tags, with their use count.
     * @param   array  $skipped             The source tags that were not imported.
     * @param   array  $categoryTitled      Those imported tags whose title is also a category title.
     * @param   array  $unpublished         Those imported tags that arrived unpublished.
     * @param   array  $droppedAssignments  Counts of assignments that could not be mapped.
     * @param   int    $taggedItems         How many extensions ended up with at least one tag.
     *
     * @return  string  The path the report was written to, or an empty string on failure.
     *
     * @since   4.0.0
     */
    private function writeTagReport(
        array $imported,
        array $skipped,
        array $categoryTitled,
        array $unpublished,
        array $droppedAssignments,
        int $taggedItems
    ): string {
        $logPath = $this->getApplication()->get('log_path', JPATH_ADMINISTRATOR . '/logs');
        $file    = rtrim((string) $logPath, '/\\') . '/jed_tag_migration_report.txt';

        $lines = [
            'JED tag migration report (P1-16)',
            str_repeat('=', 60),
            '',
            'Tags imported ................ ' . \count($imported),
            '  published .................. ' . (\count($imported) - \count($unpublished)),
            '  unpublished ................ ' . \count($unpublished),
            '  title duplicates a category  ' . \count($categoryTitled),
            'Tags not imported ............ ' . \count($skipped),
            'Extensions with >= 1 tag ..... ' . $taggedItems,
            'Assignments dropped:',
            '  extension did not survive .. ' . (int) ($droppedAssignments['extension_gone'] ?? 0),
            '  tag not imported ........... ' . (int) ($droppedAssignments['tag_not_imported'] ?? 0),
            '',
            'The import carries the JED3 published state over verbatim, so each tag behaves here',
            'exactly as it does on the old site. The three lists below are what the JED team has to',
            'decide about - none of it is decided by this import.',
            '',
            'Before deciding, note what "unpublished" means in core com_tags, because it is not what',
            'it sounds like: /tags/<slug> of an UNPUBLISHED tag still answers 200 and still lists the',
            'tag\'s extensions. Only the tag\'s own title and description are withheld',
            '(TagModel::getItem() filters on the tag state, getListQuery() does not). Unpublishing a',
            'tag therefore does not take it off the web - only deleting the record does.',
            '',
        ];

        $lines[] = 'NOT IMPORTED - fewer than ' . self::TAG_MIN_USES . ' surviving assignments';
        $lines[] = str_repeat('-', 60);

        foreach ($skipped as $tag) {
            $lines[] = sprintf(
                '  %-50s id %-5d %d use(s)%s',
                $tag['title'],
                (int) $tag['id'],
                (int) $tag['uses'],
                trim((string) $tag['alias']) === '' ? '  [no alias]' : ''
            );
        }

        $lines[] = '';
        $lines[] = 'IMPORTED, BUT THE TITLE IS ALSO A com_jed CATEGORY TITLE';
        $lines[] = 'These say what the listing\'s category already says. Retiring them is a curation';
        $lines[] = 'decision; they are imported so that the decision can be made in the admin.';
        $lines[] = str_repeat('-', 60);

        foreach ($categoryTitled as $tag) {
            $lines[] = sprintf(
                '  %-50s id %-5d %6d use(s)  %s',
                $tag['title'],
                (int) $tag['id'],
                (int) $tag['uses'],
                (int) $tag['published'] === 1 ? 'published' : 'unpublished'
            );
        }

        $lines[] = '';
        $lines[] = 'IMPORTED UNPUBLISHED (the JED3 state, carried over)';
        $lines[] = 'This is the functional vocabulary the "function layer" question of 13.4 / P2-12';
        $lines[] = 'is about. It is not visible on the site until somebody publishes it.';
        $lines[] = str_repeat('-', 60);

        foreach ($unpublished as $tag) {
            $lines[] = sprintf('  %-50s id %-5d %6d use(s)', $tag['title'], (int) $tag['id'], (int) $tag['uses']);
        }

        $lines[] = '';

        return file_put_contents($file, implode("\n", $lines)) === false ? '' : $file;
    }

    /**
     * Verify that the JED3 source database is configured and reachable before the first
     * step writes anything.
     *
     * @return  string|null  An error message, or null when the source looks usable.
     *
     * @since   4.0.0
     */
    private function checkSourceDatabase(): ?string
    {
        $params   = ComponentHelper::getParams('com_jed');
        $database = (string) $params->get('jed3_db_database_name', '');
        $prefix   = (string) $params->get('jed3_db_prefix', '');

        if ($database === '' || $prefix === '') {
            return Text::_('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_NOT_CONFIGURED');
        }

        $db = $this->getDatabase();

        try {
            $db->setQuery(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '
                . $db->quote($database) . ' AND table_name = '
                . $db->quote($prefix . '_jed_extensions')
            );

            if (!$db->loadResult()) {
                return Text::sprintf(
                    'PLG_SAMPLEDATA_JED_MIGRATE_ERROR_SOURCE_MISSING',
                    $database . '.' . $prefix . '_jed_extensions'
                );
            }
        } catch (\RuntimeException $e) {
            return Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_SOURCE_UNREADABLE', $e->getMessage());
        }

        return null;
    }

    /**
     * Rewrite the source table placeholder to the configured JED3 database and prefix.
     *
     * @param   string  $sql  The raw step SQL.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function applySourcePrefix(string $sql): string
    {
        $params = ComponentHelper::getParams('com_jed');

        $replacement = (string) $params->get('jed3_db_database_name', '')
            . '.' . (string) $params->get('jed3_db_prefix', '') . '_';

        return str_replace(self::SOURCE_PLACEHOLDER, $replacement, $sql);
    }

    /**
     * Split a step file into individual statements.
     *
     * This deliberately does not reuse the splitter from plg_sampledata_jed: that one strips
     * every line beginning with "#" as a comment, which would silently delete any statement
     * that happens to start with the Joomla table prefix "#__". Here only "--" starts a
     * comment, and semicolons inside quoted strings do not split a statement.
     *
     * @param   string  $sql  The step file contents.
     *
     * @return  string[]  The statements, with blanks and comment-only entries removed.
     *
     * @since   4.0.0
     */
    private function splitQueries(string $sql): array
    {
        $queries = [];
        $current = '';
        $quote   = null;
        $length  = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                $current .= $char;

                if ($char === '\\' && $i + 1 < $length) {
                    // Keep an escaped character with its backslash so an escaped quote
                    // does not end the string.
                    $current .= $sql[++$i];
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote    = $char;
                $current .= $char;
                continue;
            }

            // A "--" comment runs to the end of the line. "#" is not treated as a comment
            // because "#__" is the table prefix.
            if ($char === '-' && $i + 1 < $length && $sql[$i + 1] === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }

                $current .= "\n";
                continue;
            }

            if ($char === ';') {
                $queries[] = $current;
                $current   = '';
                continue;
            }

            $current .= $char;
        }

        $queries[] = $current;

        return array_values(array_filter(array_map('trim', $queries), static fn ($q) => $q !== ''));
    }

    /**
     * Shorten a statement for an error message.
     *
     * @param   string  $query  The statement.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function shorten(string $query): string
    {
        $query = trim(preg_replace('/\s+/', ' ', $query));

        return strlen($query) > 300 ? substr($query, 0, 300) . ' ...' : $query;
    }
}
