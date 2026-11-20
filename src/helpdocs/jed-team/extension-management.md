# Extension management

Listings are managed in **Components > JED > Extensions**.

## How listings are stored

Understanding two ideas makes everything else in this guide straightforward.

### 1. The live listing and its revisions

Each listing has a **live** record - what the public sees - and a series of **revisions** (the
history). Whenever anyone saves the listing form (a developer on the site, or you in the
administrator), a **new revision** is written and the live record is **not** changed. The revision
waits until someone with **Approve Listings** permission **approves** it; approval copies it onto
the live record.

Exceptions, which change the live record directly:

- block/unblock and delete/restore (they also write an inactive revision so the history records
  who did what), and the online/offline state;
- the organisation and owner, which change only through a handover or a forced transfer (see
  [Change the owner](how-to/change-owner.md)) - never through a revision;
- automatic version bumps from the developer's update server;
- additional categories, tags, screenshots and uploaded files, which are saved
  straight away when the form is saved.

### 2. How visibility works

Four independent switches decide what a visitor gets:

| Switch | Owned by | Set where |
|---|---|---|
| **approved** | JED team (moderation) | Approve / Reject |
| **state** (online/offline, *Status* column) | the developer (dashboard *Take offline* / *Put online*); set to online automatically by the listing's **first approval** | the developer's dashboard, or *Change Status* in the Extensions list |
| **blocked** | JED team | *Block* / *Unblock* on the edit screen, or the ticket action |
| **deleted** | JED team | *Delete* / *Restore* on the edit screen |

The result for a public visitor, in order of precedence:

1. **Deleted** - "This extension has been deleted" (HTTP 410), even for the owner.
2. **Never approved** - not found (404). The owner works on it through the dashboard.
3. **Blocked** - the public block notice with the reason title and code (HTTP 200, not indexed by
   search engines). The internal note is never shown.
4. **Offline** (state not published) - not found (404) for the public; members of the owning
   organisation can still see it.
5. Otherwise **visible**.

Blocking never changes *state*, and publishing never lifts a block. A developer therefore cannot
hide a block notice by going offline, and approving an old revision cannot undo a block.

The developer's dashboard translates these into six statuses: *Awaiting approval*, *Not approved*,
*Online*, *Offline*, *Blocked*, *Deleted* (see the developer guide
[Listing status](../developers/listing-status.md)).

## The Extensions list

| Column | Meaning |
|---|---|
| **Status** | The online/offline state toggle, plus an icon for the approval status |
| **Pending** | An orange refresh icon means a revision is **awaiting a decision** - one a developer (or a team member) saved that nobody has approved or rejected yet. Click it to compare that revision with the live listing and approve or reject it. A grey tick means *Up to date*. Blocks, unblocks, deletions and restores also write revisions, but they never make a listing *Pending*. |
| **History** | Opens the revision history in a pop-up |
| **Title** | Click to open the edit screen; the alias is shown underneath |
| **Category**, **Developer**, **Type** | |
| **Reviews** | Number of reviews |
| **Versions** | Number of revisions |
| **Last Updated**, **Date Added**, **ID** | |

### Searching and filtering

- **Search** - by title, or `id:123` for one listing.
- **Status** - published, unpublished, archived, trashed.
- **Video** - *Unrecognised video* (a video link that could not be converted - needs attention),
  *Video recognised*, *No video*.
- **Links** (from the periodic link check) - *Has a broken link*, *Left unattended* (failing so
  long the team was alerted), *Developer notified* (a link-check ticket is open), *All links
  working*.
- **Sort by** and **Display Number**.

### Toolbar

| Button | Does |
|---|---|
| **New** | Create a listing from the administrator (it still needs approving) |
| **Change Status > Publish / Unpublish / Archive / Trash** | Sets the **state** (online/offline) of the ticked listings. Developers can do this themselves for their approved listings; you rarely need to. |
| **Change Status > Duplicate**, **Check-in** | Standard Joomla actions |
| **Empty Trash** | Shown when the Status filter is *Trashed* - permanently deletes |
| **Tickets** / **Extensions** | Shortcuts |
| **Copy JED3 Data** | The old JED3 import screen (one-off migration tool) |
| **Options** | Component options and permissions |

> Prefer **Block** (with a reason) or **Delete** (soft delete) over Trash / Empty Trash for
> listings. Empty Trash removes data permanently.

## The edit screen

Click a listing title. The form loads the **most recent revision** (which may be newer than what
is live). Tabs:

