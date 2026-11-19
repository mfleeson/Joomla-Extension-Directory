# PRD-08: JED Checker - Joomla 6 and security review

| | |
|---|---|
| Status | Implemented |
| Component | com_jedchecker (`administrator/components/com_jedchecker`, `media/com_jedchecker`) |
| Related | PRD-07 (JED's ticket tab runs JED Checker through `CheckerApi`) |
| Users affected | Anyone using JED Checker's own screen; the JED team through the ticket tab |

## 1. Summary

The supplied com_jedchecker source was reviewed for two things:
- **Joomla 6:** APIs that are deprecated or removed, and asset handling.
- **Safe code:** the packages it reads are written by extension developers, so every byte of them
  is untrusted input, and its output is shown to administrators.

The code base was already in good shape for Joomla 6: namespaced MVC, a service provider,
`Joomla\Filesystem`, no legacy `J*` classes, and no database access. It was also clean under PHP
8.3 with `E_ALL`. The review found and fixed:
- three ways a package could inject HTML/JavaScript into the administrator's screen;
- one way it could rewrite any language string for the rest of the request;
- a guessable, web-reachable folder full of the package's PHP files;
- unchecked "zip bombs";
- a manifest path trick that probes the server's file system;
- two functional bugs.

## 2. Findings and fixes

### 2.1 Security

| # | Severity | Finding | Fix |
|---|---|---|---|
| S1 | High | **HTML injection from the manifest.** `XmlInfoRule` put `<version>`, `<creationDate>`, `<authorUrl>` and `<packagerurl>` into the report unescaped, and the report is HTML. A manifest with `<version>&lt;img src=x onerror=…&gt;</version>` ran script in the administrator's browser, both on JED Checker's screen and on JED's ticket tab. | Escaped with `htmlspecialchars()`. |
| S2 | High | **A package could redefine any language string.** `XmlInfoRule::loadExtensionLanguage()` loaded the package's `.sys.ini` into the **application's** `Language` object, through reflection on a protected method. A package defining `COM_JEDCHECKER_IN_LINE="<script>…"` (or any `COM_JED_…` / core key) changed that string for the rest of the request. JED Checker's own report then printed it raw. | The file is read with `LanguageHelper::parseIniFile()` into the rule's own table. It's used only to translate the extension's name and menu (`translate()`). No reflection. |
| S3 | High (nginx) / Low (Apache, IIS) | **Package PHP in a guessable, web-reachable folder.** The screen unpacked uploads into `tmp_path/jed_checker/unzipped/<file>.zip/…`. Joomla's default `tmp_path` is inside the web root. `.htaccess`/`web.config` protect Apache/IIS, but not nginx, where anyone who could upload a package could request its PHP. | One folder per administrator, named with an HMAC of the user id and the site secret: `tmp_path/jed_checker_<24 hex>` (`CheckerHelper::workPath()`). The work folder is protected **before** anything is unpacked, and again after. `.user.ini` and `.phar` were added to the protection. This also stops two administrators overwriting each other's upload. |
| S4 | Medium | **`CheckerApi` unpacked without protection.** With the new `$workDir` (PRD-07), packages could be unpacked under `tmp_path`. | `CheckerApi` protects its folder the same way. JED now passes the system temp folder (normally outside the web root), and uses `tmp_path` only when that isn't writable. |
| S5 | Medium | **Zip bombs.** Limits applied only to nested archives, and only *after* unpacking them. A 600 KB zip that unpacks to 600 MB was unpacked in full. | `CheckerHelper::exceedsSizeLimit()` reads the declared size before unpacking: the zip central directory, or the gzip size trailer. It's used for the uploaded archive and for every nested one, in the screen and in `CheckerApi` ("The archive unpacks to more data than JED Checker will extract (500 MB)."). |
| S6 | Low | **File-system probing through the manifest.** `XmlFilesRule` tested `is_file()`/`is_dir()` on paths taken from the manifest, including `../../../../etc/passwd` and absolute paths. This told the uploader whether any file on the server exists. | `CheckerHelper::isInsidePackage()`. Paths containing `..`, absolute paths, drive letters or streams are reported as *points outside the package* and never tested. |
| S7 | Low | **XML parsing of untrusted files.** Parsing used plain `simplexml_load_file()`: network access was possible for DTDs, and parse errors printed PHP warnings into the AJAX answer. | `CheckerHelper::loadXml()`: `LIBXML_NONET`, internal error collection, used by every rule. No entity substitution, so XXE stays off; this was checked. |
| S8 | Low | **Internal methods reachable as tasks.** `UploadsController::unzip()` and `unzipAll()` were public, so Joomla routed them as tasks (`task=uploads.unzipAll`). | Both are now protected. Only `check`, `clear`, `upload` and `display` are tasks. |
| S9 | Low | **Refusals were blank 200 pages.** `… \|\| $app->close(403)` exits with process code 403 and sends an empty "200 OK". | `check` (AJAX) answers HTTP 403 with a message. `upload`/`clear` use `checkToken()` (redirect with Joomla's notice) and throw `NotAllowed` (403). `DisplayController` throws `NotAllowed`. |
| S10 | Low | **Upload checks.** The upload error code wasn't checked, and the original file name became a folder name as given. | `UPLOAD_ERR_OK` and `is_uploaded_file()` are checked. The name is reduced to `[A-Za-z0-9._-]`. |
| S11 | Low | **Script error shown as HTML.** `script.js` put `response.statusText` into `innerHTML`. | Shown with `textContent`. |
| S12 | Info | **Language keys read with expansion.** `LanguageRule` read language files with `parse_ini_file()` in normal mode, which expands constants and `${…}` and fails on Joomla's escaped quotes. A failed parse made every key look "unknown". | `INI_SCANNER_RAW`; only the keys are used. |

### 2.2 Joomla 6

| # | Finding | Fix |
|---|---|---|
| J1 | `HTMLHelper::_('stylesheet' / 'script')`: deprecated since 5.3, removed in 7.0 | `media/com_jedchecker/joomla.asset.json` (`com_jedchecker.style`, `com_jedchecker.script` as an ES module depending on `core`, `bootstrap.collapse`, `bootstrap.tab`, `bootstrap.popover`). The template uses the Web Asset Manager. |
| J2 | Options passed as a hand-made JSON `<script>` block read via `innerHTML` | `$document->addScriptOptions('com_jedchecker', …)` in the view; `Joomla.getOptions('com_jedchecker')` in the script |
| J3 | A bundled copy of **Bootstrap 5.2.3** (`js/bootstrap.bundle.min.js`), never loaded (Joomla provides Bootstrap), and an unused `css/j4-style(.min).css` | Deleted. `script.php` `update()` removes them from existing installs, together with the old shared `tmp/jed_checker/{archives,unzipped}` (a `local.txt` is kept). |
| J4 | jQuery call left in `script.js` (`jQuery.fn.scrollspy.noConflict()`); Joomla 6's administrator doesn't load jQuery | Removed |
| J5 | Reflection into `Language::loadLanguage()` (protected API) | Removed (S2) |
| J6 | `$app->getConfig()->get('tmp_path')` | `$app->get('tmp_path')` (through `CheckerHelper::workPath()`) |

Checked and fine for Joomla 6:
- **Structure:** service provider, `MVCComponent`, `<namespace>` in the manifest, `Joomla\Filesystem` / `Joomla\Archive` (framework packages), `getInput()`, `getIdentity()`, `bootstrap.*` HTML helpers (not deprecated).
- **Assets:** `access.xml`/`config.xml`.
- **Installer script:** the old-style class-based one still runs under Joomla 6 (through `LegacyInstallerScript`).
- **PHP 8.3:** no implicit-nullable parameters (deprecated in PHP 8.4), and no `${}` interpolation. No deprecation notices under `E_ALL`.

### 2.3 Bugs found on the way

| # | Bug | Fix |
|---|---|---|
| B1 | After an upload, `unzip()` took "the first file" in the archive folder. That folder also holds the `web.config` written by `protectDirectory()`, which sorts before names from `web_…` onwards. So an upload such as `zz_foo.zip` or `x.zip` failed with "unzip failed". | The archive is picked by its uploaded name. |
| B2 | `XmlManifestRule` raised `foreach() argument must be of type array\|object, null given` for manifests without `<files>`. Such a manifest is valid, for example one used only by a package. | `children() ?? []` |

## 3. What didn't change (by design)

- **The report is HTML.** Rules build their messages as HTML (`<br />`, `<b>`, `<code>`), and `Report`
  prints the message text as it is. Anything a rule takes from the package must be escaped with
  `htmlspecialchars()`, as all rules now do. A check for this (§4) should be kept for new rules.
- **`local.txt`** (extra folders to check, one per line, in the work folder or in the old shared
  `tmp/jed_checker/`) can name any folder the web server can read. This is deliberate: it can only
  be created by someone with file access, not through the screen.
- **Not measurable before unpacking:** bzip2 archives and gzip files over 4 GB (the size trailer
  wraps). The limits after unpacking still apply to those.

## 4. Verification

Run with PHP 8.3 (WebAssembly) against the real Joomla 6 libraries.

**Injection test:** a crafted package put a numbered `<xssN>` marker into every manifest field and
attribute, file name, language key and value, PHP string and comment.
- Before the fixes: markers 2, 3, 5 and 42 came through unescaped, the package's `.sys.ini`
  rewrote `COM_JEDCHECKER_LEVEL_ERROR`, and markers 28 and 37 were printed through JED Checker's
  own (rewritten) strings.
- After: **no raw markers**, and the application's language is untouched.

**Helpers:**
- `isInsidePackage()` (relative, `..`, absolute, drive, stream);
- `loadXml()` (malformed XML gives false and prints nothing; an external entity isn't expanded);
- `exceedsSizeLimit()` (600 KB zip and gzip bombs refused; an ordinary package passes).

**CheckerApi:**
- the zip bomb is refused before unpacking and nothing is left behind;
- an ordinary package checks all 14 rules in a given work folder, which is then removed;
- `../../../../etc/passwd`, `/etc` and a `../../x` namespace path are reported as outside the
  package;
- a manifest without `<files>` raises no PHP warning.

**Screen (controller, model, view):**
- `workPath()` is per user, unguessable and stable;
- `protectDirectory()` writes its rules and removes the package's `.htaccess`/`.user.ini`;
- the only tasks are `check`, `clear`, `upload` and `display`;
- an archive whose name sorts after `web.config` is unpacked (B1);
- a zip bomb upload gets a message;
- the view renders with the script options and the two web assets active, and the form carries
  the token;
- `check` without a token, or without `core.manage`, answers 403 with a message, and `clear`
  without `core.manage` throws `NotAllowed`.

**Web assets:** `com_jedchecker.script` resolves with `core`, `bootstrap.collapse`, `bootstrap.tab`
and `bootstrap.popover` loaded before it.

**Script (jsdom):**
- it reads its options, calls `uploads.check` with the token and counts the badges;
- an error status containing `<img onerror>` is shown as text, with no element created.

**Regression:** the PRD-07 checks (31 + 15), the PRD-01 to PRD-06 checks (78) and the language key
check still pass. Three packages checked with `E_ALL` raised no notices or deprecations.
