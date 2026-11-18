# Known issues found while writing this documentation

These are places where the software does not (yet) match what the documentation describes, or
where a feature exists in the code but cannot be reached from the interface. They are listed for
the people maintaining the JED code; the user guides mention workarounds where one exists.

Numbers are kept stable: fixed items are listed under **Fixed** at the end rather than renumbered.
The PRDs for the fixes are in `docs/prd/`.

File paths are relative to the repository root.

## Broken or placeholder links

| # | Where | Problem |
|---|---|---|
| 1 | `plugins/sampledata/jed_menu/menu.json` - **Search** | Links to `index.php?option=com_jed&view=search`; com_jed has no `search` view. Point it at Smart Search (`com_finder`). |
| 2 | `menu.json` - **Community** and **Support** items | All point to `view=categories&id=0` as placeholders (Meet the JED Team, Blog, Newsletter, Terms of Service, Help Joomla!, Knowledgebase, Sponsor Joomla!). The Knowledgebase item could point to the category created by the *JED Knowledgebase* sample data. |
| 3 | `menu.json` - **Vulnerable Extensions** children *About*, *Submit a Report*, *Submit an Update*, *Submit AbandonWare* | Point to com_jed views removed with the old VEL (`velliveitems`, `velreportform`, `veldeveloperupdateform`, `velabandonwareform`). Suggested targets: `com_vel&view=report`, `com_vel&view=fixnotice`, `com_abandonware&view=report`. (*JSON Feed* is fixed - see below.) |
| 4 | `components/com_jed/layouts/ticket/new_ticket_header.php` | The ticket form intro links "VEL abandoned report" to `com_jed&view=velabandonedreportform` (should be `com_abandonware&view=report`) and "this link" for new extensions to `view=extensionform` (should be `view=newextension`). |
| 5 | `administrator/components/com_jed/src/Helper/JedHelper.php` `addConfigToolbar()` | The **Vulnerable Extensions** toolbar button opens `com_jed&view=velvulnerableitems`, which no longer exists (should be `com_vel`). |
| 6 | `administrator/components/com_jed/script.php` - `com_jed.listing_submission_received` / `listing_update_received` | Bodies contain hard-coded `extensions.joomla.org` links with unreplaced `{fab_qs}...{/fab_qs}` placeholders (from the old Fabrik-based JED). |
| 7 | `script.php` - `com_jed.ticket_confirmation` | Body ends with "You can view your tickets by clicking on blah-blah-blah link." |

## Moderation

| # | Problem |
|---|---|
| 12 | The admin edit form's **Status** field and **Approval** tab are written to the revision but not applied on approval (by design for `state`/`approved`), which is confusing. Consider making them read-only or removing them from the form. (The **Owner** field is now read-only for existing listings - PRD-05 - and a saved revision no longer carries the Approval tab's values: every new revision starts undecided - PRD-06.) |
| 15 | **Blocking does not notify the developer**; the `com_jed.extension_vl1_vulnerable` template is never sent automatically. |
| 16 | **Edited reviews** are set back to unpublished (`ReviewformModel::save()`) but no ticket is opened, so they can go unnoticed. |
| 17 | The review **rejected** e-mail always has an empty reason (`ReviewModel` passes `REASONNOTES => ''`). |
| 18 | The **Reviews auto-approved** trusted flag is stored but never applied (`JedAccessHelper::isTrusted(..., true)` has no caller). |
| 19 | A review's own **overall score** is never calculated for reviews written on the site (`overall_score` is not part of the review form), so review pages show 0.0 and the extension page shows no overall stars per review. |
| 20 | The default score plugin (`plugins/jed/score_avg`) adds unrated (NULL) dimensions as 0, which lowers an extension's averages when reviewers skip the optional Support, Documentation or Value for Money scores. |

## Tickets

No open items - see *Fixed* below.

## Access and privileges

