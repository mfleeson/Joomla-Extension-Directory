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
use Jed\Component\Vel\Administrator\Cve\CveServicesClient;
use Jed\Component\Vel\Administrator\Enum\FeedSource;
use Joomla\CMS\Log\Log;

/**
 * The CVE Program feed (feeds PRD §3): the Joomla CNA's own records and other CNAs' records
 * that name a Joomla extension, from the cvelistV5 mirror - the only source available to a CNA.
 *
 * Two modes:
 *
 *  - **Delta polling** ({@see self::fetch()}): the head of `deltaLog.json` since the last run,
 *    every record in it fetched (capped per run), classified, and the unrelated ones dropped.
 *    Roughly 4,100 records change a day across the whole programme; a handful are Joomla's.
 *  - **Seed** ({@see self::seed()}): the one-off backfill a fresh installation needs, because
 *    the delta log reaches back 30 days only and a first run over it would fetch ~29,000
 *    records for a handful of ours. Ids come cheaply from the NVD (`sourceIdentifier` for ours,
 *    `keywordSearch` for everybody else's Joomla records - one request each) and from CVE
 *    Services' `GET /cve-id` when credentials exist (which also sees RESERVED ids the NVD
 *    cannot). The records themselves come from the mirror, which is authoritative.
 *
 * @since 4.0.0
 */
final class CnaFeedSource implements FeedSourceInterface
{
    /**
     * What the last fetch() left behind for the runner: whether the delta gap was covered, the
     * ETag to store, and where to resume from when it was not.
     *
     * @var array{complete: bool, reason: string, etag: ?string, resume_at: ?string, fetched: int}
     *
     * @since 4.0.0
     */
    private array $status = ['complete' => true, 'reason' => '', 'etag' => null, 'resume_at' => null, 'fetched' => 0];

    /**
     * @param CvelistClient          $cvelist    The mirror client.
     * @param RecordClassifier       $classifier Decides what is worth keeping.
     * @param int                    $maxRecords Per-run record fetch cap.
     * @param string|null            $etag       The ETag stored by the last run.
     * @param NvdClient|null         $nvd        For the seed's id lists.
     * @param CveServicesClient|null $services   For the seed's own-id list, when configured.
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly CvelistClient $cvelist,
        private readonly RecordClassifier $classifier,
        private readonly int $maxRecords = 400,
        private readonly ?string $etag = null,
        private readonly ?NvdClient $nvd = null,
        private readonly ?CveServicesClient $services = null
    ) {
    }

    /**
     * @return FeedSource
     *
     * @since 4.0.0
     */
    public function source(): FeedSource
    {
        return FeedSource::CNA;
    }

    /**
     * @return array{complete: bool, reason: string, etag: ?string, resume_at: ?string, fetched: int}
     *
     * @since 4.0.0
     */
    public function status(): array
    {
        return $this->status;
    }

