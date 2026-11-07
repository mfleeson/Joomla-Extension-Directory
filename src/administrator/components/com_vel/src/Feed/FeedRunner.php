<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Feed;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use DateTimeImmutable;
use DateTimeZone;
use Jed\Component\Vel\Administrator\Cve\CveServicesClient;
use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\FeedClassification;
use Jed\Component\Vel\Administrator\Enum\FeedDecision;
use Jed\Component\Vel\Administrator\Enum\FeedSource;
use Jed\Component\Vel\Administrator\Service\VelService;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Throwable;

/**
 * One pass of one feed (feeds PRD §3, §6): a run row, a window, the records, the queue.
 *
 * Idempotent by `(source, external_id)` and the content hash. `partial` is reported with a
 * message that says which of its meanings applies - the per-run fetch cap, an under-sized delta
 * range, a gap older than the log's retention, or the time budget - and the window end is left
 * where the next run has to resume. Nothing here publishes; the most a run does is create a
 * *draft* for a Joomla CNA record when the option allows it.
 *
 * @since 4.0.0
 */
final class FeedRunner
{
    /**
     * @param DatabaseInterface $db      The database driver.
     * @param VelService        $service The lifecycle service.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db, private readonly VelService $service)
    {
    }

    /**
     * The classifier, from the options.
     *
     * @return RecordClassifier
     *
     * @since 4.0.0
     */
    public static function classifier(): RecordClassifier
    {
        $params = ComponentHelper::getParams('com_vel');

        return new RecordClassifier(
            (string) $params->get('cna_short_name', 'Joomla'),
            (string) $params->get('cna_org_id', ''),
            (int) $params->get('feed_import_other_cnas', 1) === 1
        );
    }

    /**
     * The configured source for a feed.
     *
     * @param FeedSource  $source Which feed.
     * @param string|null $etag   The CVE feed's stored ETag.
     *
     * @return FeedSourceInterface
     *
     * @since 4.0.0
     */
    public static function source(FeedSource $source, ?string $etag = null): FeedSourceInterface
    {
        $params = ComponentHelper::getParams('com_vel');
        $http   = HttpFactory::getHttp(['timeout' => 60]);
        $nvd    = new NvdClient($http, (string) $params->get('nvd_api_key', ''));

        return match ($source) {
            FeedSource::CNA => new CnaFeedSource(
                new CvelistClient(
                    $http,
                    max(64, (int) $params->get('cna_range_max_kb', 2048)) * 1024,
                    max(1, (int) $params->get('cna_full_fetch_max_mb', 64)) * 1024 * 1024
                ),
                self::classifier(),
                max(10, (int) $params->get('cna_max_records_per_run', 400)),
                $etag,
                $nvd,
                CveServicesClient::fromOptions()
            ),
            FeedSource::NVD => new NvdFeedSource($nvd, self::classifier()),
        };
    }

    /**
     * @param FeedSource $source        Which feed.
     * @param int        $budgetSeconds How long the run may take.
     *
     * @return object  The run row.
     *
     * @since 4.0.0
     */
    public function run(FeedSource $source, int $budgetSeconds = 120): object
    {
        $params  = ComponentHelper::getParams('com_vel');
        $enabled = $source === FeedSource::CNA ? (int) $params->get('cna_enabled', 1) === 1 : (int) $params->get('nvd_enabled', 1) === 1;
        $now     = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $run     = $this->newRun($source, $now);

        if (!$enabled) {
            $run->status   = 'ok';
            $run->finished = $run->started;
            $run->message  = 'disabled';

            return $run;
        }

        $last = $this->lastRun($source);
        $from = $this->windowStart($source, $now, $last);

        $run->window_from = $from->format('Y-m-d H:i:s');
        $run->cursor      = (string) ($last->cursor ?? '');

        $this->db->insertObject('#__vel_feed_runs', $run, 'id');

        $start      = microtime(true);
        $deadline   = static fn (): bool => (microtime(true) - $start) > $budgetSeconds;
        $autoCreate = (int) $params->get('feed_auto_create', 1) === 1;
        $feed       = null;

        try {
            $feed = self::source($source, $run->cursor !== '' ? $run->cursor : null);

            foreach ($feed->fetch($from, $now, $deadline) as $record) {
                $this->tally($run, $this->store($record, $autoCreate));
            }

            $this->finish($run, $feed, $deadline());
        } catch (Throwable $e) {
            $run->status  = 'failed';
            $run->message = mb_substr($e->getMessage(), 0, 2000);
            Log::add('VEL feed ' . $source->value . ' failed: ' . $e->getMessage(), Log::ERROR, 'com_vel');
        }

        $run->finished = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->db->updateObject('#__vel_feed_runs', $run, 'id');

        if ($source === FeedSource::NVD && $feed !== null && !$deadline()) {
            $this->enrichOwn($feed, $deadline);
        }

        return $run;
    }

