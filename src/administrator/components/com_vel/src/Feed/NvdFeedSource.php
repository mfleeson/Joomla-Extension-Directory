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
use Jed\Component\Vel\Administrator\Enum\FeedSource;
use Joomla\CMS\Language\Text;

/**
 * The NVD feed (VEL2-PRD §6.6.2): discovery by keyword, enrichment by id.
 *
 * The classifier recognises our own records by the NVD's `sourceIdentifier`
 * (`security@joomla.org`). What the NVD contributes for them is enrichment - CVSS, CWE, CPE
 * bounds - done by {@see self::fetchOne()} for every advisory with a CVE id, and it comes back
 * empty for most recent records (`Deferred`: 209 of the 326 records of 2026). Discovery keeps
 * other CNAs' records that name a Joomla extension and queues mere mentions for a person.
 *
 * Errors: the NVD answers a bad request with 404 and a `message` header; `NvdClient` turns
 * that into an exception, and this source lets it end the run loudly rather than as "nothing
 * found".
 *
 * @since 4.0.0
 */
final class NvdFeedSource implements FeedSourceInterface
{
    /**
     * @param NvdClient        $client     The client.
     * @param RecordClassifier $classifier The classifier.
     * @param string           $keyword    The discovery keyword.
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly NvdClient $client,
        private readonly RecordClassifier $classifier,
        private readonly string $keyword = 'Joomla'
    ) {
    }

    /**
     * @return FeedSource
     *
     * @since 4.0.0
     */
    public function source(): FeedSource
    {
        return FeedSource::NVD;
    }

    /**
     * @param DateTimeImmutable $from     Window start.
     * @param DateTimeImmutable $to       Window end.
     * @param callable          $deadline Budget check.
     *
     * @return iterable<FeedRecord>
     *
     * @since 4.0.0
     */
    public function fetch(DateTimeImmutable $from, DateTimeImmutable $to, callable $deadline): iterable
    {
        // The API refuses windows over 120 days; split.
        $cursor = $from;

        while ($cursor < $to) {
            $end   = min($to, $cursor->modify('+119 days'));
            $start = 0;

            do {
                if ($deadline()) {
                    return;
                }

                try {
                    $page = $this->client->search($this->keyword, $cursor, $end, $start, 500);
                } catch (\Throwable $e) {
                    // A refused request is our bug (404 + message) or a rate limit; either way
                    // the run must say so, not report a clean empty window.
                    throw new \RuntimeException(Text::sprintf('COM_VEL_ERROR_NVD_DISCOVERY', $e->getMessage()), 0, $e);
                }

                $items = (array) ($page['vulnerabilities'] ?? []);

                foreach ($items as $item) {
                    if (!\is_array($item)) {
                        continue;
                    }

                    $mapped = NvdRecordMapper::map($item);

                    if ($mapped === null) {
                        continue;
                    }

                    $this->classifier->classify($mapped);

                    if ($this->classifier->worthKeeping($mapped->classification)) {
                        yield $mapped;
                    }
                }

                $total = (int) ($page['totalResults'] ?? 0);
                $start += max(1, \count($items));
            } while ($items !== [] && $start < $total);

            $cursor = $end;
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
        $item = $this->client->byId($cveId);

        if ($item === null) {
            return null;
        }

        $mapped = NvdRecordMapper::map($item);

        if ($mapped !== null) {
            $this->classifier->classify($mapped);
        }

        return $mapped;
    }
}
