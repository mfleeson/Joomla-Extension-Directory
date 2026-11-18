# PRD-07: Run JED Checker from tickets through its own API

| | |
|---|---|
| Status | Implemented |
| Component | com_jed (administrator): `src/Checker/JedCheckerService.php`, ticket *JED Checker* tab |
| Depends on | com_jedchecker (the namespaced `Joomla\Component\Jedchecker` code base) |
| Users affected | JED team |
| Related | PRD-04 (fixed the `jedcheck()` crash; the check itself still could not run, see §2.3) |

## 1. Summary

A ticket's **JED Checker** tab should run JED Checker's rules against the listing's package. In
practice it always says *"JED Checker is not installed on this site"*, even when com_jedchecker is
installed and enabled, and the **Run JED Checker** button never appears.

The cause is that `JedCheckerService` doesn't ask Joomla whether com_jedchecker is installed.
Instead it looks for three files from an older JED Checker layout (`libraries/rule.php`,
`libraries/report.php`, `libraries/rules/`), and those files don't exist in the current JED
Checker. The rest of the service is written against that same old layout: class names,
constructors, report methods. So even if the file check passed, nothing after it would work.

The current JED Checker provides a supported entry point built for exactly this use:
`Joomla\Component\Jedchecker\Administrator\Api\CheckerApi::check(string $zipFile)`. This PRD
replaces the file checks with a proper installed/enabled check and replaces the home-made rule
runner with a call to `CheckerApi`.

## 2. The problem

### 2.1 How availability is detected

```php
// administrator/components/com_jed/src/Checker/JedCheckerService.php
public const CHECKER_PATH = JPATH_ADMINISTRATOR . '/components/com_jedchecker';

public function isAvailable(): bool
{
    return is_file(self::CHECKER_PATH . '/libraries/rule.php')
        && is_file(self::CHECKER_PATH . '/libraries/report.php')
        && is_dir(self::CHECKER_PATH . '/libraries/rules');
}
```

The installed com_jedchecker has no `libraries/` folder at all. Its code is in `src/`:

```
administrator/components/com_jedchecker/
  jedchecker.xml            <namespace path="src">Joomla\Component\Jedchecker</namespace>
  services/provider.php
  src/Api/CheckerApi.php    <- public entry point: check(string $zipFile): array
  src/Rule/RuleDiscovery.php
  src/Rule/AbstractRule.php
  src/Rule/Rules/*Rule.php  (14 rules: XmlInfo, Jexec, Gpl, Php, Security, Jamss, Language, ...)
  src/Report/Report.php, ReportItem.php
  src/Helper/CheckerHelper.php
```

So `isAvailable()` is always `false`. `TicketModel::loadJedChecker()` passes that to the layout
(`layouts/ticket/jedchecker.php`), which shows the "not installed" warning and hides the button.

Checking for files is the wrong test in any case:
- It says nothing about whether the component is **installed** (files can be left behind after
  an uninstall) or **enabled**.
- It breaks every time JED Checker reorganises its files, which is what happened here.

### 2.2 How the rules are run

`runRules()` copies how JED Checker 2.x worked internally. None of it matches the current code:

| `JedCheckerService` expects | Current JED Checker has |
|---|---|
| `require_once libraries/report.php`, `libraries/rule.php` | No such files; classes are autoloaded from `src/` through the component's namespace |
| Rule files in `libraries/rules/<name>.php`, class `JedcheckerRules<Name>` | `src/Rule/Rules/<Name>Rule.php`, class `Joomla\Component\Jedchecker\Administrator\Rule\Rules\<Name>Rule`, listed by `RuleDiscovery::getRules()` |
| `new $class(['params' => ..., 'basedir' => ...])`, then setting `$rule->params` / `$rule->basedir` | `new $class(string $basedir, ?Registry $params = null)`; the properties are protected and typed |
| `$rule->get('report')`, `get('id')`, `get('title')`, `get('ordering')` | `getReport()`, `getId()`, `getTitle()`, `getDescription()`, `static $ordering` |
| `$report->getErrorCount()`, `getWarningCount()`, `getCompatCount()`, `getNoticeCount()`, `getInfoCount()` | `$report->getData()['count']` with keys `error`, `warning`, `compatibility`, `notice`, `info`, `passed`, `total` |
| Its own unpacking, including nested archives, with no limits | `CheckerApi` unpacks (nested archives too) with limits on depth (5), number of archives (200) and total size (500 MB) |

