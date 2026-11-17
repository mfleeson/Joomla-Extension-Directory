# PRD-01: Rejecting a listing from the administrator

| | |
|---|---|
| Status | Implemented |
| Component | com_jed (administrator) |
| Users affected | JED team (listing specialists), developers |
| Related | PRD-04 (ticket screen), helpdocs `jed-team/how-to/approve-or-reject-a-listing.md` |

## 1. Summary

The JED team could approve a submitted listing (or an edit to one) but could not reject it. The
ticket's **Reject** button always failed, and the compare page only offered **Approve**. The team
worked around it by sending a canned reply and leaving the listing waiting indefinitely. Rejection
is now possible from both places, always with a reason code and notes, and the developer receives
the decision by e-mail *and* on their ticket.

While fixing this, a second, larger defect was found and fixed: **no action button on the ticket
screen had ever reached its controller** (Approve, Reject, Delete, Block Listing, Open Case...),
because each action's form was nested inside the ticket's own form.

## 2. Background

Every change to a listing is stored as a *revision* in `#__jed_extensions_history`; the public
listing (`#__jed_extensions`) only changes when a revision is approved
(`ExtensionModel::approve()`). The counterpart, `ExtensionModel::reject()`, exists and is sound:

- it requires a reason code from the shared vocabulary `#__jed_block_reasons` (ER1, NM1 ... US1,
  PE1, VL1, PV1) - "a rejection the developer cannot act on is not a moderation decision";
- it marks the revision as decided-and-refused, mirrors the verdict onto the live row only for a
  listing that has never been public, e-mails the developer with the reason code's own mail
  template, and logs the decision.

The model was right; nothing in the interface could call it correctly.

## 3. The problem

### 3.1 Ticket Reject never carried a reason

`ExtensionTicketHandler::getActions()` returned a Reject `TicketAction` with only two hidden fields
(`extension_id`, `history_id`). `TicketAction` could only carry hidden fields, so there was no way to
ask the team member for a reason. `ExtensionController::reject()` therefore always passed an empty
reason and the model refused: *"A rejection needs a reason code."*

### 3.2 The compare page had no Reject at all

`View\Extension\HtmlView::addToolbar()` added only **Approve** to the compare layout.

### 3.3 Ticket action buttons submitted the wrong form (found during the fix)

`tmpl/ticket/edit.php` is one `<form id="ticket-form">` wrapping the whole screen, and the action
buttons (`layouts/ticket/action_button.php`) each rendered their own `<form>` *inside* it. HTML has no
nested forms: the parser drops the inner `<form>` start tag, so the button belongs to the ticket
form. Verified with an HTML parser:

```
forms in DOM: 1   button.form.id = ticket-form
```

Clicking Approve posted the ticket form; its own empty `task` field came last and won, so the
request ended in the ticket controller's default display. This affected **every** ticket action:
listing Approve/Reject, review Approve/Reject/Delete, developer-response Approve/Delete,
Vulnerability Block/Unblock Listing, and abandonware Open Case.

### 3.4 The rejection notes never reached the developer for edits

