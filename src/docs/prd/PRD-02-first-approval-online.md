# PRD-02: A listing goes online when it is first approved

| | |
|---|---|
| Status | Implemented |
| Component | com_jed (administrator model, mail templates) |
| Users affected | Developers, JED team |
| Related | PRD-03 (developer online/offline switch) |

## 1. Summary

A newly submitted listing was created **offline**, and approving it never changed that. The
developer was e-mailed "Your extension *X* is now listed" about a listing nobody could see, and the
JED team had to remember to **publish** every new listing after approving it. Now the **first
approval puts the listing online**. Approving later edits leaves the online/offline state alone,
and those approvals get their own, accurate e-mail.

## 2. Background: the four visibility switches

A listing's visibility is decided by four independent columns on `#__jed_extensions`
(`ListingAccess::forItem()`):

| Column | Owner | Meaning |
|---|---|---|
| `approved` | JED team | Passed moderation |
| `state` | Developer | Online (1) / offline (0) |
| `blocked` | JED team | Withdrawn with a public reason |
| `deleted` | JED team | Soft-deleted |

A visitor sees a listing only when it is approved, online, not blocked and not deleted. `approve()`
deliberately never copies `state` from a revision, because *state* is the developer's decision and a
moderation outcome must not override it.

## 3. The problem

- `NewextensionModel::createExtension()` creates the live row with `state = 0` (and the admin
  `ExtensionModel::createExtension()` does the same for listings created by the team).
- `ExtensionModel::approve()` unsets `state` from the data it copies, so after approval
  `approved = 1, state = 0`: the developer's dashboard shows **Offline** and the public listing
  answers **404**.
- `notifyDeveloperOfDecision()` sends `com_jed.listing_approved` ("... is now listed on {SITENAME}")
  for **every** approval - including an approved typo fix on a listing that is already listed, and
  including listings the developer had deliberately taken offline.
- The workaround ("approve, then Change Status > Publish") was undocumented in the product and easy
  to forget; each forgotten listing looked to the developer as if the JED had lost it.

## 4. Goals and non-goals

**Goals**

- The first approval of a listing makes it visible (unless blocked or deleted).
- After that, `state` remains the developer's: approving an edit never changes it.
- The e-mail matches what happened: "is now listed" for the first approval, "your changes have been
  approved" for later ones.

**Non-goals**

- Changing how `state` is set on creation (an unapproved listing is invisible anyway).
- Repairing historical data automatically (see §9).

## 5. Requirements

| # | Requirement |
|---|---|
| R1 | When `approve()` promotes a revision of a listing whose live row is **not yet approved**, it also sets `state = 1`. |
| R2 | When the live row is already approved, `state` is left unchanged. |
| R3 | Blocks and deletions are unaffected: a blocked listing stays blocked after approval. |
| R4 | First approval mails `com_jed.listing_approved`; an approval of an already listed listing mails the new `com_jed.listing_update_approved`. |
| R5 | Automatic approvals of trusted developers follow the same rules (they go through `approve()`). |

"First approval" includes a listing that was **rejected** before and is approved after the developer
fixed it - it has never been public.

## 6. Solution

### 6.1 `Model\ExtensionModel::approve()`

Before copying the revision onto the live row, the model records
`$firstApproval = (int) $liveTable->approved !== 1`. After binding the revision data it sets
`$liveTable->state = 1` when `$firstApproval` is true. `state` is still never copied **from the
revision**.

The same change (PRD-05) now also stops `owner` from being copied from a revision.

### 6.2 `notifyDeveloperOfDecision()`

Gained a `$firstApproval` parameter and chooses `com_jed.listing_approved` or
`com_jed.listing_update_approved`.

### 6.3 New mail template (`script.php`)

```
com_jed.listing_update_approved
Subject: Your changes to {EXTENSIONNAME} have been approved
Body:    The changes you submitted to {EXTENSIONNAME} have been reviewed and are now live on
         {SITENAME}. If the listing is offline it stays offline - you can put it back online at
         any time from your dashboard.
```

It is installed by the component's postflight like the other templates. `listing_approved`'s line
"You can take it offline again at any time from your dashboard" is now true (PRD-03).

## 7. User experience

- **Developer:** submit -> *Awaiting approval* -> approval e-mail "is now listed" -> dashboard
  **Online**, public page visible.
- **Developer, later edit:** "Your changes to *X* have been approved"; an offline listing stays
  offline.
- **JED team:** Approve is the only step. No separate Publish.

## 8. Permissions and security

Unchanged: approval requires `jed.approve`.

## 9. Data and migration

Listings approved **before** this fix may be sitting at `approved = 1, state = 0` although their
developer never chose to take them offline (there was no way to). They cannot be told apart
automatically from listings that were deliberately unpublished (for example imported from JED3 or
unpublished by the team). To review them:

```sql
SELECT id, name, owner, approved_time, modified
FROM #__jed_extensions
WHERE approved = 1 AND state = 0 AND deleted = 0 AND blocked = 0
ORDER BY approved_time DESC;
```

Listings approved since the JED4 launch with no later change are the likely candidates; publish them
with **Extensions > Change Status > Publish** after checking.

## 10. Verification

| Check | Result |
|---|---|
| A new listing is approved | PASS |
| The first approval puts the listing online (`state = 1`) | PASS |
| The first approval mails "Your extension Foo Gallery is now listed" | PASS |
| Approving an edit leaves the developer's offline state alone | PASS |
| An approved edit mails "Your changes to Foo Gallery Pro have been approved" | PASS |

## 11. Acceptance criteria

1. Submit a listing as a developer; approve it from its ticket. The listing is visible on the site
   and the dashboard shows **Online**, without any Publish step.
2. Take it offline from the dashboard (PRD-03), submit an edit, approve it: the listing stays
   offline and the developer receives "Your changes to ... have been approved".
3. Approve a new listing that is blocked: it remains blocked.

## 12. Risks

- A team member who *wants* a newly approved listing to stay offline must now unpublish it after
  approval. That is unusual and is the developer's decision anyway.