Because `property()`, `count()` and `html()` fall back to `null` or `0` quietly, and a missing
class is skipped with `continue`, a partly matching install would "succeed" with no rules and no
findings. That's worse than failing.

### 2.3 What PRD-04 did and didn't fix

PRD-04 fixed a fatal error in `TicketController::jedcheck()` (a protected `getDatabase()` call).
With the current JED Checker that code is never reached, because the button is hidden. So PRD-04
made **Run JED Checker** not crash, but it never made it work.

## 3. Goals

- The tab recognises an installed, enabled JED Checker, using Joomla's own extension records.
- The check runs through JED Checker's public API (`CheckerApi::check()`), not by copying its
  internals.
- The results keep the current tab layout: rules down the left with counts, findings on the right.
- When JED Checker can't be used, the message says why: not installed, disabled, or a version
  without the API.
- A failing rule, a broken package or a JED Checker error produces a message on the tab. It never
  produces a broken ticket screen or leftover temporary files that JED can avoid.

### Non-goals

- Supporting the old (`libraries/`) JED Checker. See §8, Q1.
- Running the check in the background or storing results in the database. They stay in the
  session per ticket, as now.
- Changing JED Checker itself. A few upstream improvements are suggested in §7.

## 4. Requirements

| # | Requirement |
|---|---|
| R1 | JED Checker is **available** only when all three hold: (a) `#__extensions` has a `component` row with element `com_jedchecker`; (b) it is enabled (`ComponentHelper::isEnabled('com_jedchecker')`); (c) `class_exists(CheckerApi::class)` and `method_exists(CheckerApi::class, 'check')`. No file-path checks. |
| R2 | The service reports *why* it's unavailable: `NotInstalled`, `Disabled`, `Unsupported` (installed and enabled, but no `CheckerApi`). Its version (from `manifest_cache`) is reported when known. |
| R3 | The tab shows a specific message for each state: install link for *NotInstalled*; "enable it in System > Manage > Extensions" for *Disabled*; "version X has no API JED can use; update JED Checker" for *Unsupported*. The **Run** button only appears when *Available*. |
| R4 | The check calls `CheckerApi::check($file)` once per run. An uploaded package is passed by its path. A download-link package is downloaded first (the existing `download()` with its size limit and timeout), then passed to `CheckerApi`. |
| R5 | `success: false` from `CheckerApi` becomes a `RuntimeException` with JED Checker's error text (`COM_JED_JEDCHECKER_FAILED`). Any `Throwable` from the call is caught the same way. `CheckerApi` only catches `\Exception`, so a PHP `Error` in a rule would otherwise reach the ticket screen. |
| R6 | Each rule result maps onto the shape the tab already uses (§5.3). Titles and descriptions are JED Checker language keys, translated with com_jedchecker's language loaded. |
| R7 | Temporary directory paths are removed from finding locations before display: `/tmp/jed_checker_api_<hash>/unzipped/com_foo_1.0.0.zip/foo.xml` is shown as `com_foo_1.0.0.zip/foo.xml`. |
| R8 | JED's own unpacking code (`unpack()`, `runRules()`, `property()`, `count()`, `html()`, `CHECKER_PATH`) is removed. |
| R9 | Downloaded packages go to a per-run folder under Joomla's `tmp_path`, not under `administrator/components/com_jed/`. The folder is deleted in a `finally` block, as now. |
| R10 | Permissions are unchanged: `core.manage` on `com_jed` runs the check (`TicketController::jedcheck()`). JED Checker's own `core.manage` isn't required, because the API doesn't check it and the tab is team-only. |

## 5. Solution

### 5.1 Availability

Add a small enum and a status method. `isAvailable()` is kept for existing callers.