| # | Problem |
|---|---|
| 23 | The **Update XML** privilege is not checked by the update check (`UpdateCheckService`). |
| 24 | The **Report** privilege is enforced for VEL and abandonware reports but not for JED tickets ("Report this extension/review"). |
| 25 | There is no administrator screen for **targeted review bans** (`#__jed_user_review_bans`, per developer or category) or **suspect IP ranges** (`#__jed_suspect_ip_ranges`), although both are enforced/used by `JedAccessHelper`. |

## Listings

| # | Problem |
|---|---|
| 26 | The listing **Version** cannot be entered on the listing form; it is only ever set from the developer's update server. Listings without an update server never show a new version. |
| 27 | Changes to **additional categories, tags, screenshots, uploaded files and maintainers** are saved immediately, not held with the revision for moderation, while all other listing fields are moderated. If that is not intended, they need to move into the revision. |

## Abandonware

| # | Problem |
|---|---|
| 28 | There is no way to open a case by hand in the administrator, although the source *Opened by hand* exists. |

## Dead code

| # | Problem |
|---|---|
| 29 | `administrator/components/com_jed/layouts/vulnerableitem/` (`header.php`, `common_vulnerability.php`, `public_description.php`) are leftovers of the VEL that lived in com_jed. Nothing renders them any more, and the 11 language keys they use are defined nowhere. They can be deleted. (Found by `tests/language/check-language-keys.php`.) |

## Fixed

| # | Problem | Fix |
|---|---|---|
| 8 | Listings could not be rejected from the interface | [PRD-01](../docs/prd/PRD-01-reject-listings.md): Reject on the ticket (with reason and notes) and on the compare page |
| 9 | The ticket's Compare link failed | [PRD-01](../docs/prd/PRD-01-reject-listings.md): links straight to the compare page |
| - | Ticket action buttons (Approve, Reject, Delete, Block Listing, Open Case...) never reached their controller: their forms were nested in the ticket form | [PRD-01](../docs/prd/PRD-01-reject-listings.md): action forms rendered outside the ticket form |
| 10 | New listings stayed offline after approval | [PRD-02](../docs/prd/PRD-02-first-approval-online.md): the first approval puts a listing online; approved edits get their own e-mail |
| 11 | Developers could not switch a listing online/offline | [PRD-03](../docs/prd/PRD-03-developer-online-switch.md): *Take offline* / *Put online* on the dashboard |
| 14 | No interface for forced ownership transfer | [PRD-05](../docs/prd/PRD-05-forced-ownership-transfer.md): Ownership tab |
| - | Approving a revision written before an ownership change handed the listing back to its previous owner | [PRD-05](../docs/prd/PRD-05-forced-ownership-transfer.md): the owner is never copied from a revision |
| 13 | The Extensions list showed *Pending* after a block, unblock, delete or restore | [PRD-06](../docs/prd/PRD-06-ticket-return-and-pending.md): *Pending* means a revision awaiting a decision; administrator edits are written undecided |
| - | Review, review Delete and developer-response actions on a ticket went to the Reviews list | [PRD-06](../docs/prd/PRD-06-ticket-return-and-pending.md): they return to the ticket |
| 21 | Send Message on a ticket ended on "component not found" (com_tickets) | [PRD-04](../docs/prd/PRD-04-ticket-reply-redirect.md) |
| 22 | The status chosen with a message template was lost | [PRD-04](../docs/prd/PRD-04-ticket-reply-redirect.md): saved with the message |
| - | Run JED Checker on a ticket crashed (protected `getDatabase()`) | [PRD-04](../docs/prd/PRD-04-ticket-reply-redirect.md) |
| - | The VEL legacy JSON feed (`/vulnerable-extensions/json-feed`) and the JSON v2 API (`/vulnerable-extensions/api/v2/...`) answered 404 with SEF URLs: `ApiRules::parse()` waited for a `view` that no earlier rule sets | `ApiRules` takes the view from the matched menu item; the feed has a menu item type (*VEL: JSON Feed (legacy)*), a *JSON Feed* item in the JED main menu and a link on the list page |