| Tab | Contents |
|---|---|
| **Common** | Revision Id, Extension Id, Title, Alias, main Category, additional Categories, Tags, **Owner** (read-only for an existing listing - see the Ownership tab), Status |
| **Approval** | The recorded decision: Approved, Approved Time, Approved Notes, Approved Reason. Informational - use the Approve/Reject actions rather than editing these. |
| **Blocking** | **Block Reason** (required to block; shown publicly), **Internal Note** (team only), Blocked On/By, Deleted On/By |
| **Linked Extensions** | Add-on For, **Parent Confirmed** (team only), Free/Paid Version |
| **Info** | Intro, description, licence and licence URL, requires registration, type, extension types, Joomla versions |
| **Links** | All the listing's links, internal download URL and key, update system confirmation, update URL, developer URL/e-mail, changelog |
| **Media** | Logo, video, images (drag and drop area) |
| **Scores** | The computed scores and review count, and the **Popular** flag |
| **Organisation** | The organisation that owns the listing, read only here (see [Manage organisations](how-to/manage-organisations.md)) |
| **Files** | Uploaded packages (drag and drop area) |
| **Publishing** | Created/modified information and an **Internal Note** (team only) |
| **Ownership** | Only with **Force Ownership Transfer**: the owning organisation and its primary contact, any handover in progress, and the forced transfer - see [Change the owner](how-to/change-owner.md) |
| **Reviews** | The listing's reviews with links to edit them |

### Edit screen toolbar

| Button | Permission | Does |
|---|---|---|
| **Save**, **Save & Close** | Edit | Writes a **new revision** (pending - approve it afterwards) |
| **Block** / **Unblock** | Block Listings | Blocks with the reason chosen on the *Blocking* tab, or lifts the block |
| **Delete** | Delete | Soft-deletes the listing (see [Unpublish, block or delete](how-to/unpublish-block-or-delete.md)) |
| **Save & New**, **Save as Copy** | Create | Standard |
| **Recalculate Score** | Edit | Queues a score recalculation for this listing |
| **Close** | | Leave without saving |

A deleted listing opens read-only, with only **Back** and **Restore**.

> **The Status field on the edit form does not take a listing online or offline.** The state is
> not copied when a revision is approved. A listing goes online with its first approval; after that
> the developer switches it from their dashboard, or you use **Change Status** in the Extensions
> list.

## History and comparing revisions

- Click the **History** icon in the list for a pop-up of all revisions (date, editor, whether it is
  the active one) with an **activate** button per revision.
- Or open the full **History** page (`layout=historylist`): tick one or two revisions and choose
  **Compare selected**.

The **Compare** page shows the fields side by side - *Live* (or the first selected revision) on the
left, *History #n* on the right - with differing fields highlighted. With **Approve Listings**
permission, **Approve** promotes the right-hand revision to live. When the right-hand revision is
the one awaiting a decision, **Reject** is offered too, with a *Reject this revision* panel for the
reason and notes - see [Approve or reject a listing](how-to/approve-or-reject-a-listing.md).

*Activate* only marks which revision is the active working copy; it does **not** change the live
listing. To put an older revision live, compare it and approve it - see
[Restore an older version](how-to/restore-older-version.md).

Revisions are also written for blocks, unblocks, deletions and restores, so the history shows who
took those actions and when. They are recorded as inactive and are never "awaiting a decision".

Every revision saved from the listing form - by the developer or in the administrator - starts
**undecided**, whatever the *Approval* tab said when it was saved. That is what puts the listing in
the *Pending* column until someone approves or rejects it.

## Where decisions are recorded

- **User Actions Log** (Users > User Actions Log): approvals, rejections, blocks, unblocks,
  deletions, restores, developers taking listings online/offline, organisation membership changes, forced
  ownership transfers.
- The revision itself: who approved/rejected it, when, the reason code and notes.
- E-mails to the developer: approval (first approval: "is now listed"; later edits: "your changes
  have been approved") and rejection (the reason code's own template).
- The listing's ticket: a rejection writes "Not approved: *code*" with the notes there.

## Related guides

- [Approve or reject a listing](how-to/approve-or-reject-a-listing.md)
- [Unpublish, block or delete a listing](how-to/unpublish-block-or-delete.md)
- [Rename an extension](how-to/rename-an-extension.md)
- [Change the owner](how-to/change-owner.md)
- [Automation](automation.md) - link checks and update checks that feed this screen
