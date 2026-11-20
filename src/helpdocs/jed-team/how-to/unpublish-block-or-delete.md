# How to unpublish, block or delete a listing

There are three ways to take a listing off the public site. Choose the one that says what you
mean.

| Action | Use when | What visitors see | Developer can... | Reversible by |
|---|---|---|---|---|
| **Unpublish** (state offline) | Normally the developer's own choice - they use *Take offline* on their dashboard. Use it yourself only on their behalf. | Not found (404) | still see and edit it, and put it back online | **Publish** (or the developer's *Put online*) |
| **Block** | The listing breaks a rule or must be withdrawn for a reason the public should know (licensing, security advisory, broken links, investigation...) | "This listing is currently unavailable", the reason and a Knowledgebase link (200, not indexed) | still edit it to fix the cause | **Unblock** |
| **Delete** (soft delete) | The listing should be gone for good (duplicate, withdrawn, spam) | "This extension has been deleted" (410) | nothing - it is read-only | **Restore** |

**Block rather than unpublish** whenever the JED team is the one taking the listing down: a block
carries a public reason, stays in force whatever the developer does with the online switch, and
keeps the block history.

## Unpublish (take offline)

Developers take their own approved listings offline and back online from **My Dashboard > My
Extensions** (*Take offline* / *Put online*); members of the owning organisation can do this, and it is
recorded in the action log. So when a developer asks, point them to the dashboard.

To do it on their behalf - permission **Edit State**:

1. **Components > JED > Extensions**, tick the listing.
2. **Change Status > Unpublish**.

To put it back: **Change Status > Publish**. (The *Status* field on the edit form does not do
this - see [Extension management](../extension-management.md#the-edit-screen).)

You do **not** need to publish a newly approved listing: its first approval puts it online.

## Block

Permission: **Block Listings** (`jed.block`).

1. Open the listing (click its title).
2. On the **Blocking** tab choose the **Block Reason**. Its title and code are shown to the public.
3. Optionally add an **Internal Note** - visible to the JED team only, never public.
4. Choose **Block** in the toolbar.

"The extension has been blocked." The block, with reason and note, is recorded as a revision and in
the action log, and the listing is removed from search.

Blocking does **not** e-mail the developer. A blocked listing can still be taken offline or put
online by its developer; that never lifts the block. Tell them through their ticket - the message templates
include one per reason code (e.g. *Extension - Broken Links*, *Extension - Listed on VEL*) - or
open a ticket for them.

For a **security advisory**, the Vulnerability ticket has a one-click **Block Listing** action that
blocks with reason **VL1** - see [Handle a security advisory](handle-vel-ticket.md).

### Unblock

Open the listing and choose **Unblock** (or **Unblock Listing** on a Vulnerability ticket). The
listing becomes visible again if it is approved and online. The reason fields are kept for the
record.

## Delete (soft delete) and restore

Permission: **Delete**.

1. Open the listing.
2. Choose **Delete** in the toolbar.

"The extension has been deleted. It is no longer reachable on the public site." The record, its
uploads and history are kept; any open ownership handover is cancelled (both parties are told).
The listing opens read-only from now on.

To undo: open the listing and choose **Restore**.

> Do not use *Trash* and *Empty Trash* for listings you only want to remove from the public site -
> *Empty Trash* deletes the data permanently.

## Block reason codes

The list of reasons lives in the database table `#__jed_block_reasons`, seeded on installation:

| Code | Public title |
|---|---|
| ER1 | error_reporting(0) found |
| NM1 | Install name does not match listing name |
| NM2 | Extension-specific naming issue |
| NM3 | Non-permitted words in name |
| LK2 | Invalid download link |
| ZP1 | Zip file issues |
| TM2 | Use of the Joomla trademark |
| LC1 | Licensing violation |
| LC2 | Paid listing without licence link |
| LC3 | Licence link does not mention the extensions |
| LC4 | Invalid licence type |
| US1 | Update server requirement not met |
| PE1 | Under investigation |
| PV1 | No owner - the previous owner withdrew their account (applied automatically when an owner's account is erased under a privacy request) |
| VL1 | Listed on the Vulnerable Extension List |

Each code can point to a Knowledgebase article (shown as "Read more about this reason") and to the
mail template used when a **rejection** uses that code. Reasons can be added or retired in that
table without a software release.
