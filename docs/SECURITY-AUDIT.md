# Security and logic review: com_jed, com_vel, com_abandonware

Scope: the three JED components (site and administrator sides), plus the plugins and shared
helpers they call directly (`plg_task_jed`, `plg_privacy_vel`, `plg_system_jedidentity`, the
`Url`, `Parser`, `Audit`, `Checker`, `Transfer` and `Identity` services). Joomla core was not
reviewed. The review was done by reading the code; there is no PHP runtime in the review
environment, so fixes were checked with a PHP parser (syntax), a class-name resolution check and
an undefined-variable check, not by running the test suites. **Run `tests/` and click through the
affected screens before deploying.**

Severity is rated for this site: who can trigger it, and what it exposes or breaks.

| Severity | Meaning |
|---|---|
| Critical | Any registered user can read or change data that is not theirs |
| High | Confidential data leaks, or stored XSS on a public page, or a control that protects people does not work |
| Medium | Abuse needs a staff account or a less common setup, or integrity of public data (scores, markers) |
| Low | Defence in depth, robustness, broken features with no security effect |

All items marked **Fixed** are changed in this commit. Items under *Not changed* are
recommendations that need a product decision.

---

## Summary

| ID | Sev. | Component | Finding | Status |
|---|---|---|---|---|
| JED-01 | Critical | com_jed site tickets | Ticket form mass assignment: overwrite/hijack any ticket, read other people's tickets, write internal notes, link tickets to draft VEL advisories / abandonware cases and read them | Fixed |
| VEL-01 | High | com_vel messaging | Reporters attached to the same advisory see each other's reports, messages and attachments; a reply on one report is mailed to every reporter | Fixed |
| VEL-02 | High | com_vel feeds | HTML from third-party CVE records (`supportingMedia`) stored unfiltered as advisory description → stored XSS on the public advisory page and vendor case page | Fixed |
| VEL-03 | High | com_vel site | JSON-LD `<script>` block breakout on the public advisory page | Fixed |
| ABW-01 | High | com_abandonware | Reporter's username put into the case signal, which is mailed to the reported developer and copied into the ticket | Fixed |
| JED-02 | High | com_jed parsers | Upload/GitHub package parsers bypass `SafeZip` (zip bomb, no size cap, temp dirs leaked) – and crash on an unimported `ZipArchive` | Fixed |
| JED-03 | Medium | com_jed JED Checker | Developer-controlled download URL fetched with the plain HTTP client (SSRF into internal network / cloud metadata, unbounded memory) | Fixed |
| VEL-04 | Medium | com_vel | JED ticket can be opened for an unpublished (embargoed) advisory – UI-only guard | Fixed |
| VEL-05 | Medium | com_vel | `core.edit` users can rewrite a *published* advisory although publishing needs `vel.publish` | Fixed |
| VEL-06 | Medium | com_vel | "Spam" never withdrew the reporter's report privilege (fatal error swallowed); would also have run when the spam decision was refused | Fixed |
| VEL-07 | Medium | com_vel | Public fix-notice can pin itself to any advisory id (drafts, non-existent ids) | Fixed |
| VEL-08 | Low | com_vel | `notifyVendor()` harvests developer addresses and summaries from spam/rejected/unverified reports | Fixed |
| ABW-02 | Medium | com_abandonware | Case can be marked (and published) abandoned seconds after the owner is contacted – grace period not enforced | Fixed |
| ABW-03 | Medium | com_abandonware | `extension_id` of a contacted/abandoned case editable → public marker moved to a listing whose owner was never contacted | Fixed |
| JED-04 | Medium | com_jed reviews | `canViewReview()` treats reviews of blocked/deleted listings as public | Fixed |
| JED-05 | Medium | com_jed reviews | No server-side rules: self-review, several reviews per listing, reviews of unpublished listings, moving a review to another listing | Fixed |
| JED-06 | Medium | com_jed reviews | Review scores unbounded (−9.9…9.9 on a 0–5 scale); overall score counted unscored dimensions | Fixed |
| JED-07 | Medium | com_jed admin | Audit pipeline always failed (undefined `$response`); truncated packages would have been audited | Fixed |
| JED-08 | Low | com_jed reviews | "One response per review, not editable, not after deletion" not enforced server-side | Fixed |
| JED-09 | Low | com_jed tickets | "Reported extension" picker lists every listing incl. unapproved/blocked/deleted | Fixed |
| JED-10 | Low | com_jed | Any logged-in user could check out (lock) any ticket or review | Fixed |
| JED-11 | Low | com_jed admin | `jed.user.ban` holders can change their own access row (grant themselves auto-approval / unban) | Fixed |
| JED-12 | Low | com_jed admin | Public controller method `TicketController::storeMessage()` routable as a task | Fixed |
| JED-13 | Low | com_jed admin | Extension image list filter inverted (admins saw only their own uploads) | Fixed |
| JED-14 | Low | com_jed site | Broken tasks: `review.edit` and `ticketform.remove` fatal; ticket save failure reported as success | Fixed |
| VEL-09 | Low | com_vel | CWE refresh always failed (same driver `bind()` bug); temp file leaked on failure | Fixed |
| VEL-10 | Low | com_vel | Advisory canonical redirect used an HTML-escaped URL (`&amp;`) | Fixed |
| VEL-11 | Low | com_vel | Inbound mail daily cap counted the team's outbound mail | Fixed |
| ABW-04 | Low | com_abandonware | Signal scan batch size unbounded | Fixed |
| GEN-01 | Low | forms | Admin forms depend on the deprecated `JComponentHelper` alias (Joomla 6.1 throws without the compat plugin) | Fixed |

