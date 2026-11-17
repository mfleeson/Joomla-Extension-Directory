<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Checks that every language key com_jed, com_vel and com_abandonware can show is defined in the
 * language file Joomla loads for that side - the site file on the front end, the administrator
 * files in the backend. A key defined only for the other side is shown raw (COM_JED_SOMETHING).
 *
 * --side=site (default) - front-end code:
 *   components/com_x/**                     PHP and forms; not tmpl/*.xml (read by the menu manager)
 *   administrator/components/com_x/src/**   except src/View and src/Controller, which are
 *                                           administrator only; the rest (services, models, tables,
 *                                           fields, enums) is used by the site too
 *   administrator/components/com_x/layouts/**
 *   media/com_x/**.js                       strings passed with Text::script()
 *   Defined = components/com_x/language/en-GB/com_x.ini
 *
 * --side=admin - backend code:
 *   administrator/components/com_x/**       PHP, forms, config.xml, access.xml, the manifest, script.php
 *   components/com_x/tmpl/**.xml            the menu item types the menu manager shows
 *   site classes the administrator imports  (use Jed\Component\X\Site\...)
 *   Defined = administrator/components/com_x/language/en-GB/com_x.ini + com_x.sys.ini
 *
 * Keys built at run time ('COM_VEL_STATUS_' . strtoupper($x)) are handled by prefix: every defined
 * key starting with the prefix is required.
 *
 * Usage:
 *   php tests/language/check-language-keys.php [--side=site|admin]
 *       report; exit 1 when a key is only defined for the other side
 *   php tests/language/check-language-keys.php [--side=...] --fix
 *       also copy those keys from the other side's file (values unchanged)
 *   php tests/language/check-language-keys.php [--side=...] --list=docs/language-keys.md
 *       write the full list of keys used on that side
 */

// phpcs:disable PSR1.Files.SideEffects

$root = dirname(__DIR__, 2);
$fix  = in_array('--fix', $argv, true);
$list = null;
$side = 'site';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--list=')) {
        $list = $root . '/' . substr($arg, 7);
    }

    if (str_starts_with($arg, '--side=')) {
        $side = substr($arg, 7) === 'admin' ? 'admin' : 'site';
    }
}

$components = ['JED' => 'com_jed', 'VEL' => 'com_vel', 'ABANDONWARE' => 'com_abandonware'];
$names      = ['JED' => 'Jed', 'VEL' => 'Vel', 'ABANDONWARE' => 'Abandonware'];

/** Read an ini file into key => raw value (the text between the quotes). */
$parseIni = static function (string $file): array {
    $keys = [];

    foreach (is_file($file) ? file($file) : [] as $line) {
        if (preg_match('/^([A-Z0-9_]+)\s*=\s*"(.*)"\s*$/', rtrim($line, "\r\n"), $m)) {
            $keys[$m[1]] = $m[2];
        }
    }

    return $keys;
};

/** The keys of a file that sit below a block this script added (for the list's status column). */
$copiedKeys = static function (string $file): array {
    $copied = [];
    $inside = false;

    foreach (is_file($file) ? file($file) : [] as $line) {
        if (str_starts_with($line, ';')) {
            if (str_contains($line, 'check-language-keys.php') || str_contains($line, 'check-site-language-keys.php')) {
                $inside = true;
            }

            continue;
        }

        if ($inside && preg_match('/^([A-Z0-9_]+)\s*=/', $line, $m)) {
            $copied[$m[1]] = true;
        }
    }

    return $copied;
};

$siteFile  = static fn (string $c): string => "$root/components/$c/language/en-GB/$c.ini";
$adminFile = static fn (string $c): string => "$root/administrator/components/$c/language/en-GB/$c.ini";
$sysFile   = static fn (string $c): string => "$root/administrator/components/$c/language/en-GB/$c.sys.ini";

$defined = $other = $copied = [];   // tag => key => value