The per-code mail templates (e.g. *Extension: NM1 - Install Name Doesn't Match Listing Name*) are the
JED3 wording and have no `{REASONNOTES}` placeholder. The notes were only visible on the developer's
dashboard for listings that had never been public; for a refused **edit** to an online listing the
developer never saw them.

### 3.5 The Compare link on the ticket was broken (found during the fix)

`layouts/ticket/masterdata_extension.php` linked to `task=extension.compareHistory` with GET
parameters. That task checks a POSTed token and reads the selection from POST, so the link always
failed.

## 4. Goals and non-goals

**Goals**

- A team member with *Approve Listings* can reject the pending revision from the ticket and from the
  compare page.
- A reason code is mandatory; notes are offered and encouraged.
- The developer learns the reason (e-mail) and the notes (ticket), wherever the listing stands.
- After acting from a ticket, the team member returns to that ticket.
- Ticket action buttons work.

**Non-goals**

- Changing the reason vocabulary or the mail templates' wording.
- Rejecting an older, already decided revision (that would mail a refusal of something the
  developer never submitted for review).

## 5. Requirements

| # | Requirement |
|---|---|
| R1 | The Extension ticket offers **Reject** next to Approve when a revision awaits a decision. |
| R2 | Reject opens a small form: **Reason** (required; the enabled codes, in their configured order) and **Notes for the developer** (optional), then a confirm prompt. |
| R3 | The compare page shows **Reject** in the toolbar, and a *Reject this revision* panel with the same two inputs, **only** when the right-hand revision is the one awaiting a decision and the user has *Approve Listings*. The button refuses to submit without a reason. |
| R4 | The server refuses a rejection without a reason (unchanged) and refuses to reject any revision other than the pending one. |
| R5 | On rejection the developer is e-mailed (reason code template, unchanged) **and** an outgoing message is written on the listing's latest ticket with the reason and the notes; that ticket's status becomes **Awaiting User**. |
| R6 | After Approve or Reject from a ticket, the team member is returned to the ticket. A refused rejection returns to where it was made, with the error. Only internal return addresses are followed. |
| R7 | All ticket actions submit their own form. |

## 6. Solution

### 6.1 Ticket actions can ask for input (`TicketAction`)

`TicketAction` gained an optional `fields` list. Each field has `name`, `type` (`select`,
`textarea`, `text`), `label`, `options`, `required` and `hint`. An action with fields renders as a
button that opens a small form (an HTML `<details>` element - no script needed); the submit is a
deliberate second click.

### 6.2 Action forms live outside the ticket form (`layouts/ticket/action_button.php`, `tmpl/ticket/edit.php`)

The layout now renders in two parts:

- `part => 'controls'` - the buttons and inputs, placed where the actions are shown. Every control
  names its form with the HTML `form="..."` attribute.
- `part => 'form'` - the `<form>` with the hidden fields, the CSRF token and a `return` field
  (the current ticket URL, base64), rendered by `tmpl/ticket/edit.php` **after** `</form>`.

Without `part`, both are rendered together (for pages without an enclosing form). This fixes every
ticket action (§3.3).

### 6.3 The Reject action (`ExtensionTicketHandler`)

Reject now carries the two fields, with options from `Listing\BlockReasons::options()`, and a
confirm message.

### 6.4 The compare page (`View\Extension\HtmlView`, `tmpl/extension/compare.php`)

- New view state `compareCanReject` = "the right-hand revision is `getPendingHistoryId()`".
- Toolbar: **Reject** after **Approve** when allowed.
- Template: a *Reject this revision* card inside the page's `adminForm` with the reason select and
  notes, and a small inline script that overrides `Joomla.submitbutton` to require a reason and
  confirm before submitting `extension.reject`.

### 6.5 The model (`Model\ExtensionModel::reject()`)

- Refuses a revision that is not the pending one (`COM_JED_REJECT_ERROR_NOT_PENDING`).
- After the e-mail, calls `recordOnLinkedTicket(TicketType::Extension, ...)` with subject "Not
  approved: *CODE* - *title*" and a body naming the listing, the reason and the notes (escaped), and
  status 1 (*Awaiting User*).

### 6.6 Return to the ticket (`Controller\ExtensionController`)

New `returnUrl()` honours a base64 `return` only when `Uri::isInternal()`; used by approve,
reject and the block/unblock/delete/restore transitions. A failed rejection falls back to the
compare page. The block/unblock/delete/restore transitions also release the edit lock when they were
started from the edit form.

### 6.7 Shared reason reader (`Listing\BlockReasons`, `Field\BlockreasonField`)

`BlockReasons::options($db)` returns `code => "CODE - Title"` for enabled reasons in order.
`BlockreasonField` now uses it.

### 6.8 Compare link on the ticket (`layouts/ticket/masterdata_extension.php`)

Links straight to `view=extension&layout=compare&id=...&right=...`.

## 7. User experience

**Ticket, Linked Extension tab:** `[Approve] [Reject v]` -> Reject opens: *Reason \** (select),
*Notes for the developer* (textarea, hint: "What exactly has to be corrected..."), red **Reject**
button -> confirm "Reject this revision? The developer is e-mailed the reason, and your notes are
written on this ticket." -> back on the ticket with "The submission has been rejected and the
developer has been notified."

**Compare page:** toolbar `Back | Approve | Reject`; below the diff a card *Reject this revision*
with the same inputs.

**Developer:** e-mail for the reason code; in *My Tickets* a message "Not approved: NM1 - Install
name does not match listing name" with the notes; the ticket shows *Awaiting User*. A never-public
listing shows *Not approved* with the notes on the dashboard, as before.

## 8. Permissions and security

- Approve/Reject require `jed.approve` (unchanged; checked in the controller and when building the
  actions).
- The action forms carry Joomla's CSRF token.
- `return` is only followed for internal URLs.
- Notes are HTML-escaped before they are stored on the ticket.

## 9. Data and migration

None. No schema change.

## 10. Verification

| Check | Result |
|---|---|
| An already decided revision cannot be rejected | PASS |
| A rejection without a reason code is refused | PASS |
| The pending revision is rejected with its reason | PASS |
| An approved listing stays approved when an edit is rejected | PASS |
| The developer gets the reason code's own template | PASS |
| The decision is written on the listing's ticket with the notes | PASS |
| The ticket becomes Awaiting User | PASS |
| A never-approved listing becomes Not approved with the notes | PASS |
| A rejection succeeds even when no ticket is linked | PASS |
| The ticket offers Approve and Reject; Reject asks for a required reason (enabled codes only) and notes | PASS |
| Rendered inside a ticket form, the action form is not nested and carries task, ids, return and token; the inputs and the button belong to it | PASS |
| Through the controller: no reason -> back to the ticket with the error; with a reason -> rejected and back to the ticket; an external `return` is ignored | PASS |
| Compare page: the panel is inside the form, offers the reasons, posts the revision, the script guards Reject, and there is no panel without *Approve Listings* | PASS |

## 11. Acceptance criteria

1. On a ticket "New listing submitted: X", a team member with *Approve Listings* picks **Reject**,
   chooses *NM1*, writes notes, confirms -> the developer receives the NM1 e-mail, the ticket shows
   an outgoing "Not approved: NM1 ..." message with the notes and status *Awaiting User*, and the
   team member is back on the ticket.
2. The same from the compare page (Pending icon in the Extensions list).
3. Reject without choosing a reason is blocked in the browser and, if forced, refused by the server.
4. Approve, review Approve/Reject/Delete and Vulnerability Block Listing on tickets all perform
   their action.

## 12. Risks and follow-ups

Both follow-ups noted here originally are done in [PRD-06](PRD-06-ticket-return-and-pending.md):
review and developer-response actions now return to the ticket too, and the Extensions list's
*Pending* icon uses the same "awaiting a decision" definition as the ticket actions.
