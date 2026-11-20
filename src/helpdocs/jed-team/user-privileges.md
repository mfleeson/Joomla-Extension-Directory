# User privileges, bans and trusted developers

**Components > JED > User Privileges** lets the JED team restrict, ban or trust individual
accounts without touching their Joomla account. Permission needed to change anything: **Ban Users**
(`jed.user.ban`).

## The defaults

**Everyone starts with every privilege and no ban.** A user only gets a record here once somebody
decides otherwise - an empty *Last decision* means nothing was ever changed for that person.

## The privileges

| Privilege | Column label | Controls |
|---|---|---|
| Submit a new listing | **Submit** | The *Submit an extension* wizard |
| Edit a listing | **Edit** | Saving the listing form on the site |
| Update-server integration | **Update XML** | Reading the listing's update server on the user's behalf |
| Write a review | **Review** | Writing and editing reviews |
| Report a listing or review | **Report** | VEL reports and abandonware reports |

Plus the two **trusted status** flags:

| Flag | Effect |
|---|---|
| **Listings auto-approved** | New submissions and edits by this user's listings are approved automatically (the revision records the owner as approver). Applied on the **owner's** trust - a member's edit to a listing whose organisation's primary contact is trusted is auto-approved too. |
| **Reviews auto-approved** | Reserved for reviews by this user. |

A **banned** user is never treated as trusted, whatever the flags say.

Independently of this page, a developer may have at most **3 listings awaiting approval** at a time.

## The list

Columns: user, **Status** (*Active*, *Banned* with its dates, *Ban not in force*, and a note if the
Joomla account itself is blocked), **Privileges** (the ones withdrawn), **Trusted status**, **Last
decision** (who, when, why) and ID.

- **Search** by name, username or e-mail.
- **Status** filter: *All users*, *Decided about*, *Banned right now*, *Trusted*, *Restricted*.
- Sort by name, username or most recently decided.

## Changing privileges or banning

1. Find the user and choose **Change privileges**.
2. In the dialog:
   - **Privileges** - untick what the user may no longer do.
   - **Trusted status** - tick *Listings auto-approved* and/or *Reviews auto-approved*.
   - **Ban** - tick **Banned**; optionally set **From** and **Until**. Leave both empty for a ban with
     no end date. A ban outside its window simply does not apply - no clean-up needed.
   - **Reason** - **required**. Recorded with your name and the time, and **shown to the user** if
     this is a ban.
3. **Save**.

### Lifting a ban

Choose **Lift ban** on the user's row. A default reason ("Ban lifted from the user privileges list")
is recorded.

## What the user sees

- A refused action names the reason, e.g. "Your account is not permitted to write reviews." or
  "Your account is currently suspended: *your reason*".
- The ticket screen's **Ticket Help** tab shows every ticket creator's privileges, with a link here.

## When to use what

| Situation | Suggested action |
|---|---|
| Repeated guideline-breaking reviews | Withdraw **Review** (the user can still do everything else) |
| Fake or abusive reports | Withdraw **Report** |
| Spam listings | Withdraw **Submit**, or ban |
| Abusive behaviour towards the team | Ban for a period, with a clear reason |
| Long-standing, reliable developer | **Listings auto-approved** |
| A VEL report marked as *spam* | Done automatically - the VEL withdraws **Report** from the reporter's account |

Blocking the **Joomla account** (Users > Manage) stops the person signing in at all - use it only
when a JED-level restriction is not enough.