```php
enum JedCheckerStatus: string
{
    case Available    = 'available';
    case NotInstalled = 'not_installed';
    case Disabled     = 'disabled';
    case Unsupported  = 'unsupported';
}

public function status(): JedCheckerStatus
{
    $db  = $this->db;
    $row = $db->setQuery(
        $db->getQuery(true)
            ->select($db->quoteName(['enabled', 'manifest_cache']))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_jedchecker'))
    )->loadObject();

    if ($row === null) {
        return JedCheckerStatus::NotInstalled;
    }

    if ((int) $row->enabled !== 1 || !ComponentHelper::isEnabled('com_jedchecker')) {
        return JedCheckerStatus::Disabled;
    }

    return class_exists(CheckerApi::class) && method_exists(CheckerApi::class, 'check')
        ? JedCheckerStatus::Available
        : JedCheckerStatus::Unsupported;
}

public function version(): ?string   // from manifest_cache->version, for the tab
public function isAvailable(): bool { return $this->status() === JedCheckerStatus::Available; }
```

Why the database row **and** `class_exists()`: Joomla builds its class autoloader map
(`administrator/cache/autoload_psr4.php`) by scanning manifest files on disk, not from
`#__extensions`. `class_exists()` alone would say "available" for files left behind after an
uninstall. The `#__extensions` row alone would say "available" for a JED Checker too old to have
the API.

`CheckerApi` is referred to by its fully qualified name in a `use` statement. That's safe when the
class is missing, because `class_exists()` is checked before anything is called.

### 5.2 Running the check

```php
public function check(int $extensionId): array
{
    $status = $this->status();

    if ($status !== JedCheckerStatus::Available) {
        throw new RuntimeException($this->statusMessage($status));
    }

    $package = $this->describePackage($extensionId)
        ?? throw new RuntimeException(Text::_('COM_JED_JEDCHECKER_NO_PACKAGE'));

    $workspace = Path::clean(Factory::getApplication()->get('tmp_path') . '/jedchecker-' . $extensionId . '-' . bin2hex(random_bytes(4)));

    try {
        $file = $package['path'] ?? $this->download((string) $package['url'], $workspace);

        $this->loadCheckerLanguage();

        try {
            $api = CheckerApi::check($file);
        } catch (\Throwable $e) {
            throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_FAILED', $e->getMessage()), 0, $e);
        }

        if (empty($api['success'])) {
            throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_FAILED', (string) ($api['error'] ?? '')));
        }

        $rules  = array_map([$this, 'mapRule'], $api['rules']);
        $totals = /* sum of errors, warnings, compat, notices, infos over $rules */;

        return ['checked' => Factory::getDate()->toSql(), 'package' => $package['label'], 'rules' => $rules, 'totals' => $totals];
    } finally {
        if (is_dir($workspace)) {
            Folder::delete($workspace);
        }
    }
}
```

`download()` keeps its checks (`http(s)` only, 60-second timeout, `MAX_PACKAGE_BYTES`, HTTP
status). It changes in one way: it keeps the file name from the URL when that name ends in a known
archive extension. `CheckerApi` uses the file name as the top folder name, so findings then read
`mycomponent_1.2.zip/...` rather than `package.zip/...`.

### 5.3 Mapping a rule result

`CheckerApi::check()` returns, per rule:

```
['id' => 'PH2', 'title' => 'COM_JEDCHECKER_RULE_PH2', 'description' => 'COM_JEDCHECKER_RULE_PH2_DESC',
 'data' => ['count' => ['total'=>1,'error'=>1,'warning'=>0,'compatibility'=>0,'notice'=>0,'info'=>0,'passed'=>0],
            'issues' => [['type','subtype','location','text','line','code'], ...]],
 'html' => '<div class="alert alert-danger" ...>...</div>']
```

| Tab field (unchanged) | From |
|---|---|
| `id` | `id` |
| `title` | `Text::_(title)` |
| `description` | `Text::_(description)` |
| `errors` | `data.count.error` |
| `warnings` | `data.count.warning` |
| `compat` | `data.count.compatibility` |
| `notices` | `data.count.notice` |
| `infos` | `data.count.info` |
| `html` | `html` with the temporary path prefix removed (R7); `''` when `data.count.total` is 0 |

The rules arrive already sorted by JED Checker's own `$ordering`.

**Removing the temporary path (R7).** `CheckerApi` unpacks into
`sys_get_temp_dir() . '/jed_checker_api_' . md5(...) . '/unzipped/'`. JED never sees that path, so
it is removed by pattern: `~[^\s<>"']*[\\/]jed_checker_api_[0-9a-f]{32}[\\/]unzipped[\\/]~`. The
`[\\/]` matches either kind of slash, because on Windows the temp folder uses backslashes while
`CheckerApi` adds forward slashes. The `html` is JED Checker's own rendering. It escapes
locations and code, and the rules' messages are its own language strings. It is output as it is
today.

