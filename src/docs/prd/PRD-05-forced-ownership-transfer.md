# PRD-05: Forcing an ownership transfer from the administrator

| | |
|---|---|
| Status | Implemented |
| Component | com_jed (administrator edit screen, controller, model, form), TransferService |
| Users affected | JED team, developers (old and new owners) |
| Related | Abandonware cases (adoptions), helpdocs `jed-team/how-to/change-owner.md` |

## 1. Summary

When a listing's owner cannot be reached - typically when somebody adopts an abandoned extension -
the JED team must move the listing to a new owner without the old owner's confirmation. The service,
the controller task and the *Force Ownership Transfer* permission existed, but no screen could call
them. The documented workaround (edit the *Owner* field and approve the revision) sent no e-mails,
did not cancel open handovers, left the new owner also listed as a maintainer, and left no
transfer record. There is now an **Ownership** tab on the listing's edit screen, and the *Owner*
field can no longer be used to change ownership.

While fixing this, a data-integrity defect was found and fixed: **approving any revision could hand
a listing back to its previous owner**, because `approve()` copied the revision's `owner` onto the
live row.

## 2. Background

Ownership changes in two ways (`Transfer\TransferService`):

- **Handover** - the owner starts it from the dashboard; both parties confirm by e-mail link within
  14 days.
- **Forced transfer** - `TransferService::force()`: requires a reason, cancels any open handover
  ("The JED team reassigned the listing directly"), records a `forced` transfer, moves `owner`,
  removes the new owner's maintainer row, e-mails both parties (`com_jed.transfer_forced` with the
  reason) and writes the action log. The recipient is looked up with the same rate-limited
  `findRecipient()` the developers use (activated, unblocked account; not yourself).

## 3. The problem

1. No interface posted to `extension.forceTransfer` with `transfer_email` and `transfer_reason`.
2. The *Owner* field on the edit form was editable, and `ExtensionModel::approve()` copied `owner`
   from the approved revision. So changing *Owner* + approving "worked", bypassing every rule above.
3. Worse, every revision stores the owner it was saved under. A revision written **before** a
   transfer and approved **after** it restored the previous owner.
4. `force()` did not refuse a soft-deleted listing.
5. After a successful forced transfer the controller left the listing checked out to the team member.

## 4. Goals and non-goals

**Goals**

- A team member with *Force Ownership Transfer* can reassign a listing from its edit screen, giving
  the new owner's account e-mail and a reason.
- Ownership changes **only** through `TransferService` (handover or forced transfer).
- Both parties are told; the transfer is recorded and logged.

**Non-goals**

- Changing the handover process.
- Choosing the owner of a listing the team creates from scratch (still the *Owner* field, for new
  listings only).

## 5. Requirements

| # | Requirement |
|---|---|
| R1 | The listing's edit screen has an **Ownership** tab for users with *Force Ownership Transfer*, showing the current owner (name, username, e-mail, link to the user) and any handover in progress (to whom, when it lapses). |
| R2 | The tab offers *New owner's account e-mail* and *Reason* (both required) and **Transfer ownership now**, with a confirmation that mentions unsaved changes on other tabs are not saved. |
| R3 | On success: the listing belongs to the new owner, both owners are e-mailed the reason, an open handover is cancelled, the new owner is removed from the maintainers, the transfer is recorded as *forced* and logged; the team member returns to the Extensions list with "The listing now belongs to *name*...", and the edit lock is released. |
| R4 | On failure (no reason, invalid or unusable address, rate limit, deleted listing, same owner) the team member returns to the edit screen with the error. |
| R5 | For an existing listing the *Owner* field is read-only (with the hint "The owner changes only through a handover..."). Saving the form records the live owner on the revision; approving a revision never changes the owner. |
| R6 | A deleted listing cannot be force-transferred. |

## 6. Solution

### 6.1 The Ownership tab (`View\Extension\HtmlView`, `tmpl/extension/edit.php`)

- `HtmlView::loadOwnership()` (new) returns the live owner (from `#__jed_extensions`, not from the
  revision), the open transfer (`TransferService::getOpenTransfer()`) and its recipient, or `null`
  when the tab is not offered (new listing, or no permission).
- The tab renders the facts and two inputs named `transfer_email` / `transfer_reason` (outside
  `jform`, so a normal Save ignores them), and a button whose small script validates both inputs,
  asks for confirmation and submits `extension.forceTransfer`.

### 6.2 Controller (`Controller\ExtensionController::forceTransfer()`)

- Permission `jed.transfer.force` (unchanged).
- Checks the reason **before** the address lookup, so a missing reason does not use up one of the
  team member's rate-limited lookups.
- On error: back to the edit screen (still checked out) with the message.
- On success: `COM_JED_TRANSFER_FORCED_MESSAGE_NAMED`, releases the edit lock, Extensions list.

### 6.3 Service (`Transfer\TransferService::force()`)

Refuses a deleted listing (`COM_JED_TRANSFER_ERROR_DELETED`), as `initiate()` already did.

### 6.4 The Owner field (`Model\ExtensionModel`)

- `getForm()`: for an existing listing, `owner` is read-only and carries a description pointing to
  the Ownership tab.
- `save()`: the revision's `owner` is taken from the live row, never from the form.
- `approve()`: `owner` is no longer copied from the revision to the live row.

## 7. User experience

Edit screen > **Ownership** tab:

```
Current owner         Jane Developer (jane, jane@example.com)
Handover in progress  To Bob Builder, lapses on 12 March 2027
                      A forced transfer calls this handover off; both of its parties are told.

Transfer ownership without the owner's confirmation
  For when the owner cannot be reached ... If the owner is active, ask them to hand the
  listing over from their dashboard instead ...
  New owner's account e-mail *  [                 ]
  Reason *                      [                 ]   Sent to both owners and recorded in the log.
  [Transfer ownership now]
```

Both owners receive "*Extension* has been reassigned" with the reason.

## 8. Permissions and security

- `jed.transfer.force` required for the tab and the task.
- The address lookup is logged and rate-limited exactly as for developers (10 per 24 hours).
- The new owner must have an activated, unblocked account.
- CSRF token checked (the tab submits the edit form, which carries it).

## 9. Data and migration

None. Listings whose owner was previously changed through the *Owner* field have no transfer
record; that history cannot be reconstructed.

## 10. Verification

| Check | Result |
|---|---|
| A forced transfer needs a reason | PASS |
| A blocked account cannot receive a listing | PASS |
| A deleted listing cannot be transferred | PASS |
| A forced transfer moves the owner and is recorded as `forced` with the reason | PASS |
| The new owner is no longer also a maintainer | PASS |
| Both owners are e-mailed | PASS |
| Approving a revision saved under another owner does not change the owner | PASS |
| Through the controller: no reason -> back to the edit form, nothing moves | PASS |
| Through the controller: the transfer reassigns the listing, names the new owner, releases the edit lock | PASS |
| Without *Force Ownership Transfer* nothing moves | PASS |

## 11. Acceptance criteria

1. As a team member with *Force Ownership Transfer*, open a listing, go to **Ownership**, enter an
   existing user's e-mail and a reason, confirm: the listing's owner changes, both users get the
   e-mail, the action log shows the forced transfer, and the new owner sees the listing on their
   dashboard.
2. Without the permission, the tab is not shown.
3. The *Owner* field on an existing listing cannot be changed.
4. A revision saved before a transfer and approved after it does not change the owner back.
