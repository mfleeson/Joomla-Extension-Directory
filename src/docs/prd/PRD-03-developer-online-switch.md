# PRD-03: Developers can take their listing offline and back online

| | |
|---|---|
| Status | Implemented |
| Component | com_jed (site: dashboard, listing page, controller, model), plg_actionlog_jed |
| Users affected | Developers (owners and maintainers), JED team |
| Related | PRD-02 |

## 1. Summary

The listing's online/offline switch (`state`) is the developer's, by design - but developers had no
way to use it. The approval e-mail told them they could take a listing offline "from your
dashboard"; the help had to tell them to open a ticket instead. The dashboard now has **Take
offline** / **Put online** buttons, and an offline listing's own page tells its owner it is offline
and lets them put it back online.

## 2. Background

The site controller task `extension.setOnlineState` and the site model method
`ExtensionModel::setOnlineState()` already existed, answering JSON for a script that was never
written. Nothing in the interface called them. The model also had gaps:

- it allowed switching listings that were not yet approved (meaningless, and after PRD-02 it would
  be overridden by the first approval);
- it did not tell Smart Search, so a listing taken offline stayed in search results until the next
  full reindex;
- it left no trace, so the JED team could not answer "why did my listing disappear?".

## 3. Goals and non-goals

**Goals**

- Owners and accepted maintainers can switch an **approved** listing online/offline from the
  dashboard, with no script required.
- The switch never touches a block: going online does not lift it; going offline is allowed while
  blocked.
- Search results and the action log follow the change.
- An owner looking at their offline listing understands that visitors cannot see it.

**Non-goals**

- Deleting listings from the dashboard (still a JED team action).
- Switching listings that are awaiting approval or rejected.

## 4. Requirements

| # | Requirement |
|---|---|
| R1 | *My Extensions* shows **Take offline** for an approved, online listing and **Put online** for an approved, offline listing (including blocked ones). Nothing for unapproved listings. |
| R2 | Taking a listing offline asks for confirmation. |
| R3 | Only the owner or an accepted maintainer may switch; others get "Only the owner or a maintainer of a listing can take it online or offline." |
| R4 | An unapproved or deleted listing cannot be switched ("A listing can only be taken online or offline once the JED team has approved it."). |
| R5 | The change refreshes the Smart Search entry and is recorded in the action log ("User X took the listing Y offline" / "put the listing Y online"). |
| R6 | After switching from the dashboard the user returns to the dashboard with a message. The JSON behaviour for `format=json` / AJAX callers is kept. |
| R7 | On an offline listing's own page, the owner/maintainer sees "This listing is offline. Only you and its maintainers can see this page; visitors get a 'not found'." with **Put online**. |

## 5. Solution

### 5.1 Model (`components/com_jed/src/Model/ExtensionModel.php::setOnlineState()`)

- Loads the listing; refuses deleted (404) and not-approved (400) listings.
- No-op when the value is unchanged.
- Updates `state` only - never `blocked`.
- Dispatches `onContentChangeState` with context `com_jed.extension` (the same event the backend's
  Publish/Unpublish sends), so *Smart Search - JED* refreshes the listing.
- Records `JedActionLog::EXTENSION_ONLINE` / `EXTENSION_OFFLINE`.

### 5.2 Controller (`components/com_jed/src/Controller/ExtensionController.php::setOnlineState()`)

Checks the CSRF token, login, and owner-or-maintainer; calls the model; then answers with JSON when
asked for it (`format=json` or an `X-Requested-With: XMLHttpRequest` header) and otherwise redirects
to the dashboard with a success or error message.

### 5.3 Dashboard (`components/com_jed/tmpl/dashboard/default.php`)

In the *Status* cell of *My Extensions*, under the status badge, a small POST form per approved
listing with the token, `extension_id`, `online` (the opposite of the current state) and one button.

### 5.4 Listing page (`components/com_jed/tmpl/extension/default.php`)

When `state != 1` and the current user owns or maintains the listing, a notice with a **Put online**
form at the top of the page. (Offline listings are only reachable by them - `ListingAccess`.)

### 5.5 Action log (`JedActionLog`, `plugins/actionlog/jed`)

New actions `extension.online` and `extension.offline` with messages
`PLG_ACTIONLOG_JED_EXTENSION_ONLINE` / `_OFFLINE`.

### 5.6 Language (site `com_jed.ini`)

`COM_JED_ONLINE_STATE_*`: TAKE_OFFLINE, PUT_ONLINE, CONFIRM_OFFLINE, NOW_ONLINE, NOW_OFFLINE,
NOT_APPROVED, NOT_YOURS, OFFLINE_NOTICE.

## 6. User experience

*My Dashboard > My Extensions*, Status column:

```
[Online]              [Offline]                [Blocked]
(Take offline)        (Put online)             (Take offline | Put online)
```

Take offline -> "Take this listing offline? It disappears from the directory and from search until
you put it back online. Nothing else changes." -> "The listing is now offline. Only you and its
maintainers can see it, and you can put it back online at any time."

## 7. Permissions and security

- Owner or **accepted** maintainer only (invited-but-not-accepted maintainers cannot).
- POST with CSRF token.
- The block stays the JED team's: `blocked` is never written.

## 8. Data and migration

None.

## 9. Verification

| Check | Result |
|---|---|
| A listing awaiting approval cannot be switched | PASS |
| A deleted listing cannot be switched | PASS |
| An approved listing can be taken offline | PASS |
| Putting a blocked listing online does not lift the block | PASS |
| Take offline from the dashboard form works and returns to the dashboard | PASS |
| The developer is told what happened | PASS |
| An accepted maintainer can put it back online | PASS |
| Somebody else cannot (with the new message) | PASS |
| `format=json` answers with JSON | PASS |

## 10. Acceptance criteria

1. As an owner, *Take offline* on an online listing: the listing disappears from lists and search;
   the dashboard shows **Offline**; the public URL answers 404; the owner's own view shows the
   offline notice.
2. *Put online* restores it.
3. A blocked listing taken online still shows the block notice publicly.
4. The action log shows who switched it.

## 11. Risks

- Developers can now hide a listing without the JED team noticing; the action log entry is the
  record. This is the intended ownership of `state`.