    /**
     * Records changed since the window start, newest first.
     *
     * @param DateTimeImmutable $from     Window start (the last run's end).
     * @param DateTimeImmutable $to       Window end (now).
     * @param callable          $deadline Budget check.
     *
     * @return iterable<FeedRecord>
     *
     * @since 4.0.0
     */
    public function fetch(DateTimeImmutable $from, DateTimeImmutable $to, callable $deadline): iterable
    {
        $this->status = ['complete' => true, 'reason' => '', 'etag' => $this->etag, 'resume_at' => null, 'fetched' => 0];

        // The log reaches back 30 days; a longer gap cannot be served by it.
        $floor = $to->modify('-30 days');

        if ($from < $floor) {
            $this->status['complete']  = false;
            $this->status['reason']    = 'gap_exceeds_retention';
            $this->status['resume_at'] = $floor->format('Y-m-d H:i:s');
            $from                      = $floor;
        }

        $head = $this->cvelist->deltaEntriesSince($from, $this->etag);

        $this->status['etag'] = $head['etag'];

        if ($head['not_modified']) {
            return;
        }

        if (!$head['complete']) {
            // The Range cap was reached before the gap was covered: process what was read and
            // resume from its oldest entry next time.
            $this->status['complete'] = false;
            $this->status['reason']   = 'range_under_sized';
        }

        // Dedupe ids across entries (a record updated twice in the window appears twice),
        // newest first so a capped run still delivers the most recent items.
        $seen  = [];
        $items = [];

        foreach ($head['entries'] as $entry) {
            foreach (['new', 'updated'] as $bucket) {
                foreach ((array) ($entry[$bucket] ?? []) as $item) {
                    $id = strtoupper((string) ($item['cveId'] ?? ''));

                    if ($id === '' || isset($seen[$id]) || !preg_match('/^CVE-\d{4}-\d{4,}$/', $id)) {
                        continue;
                    }

                    $seen[$id] = true;
                    $items[]   = ['id' => $id, 'url' => (string) ($item['githubLink'] ?? ''), 'fetchTime' => (string) ($entry['fetchTime'] ?? '')];
                }
            }
        }

        $oldestProcessed = null;

        foreach ($items as $item) {
            if ($deadline() || $this->status['fetched'] >= $this->maxRecords) {
                $this->status['complete']  = false;
                $this->status['reason']    = $deadline() ? 'time_budget' : 'fetch_cap';
                $this->status['resume_at'] = $oldestProcessed;

                return;
            }

            try {
                $record = $item['url'] !== '' ? $this->cvelist->recordByUrl($item['url']) : $this->cvelist->record($item['id']);
            } catch (\Throwable $e) {
                Log::add('VEL CNA feed: ' . $item['id'] . ': ' . $e->getMessage(), Log::WARNING, 'com_vel');

                continue;
            } finally {
                $this->status['fetched']++;
                $oldestProcessed = $this->sqlStamp($item['fetchTime']) ?? $oldestProcessed;
            }

            if ($record === null) {
                continue;
            }

            $mapped = CveRecordMapper::map($record);

            if ($mapped === null) {
                continue;
            }

            $this->classifier->classify($mapped);

            if ($this->classifier->worthKeeping($mapped->classification)) {
                yield $mapped;
            }
        }

        if (!$head['complete'] && $this->status['resume_at'] === null) {
            $this->status['resume_at'] = $oldestProcessed;
        }
    }