---

## Details

### JED-01 (Critical) – Ticket form mass assignment

**Where:** `components/com_jed/src/Model/TicketformModel.php::save()`,
`components/com_jed/forms/ticketform.xml`, `components/com_jed/src/View/Ticket/HtmlView.php`.

`ticketform.xml` declares `id`, `created_by`, `ticket_status`, `internal_notes`, `allocated_to`,
`allocated_group`, `linked_item_type`, `linked_item_id`, `parent_id`, `state` (rendered as hidden
inputs). `save()` passed the validated form data straight to `TicketTable::save()`. Because
`Form::filter()` keeps every declared field, any logged-in user could:

* post `jform[id]=<any ticket id>` → `Table::save()` runs an UPDATE on that ticket (subject, text,
  status, internal notes, assignment…);
* add `jform[created_by]=<own id>` → the ticket becomes theirs, and `view=ticket&id=…` then shows
  it with all non-internal messages;
* create tickets impersonating another user (`created_by`), pre-closed, pre-assigned, with
  internal notes;
* set `linked_item_type=11` (VEL advisory) or `10` (abandonware case) with any id, then open their
  own ticket: the site ticket view rendered the administrator master-data panel – a **draft
  advisory's title, products and affected versions** (embargoed vulnerability details) or a
  non-public abandonware case with its signals. Listing master data showed pending, unmoderated
  revisions of other people's listings.

**Fix:** `save()` now builds the row itself: always a new ticket (`id = 0`), `created_by` = the
current user, status New, no assignment, no internal notes, state published. Only category,
subject, text and the routing group come from the request. `resolveLinkedItem()` allows only an
Extension the user can view (owned/maintained when the category is "My Extension") or a Review
the user can view; anything else is dropped. The site ticket view checks ownership *before*
loading anything, shows master data to non-staff only for Extension / Review / Other /
own-listing Developer-response tickets, and hides pending revisions of listings the reader does
not own. `getTicketMessages()` on the form model no longer returns internal messages to non-staff.
The controller no longer indexes into a `false` validation result.

**Follow-up:** check existing `#__jed_tickets` rows with `created_by` > 0 and
`linked_item_type` in (8, 9, 10, 11) or `ticket_status`/`internal_notes` set on creation by a
non-staff user.

### VEL-01 (High) – Cross-reporter disclosure in case threads

**Where:** `administrator/components/com_vel/src/Service/MessageService.php::thread()`,
`src/Controller/MessageController.php::reply()`, `src/Mail/ThreadResolver.php::reply()`.

`ReportService::attach()` → `MessageService::adopt()` moves a report's participants and messages
onto the advisory. `thread()` then scopes by advisory and filters only by role, so reporter A's
private case page showed reporter B's "report filed" message (the full report summary), B's
replies, the team's replies to B and the attachments on those messages. A team reply written on
report B's screen was delivered to every reporter of the advisory. Duplicate reports of the same
vulnerability are common, so this is a realistic path.

**Fix:** for a REPORTER reader, messages that belong to another report (`report_id` set and
different) are skipped; the team's advisory-wide messages (`report_id` NULL) still reach everyone.
A reply written on a report's screen is delivered only to that report's reporter. Inbound e-mail
from a reporter on an adopted case is recorded against the reporter's own report.

### VEL-02 (High) – Stored XSS from CVE feed HTML

**Where:** `administrator/components/com_vel/src/Feed/AdvisoryMerger.php::create()`.

`CveRecordMapper` copies a CVE record's `supportingMedia` (type `text/html`) into
`description_html`; `AdvisoryMerger::create()` stored it verbatim as the advisory description. The
form path filters the description (`filterText`), the feed path did not. The public advisory page
and the vendor's case page print the description through `content.prepare` without escaping. CVE
records are written by any CNA, and the classifier picks up any record that mentions Joomla.