**Language.** Load com_jedchecker's language, as `runRules()` does now:
`load('com_jedchecker', JPATH_ADMINISTRATOR) || load('com_jedchecker', JPATH_ADMINISTRATOR . '/components/com_jedchecker')`.
`Report` translates its level titles ("ERROR", "NOTICE", ...) when it is created, so the language
must be loaded before `CheckerApi::check()` is called, not just before the results are mapped.

### 5.4 Tab and messages

`TicketModel::loadJedChecker()` returns `status` and `version` next to `available`. The layout
shows the message for the status (R3).

New language strings (administrator `com_jed.ini`):

```
COM_JED_JEDCHECKER_DISABLED="JED Checker is installed but disabled. Enable it in System > Manage > Extensions to run it from here."
COM_JED_JEDCHECKER_UNSUPPORTED="The installed JED Checker (version %s) has no programming interface (CheckerApi) the JED can use. Update JED Checker."
COM_JED_JEDCHECKER_FAILED="JED Checker could not check the package: %s"
COM_JED_JEDCHECKER_VERSION="Version %s"
```

`COM_JED_JEDCHECKER_UNPACK_FAILED` isn't needed any more, because unpacking errors now come back
from `CheckerApi`. `COM_JED_JEDCHECKER_RULE_FAILED` was dropped at first, because the API ran the
rules as a whole. It came back with U1 (§11), once JED Checker started reporting failures per rule.

### 5.5 Files

| File | Change |
|---|---|
| `administrator/components/com_jed/src/Checker/JedCheckerService.php` | Rewritten as above; `CHECKER_PATH`, `unpack()`, `runRules()`, `property()`, `count()`, `html()` removed |
| `administrator/components/com_jed/src/Checker/JedCheckerStatus.php` | New enum |
| `administrator/components/com_jed/src/Model/TicketModel.php` | `loadJedChecker()` adds `status`, `version` |
| `administrator/components/com_jed/src/View/Ticket/HtmlView.php` | Default `$jedChecker` array gains `status`, `version` |
| `administrator/components/com_jed/layouts/ticket/jedchecker.php` | Message per status; version shown |
| `administrator/components/com_jed/src/Controller/TicketController.php` | No change (still catches `Throwable` and stores the message) |
| `administrator/components/com_jed/language/en-GB/com_jed.ini` | Strings in §5.4 |
| `helpdocs/jed-team/ticket-management.md`, `site-setup.md` | "Installed **and enabled**"; the status messages |

## 6. Acceptance criteria

1. With com_jedchecker installed and enabled, the tab shows no "not installed" warning and shows
   **Run JED Checker** (for a listing with a package).
2. With no com_jedchecker row in `#__extensions`, the tab says *not installed* with the link, even if
   `administrator/components/com_jedchecker/` is still on disk.
3. With com_jedchecker disabled, the tab says *disabled*, and there's no Run button.
4. **Run JED Checker** on an uploaded package lists all of JED Checker's rules (14 in the supplied
   version) in JED Checker's order, with counts that match JED Checker's own screen for the same
   zip.
5. On a download-link package, the package is fetched and checked. Packages over 50 MB or non-2xx
   responses give the existing download messages.
6. Finding locations start at the package name, never at `/tmp/...`.
7. A package that isn't an archive gives "JED Checker could not check the package: *reason from
   JED Checker*". The ticket screen stays usable.
8. After a run, no `jedchecker-*` folder is left under `tmp_path`.
9. The ticket message "JED Checker finished: *n* errors, *m* warnings." matches the sum of the
   rule counts.

## 7. Notes for JED Checker (upstream)

These weren't needed for this PRD, but make the integration more robust. They were found while
exercising `CheckerApi::check()` with the supplied source. **All four are now implemented in
`administrator/components/com_jedchecker`** (§11) and should be offered to the JED Checker project
(github.com/joomla-extensions/jedchecker).