    /**
     * The one-off backfill (feeds PRD §3.5). Resumable: every id already queued is skipped.
     *
     * @param int $budgetSeconds How long it may take.
     *
     * @return object  The run row.
     *
     * @since 4.0.0
     */
    public function seed(int $budgetSeconds = 120): object
    {
        $params = ComponentHelper::getParams('com_vel');
        $now    = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $run    = $this->newRun(FeedSource::CNA, $now);

        $run->window_from = null;
        $run->message     = 'seed';

        $this->db->insertObject('#__vel_feed_runs', $run, 'id');

        $start      = microtime(true);
        $deadline   = static fn (): bool => (microtime(true) - $start) > $budgetSeconds;
        $autoCreate = (int) $params->get('feed_auto_create', 1) === 1;

        try {
            /** @var CnaFeedSource $feed */
            $feed  = self::source(FeedSource::CNA);
            $known = $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName('external_id'))
                    ->from($this->db->quoteName('#__vel_feed_items'))
                    ->where($this->db->quoteName('source') . ' = ' . $this->db->quote(FeedSource::CNA->value))
            )->loadColumn() ?: [];

            foreach ($feed->seed($known, max(1, (int) $params->get('cna_seed_years', 2)), $deadline) as $record) {
                $this->tally($run, $this->store($record, $autoCreate));
            }

            $status       = $feed->status();
            $run->status  = $status['complete'] ? 'ok' : 'partial';
            $run->message = 'seed: ' . ($status['complete'] ? 'complete' : $status['reason'] . ' - run Seed again to continue') . ' (' . (int) $status['fetched'] . ' records fetched)';