foreach ($components as $tag => $component) {
    if ($side === 'site') {
        $defined[$tag] = $parseIni($siteFile($component));
        $other[$tag]   = $parseIni($adminFile($component)) + $parseIni($sysFile($component));
        $copied[$tag]  = $copiedKeys($siteFile($component));
    } else {
        $defined[$tag] = $parseIni($adminFile($component)) + $parseIni($sysFile($component));
        $other[$tag]   = $parseIni($siteFile($component));
        $copied[$tag]  = $copiedKeys($adminFile($component));
    }
}

/** Every file under a directory, relative to the root. */
$walk = static function (string $dir) use ($root): array {
    if (!is_dir("$root/$dir")) {
        return [];
    }

    $out = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS)) as $file) {
        $out[] = str_replace($root . '/', '', $file->getPathname());
    }

    return $out;
};

$files = [];

foreach ($components as $tag => $component) {
    if ($side === 'site') {
        foreach (["components/$component", "administrator/components/$component/src", "administrator/components/$component/layouts", "media/$component"] as $dir) {
            foreach ($walk($dir) as $path) {
                if (preg_match('#^administrator/components/com_\w+/src/(View|Controller)/#', $path)
                    || preg_match('#^components/com_\w+/tmpl/.+\.xml$#', $path)) {
                    continue;
                }

                $files[] = $path;
            }
        }
    } else {
        foreach ($walk("administrator/components/$component") as $path) {
            if (!preg_match('#/(sql|vendor|docker|schema|language)/#', $path)) {
                $files[] = $path;
            }
        }

        foreach ($walk("components/$component/tmpl") as $path) {
            if (str_ends_with($path, '.xml')) {
                $files[] = $path;
            }
        }
    }
}

$files = array_values(array_filter($files, static fn ($p) => preg_match('/\.(php|xml|js)$/', $p)
    && !str_ends_with($p, '.min.js') && !str_contains($p, '/vendor/') && !str_contains($p, '/node_modules/')));

// The administrator also runs site classes it imports (site helpers, mostly).
if ($side === 'admin') {
    $seen = array_flip($files);

    foreach ($files as $path) {
        if (!str_ends_with($path, '.php')) {
            continue;
        }

        preg_match_all('/^use\s+Jed\\\\Component\\\\(\w+)\\\\Site\\\\([\w\\\\]+)/m', file_get_contents("$root/$path"), $m, PREG_SET_ORDER);

        foreach ($m as [, $name, $class]) {
            $component = 'com_' . strtolower($name);
            $file      = "components/$component/src/" . str_replace('\\', '/', $class) . '.php';

            if (is_file("$root/$file") && !isset($seen[$file])) {
                $files[]     = $file;
                $seen[$file] = true;
            }
        }
    }
}

sort($files);

$used     = [];   // key => [files]
$prefixes = [];   // prefix => [files]