**Fix:** `AdvisoryMerger::cleanHtml()` runs the HTML through Joomla's `InputFilter` in block-list
mode (drops script/iframe/object/…, `on*` attributes, `javascript:` URLs) before it is stored.
**Follow-up:** re-save (or re-filter) descriptions of advisories created from feeds before this
fix.

### VEL-03 (High) – JSON-LD breakout

**Where:** `components/com_vel/tmpl/advisory/default.php`.

`json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)` inside
`<script type="application/ld+json">`. `description_text` is entity-*decoded* text, so an advisory
that quotes a payload such as `</script><script>…` (routine for XSS advisories) ended the script
element and executed on the public page. **Fix:** `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS |
JSON_HEX_QUOT` added.

### ABW-01 (High) – Reporter identity sent to the reported developer

**Where:** `administrator/components/com_abandonware/src/Service/ReportService.php::submit()`.

The signal detail was `"Reported by <username>."`. Signals are summarised into the JED ticket and
into `{REASON}` of the owner-contact mail (`CaseService::mailOwner()`), i.e. sent to the developer
whose extension was reported – contradicting the schema's own rule that reporter identity lives
only in `#__jed_abandonware_reports`. **Fix:** the detail is now "A member of the public filed a
report on <date>." (language string changed in both language files).
**Follow-up:** existing rows of `#__jed_abandonware_cases.signals` and the matching ticket bodies
still contain usernames; clean them up with a one-off script.

### JED-02 (High) – Package parsers bypass SafeZip

**Where:** `administrator/components/com_jed/src/Parser/File.php`, `Parser/Github.php`.

`SafeZip` was written for exactly this ("any logged-in user can submit one") but was not used.
Both parsers called `ZipArchive::extractTo()` – no size or entry limit – and, because
`ZipArchive` was not imported in that namespace, actually died with "class not found". When the
manifest was missing the constructor threw and `__destruct()` never ran, so extracted folders were
left in the temp directory. **Fix:** both use `SafeZip::extract()`, delete their temp folder when
the manifest cannot be read, and the GitHub download is removed in all cases.

### JED-03 (Medium) – SSRF in JED Checker downloads

**Where:** `administrator/components/com_jed/src/Checker/JedCheckerService.php::download()`.

The listing's `download_url` / `internal_download_url` (developer-controlled) was fetched with
`HttpFactory` (follows redirects anywhere, no address checks) and the whole body buffered before
the size check. Every other fetch of a developer URL already goes through `SafeHttpFetcher`.
**Fix:** `SafeHttpFetcher` with the package size cap.

### VEL-04 (Medium) – JED ticket for an unpublished advisory

`AdvisoryController::jedticket()` → `afterPublish()` had no state check; only the template hid
the button. A POST opened a JED ticket containing an embargoed advisory's title, products and
versions. **Fix:** refused unless the advisory is published.

### VEL-05 (Medium) – Editing a public advisory without `vel.publish`

`access.xml` reserves "putting a statement about somebody's product on the site" for
`vel.publish`, but `AdvisoryModel::save()` let any `core.edit` user change a published/retired
advisory, which `touchPublished()` puts live immediately. **Fix:** saving a published or retired
advisory requires `vel.publish`.

### VEL-06 (Medium) – Spam ban never applied

`ReportController::spam()` called `$db->setQuery('INSERT …')->bind(…)`. `DatabaseDriver` has no
`bind()` (and `quoteName([...])` returns an array, producing `(Array)` in the SQL); the Error was
caught and ignored, so the privilege listed in `docs/vel/security-review.md` was never withdrawn.
It also ran after `decide()` had refused the change. **Fix:** statement built on a query object;
runs only when the spam decision was recorded; failures are logged; `JedAccessHelper` cache
cleared.

### VEL-07 / VEL-08 – Report-to-advisory linking

`ReportService::submit()` turned a public fix-notice's `advisory_reference` into an id without
checking it exists or is public, so a report could be attached to any draft by guessing the next
number. Now only public advisories are accepted; otherwise the reference is kept as the report's
tracking reference. `notifyVendor()` now ignores spam, rejected and unverified reports, so their
`developer_email` (typed by the reporter) does not receive a vendor case link.

### ABW-02 (Medium) – Grace period not enforced

`CaseStatus` allows `owner_contacted → abandoned`, and `markAbandoned()` only checked that a
contact was recorded. The developer help promises a 30-day grace period before marking. **Fix:**
`markAbandoned()` requires `grace_until` to have passed (`CaseService::graceHasEnded()`); the edit
screen explains why the button is unavailable.

### ABW-03 (Medium) – Re-pointing a case

The admin case form keeps `extension_id` editable (useful during triage). After contact, changing
it moved the contact attempt, grace period and public marker onto another listing. **Fix:**
`extension_id` is locked once the owner has been contacted or the case has left
received/reviewing; server-managed columns (`open_extension_id`, `created*`, `contact_note`) are
stripped too.