    /**
     * The backfill. Ids from the NVD (and CVE Services when configured), records from the
     * mirror; every id already in the queue is skipped, so the action is resumable.
     *
     * @param int[]    $knownIds Ids already in `#__vel_feed_items` for this source.
     * @param int      $years    How many CVE years back from the current one.
     * @param callable $deadline Budget check.
     *
     * @return iterable<FeedRecord>
     *
     * @since 4.0.0
     */
    public function seed(array $knownIds, int $years, callable $deadline): iterable
    {
        $this->status = ['complete' => true, 'reason' => '', 'etag' => $this->etag, 'resume_at' => null, 'fetched' => 0];

        $ids   = [];
        $known = array_fill_keys(array_map('strtoupper', $knownIds), true);
        $minYear = (int) gmdate('Y') - max(0, $years - 1);

        $collect = static function (array $page) use (&$ids, $known, $minYear): void {
            foreach ((array) ($page['vulnerabilities'] ?? []) as $item) {
                $id = strtoupper((string) ($item['cve']['id'] ?? ''));

                if (preg_match('/^CVE-(\d{4})-\d{4,}$/', $id, $m) && (int) $m[1] >= $minYear && !isset($known[$id])) {
                    $ids[$id] = true;
                }
            }
        };

        if ($this->nvd !== null) {
            try {
                // Ours: one request covers the whole CNA (558 records NVD-wide).
                $collect($this->nvd->bySource(RecordClassifier::NVD_SOURCE_IDENTIFIER, null, null, 0, NvdClient::MAX_PER_PAGE));
            } catch (\Throwable $e) {
                Log::add('VEL seed: NVD source query failed: ' . $e->getMessage(), Log::WARNING, 'com_vel');
            }

            try {
                // Everybody else's Joomla records: the keyword corpus, 1,633 records in one page.
                $start = 0;

                do {
                    $page  = $this->nvd->keyword('Joomla', $start, NvdClient::MAX_PER_PAGE);
                    $total = (int) ($page['totalResults'] ?? 0);
                    $count = \count((array) ($page['vulnerabilities'] ?? []));
                    $collect($page);
                    $start += max(1, $count);
                } while ($count > 0 && $start < $total && !$deadline());
            } catch (\Throwable $e) {
                // Should the un-windowed query ever be refused, walk 120-day publication
                // windows instead - slower, same result.
                Log::add('VEL seed: NVD keyword query failed (' . $e->getMessage() . '); walking publication windows.', Log::INFO, 'com_vel');

                $cursor = new DateTimeImmutable($minYear . '-01-01', new \DateTimeZone('UTC'));
                $now    = new DateTimeImmutable('now', new \DateTimeZone('UTC'));

                while ($cursor < $now && !$deadline()) {
                    $end = min($now, $cursor->modify('+119 days'));

                    try {
                        $collect($this->nvd->search('Joomla', $cursor, $end, 0, NvdClient::MAX_PER_PAGE, 'pub'));
                    } catch (\Throwable $inner) {
                        Log::add('VEL seed: window ' . $cursor->format('Y-m-d') . ': ' . $inner->getMessage(), Log::WARNING, 'com_vel');
                    }

                    $cursor = $end;
                }
            }
        }

        if ($this->services !== null && $this->services->configured()) {
            for ($year = (int) gmdate('Y'); $year >= $minYear; $year--) {
                $page = 1;

                do {
                    try {
                        $list = $this->services->listOwnIds(null, $year, $page);
                    } catch (\Throwable $e) {
                        Log::add('VEL seed: CVE Services id list failed: ' . $e->getMessage(), Log::WARNING, 'com_vel');

                        break;
                    }

                    foreach ((array) ($list['cve_ids'] ?? []) as $entry) {
                        $id = strtoupper((string) ($entry['cve_id'] ?? ''));

                        if (preg_match('/^CVE-\d{4}-\d{4,}$/', $id) && !isset($known[$id])) {
                            $ids[$id] = true;
                        }
                    }

                    $next = $list['nextPage'] ?? null;
                    $page = \is_int($next) && $next > $page ? $next : 0;
                } while ($page > 0 && !$deadline());
            }
        }

        $ids = array_keys($ids);
        rsort($ids);

        foreach ($ids as $id) {
            if ($deadline() || $this->status['fetched'] >= $this->maxRecords) {
                $this->status['complete'] = false;
                $this->status['reason']   = $deadline() ? 'time_budget' : 'fetch_cap';

                return;
            }

            try {
                $record = $this->cvelist->record($id);
            } catch (\Throwable $e) {
                Log::add('VEL seed: ' . $id . ': ' . $e->getMessage(), Log::WARNING, 'com_vel');

                continue;
            } finally {
                $this->status['fetched']++;
            }

            if ($record === null) {
                continue;
            }

            $mapped = CveRecordMapper::map($record);

            if ($mapped === null) {
                continue;
            }

            $this->classifier->classify($mapped);

            if ($this->classifier->worthKeeping($mapped->classification)) {
                yield $mapped;
            }
        }
    }

    /**
     * @param string $cveId The id.
     *
     * @return FeedRecord|null
     *
     * @since 4.0.0
     */
    public function fetchOne(string $cveId): ?FeedRecord
    {
        $record = $this->cvelist->record($cveId);

        if ($record === null && $this->services !== null) {
            try {
                $record = $this->services->getRecord($cveId);
            } catch (\Throwable $e) {
                $record = null;
            }
        }

        if ($record === null) {
            return null;
        }

        $mapped = CveRecordMapper::map($record);

        if ($mapped !== null) {
            $this->classifier->classify($mapped);
        }

        return $mapped;
    }

    /**
     * @param string $iso A delta `fetchTime`.
     *
     * @return string|null  SQL datetime.
     *
     * @since 4.0.0
     */
    private function sqlStamp(string $iso): ?string
    {
        return CveRecordMapper::sql($iso);
    }
}
