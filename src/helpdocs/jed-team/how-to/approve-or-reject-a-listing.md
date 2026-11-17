# How to approve or reject a listing

Applies to **new submissions** and to **edits** of existing listings - both arrive as a revision
waiting for a decision, and both open an **Extension** ticket.

Permission needed: **Approve Listings** (`jed.approve`).

## 1. Find the submission

Either:

- **Tickets** - open the ticket "New listing submitted: *name*" or "Listing *name* was edited by
  its developer"; or
- **Extensions** - look for the orange **Pending** icon in the list.

## 2. Check it

From the ticket:

1. The **Linked Extension** tab shows the listing's details and notes that a newer revision is
   pending; **Compare** opens the side-by-side comparison.
2. The **JED Checker** tab runs JED Checker against the listing's package - the one **uploaded**
   to the listing, or else the one at its (internal) download link. Choose **Run JED Checker**; the
   tab badge shows *errors/warnings*. If automated audits are enabled, the latest audit result is
   shown here too (with **View Audit Results** permission).
3. The **Ticket Help** tab shows who the developer is, their privileges, and their other listings
   and tickets.

From the Extensions list: click the **Pending** icon to open **Compare** - live on the left, the
pending revision on the right, differences highlighted.

Check the submission against the Knowledgebase rules (naming, licence, download link, update
server, backlinks, JED Checker errors...).

## 3a. Approve

- In the ticket: on the **Linked Extension** tab, choose **Approve**; or
- on the **Compare** page: choose **Approve** in the toolbar.

What happens:

- the revision is copied onto the live listing and recorded as approved by you;
- **a new listing goes online at once** - its first approval is what lists it. For an edit to a
  listing that is already approved, the online/offline state stays as the developer set it;
- the search index is refreshed and the decision is logged;
- the developer is e-mailed - "Your extension *name* is now listed" for a first approval, "Your
  changes to *name* have been approved" for an edit.

From a ticket you come back to the ticket. Reply if needed and set its **Status** to *Resolved* or
*Closed* (see [Ticket management](../ticket-management.md)).

## 3b. Reject

A rejection **must** carry a **reason code** from the shared list (ER1, NM1, NM2, NM3, TM2, LK2,
ZP1, LC1-LC4, US1, PE1, ...) and should carry **notes** telling the developer exactly what to
correct.

**From the ticket:** on the **Linked Extension** tab choose **Reject**. A small form opens:

1. **Reason** - pick the code (required).
2. **Notes for the developer** - what exactly has to be corrected.
3. Choose the red **Reject** button and confirm.

**From the Compare page:** below the comparison there is a **Reject this revision** panel. Pick the
**Reason**, write the **Notes**, then choose **Reject** in the toolbar and confirm. (The panel and the
button only appear when the right-hand revision is the one awaiting a decision - an older revision
can be approved, which restores it, but not rejected.)

What a rejection does:

- the revision is marked *not approved* with your reason and notes;
- for a listing that has **never been public**, the listing itself becomes *Not approved* - the
  developer sees your notes on their dashboard;
- for an edit to an **online** listing, only the edit is refused - the listing stays online
  unchanged;
- the developer is e-mailed using the reason code's own template (e.g. *Extension: NM1 - Install
  Name Doesn't Match Listing Name*), or the generic *"Your extension was not accepted"*;
- a message **"Not approved: *code* - *reason*"** with your notes is written on the listing's
  ticket, where the developer reads it in *My Tickets*, and the ticket is set to **Awaiting User**.

From a ticket you come back to the ticket afterwards. If you forgot the reason, you are returned
with "A rejection needs a reason code".

## After the developer fixes it

The developer saves the listing again (a new revision, a new ticket or an update on the existing
one) and usually says so on the ticket. Compare again and approve.

## Trusted developers

Developers marked **Listings auto-approved** in [User privileges](../user-privileges.md) are
approved automatically - including going online on first approval. The revision records the owner
as the approver, which is how you can tell nobody looked at it.