### JED-04 (Medium) – Reviews of blocked/deleted listings

`JedHelper::canViewReview()` selected only `id, state, approved` and passed the row to
`canViewExtension()`, which reads `blocked` and `deleted` – missing, so treated as 0. **Fix:** all
four carriers selected.

### JED-05 / JED-06 (Medium) – Review integrity

`ReviewformModel::save()` now refuses new reviews of listings that are not public, of listings the
reviewer owns or maintains (help: "Developers must not review their own extensions"), and second
reviews by the same person; an edit can no longer move a review to another listing
(`extension_id` is forced to the stored value and the ban check repeated for it).
`ReviewformController::save()` clamps each rating to 0.5–5 in half steps (anything else = not
scored), computes the overall score from the scored dimensions only, and shows refusals as
messages instead of error pages.

### JED-07 (Medium) – Audit pipeline

`AuditPipeline::downloadExtensionZip()` used an undefined `$response` (fatal on every audit) and
accepted a body cut off at the fetcher's cap. Both fixed.

### JED-08 … JED-14, VEL-09 … VEL-11, ABW-04, GEN-01 (Low)

* **JED-08** `ReviewModel::saveResponse()` refuses when a response exists or was deleted.
* **JED-09** `JedextensionsField` lists only listings the user can view.
* **JED-10** `reviewform.edit` / `ticketform.edit` hold and check out only the user's own
  review/ticket (staff excepted).
* **JED-11** `UseraccessController` refuses decisions about one's own account.
* **JED-12** `TicketController::storeMessage()` made private; it also stored the literal
  `'now()'` as `modified_on`.
* **JED-13** `ExtensionimagesModel`: users without `core.edit` see only their uploads.
* **JED-14** `ReviewController::edit()` called a non-existent `checkout()` (every "Edit" on a
  review page fatal) – now delegates to `reviewform.edit`; `TicketformController::remove()`
  called a non-existent `delete()` without a token – now a token-checked refusal;
  `TicketformController::save()` fell through to "saved successfully" after a failure.
* **VEL-09** `CwesController::import()` had the same `bind()`-on-driver bug; temp file removed in a
  `finally`.
* **VEL-10** `VelHelper::route()` takes `$xhtml`; the 301 redirect uses an unescaped URL.
* **VEL-11** inbound daily cap counts only `direction = 'in'`.
* **ABW-04** `cases.scan` batch clamped to 1–500.
* **GEN-01** `filter="JComponentHelper::filterText"` → fully-qualified
  `Joomla\CMS\Component\ComponentHelper::filterText` (com_vel advisory form, com_jed ticket forms).

---

## Not changed – recommendations

1. **VEL webhook replay.** `WebhookReceiver::verify()` signs the body only; a captured request can
   be replayed forever (de-duplication works only when a Message-ID is present). Sign a timestamp
   as `plg_system_jedidentity`'s `PingVerifier` does and reject stale ones.
2. **Vendor recipients from reporters.** `notifyVendor()` still mails any `developer_email` typed
   into an accepted report; whoever it names gets the draft and the vendor thread. Show the
   recipient list to the team and require confirmation, or restrict to listing contacts when the
   product is a JED listing.
3. **Feed merge into public advisories** needs only `vel.feeds`; consider requiring `vel.publish`
   when the target is published (same reasoning as VEL-05).
4. **com_jed admin edit layouts** are protected by `ViewAccess` only (no `checkEditId()` as in
   com_vel's dispatcher); a backend user with `core.edit.own` can open any record's edit layout.
5. **`JedHelper::isAdminOrSuperUser()`** hard-codes group ids 7 and 8 and is used for site
   authorisation (edit/delete any review, read any ticket); prefer ACL actions.
6. **Duplicate tasks** on the Extensions, Reviews and Extension images lists call `duplicate()` on
   models that have no such method (fatal). Remove the toolbar buttons or implement them.
7. **Site ticket form text filter** `filter="ComponentHelper::filterText"` cannot be resolved and
   falls back to stripping all HTML. That is safe, and was left as is on purpose; switching it to
   the real `filterText` would apply the Registered group's text filter to HTML that staff view.
8. **Privacy export** attributes developer responses to the *current* owner of the listing; after
   a transfer the new owner receives the previous owner's (or a maintainer's) words.
9. **Moderation scope.** Screenshots, files, categories, tags and maintainers saved from the site
   edit form go live immediately while the text waits for approval. Confirm this is intended.
10. **IpGuard** could also block `2002::/16` (6to4) and `2001::/32` (Teredo), which embed IPv4.
11. **Data clean-up** for ABW-01 (usernames in existing signals/tickets), VEL-02 (feed-created
    descriptions) and JED-01 (tickets created through the mass-assignment path).