| # | Observation | Suggestion |
|---|---|---|
| U1 | `check()` catches `\Exception` only. A PHP `Error` in a rule (e.g. a `TypeError` on an unusual file) escapes, and the `jed_checker_api_*` folder in the system temp directory is **not** removed. Reproduced in the sandbox: with `JVERSION` undefined, `LanguageRule` threw an `Error` and the run was aborted. | Catch `\Throwable`, and do the cleanup in `finally`. Optionally, run each rule in its own `try` so one rule can't hide the others' results. |
| U2 | Works in `sys_get_temp_dir()`. On hosts with `open_basedir`, that folder may not be writable, and `mkdir()` then only returns "Failed to create temporary directories". | Accept an optional working directory (e.g. Joomla's `tmp_path`). |
| U3 | Report locations are absolute paths in the temp folder (`Report::$basedir` is never set by `AbstractRule`). | Set the report's base directory so locations are relative to the package. |
| U4 | `copy()` of the package isn't checked. | Check it and return an error. |

If U3 is fixed upstream, R7's path-stripping does nothing, which is harmless.

## 8. Open questions

| # | Question | Proposal |
|---|---|---|
| Q1 | Keep support for the old `libraries/`-based JED Checker? | No. It isn't what JED 6 installs, and keeping two code paths is what caused this defect. *Unsupported* tells the team to update. |
| Q2 | Minimum JED Checker version? | Decide by feature, not version number: `CheckerApi::check()` exists. Show the version so the team can see what's installed. |
| Q3 | Should results be kept beyond the session (e.g. as an internal ticket note)? | Out of scope; could be a follow-up. |

## 9. Verification done while writing this PRD

In the sandbox (PHP 8.3 WebAssembly, real Joomla 6 libraries, the supplied com_jedchecker source):

- Confirmed `administrator/components/com_jedchecker/libraries/` doesn't exist, so `isAvailable()`
  as written can only return `false`.
- Ran `CheckerApi::check()` on a small test component zip (`com_foo_1.0.0.zip`, a manifest and a
  PHP file without `_JEXEC` that calls `error_reporting(0)`):
  - `success: true`, with 14 rules in JED Checker order (INFO_XML, PH3, MANIFEST, XMLFILES, US1,
    PH1, PH2, Framework, PHP, Security, errorreporting, encoding, Jamss, LANG);
  - counts as in §5.3 (e.g. PH2 `error: 1`, errorreporting `warning: 1`);
  - titles returned as language keys;
  - locations as absolute `/tmp/jed_checker_api_<hash>/unzipped/...` paths;
  - the temp folder was removed after a successful run.
- Without `JVERSION` defined, `LanguageRule` threw an `Error` that escaped `CheckerApi::check()`
  (U1). In a real Joomla request `JVERSION` is always defined. The test only shows that errors
  aren't contained.

## 10. Implementation

Implemented as described in §5, with these details:

- `JedCheckerStatus` (`src/Checker/JedCheckerStatus.php`) has `messageKey()`. The service has
  `status()`, `isAvailable()`, `version()` and `statusMessage()`. `check()` calls `CheckerApi`
  through a closure, which tests can replace (third constructor argument). The second constructor
  argument (work folder) now defaults to Joomla's `tmp_path` instead of
  `administrator/components/com_jed/audit-workspace`.
- `COM_JED_JEDCHECKER_UNPACK_FAILED` and `COM_JED_JEDCHECKER_RULE_FAILED` were removed from both
  com_jed language files. The four new strings were added to both, because the language check
  counts administrator classes as used on the site too.
- The tab shows the install link for *not installed* and *unsupported*, but not for *disabled*.
- `TicketController::jedcheck()` is unchanged. It already catches `Throwable` and stores the
  message for the tab.

### Verification

31 checks, run in the sandbox against the real Joomla 6 libraries, com_jed and the supplied
com_jedchecker:

- **Status:**
  - no `#__extensions` row gives *not installed*, with its message, and `check()` refuses;
  - a disabled row gives *disabled*, with the version read from `manifest_cache`;
  - enabled with no `CheckerApi` class gives *unsupported*, with the version in the message;
  - enabled with the class gives *available*;
  - disabling the row again gives *disabled*, even though the class exists.
- **Check of an uploaded package:**
  - all 14 rules come back in JED Checker's order, with counts mapped as in §5.3;
  - titles, descriptions and finding levels are translated;
  - totals are the sum of the rules;
  - locations start at `com_foo_1.0.0.zip/`, never the temp folder;
  - clean rules have no HTML;
  - the uploaded file stays in place, and JED Checker's temp folder is removed.
- **Failures:**
  - `success: false` gives "JED Checker could not check the package: Failed to extract archive";
  - a `TypeError` thrown inside the run is caught the same way;
  - a listing with no package gives the existing message;
  - an unreachable download link gives the download message, with no work folder left behind;
  - the path pattern also handles Windows temp paths.
- **Tab:**
  - `TicketModel::loadJedChecker()` returns the status, version and package;
  - the layout shows the Run button and version when available;
  - it shows the right message, and no Run button, when disabled or not installed;
  - it lists all 14 rules with their findings after a run.
- The earlier com_jed tests (PRD-01 to PRD-06, 78 checks) still pass.

A successful download from a live link wasn't exercised, because the sandbox has no network. The
download code is the existing code, except that the file name now comes from the link.

## 11. JED Checker changes (U1-U4)

Made in the supplied com_jedchecker source. New methods are tagged `@since __DEPLOY_VERSION__`, as
JED Checker's own code does.

| # | File | Change |
|---|---|---|
| U1 | `src/Api/CheckerApi.php` | `check()` catches `\Throwable`, and the temp folder is removed in `finally` on every path, including early returns. Each rule runs in its own `runRule()` with its own `try`. A rule that fails is returned with `error` set to its message, empty counts, and `html` saying "This rule could not be run on the package: …". The other rules still run and `success` stays `true`. New helpers: `failure()`, `emptyData()`, `shortName()`. |
| U2 | `src/Api/CheckerApi.php` | `check(string $zipFile, ?string $workDir = null)`. It unpacks in `$workDir` when given, and in the system temp folder otherwise. A folder that is missing or not writable gives "The temporary folder is not writable: …". The temp folder name now includes `random_int()` as well as `microtime()`. |
| U3 | `src/Report/Report.php`, `src/Api/CheckerApi.php` | New `Report::setBasedir()`. `addItem()` makes a location relative only when it is inside that folder. Anything else is left as it is, e.g. a location that isn't a path, or a neighbouring folder with the same prefix. `CheckerApi` sets the base folder to its `unzipped` folder, so locations read `com_foo_1.0.0.zip/site/foo.php`. JED Checker's own screen doesn't call `setBasedir()`, so its output is unchanged. |
| U4 | `src/Api/CheckerApi.php` | `copy()` and `mkdir()` results are checked: "Failed to copy the package to the temporary directory" / "Failed to create temporary directories in …". |
| - | `administrator/language/en-GB/en-GB.com_jedchecker.ini` | `COM_JEDCHECKER_RULE_FAILED`. Other languages fall back to the English text built into `runRule()`. |

The `check()` signature change is backwards compatible: the second argument is optional. The
result array only gains a per-rule `error` key.

**JED side of these changes:**
- `JedCheckerService` lets JED Checker unpack in the system temp folder, which is normally outside
  the web root. It passes Joomla's `tmp_path` as `$workDir` only when that folder isn't writable
  (open_basedir); JED Checker protects its folder there (PRD-08, S4). JED Checker versions without
  the argument ignore it.
- A rule with `error` set is mapped with `failed: true` and a "This rule could not be run on the
  package: …" message (`COM_JED_JEDCHECKER_RULE_FAILED`, back in both com_jed language files).
  Before, it would have shown "Nothing found".
- The tab marks a failed rule with a grey "!" badge instead of the green tick.

**Verification:** 15 more checks, run against the real rules.

- **U1:** with `JVERSION` undefined, JED Checker's LanguageRule throws an `Error`.
  - The check still succeeds with all 14 rules.
  - LanguageRule is reported as failed with the reason, and the other rules keep their findings.
  - No temp folder is left.
  - A file that isn't a zip fails cleanly, with nothing left behind.
- **U2:**
  - unpacking happens in the given folder, which is then removed;
  - a missing folder gives the clear error.
- **U3:**
  - every location starts at `com_foo_1.0.0.zip/`, and the HTML has no temp paths;
  - `Report` only changes paths inside its base folder;
  - without `setBasedir()` nothing changes.
- **U4:** an unreadable package gives the copy error.
- **JED:**
  - the failed rule shows as failed with its reason and the "!" badge;
  - unpacking uses JED's work folder and cleans up.

The PRD-07 checks (31) and the PRD-01 to PRD-06 checks (78) still pass.
