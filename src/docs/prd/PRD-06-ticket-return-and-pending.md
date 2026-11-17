# PRD-06: Review actions return to the ticket, and "Pending" means awaiting a decision

| | |
|---|---|
| Status | Implemented |
| Component | com_jed (administrator: reviews controller, extensions list, extension model, ticket handler) |
| Users affected | JED team |
| Related | PRD-01 (ticket actions, `return`), known issue #13 |

This PRD covers the two follow-ups left open by PRD-01.

---

## Part A - Review and developer-response actions return to the ticket

### A.1 Problem

PRD-01 made every ticket action post a `return` field (the ticket's own address) and taught the
listing actions (`ExtensionController`) to honour it. The Review ticket's **Approve** / **Reject** /
**Delete** and the Developer Response ticket's **Approve** / **Delete** post to
`ReviewsController`, which ignored it:

- `publish` / `unpublish` / `delete` are Joomla's inherited `AdminController` tasks, which always
  redirect to the list view;
- `publishResponse` / `deleteResponse` redirected to `view=reviews` explicitly.

So a review specialist working the queue was dropped on the Reviews list after every decision and
had to find the ticket again to reply and close it.

### A.2 Requirements

| # | Requirement |
|---|---|
| A-R1 | After Approve (publish), Reject (unpublish), Delete, response Approve and response Delete started from a ticket, the team member returns to that ticket, with the task's own success message. |
| A-R2 | The same tasks started from the Reviews list still return to the Reviews list. |
| A-R3 | Only internal addresses are followed. |

### A.3 Solution

`administrator/components/com_jed/src/Controller/ReviewsController.php`:

- `publish()` and `delete()` override the inherited tasks: they run `parent::publish()` /
  `parent::delete()` unchanged (permissions, token, model, messages) and then replace the redirect
  with the posted `return` when there is a usable one. `unpublish`, `archive` and `trash` are
  Joomla aliases of `publish`, so they are covered.
- `publishResponse()` / `deleteResponse()` redirect to `returnUrl(<list>)`.
- `returnUrl()` decodes the base64 `return` and accepts it only when `Uri::isInternal()` is true -
  the same rule as `ExtensionController::returnUrl()`.

`setRedirect()` with no message keeps the message the task set, so "1 review published" etc. is
still shown.

### A.4 Verification

| Check | Result |
|---|---|
| Review Approve from a ticket publishes the review and returns to the ticket | PASS |
| Review Reject (unpublish) from a ticket returns to the ticket | PASS |
| From the Reviews list it still returns to the list | PASS |
| An external return is ignored | PASS |
| Developer response Approve from a ticket returns to the ticket | PASS |
| Developer response Delete from a ticket returns to the ticket | PASS |
| Review Delete from a ticket deletes and returns to the ticket | PASS |

---

## Part B - "Pending" means a revision awaiting a decision (known issue #13)

### B.1 Background

Every change to a listing is a revision in `#__jed_extensions_history`. Since blocking and soft
deletion were introduced, **blocks, unblocks, deletions and restores also write a revision** -
marked inactive (`active = 0`) - so the history records who did what. The correct definition of
"something is waiting for a decision" already existed in `ExtensionModel::getPendingHistoryId()`:
a revision that was **submitted** (`active = 1`) and **not decided** (`approved_time IS NULL`).

### B.2 Problem

Three places used a different, older definition - "the newest revision is not the one the live
listing points at":

| Where | Effect |
|---|---|
| Extensions list, *Pending* column (`ExtensionsModel` + `tmpl/extensions/default.php`) | After a block, unblock, delete or restore, the orange *Pending* icon appeared although nothing awaited a decision. Approving from it promoted the state-change snapshot and e-mailed the developer an approval. |
| Compare page without an explicit revision (`ExtensionModel::getCompareItems()`) | Opened on the newest revision - after a block, the block's snapshot - instead of the developer's pending edit. |
| Ticket's *Linked Extension* panel (`ExtensionTicketHandler::getMasterData()`) | Announced a pending change after a block and linked to the snapshot. |

While switching these to the correct definition, a second defect showed up: **an edit made by the
JED team in the administrator never counted as pending.** The edit form's *Approval* tab is loaded
from the previous revision, so saving an approved listing wrote a new revision that already carried
`approved = 1` and an `approved_time`. Under the correct definition that revision looked decided, so
it could never be approved - the rename procedure in the help ("Save, then Pending icon, then
Approve") would have stopped working. (The old definition hid this by flagging *any* newer revision.)

### B.3 Requirements

| # | Requirement |
|---|---|
| B-R1 | The *Pending* column, the compare page's default revision and the ticket panel all use one definition: a submitted, undecided revision. |
| B-R2 | Blocks, unblocks, deletions and restores never make a listing *Pending*. |
| B-R3 | Every revision saved from the listing form - site or administrator - starts undecided. |
| B-R4 | A revision that already says "approved" is never pending (guards data imported from JED3 with `approved = 1` but no recorded time). |
| B-R5 | The Pending icon opens the compare page on exactly the pending revision, so Approve and Reject act on it. |

### B.4 Solution

- **`ExtensionModel::getPendingHistoryId()`** - adds `COALESCE(approved, 0) <> 1` to `active = 1 AND
  approved_time IS NULL` (B-R4).
- **`ExtensionModel::save()`** (administrator) - writes the new revision with `approved = 0`,
  `approved_time = NULL`, `approved_by = NULL`, empty reason and notes (B-R3). The site form never
  carried these fields, so developer edits were already undecided.
- **`ExtensionModel::getCompareItems()`** - without an explicit right-hand revision, uses the pending
  one, falling back to the newest.
- **`ExtensionsModel::getListQuery()`** - the `latest_history_id` subquery is replaced by
  `pending_history_id` with the same conditions as `getPendingHistoryId()`.
- **`tmpl/extensions/default.php`** - *Pending* when `pending_history_id` is set; the icon links to
  `layout=compare&id=...&right=<pending id>`.
- **`ExtensionTicketHandler::getMasterData()`** - loads the live row and the pending revision
  (`getPendingHistoryId()`); `isPending` is true only when there is one.

### B.5 Verification

| Check | Result |
|---|---|
| An approved listing is not pending | PASS |
| A JED3 listing approved without a recorded time is not pending | PASS |
| A submission awaiting approval is pending | PASS |
| After a block the listing is not shown as pending | PASS |
| Nor after unblock, delete and restore | PASS |
| The ticket panel does not announce a pending change either | PASS |
| An administrator edit is pending (its revision is written undecided) | PASS |
| ... and records the live owner, not the form's | PASS |
| The compare page opens on the pending revision by default | PASS |
| The ticket panel shows the pending revision | PASS |
| A block after the edit does not hide the pending edit from the compare page | PASS |
| Once approved it is no longer pending | PASS |

---

## Acceptance criteria

1. On a Review ticket, **Approve**: the review is published and you are back on the ticket with the
   success message. Same for Reject and Delete, and for the Developer Response ticket's actions.
2. Block a listing that has nothing pending: the Extensions list shows the grey tick, not *Pending*.
3. Edit an approved listing in the administrator and save: the list shows *Pending*; the icon opens
   the compare page on that revision; **Approve** makes it live.
4. A developer saves an edit, then the team blocks the listing: *Pending* stays, and the compare
   page still shows the developer's edit, not the block.

## Data and migration

None. The list and ticket simply stop flagging state-change revisions. Listings that had a
state-change revision as their newest row stop showing *Pending* immediately.
