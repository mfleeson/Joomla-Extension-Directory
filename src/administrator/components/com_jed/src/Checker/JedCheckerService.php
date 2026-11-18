<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Checker;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Api\CheckerApi;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Http\HttpFactory;
use RuntimeException;
use Throwable;

/**
 * Runs JED Checker (com_jedchecker) against a listing's package, for the JED Checker tab on the
 * ticket screen (PRD-07).
 *
 * JED Checker is the tool the JED team reviews every submission with, and the rejection mails
 * (`com_jed.extension_jed_checker_errors`, the ER1/NM1/... codes) speak its vocabulary.
 *
 * Two rules keep this working as JED Checker evolves:
 *
 * - Whether it can be used is asked of Joomla, not of the file system: a `com_jedchecker`
 *   component row in `#__extensions`, enabled, with the `CheckerApi::check()` entry point. (An
 *   earlier version looked for files of the JED Checker 2 layout - `libraries/rule.php` ... - that
 *   the current JED Checker no longer has, so the tab always said "not installed".)
 * - The check goes through JED Checker's public entry point, `CheckerApi::check($file)`, which
 *   unpacks the package (nested archives included, with its own limits) and runs every rule. JED
 *   does not reproduce JED Checker's internals.
 *
 * Nothing here is public (4.9/8.7): the tab is team-only, and the package is read from the
 * JED's own copy or the developer's download link, never from the request.
 *
 * @since 4.0.0
 */
class JedCheckerService
{
    /**
     * The most a package download is allowed to weigh. Packages are archives of source; a
     * listing whose "package" is bigger than this is not something a checker should read into
     * memory on a request.
     *
     * @since 4.0.0
     */
    public const MAX_PACKAGE_BYTES = 52428800;

    /**
     * Package extensions JED Checker can open.
     *
     * @since 4.0.0
     */
    private const PACKAGE_EXTENSIONS = ['zip', 'tar', 'gz', 'tgz', 'bz2', 'tbz2'];

    /**
     * The folder CheckerApi unpacks into (`<system temp>/jed_checker_api_<md5>/unzipped/`), to be
     * removed from the locations in its findings. Either slash: on Windows the temp folder has
     * backslashes and CheckerApi appends forward slashes.
     *
     * @since 4.0.0
     */
    private const CHECKER_TMP_PATTERN = '~[^\s<>"\']*[\\\\/]jed_checker_api_[0-9a-f]{32}[\\\\/]unzipped[\\\\/]~';

    /**
     * Runs JED Checker on a package file: CheckerApi::check() unless a test hands in another.
     *
     * @var \Closure(string): array
     *
     * @since 4.0.0
     */
    private \Closure $runner;

    /**
     * Cached #__extensions row of com_jedchecker (false when there is none).
     *
     * @var object|false|null
     *
     * @since 4.0.0
     */
    private object|false|null $extension = null;

    /**
     * @param DatabaseInterface $db            The database driver.
     * @param string|null       $workspaceRoot Where downloaded packages are kept while a check runs;
     *                                         Joomla's tmp_path when null.
     * @param \Closure|null     $runner        Replaces CheckerApi::check() (tests only).
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly ?string $workspaceRoot = null,
        ?\Closure $runner = null
    ) {
        // Where JED Checker unpacks the developer's package: the system temp folder - normally
        // outside the web root - and only where that is not writable (open_basedir), Joomla's
        // tmp_path (often inside the web root; JED Checker protects its folder there). JED Checker
        // versions without the second argument ignore it.
        $this->runner = $runner ?? function (string $file): array {
            $system = sys_get_temp_dir();
            $root   = $this->workspaceRoot();
            $dir    = @is_dir($system) && @is_writable($system) ? null : (is_dir($root) && is_writable($root) ? $root : null);

            return CheckerApi::check($file, $dir);
        };
    }

    /**
     * Whether JED Checker can be run from here, and if not, why not.
     *
     * The database row and the class are both needed: Joomla's class autoloader map is built from
     * the manifests on disk, so the class alone would also be found in files left behind after an
     * uninstall; the row alone would also be there for a JED Checker too old to have the API.
     *
     * @return JedCheckerStatus
     *
     * @since 4.0.0
     */
    public function status(): JedCheckerStatus
    {
        $extension = $this->extensionRow();

        if ($extension === false) {
            return JedCheckerStatus::NotInstalled;
        }

        if ((int) $extension->enabled !== 1 || !ComponentHelper::isEnabled('com_jedchecker')) {
            return JedCheckerStatus::Disabled;
        }

        return class_exists(CheckerApi::class) && method_exists(CheckerApi::class, 'check')
            ? JedCheckerStatus::Available
            : JedCheckerStatus::Unsupported;
    }

