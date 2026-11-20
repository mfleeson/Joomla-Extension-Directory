<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Parser;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Language\Text;
use RuntimeException;
use SimpleXMLElement;

/**
 * Parser that reads a Joomla extension manifest from a local zip file.
 *
 * @since 4.0.0
 */
class File extends Parser
{
    private SimpleXMLElement $xml;

    private string $tempDir;

    /**
     * @param string $filePath Absolute path to a Joomla extension zip file
     *
     * @throws RuntimeException
     * @since  4.0.0
     */
    public function __construct(string $filePath)
    {
        if (!is_file($filePath)) {
            throw new RuntimeException(Text::sprintf('COM_JED_PARSER_FILE_MISSING', $filePath));
        }

        $this->tempDir = $this->extract($filePath);

        try {
            $this->loadManifest($this->tempDir);
        } catch (\Throwable $e) {
            // A constructor that throws never reaches __destruct(), so without this every upload
            // without a usable manifest left its extracted copy (up to SafeZip::MAX_BYTES) behind.
            $this->removeDirectory($this->tempDir);

            throw $e;
        }
    }

    public function __destruct()
    {
        if (!empty($this->tempDir) && is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function getOwner(): string
    {
        return (string) $this->xml->author;
    }

    public function getName(): string
    {
        return (string) $this->xml->name;
    }

    public function getChangelogUrl(): string
    {
        return (string) $this->xml->changelogurl;
    }

    public function getUpdateServerUrl(): string
    {
        return (string) ($this->xml->updateservers->server[0] ?? '');
    }

    public function getVersion(): string
    {
        return (string) $this->xml->version;
    }

    public function getAuthorUrl(): string
    {
        return (string) $this->xml->authorUrl;
    }

    public function getAuthorEmail(): string
    {
        return (string) $this->xml->authorEmail;
    }

    public function getExtensionTypes(): array
    {
        $types = [];

        if (isset($this->xml->files->file)) {
            foreach ($this->xml->files->file as $file) {
                $type = (string) ($file['type'] ?? '');

                if ($type !== '') {
                    $types[] = $type;
                }
            }
        }

        if (empty($types)) {
            $rootType = (string) ($this->xml['type'] ?? '');

            if ($rootType !== '') {
                $types[] = $rootType;
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * Extracts the given zip file to a temporary directory and returns its path.
     *
     * @throws RuntimeException
     */
    private function extract(string $filePath): string
    {
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jed_file_' . bin2hex(random_bytes(8));

        // Through SafeZip, never ZipArchive::extractTo(). The archive is whatever a logged-in
        // user uploaded (or whatever a GitHub release contains): extractTo() writes it however
        // large it expands - a few hundred KB of zeros become gigabytes in the temp folder -
        // and this class also named ZipArchive without importing it, so it resolved to
        // Jed\Component\Jed\Administrator\Parser\ZipArchive and every parse died with a fatal
        // "class not found". SafeZip counts what it writes, caps the entries and skips any
        // entry that would land outside the folder; it removes what it wrote when it refuses.
        try {
            SafeZip::extract($filePath, $tmpDir);
        } catch (RuntimeException $e) {
            throw new RuntimeException(Text::sprintf('COM_JED_PARSER_ZIP_UNREADABLE', basename($filePath)) . ' ' . $e->getMessage(), 0, $e);
        }

        return $tmpDir;
    }

    /**
     * Locates and loads the Joomla manifest XML from the given directory.
     *
     * @throws RuntimeException
     */
    private function loadManifest(string $dir): void
    {
        $installer = Installer::getInstance();
        $installer->setPath('source', $dir);

        if (!$installer->findManifest()) {
            throw new RuntimeException(Text::sprintf('COM_JED_PARSER_NO_MANIFEST', $dir));
        }

        $manifestPath = $installer->getPath('manifest');
        $xml          = simplexml_load_file($manifestPath);

        if ($xml === false) {
            throw new RuntimeException(Text::sprintf('COM_JED_PARSER_MANIFEST_INVALID', $manifestPath));
        }

        $this->xml = $xml;
    }

    private function removeDirectory(string $dir): void
    {
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }

        rmdir($dir);
    }
}