foreach ($files as $path) {
    $text = file_get_contents("$root/$path");

    // Keys that are only named in a comment are not used.
    if (preg_match('/\.(php|js)$/', $path)) {
        $text = preg_replace('#/\*.*?\*/#s', '', $text);
        $text = implode("\n", array_filter(explode("\n", $text), static fn ($l) => !preg_match('#^\s*(//|\#|\*)#', $l)));
    } else {
        $text = preg_replace('/<!--.*?-->/s', '', $text);
    }

    if (!preg_match_all('/\b(COM_(JED|VEL|ABANDONWARE)_[A-Z0-9_]*)/', $text, $matches, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    foreach ($matches[1] as [$key, $offset]) {
        $after = substr($text, $offset + strlen($key), 4);

        if (str_ends_with($key, '_') || preg_match('/^[\'"]\s*\./', $after)) {
            // 'COM_X_' . strtoupper($field) . '_LABEL' - a prefix with a fixed ending narrows the family.
            $suffix = preg_match('/^[\'"]\s*\.[^;\'"]{1,80}?\.\s*[\'"]([A-Z0-9_]+)[\'"]/', substr($text, $offset + strlen($key), 120), $sm) ? $sm[1] : '';

            $prefixes[$key . '|' . $suffix][$path] = true;
        }

        // A key followed by a concatenation may be a prefix ('COM_X_OPTION_' . $n) or a whole key
        // with text appended; an underscore at the end is the only sure sign of a prefix.
        if (!str_ends_with($key, '_')) {
            $used[$key][$path] = true;
        }
    }
}

// A prefix makes every defined key that starts with it required.
foreach ($prefixes as $family => $where) {
    [$prefix, $suffix] = explode('|', $family);
    $tag               = explode('_', $prefix)[1];

    foreach (array_keys($defined[$tag] + $other[$tag]) as $key) {
        if ($key !== $prefix && str_starts_with($key, $prefix) && ($suffix === '' || str_ends_with($key, $suffix))) {
            $used[$key] = ($used[$key] ?? []) + $where;
        }
    }
}

ksort($used);

// text_prefix values and enum/list field prefixes name a family, not a key. Joomla's own list
// controllers complete a text_prefix with core suffixes (_N_ITEMS_PUBLISHED ...) that fall back to
// JLIB strings, so they are not required either.
// Text::plural('COM_X_N_ITEMS', $n) needs COM_X_N_ITEMS_1 / _MORE, not the base key, so a key whose
// family is defined counts as defined.
$isFamilyName = static function (string $key) use ($used, $defined, $other): bool {
    $tag   = explode('_', $key)[1] ?? '';
    $known = array_keys($used + ($defined[$tag] ?? []) + ($other[$tag] ?? []));

    return (bool) preg_grep('/^' . preg_quote($key, '/') . '[A-Z0-9_]+/', $known);
};

$toAdd   = [];   // tag => key => value
$missing = [];   // tag => key => files (defined nowhere)
$rows    = [];   // tag => rows for the list

foreach ($used as $key => $where) {
    $tag = explode('_', $key)[1];

    if (isset($defined[$tag][$key])) {
        $status = isset($copied[$tag][$key]) ? 'copied from ' . ($side === 'site' ? 'admin' : 'site') : $side;
    } elseif (isset($other[$tag][$key])) {
        $status            = $fix ? 'copied from ' . ($side === 'site' ? 'admin' : 'site') : ($side === 'site' ? 'admin only' : 'site only');
        $toAdd[$tag][$key] = $other[$tag][$key];
    } elseif ($isFamilyName($key) || preg_match('/^COM_(JED|VEL|ABANDONWARE)$/', $key)) {
        continue;
    } else {
        $status              = 'undefined';
        $missing[$tag][$key] = array_keys($where);
    }

    $rows[$tag][] = [$key, $status, array_keys($where)];
}

$otherSide = $side === 'site' ? 'administrator' : 'site';

foreach ($components as $tag => $component) {
    printf(
        "%-16s %4d keys used (%s), %3d defined only in the %s file, %3d defined nowhere\n",
        $component,
        count($rows[$tag] ?? []),
        $side,
        count($toAdd[$tag] ?? []),
        $otherSide,
        count($missing[$tag] ?? [])
    );
}

if (!$fix && $toAdd) {
    echo "\nDefined only in the $otherSide file:\n";

    foreach ($toAdd as $tag => $keys) {
        foreach (array_keys($keys) as $key) {
            echo "  $key\n";
        }
    }
}

if ($missing) {
    echo "\nDefined in no language file:\n";

    foreach ($missing as $keys) {
        foreach ($keys as $key => $where) {
            echo "  $key  (" . implode(', ', array_slice($where, 0, 3)) . ")\n";
        }
    }
}

if ($fix) {
    foreach ($toAdd as $tag => $keys) {
        $component = $components[$tag];
        $file      = $side === 'site' ? $siteFile($component) : $adminFile($component);
        $from      = $side === 'site' ? "administrator/components/$component" : "components/$component";
        $block     = "\n; =============================================================================================\n"
            . ($side === 'site'
                ? "; Keys used by code that runs on the site - site views, forms and layouts, and the administrator\n"
                . "; classes the site uses (services, models, tables, fields, enums, layouts) - that were only\n"
                . "; defined in the administrator language file, so the site showed the raw key.\n"
                : "; Keys used by the administrator - its views, forms and layouts, the menu manager's item types,\n"
                . "; and the site classes the administrator uses - that were only defined in the site language\n"
                . "; file, so the administrator showed the raw key.\n")
            . "; Values copied unchanged from $from/language/en-GB. Added by\n"
            . "; tests/language/check-language-keys.php --side=$side --fix; keep both files in step.\n"
            . "; =============================================================================================\n";

        foreach ($keys as $key => $value) {
            $block .= $key . '="' . $value . "\"\n";
        }

        file_put_contents($file, rtrim(file_get_contents($file), "\n") . "\n" . $block);
        printf("Added %d keys to %s\n", count($keys), str_replace($root . '/', '', $file));
    }
}

if ($list !== null) {
    $title = $side === 'site' ? 'front end' : 'administrator';
    $md    = "# Language keys used in the $title\n\n"
        . "Every `COM_JED_*`, `COM_VEL_*` and `COM_ABANDONWARE_*` key the $title can show, and where it is\n"
        . "defined. Generated by `php tests/language/check-language-keys.php --side=$side --list=...`; see that\n"
        . "script for which files count as $title code.\n\n"
        . "| Status | Meaning |\n|---|---|\n"
        . "| $side | Defined in the $side language file" . ($side === 'admin' ? 's (`com_x.ini` or `com_x.sys.ini`)' : ' (`components/com_x/language`)') . " |\n"
        . '| copied from ' . ($side === 'site' ? 'admin' : 'site') . " | Was only defined in the $otherSide file, so it showed as a raw key; copied by this script's `--fix` |\n"
        . "| undefined | Defined in no language file |\n";

    // The keys that were (or are) missing on this side, first: the reason the list exists.
    $md .= "\n## Missing on this side\n\n"
        . "Keys the $title uses that its language file did not define. *copied from ...* rows have been fixed by\n"
        . "`--fix`; *undefined* rows need text written (or the code that uses them removed).\n\n"
        . "| Component | Key | Status | Used in |\n|---|---|---|---|\n";
    $count = 0;

    foreach ($components as $tag => $component) {
        foreach ($rows[$tag] ?? [] as [$key, $status, $where]) {
            if ($status === $side) {
                continue;
            }

            $count++;
            $md .= "| $component | `$key` | $status | " . implode('<br>', array_map(static fn ($w) => "`$w`", array_slice($where, 0, 2)))
                . (count($where) > 2 ? ' +' . (count($where) - 2) . ' more' : '') . " |\n";
        }
    }

    if ($count === 0) {
        $md .= "| - | - | nothing missing | - |\n";
    }

    $md .= "\n# All keys\n";

    foreach ($components as $tag => $component) {
        $md .= "\n## $component (" . count($rows[$tag] ?? []) . " keys)\n\n| Key | Status | Used in |\n|---|---|---|\n";

        foreach ($rows[$tag] ?? [] as [$key, $status, $where]) {
            $shown = array_slice($where, 0, 2);
            $more  = count($where) > 2 ? ' +' . (count($where) - 2) . ' more' : '';
            $md   .= "| `$key` | $status | " . implode('<br>', array_map(static fn ($w) => "`$w`", $shown)) . $more . " |\n";
        }
    }

    file_put_contents($list, $md);
    echo 'Wrote ' . str_replace($root . '/', '', $list) . "\n";
}

exit(!$fix && $toAdd ? 1 : 0);