    /**
     * Whether JED Checker is installed, enabled and has the API this service drives.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function isAvailable(): bool
    {
        return $this->status() === JedCheckerStatus::Available;
    }

    /**
     * The installed JED Checker's version, from its manifest cache.
     *
     * @return string|null  Null when not installed or not recorded.
     *
     * @since 4.0.0
     */
    public function version(): ?string
    {
        $extension = $this->extensionRow();

        if ($extension === false) {
            return null;
        }

        $manifest = json_decode((string) ($extension->manifest_cache ?? ''), true);
        $version  = \is_array($manifest) ? trim((string) ($manifest['version'] ?? '')) : '';

        return $version !== '' ? $version : null;
    }

    /**
     * The message that explains a status on the ticket screen ('' when available).
     *
     * @param JedCheckerStatus $status The status.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function statusMessage(JedCheckerStatus $status): string
    {
        return match ($status) {
            JedCheckerStatus::Available   => '',
            JedCheckerStatus::Unsupported => Text::sprintf($status->messageKey(), $this->version() ?? '?'),
            default                       => Text::_($status->messageKey()),
        };
    }

    /**
     * The package on record for a listing: what the ticket screen says it would check.
     *
     * The JED's own copy wins - an uploaded package is what the developer submitted for review,
     * and it cannot change under the reviewer. Only when there is none does the listing's
     * download link count, and then it is fetched fresh at check time.
     *
     * @param int $extensionId The listing.
     *
     * @return array{source: string, label: string, path: ?string, url: ?string}|null
     *         `source` is "upload" or "url"; null when the listing has neither.
     *
     * @since 4.0.0
     */
    public function describePackage(int $extensionId): ?array
    {
        $db = $this->db;

        $upload = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['file', 'originalFile']))
                ->from($db->quoteName('#__jed_extensions_files'))
                ->where($db->quoteName('extension_id') . ' = :id')
                ->bind(':id', $extensionId, ParameterType::INTEGER)
                ->order($db->quoteName('id') . ' DESC')
        )->loadObject();

        if ($upload && $upload->file !== '') {
            $path = Path::clean(JPATH_ROOT . '/' . ltrim((string) $upload->file, '/\\'));

            if (is_file($path)) {
                return [
                    'source' => 'upload',
                    'label'  => (string) ($upload->originalFile ?: basename($path)),
                    'path'   => $path,
                    'url'    => null,
                ];
            }
        }

        $listing = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['internal_download_url', 'download_url']))
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $extensionId, ParameterType::INTEGER)
        )->loadObject();

        foreach (['internal_download_url', 'download_url'] as $column) {
            $url = trim((string) ($listing->$column ?? ''));

            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                return ['source' => 'url', 'label' => $url, 'path' => null, 'url' => $url];
            }
        }

        return null;
    }

    /**
     * Run JED Checker against the listing's package.
     *
     * @param int $extensionId The listing.
     *
     * @return array{
     *     checked: string,
     *     package: string,
     *     rules: array<int, array{id: string, title: string, description: string, errors: int, warnings: int, compat: int, notices: int, infos: int, html: string}>,
     *     totals: array{errors: int, warnings: int, compat: int, notices: int, infos: int}
     * }
     *
     * @throws RuntimeException When JED Checker cannot be used, the listing has no package, the
     *                          package cannot be fetched, or JED Checker cannot check it. The
     *                          message is shown to the team as it is.
     *
     * @since 4.0.0
     */
    public function check(int $extensionId): array
    {
        $status = $this->status();

        if ($status !== JedCheckerStatus::Available) {
            throw new RuntimeException($this->statusMessage($status));
        }

        $package = $this->describePackage($extensionId);

        if ($package === null) {
            throw new RuntimeException(Text::_('COM_JED_JEDCHECKER_NO_PACKAGE'));
        }

        $workspace = Path::clean($this->workspaceRoot() . '/jedchecker-' . $extensionId . '-' . bin2hex(random_bytes(4)));

        try {
            $file = $package['path'] ?? $this->download((string) $package['url'], $workspace);

            // Before the run, not just before reading the results: JED Checker's report
            // translates its level titles (ERROR, NOTICE ...) when it is created.
            $this->loadCheckerLanguage();

            try {
                $result = ($this->runner)($file);
            } catch (Throwable $e) {
                // CheckerApi catches \Exception only; a PHP Error in a rule would otherwise reach
                // the ticket screen.
                throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_FAILED', $e->getMessage()), 0, $e);
            }

            if (!\is_array($result) || empty($result['success'])) {
                $reason = \is_array($result) ? trim((string) ($result['error'] ?? '')) : '';

                throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_FAILED', $reason !== '' ? $reason : '?'));
            }

            $rules  = array_map([$this, 'mapRule'], array_values(array_filter((array) ($result['rules'] ?? []), 'is_array')));
            $totals = ['errors' => 0, 'warnings' => 0, 'compat' => 0, 'notices' => 0, 'infos' => 0];

            foreach ($rules as $rule) {
                foreach ($totals as $key => $count) {
                    $totals[$key] = $count + $rule[$key];
                }
            }

            return [
                'checked' => Factory::getDate()->toSql(),
                'package' => $package['label'],
                'rules'   => $rules,
                'totals'  => $totals,
            ];
        } finally {
            // A downloaded package goes as soon as the check is done. (An uploaded package is the
            // JED's own copy and is never touched; CheckerApi unpacks into its own folder and
            // removes it.)
            if (is_dir($workspace)) {
                try {
                    Folder::delete($workspace);
                } catch (Throwable) {
                    // A workspace that would not delete is a nuisance, not a failed check.
                }
            }
        }
    }

    /**
     * Fetch the package from the listing's download link.
     *
     * @param string $url       The link.
     * @param string $workspace Where to put it.
     *
     * @return string The downloaded file.
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    private function download(string $url, string $workspace): string
    {
        try {
            $response = (new HttpFactory())->getHttp()->get($url, ['User-Agent' => 'JoomlaExtensionsDirectory/4.1 (+https://extensions.joomla.org/; JED Checker)'], 60);
        } catch (Throwable $e) {
            throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_DOWNLOAD_FAILED', $url, $e->getMessage()));
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_DOWNLOAD_FAILED', $url, 'HTTP ' . $status));
        }

        $body = (string) $response->getBody();

        if (\strlen($body) > self::MAX_PACKAGE_BYTES) {
            throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_PACKAGE_TOO_LARGE', $url));
        }

        // Keep the link's file name when it is an archive name: CheckerApi names the folder it
        // unpacks into after the file, and that name starts every location in the findings.
        $name      = basename((string) parse_url($url, PHP_URL_PATH));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!\in_array($extension, self::PACKAGE_EXTENSIONS, true) || !preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            $name = 'package.' . (\in_array($extension, self::PACKAGE_EXTENSIONS, true) ? $extension : 'zip');
        }

        $target = $workspace . '/' . $name;

        if (!is_dir($workspace) && !Folder::create($workspace)) {
            throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_WORKSPACE_FAILED', $workspace));
        }

        if (!File::write($target, $body)) {
            throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_WORKSPACE_FAILED', $workspace));
        }

        return $target;
    }

    /**
     * One rule of CheckerApi::check()'s result in the shape the JED Checker tab shows.
     *
     * @param array $rule ['id', 'title', 'description', 'data' => ['count' => [...], 'issues' => [...]], 'html', 'error'].
     *
     * @return array{id: string, title: string, description: string, errors: int, warnings: int, compat: int, notices: int, infos: int, html: string, failed: bool}
     *
     * @since 4.0.0
     */
    private function mapRule(array $rule): array
    {
        $count  = (array) ($rule['data']['count'] ?? []);
        $total  = (int) ($count['total'] ?? 0);
        $failed = trim((string) ($rule['error'] ?? ''));

        if ($failed !== '') {
            // The rule itself could not run on this package (JED Checker reports it per rule and
            // runs the others). Not "Nothing found": say so.
            $html = '<div class="alert alert-secondary">'
                . htmlspecialchars(Text::sprintf('COM_JED_JEDCHECKER_RULE_FAILED', self::relativeLocations($failed)), ENT_QUOTES, 'UTF-8')
                . '</div>';
        } else {
            // JED Checker's own rendering of its own findings (it escapes locations and code);
            // empty when the rule found nothing, so the tab shows its "Nothing found."
            $html = $total === 0 ? '' : self::relativeLocations((string) ($rule['html'] ?? ''));
        }

        return [
            'id'          => (string) ($rule['id'] ?? ''),
            'title'       => Text::_((string) ($rule['title'] ?? '')),
            'description' => ($rule['description'] ?? '') !== '' ? Text::_((string) $rule['description']) : '',
            'errors'      => (int) ($count['error'] ?? 0),
            'warnings'    => (int) ($count['warning'] ?? 0),
            'compat'      => (int) ($count['compatibility'] ?? 0),
            'notices'     => (int) ($count['notice'] ?? 0),
            'infos'       => (int) ($count['info'] ?? 0),
            'html'        => $html,
            'failed'      => $failed !== '',
        ];
    }

    /**
     * Remove CheckerApi's temporary folder from the locations in its findings, so they start at
     * the package name: "com_foo_1.0.0.zip/foo.xml", not "/tmp/jed_checker_api_.../unzipped/...".
     *
     * @param string $html The findings.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function relativeLocations(string $html): string
    {
        return (string) preg_replace(self::CHECKER_TMP_PATTERN, '', $html);
    }

    /**
     * JED Checker's language: the rules' titles and findings are its own strings.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function loadCheckerLanguage(): void
    {
        $language = Factory::getApplication()->getLanguage();
        $language->load('com_jedchecker', JPATH_ADMINISTRATOR)
            || $language->load('com_jedchecker', JPATH_ADMINISTRATOR . '/components/com_jedchecker');
    }

    /**
     * Where downloaded packages are kept during a check: Joomla's tmp_path unless set.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function workspaceRoot(): string
    {
        if ($this->workspaceRoot !== null && $this->workspaceRoot !== '') {
            return rtrim($this->workspaceRoot, '/\\');
        }

        $tmp = '';

        try {
            $tmp = (string) Factory::getApplication()->get('tmp_path', '');
        } catch (Throwable) {
            // No application (CLI tooling): the system temp folder below.
        }

        return rtrim($tmp !== '' ? $tmp : sys_get_temp_dir(), '/\\');
    }

    /**
     * com_jedchecker's row in #__extensions.
     *
     * @return object|false  False when it is not installed.
     *
     * @since 4.0.0
     */
    private function extensionRow(): object|false
    {
        if ($this->extension === null) {
            $db   = $this->db;
            $type = 'component';
            $name = 'com_jedchecker';

            $row = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['extension_id', 'enabled', 'manifest_cache']))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = :type')
                    ->where($db->quoteName('element') . ' = :element')
                    ->bind(':type', $type)
                    ->bind(':element', $name)
                    ->setLimit(1)
            )->loadObject();

            $this->extension = $row ?: false;
        }

        return $this->extension;
    }
}