            // A completed seed sets the delta window to now; polling takes over from here.
            if ($status['complete']) {
                $run->window_to = $now->format('Y-m-d H:i:s');
            } else {
                // Not a window at all: the next poll must not treat the seed as its predecessor.
                $run->window_to = null;
            }
        } catch (Throwable $e) {
            $run->status  = 'failed';
            $run->message = 'seed: ' . mb_substr($e->getMessage(), 0, 2000);
            Log::add('VEL seed failed: ' . $e->getMessage(), Log::ERROR, 'com_vel');
        }

        $run->finished = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->db->updateObject('#__vel_feed_runs', $run, 'id', true);

        return $run;
    }

    /**
     * @param FeedSource        $source Which feed.
     * @param DateTimeImmutable $now    Now.
     *
     * @return object
     *
     * @since 4.0.0
     */
    private function newRun(FeedSource $source, DateTimeImmutable $now): object
    {
        return (object) [
            'source'        => $source->value,
            'started'       => $now->format('Y-m-d H:i:s'),
            'finished'      => null,
            'window_from'   => null,
            'window_to'     => $now->format('Y-m-d H:i:s'),
            'items_seen'    => 0,
            'items_new'     => 0,
            'items_updated' => 0,
            'status'        => 'running',
            'message'       => '',
            'cursor'        => '',
        ];
    }

    /**
     * @param object $run     The run.
     * @param string $outcome new | updated | unchanged | skipped.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function tally(object $run, string $outcome): void
    {
        $run->items_seen++;

        if ($outcome === 'new') {
            $run->items_new++;
        } elseif ($outcome === 'updated') {
            $run->items_updated++;
        }
    }

    /**
     * Close a run from what the source reports.
     *
     * @param object              $run         The run.
     * @param FeedSourceInterface $feed        The source.
     * @param bool                $outOfTime   Whether the budget ran out.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function finish(object $run, FeedSourceInterface $feed, bool $outOfTime): void
    {
        if ($feed instanceof CnaFeedSource) {
            $status      = $feed->status();
            $run->cursor = (string) ($status['etag'] ?? '');

            if (!$status['complete']) {
                $run->status  = 'partial';
                $run->message = match ($status['reason']) {
                    'fetch_cap'             => 'per-run fetch cap reached; the next run resumes from ' . (string) ($status['resume_at'] ?? $run->window_from),
                    'range_under_sized'     => 'delta range cap reached before the gap was covered; raise cna_range_max_kb or run again',
                    'gap_exceeds_retention' => 'the gap since the last run is older than the delta log keeps (30 days); records before ' . (string) $status['resume_at'] . ' need a Seed run',
                    'time_budget'           => 'time budget reached; the next run resumes from ' . (string) ($status['resume_at'] ?? $run->window_from),
                    default                 => (string) $status['reason'],
                };

                // Resume from where this run stopped, never from its own end.
                $run->window_to = (string) ($status['resume_at'] ?? $run->window_from);

                return;
            }

            $run->status = 'ok';

            return;
        }

        if ($outOfTime) {
            $run->status    = 'partial';
            $run->message   = 'time budget reached; the next run continues from ' . (string) $run->window_from;
            $run->window_to = $run->window_from;

            return;
        }

        $run->status = 'ok';
    }

    /**
     * @param FeedSource $source Which feed.
     *
     * @return object|null  The last run that moved the window (ok or partial), or null.
     *
     * @since 4.0.0
     */
    private function lastRun(FeedSource $source): ?object
    {
        $value = $source->value;

        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__vel_feed_runs'))
                ->where($this->db->quoteName('source') . ' = :source')
                ->where($this->db->quoteName('status') . ' IN (' . $this->db->quote('ok') . ', ' . $this->db->quote('partial') . ')')
                ->where($this->db->quoteName('window_to') . ' IS NOT NULL')
                ->bind(':source', $value)
                ->order($this->db->quoteName('id') . ' DESC'),
            0,
            1
        )->loadObject() ?: null;
    }

    /**
     * Where this run starts: the last run's window end, or the look-back.
     *
     * @param FeedSource        $source Which feed.
     * @param DateTimeImmutable $now    Now.
     * @param object|null       $last   The last run.
     *
     * @return DateTimeImmutable
     *
     * @since 4.0.0
     */
    private function windowStart(FeedSource $source, DateTimeImmutable $now, ?object $last): DateTimeImmutable
    {
        $params   = ComponentHelper::getParams('com_vel');
        $lookback = max(1, (int) $params->get($source === FeedSource::CNA ? 'cna_lookback_days' : 'nvd_lookback_days', 7));
        $floor    = $now->modify('-' . $lookback . ' days');

        if ($last === null || empty($last->window_to)) {
            return $floor;
        }

        try {
            $lastTo = new DateTimeImmutable((string) $last->window_to, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return $floor;
        }

        // A little overlap, so a record updated in the same minute the last run ended is not
        // lost; the hash makes the overlap free. The CVE feed's delta entries are ~11 minutes
        // apart, so an hour is plenty.
        $lastTo = $lastTo->modify('-1 hour');

        return $lastTo < $floor ? $floor : $lastTo;
    }

    /**
     * Upsert a record into the queue.
     *
     * @param FeedRecord $record     The record.
     * @param bool       $autoCreate Create drafts for Joomla CNA records.
     *
     * @return string  new | updated | unchanged | skipped
     *
     * @since 4.0.0
     */
    public function store(FeedRecord $record, bool $autoCreate): string
    {
        $source = $record->source->value;
        $hash   = $record->hash();
        $now    = Factory::getDate()->toSql();

        $existing = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__vel_feed_items'))
                ->where($this->db->quoteName('source') . ' = :source')
                ->where($this->db->quoteName('external_id') . ' = :id')
                ->bind(':source', $source)
                ->bind(':id', $record->externalId)
        )->loadObject();

        $merger     = new AdvisoryMerger($this->db, $this->service);
        $advisory   = $merger->findExisting($record);
        $advisoryId = $advisory !== null ? (int) $advisory->id : null;

        // A rejected record matters only when it is ours or when we carry an advisory for it;
        // the rest of the world's rejections are noise.
        if ($record->classification === FeedClassification::REJECTED && $advisoryId === null && $existing === null
            && !self::classifier()->isOurs($record->normalised)) {
            return 'skipped';
        }

        $classification = ($record->classification ?? FeedClassification::UNCLASSIFIED)->value;

        if ($existing !== null) {
            if ((string) $existing->content_hash === $hash) {
                if ($advisoryId !== null && (int) ($existing->advisory_id ?? 0) === 0) {
                    $this->db->setQuery(
                        $this->db->getQuery(true)->update($this->db->quoteName('#__vel_feed_items'))
                            ->set($this->db->quoteName('advisory_id') . ' = :aid')
                            ->where($this->db->quoteName('id') . ' = :id')
                            ->bind(':aid', $advisoryId, ParameterType::INTEGER)
                            ->bind(':id', $existing->id, ParameterType::INTEGER)
                    )->execute();
                }

                return 'unchanged';
            }

            $existing->title                 = $record->title();
            $existing->assigner              = $record->assigner();
            $existing->fetched               = $now;
            $existing->external_updated      = $record->updated();
            $existing->content_hash          = $hash;
            $existing->payload               = json_encode($record->normalised, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $existing->classification        = $classification;
            $existing->classification_reason = $record->classificationReason;
            $existing->confidence            = $record->confidence;
            $existing->advisory_id           = $advisoryId ?? ($existing->advisory_id !== null ? (int) $existing->advisory_id : null);

            // A record that changed is worth a second look even if it was ignored before.
            if ((string) $existing->decision !== FeedDecision::CREATED->value) {
                $existing->decision     = FeedDecision::PENDING->value;
                $existing->decided_by   = null;
                $existing->decided_time = null;
            }

            $this->db->updateObject('#__vel_feed_items', $existing, 'id', true);

            if ($record->classification === FeedClassification::REJECTED && $advisoryId !== null) {
                $this->flagRejected($record, $advisoryId);
            }

            return 'updated';
        }

        $item = (object) [
            'source'                => $source,
            'external_id'           => $record->externalId,
            'assigner'              => $record->assigner(),
            'title'                 => $record->title(),
            'fetched'               => $now,
            'external_updated'      => $record->updated(),
            'content_hash'          => $hash,
            'payload'               => json_encode($record->normalised, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'classification'        => $classification,
            'classification_reason' => $record->classificationReason,
            'confidence'            => $record->confidence,
            'decision'              => FeedDecision::PENDING->value,
            'advisory_id'           => $advisoryId,
            'decided_by'            => null,
            'decided_time'          => null,
        ];

        // A Joomla CNA record with no advisory becomes a draft without waiting for anybody.
        if ($autoCreate && $advisoryId === null && $record->classification === FeedClassification::JOOMLA_CNA) {
            try {
                $item->advisory_id  = $merger->create($record, 0);
                $item->decision     = FeedDecision::CREATED->value;
                $item->decided_by   = 0;
                $item->decided_time = $now;
            } catch (Throwable $e) {
                Log::add('VEL feed: draft for ' . $record->externalId . ' not created: ' . $e->getMessage(), Log::WARNING, 'com_vel');
            }
        }

        $this->db->insertObject('#__vel_feed_items', $item, 'id');

        if ($record->classification === FeedClassification::REJECTED && $advisoryId !== null) {
            $this->flagRejected($record, $advisoryId);
        }

        return 'new';
    }

    /**
     * A CVE we carry has been rejected at the Program: the advisory's team must know.
     *
     * @param FeedRecord $record     The record.
     * @param int        $advisoryId The advisory.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function flagRejected(FeedRecord $record, int $advisoryId): void
    {
        $this->service->appendNote($advisoryId, 'FEED: ' . $record->externalId . ' is REJECTED at the CVE Program: ' . (string) ($record->normalised['description'] ?? '') . ' - review whether this advisory should be retired (reason: CVE rejected).');

        Log::add(\sprintf('Advisory %d: %s has been rejected at the CVE Program', $advisoryId, $record->externalId), Log::WARNING, 'com_vel');
    }

    /**
     * Enrichment: every published advisory with a CVE id and no CVSS or no CWE gets the NVD's
     * record queued as a merge offer. Most recent records come back `Deferred` with nothing to
     * offer; the queue says so.
     *
     * @param FeedSourceInterface|null $feed     The NVD source.
     * @param callable                 $deadline Budget check.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function enrichOwn(?FeedSourceInterface $feed, callable $deadline): void
    {
        if ($feed === null) {
            return;
        }

        $published = AdvisoryState::PUBLISHED->value;
        $ready     = AdvisoryState::READY->value;

        $ids = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['a.cve_id']))
                ->from($this->db->quoteName('#__vel_advisories', 'a'))
                ->where($this->db->quoteName('a.cve_id') . ' IS NOT NULL')
                ->where($this->db->quoteName('a.state') . ' IN (:p, :r)')
                ->where('(' . $this->db->quoteName('a.cvss_vector') . ' IS NULL OR NOT EXISTS (SELECT 1 FROM '
                    . $this->db->quoteName('#__vel_advisory_cwes', 'c') . ' WHERE ' . $this->db->quoteName('c.advisory_id') . ' = ' . $this->db->quoteName('a.id') . '))')
                ->where('NOT EXISTS (SELECT 1 FROM ' . $this->db->quoteName('#__vel_feed_items', 'f')
                    . ' WHERE ' . $this->db->quoteName('f.source') . ' = ' . $this->db->quote(FeedSource::NVD->value)
                    . ' AND ' . $this->db->quoteName('f.external_id') . ' = ' . $this->db->quoteName('a.cve_id')
                    . ' AND ' . $this->db->quoteName('f.fetched') . ' > ' . $this->db->quote(Factory::getDate('now -7 days')->toSql()) . ')')
                ->bind(':p', $published)
                ->bind(':r', $ready)
                ->order($this->db->quoteName('a.date_updated') . ' DESC'),
            0,
            25
        )->loadColumn() ?: [];

        foreach ($ids as $cveId) {
            if ($deadline()) {
                return;
            }

            try {
                $record = $feed->fetchOne((string) $cveId);

                if ($record !== null) {
                    $this->store($record, false);
                }
            } catch (Throwable $e) {
                Log::add('VEL NVD enrichment: ' . $cveId . ': ' . $e->getMessage(), Log::WARNING, 'com_vel');
            }
        }
    }
}
