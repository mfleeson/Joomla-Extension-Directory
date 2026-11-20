# PRD-09: Organisations own listings

| | |
|---|---|
| Status | Implemented (schema 4.1.0) |
| Component | com_jed (site + administrator), new `plg_user_jedorganisation`, com_vel (`JedBridge`), com_abandonware (`CaseService`), `plg_actionlog_jed` |
| Users affected | Developers, new registrants, JED team |
| Related | P1-03 (maintainers), P1-04 / PRD-05 (ownership transfer), P1-18 (privacy), 8.8 / 8.8.1 |

## 1. Summary

Today a listing belongs to **one user** (`#__jed_extensions.owner`) and each listing keeps its own
list of **maintainers** (`#__jed_extensions_maintainers`). Companies that publish many extensions
have to invite the same people to every listing, one by one, and lose access when the one owning
account leaves.

This PRD introduces the **organisation**: a named party with contact details and a unique code
(UID) that **owns** listings and has **members**. Everybody who is an active member of an
organisation can look after all of its listings; the organisation's **administrators** also manage
its membership and its listings' ownership. Per-listing maintainers are retired.

## 2. The request, and what this PRD changes about it

The request, as received:

> Each organisation has a title, contact details and a unique uid code and can have many
> users/maintainers. An organisation can "own" many extensions, so instead of storing maintainers
> to extension, we store extensions to organisation. A new user can create their own organisation
> on registration or select an organisation to request membership of, which will notify the
> organisation administrator of the request; an organisation administrator can add existing
> registered users to their organisation. An organisation administrator can disable accepting
> membership requests.

All of it is implemented. The analysis below found eight places where the request, taken
literally, would weaken something the JED already guarantees. Each is resolved as follows.

| # | Issue found | Decision |
|---|---|---|
| S1 | "Store extensions to organisation" suggests a mapping table. A listing has exactly **one** owner; a mapping table would allow two organisations to own one listing, with no rule for who decides. | Ownership is a column, `#__jed_extensions.organisation_id` (1:n), like the P1-23 relations. |
| S2 | An organisation is not a person. Transfers need a person to confirm, abandonware and VEL mail a person, trust (auto-approval, P1-05) and privacy erasure are per person, and the JED3 migration, profile and reports all read `owner`. Removing `owner` would break all of them. | Each organisation has a **primary contact** (`primary_user_id`), who must be an active administrator. `#__jed_extensions.owner` is kept and always **mirrors** the owning organisation's primary contact; only `OrganisationService` writes it. Permissions no longer read `owner` - they read membership. |
| S3 | Converting every maintainer into an organisation member silently **widens access**: somebody maintaining 1 of a company's 40 listings would get all 40. | Migration makes a maintainer an active member only if they maintained **every** listing of that owner. Anybody else becomes a **pending membership request** the administrator decides on (notified on their dashboard). Nobody gains access they did not have. |
| S4 | "An administrator can add existing registered users" without their agreement puts a person's name on an organisation they may not work for, and hands them edit rights they did not ask for (P1-03 item 4: "being named by somebody else is not enough"; GDPR: association with an organisation is personal data). | An administrator's *add* sends an **invitation** the user accepts on their dashboard. A component option (`organisation_invite_requires_acceptance`, default *Yes*) lets the JED team switch to direct adding. The JED team can always add directly from the backend. |
| S5 | Finding "an existing registered user" by e-mail is an account-existence oracle (8.8.1). | The lookup is the one already bounded for transfers: same rate limit, same attributable, hashed log (`TransferService::findRecipient()`). Usernames are not accepted. |
| S6 | Membership requests from freshly registered accounts can be used to spam organisation administrators. | Requests are only possible to organisations that accept them; a user may have at most **3** open requests; administrators can reject, and can switch requests off. |
| S7 | A UID that is a sequence (e.g. the row id) lets anybody walk every organisation and request membership of all of them. | The UID is random, immutable, server-generated: `ORG-XXXX-XXXX` (Crockford base-32, ~40 bits). |
| S8 | Organisations could be left without anybody able to run them, or listings without an accountable person. | The primary contact cannot leave, be removed or be demoted - they hand the role on first. The last active administrator of an organisation that owns listings cannot leave. |

One more change follows from S1/S2: **ownership transfers move a listing to another
organisation**, addressed by its UID. This also removes the e-mail oracle from the developer's
handover form (S5), because a UID says nothing about who holds an account.

## 3. Goals and non-goals

**Goals**

- Organisations with a title, contact details, a unique UID and a setting for membership requests.
- Two roles - *administrator* and *member* - and one primary contact per organisation.
- Listings are owned by organisations; every active member may look after all of them.
- New users can create an organisation or request membership of one **when registering**;
  existing users can do the same from *My Organisations*.
- Administrators are notified of requests and decide them; they invite existing users.
- The JED team manages organisations and memberships in the backend.
- Existing data is migrated without widening anybody's access (S3).
- Everything that used to ask "owner or maintainer?" asks "member of the owning organisation?".

**Non-goals**

- Per-listing restrictions inside an organisation (all members see all listings). If needed later:
  an optional `extension_ids` scope on a membership row.
- A public organisation profile page (the existing developer profile still works through `owner`).
- Self-service deletion of organisations (the JED team can delete an organisation that owns no
  listings).
- Dropping `#__jed_extensions_maintainers`: it is no longer read or written, but is kept for one
  release for rollback and audit, then dropped.

## 4. Requirements

| # | Requirement |
|---|---|
| R1 | An organisation has: title (required), alias, UID (generated, unique, immutable), contact name, contact e-mail (required), contact phone, website, address, country, description, *accept membership requests* (default on), state (active/disabled by JED team), primary contact, created/modified audit columns. |
| R2 | Memberships have a role (*admin*, *member*) and a state: *requested*, *invited*, *active*, *declined* (invitation refused), *rejected* (request refused). Only *active* grants anything. One row per organisation and user. |
| R3 | A listing is owned by exactly one organisation (`organisation_id`). `owner` equals that organisation's primary contact at all times. |
| R4 | An active member may: edit and submit listings of the organisation, switch them online/offline, answer reviews, run URL checks, raise "my extension" tickets. |
| R5 | An active administrator may additionally: edit the organisation, switch requests on/off, approve/reject requests, invite users (by e-mail, rate-limited), remove members, change roles, hand on the primary-contact role, start and cancel transfers of the organisation's listings. |
| R6 | Registration offers: *no organisation*, *create my organisation* (title + contact e-mail), or *request to join* (choose from organisations accepting requests, or enter a UID). |
| R7 | A membership request notifies every active administrator by e-mail (`com_jed.organisation_request`). Approving or rejecting notifies the requester. An invitation notifies the invitee (`com_jed.organisation_invitation`). |
| R8 | An administrator can switch off membership requests; new requests are then refused, open ones stay decidable. |
| R9 | At most 3 open requests per user. A rejected or declined row may be re-requested/re-invited. |
| R10 | The primary contact cannot leave or be removed or demoted; the last active administrator of an organisation with listings cannot leave. Changing the primary contact re-points `owner` on all the organisation's listings. |
| R11 | Transfers move a listing between organisations; the target is given by UID; the two primary contacts confirm as before. Forced transfers (JED team) also take a UID. |
| R12 | New listings are submitted for an organisation the submitter is an active member of; a user with no organisation gets a personal one created on first submission. |
| R13 | Backend: Organisations list (search, state filter, counts), edit screen (details, state, members with add/remove/role/primary), the listing edit screen shows the owning organisation. |
| R14 | Migration (4.1.0): every current owner gets a personal organisation they administer and are primary contact of; their listings move to it; maintainers are converted per S3. Idempotent. |
| R15 | Privacy: memberships and organisations administered are exported; on erasure memberships are deleted, the primary-contact role passes to another active administrator if there is one (the listings then stay unblocked), and a personal organisation's contact details are scrubbed. |
| R16 | Action log: organisation created, member added/removed, request approved/rejected, role changed, primary contact changed. |
| R17 | VEL vendor notification and abandonware owner contact go to the owning organisation's active members (previously owner + maintainers). Link-check notices and their tickets keep going to the primary contact (the `owner` mirror), so one person owns the follow-up. |

## 5. Data model (schema 4.1.0)

```sql
#__jed_organisations
  id, uid (UNIQUE, ORG-XXXX-XXXX), title, alias (UNIQUE), description,
  contact_name, contact_email, contact_phone, website, address, country,
  accept_requests (1), is_personal (0), state (1 active / 0 disabled),
  primary_user_id, created, created_by, modified, modified_by, checked_out, checked_out_time

#__jed_organisation_members
  PK (organisation_id, user_id), role ('admin'|'member'), state (0 requested, 1 active,
  2 invited, -1 declined, -2 rejected), request_message, requested_time, invited_by,
  invited_time, decided_by, decided_time, joined_time
  FK organisation_id -> #__jed_organisations ON DELETE CASCADE

#__jed_extensions.organisation_id          NULL, FK ON DELETE RESTRICT
#__jed_extensions_history.organisation_id  mirror (never promoted by approve(), like owner)
#__jed_extension_transfers.from_organisation_id / to_organisation_id
```

`RESTRICT` on the listing FK: an organisation that owns listings cannot be deleted by accident;
the JED team transfers the listings first.

## 6. Solution

### 6.1 Service layer (`Administrator\Organisation`)

- `OrganisationService` - the only writer of organisations, memberships and `owner`. Create,
  update, request, invite, accept/decline, approve/reject, remove, leave, change role, hand on
  primary contact, membership queries, `extensionContactUserIds()`, erasure handling.
- `OrganisationAccess` - the permission questions, cached per request:
  `isMemberOfExtensionOwner()`, `isAdminOfExtensionOwner()`, `memberCondition()` (SQL fragment).
- `MembershipState`, `OrganisationRole` (enums), `OrganisationUid` (generate/normalise/validate).
- `OrganisationMigration` - R14, run from `script.php` postflight; safe to run again.

### 6.2 Existing code rewired

| Before | After |
|---|---|
| `JedHelper::isOwner()` | active **administrator** of the owning organisation (transfers) |
| `JedHelper::isOwnerOrMaintainer()` | active **member** of the owning organisation |
| `JedHelper::getOwnedOrMaintainedCondition()` | `EXISTS` active membership of `a.organisation_id` |
| `ExtensionUtilities::storeMaintainers()` | removed; the listing forms carry an organisation picker (new listings) or show the owner organisation |
| Dashboard "maintainer invitations" | organisation invitations + requests awaiting the user's decision |
| `extension.respondToInvitation` task | `organisation.respond` |
| `TransferService` user -> user | organisation -> organisation (UID), primary contacts confirm |
| `JedBridge::listingContacts()`, `CaseService::mailOwner()`, `LinkedExtensions` | organisation members |
| Privacy export/removal, `ClaimService::CONTENT` | include memberships |

### 6.3 Site

- `view=organisations` - *My Organisations*: memberships, open invitations (accept/decline), open
  requests (cancel), *Create an organisation*, *Request to join* (list or UID).
- `view=organisation&id=` - for members: details, members; for administrators also the requests
  queue, invite form, role/primary/remove buttons, requests on/off; *Leave*.
- `view=organisation&layout=edit` - create/edit form.
- `OrganisationController` - every task checks the CSRF token, login and the role, and
  delegates to `OrganisationService`.

### 6.4 Registration (`plg_user_jedorganisation`)

Adds a *Your organisation* fieldset to `com_users.registration`. `onUserAfterSave` (new users only,
successful save) creates the organisation with the new user as administrator and primary contact,
or files the membership request. Validation (`onUserBeforeSave`) refuses a missing title/e-mail or
an unknown/closed organisation before the account is created, so a refused choice never leaves a
half-registered user.

### 6.5 Backend

Organisations submenu; list and edit screens; member management tasks (`core.edit`); listing edit
shows the organisation and the forced-transfer form takes a UID.

## 7. Permissions and security

- Every state change is a POST with a CSRF token (or a GET with a token for the dashboard links),
  checked in the controller, re-checked in the service against the database - never against
  values in the request.
- Roles are read from the database at the moment of the action; an administrator demoted in another
  tab cannot finish an action in this one.
- The service refuses: acting on an organisation that is disabled; a request to an organisation
  that does not accept them; more than 3 open requests; demoting/removing/leaving as primary
  contact; leaving as last administrator of an organisation with listings; inviting somebody who is
  already active.
- E-mail lookups for invitations share the transfer lookup rate limit and log (S5).
- The organisation contact e-mail is shown only to members and the JED team.

## 8. Migration and rollout

1. Run the update; the schema file adds the tables and columns.
2. `postflight` runs `OrganisationMigration` (also safe on a fresh install - it finds nothing).
3. Administrators see migrated maintainers as pending requests on *My Organisations* (S3).
4. After one release, drop `#__jed_extensions_maintainers`.

## 9. Acceptance criteria

1. Registering with *Create my organisation* creates an organisation whose only member is the new
   user, as administrator and primary contact.
2. Registering with *Request to join* an organisation that accepts requests files a request and
   mails every administrator; with one that does not, registration is refused with a message.
3. An administrator approves a request; the user can then edit every listing of the organisation.
4. An administrator switches requests off; a new request is refused.
5. An administrator invites an existing user by e-mail; the user accepts on *My Organisations* and
   gains access; declining grants nothing.
6. A member (not administrator) cannot approve, invite, remove or transfer.
7. The primary contact cannot leave; after handing the role on they can, and every listing's
   `owner` now names the new primary contact.
8. A maintainer of all of an owner's listings is an active member after migration; a maintainer of
   some of them is a pending request and has no access until it is approved.
9. A transfer to another organisation's UID moves `organisation_id` and `owner` after both primary
   contacts confirm.
10. VEL vendor notifications and abandonware contact mails reach the organisation's active members.
11. Privacy erasure of a primary contact with a co-administrator hands the role on and does not block
    the listings; without one the listings are blocked as before.

## 10. Risks and open questions

- **Access is broader inside an organisation** than with per-listing maintainers. Mitigated for
  existing data by S3; for new data it is the model asked for.
- **`owner` as a mirror** is denormalised; every write goes through `OrganisationService`, and
  `OrganisationService::syncOwner()` can be run to repair drift.
- Whether a public organisation page should replace the per-user developer profile is open.

## 11. Implementation notes

### Files

| Area | Files |
|---|---|
| Schema | `sql/install.mysql.utf8.sql`, `sql/updates/mysql/4.1.0.sql`, `sql/reset.sql` |
| Domain | `administrator/.../src/Organisation/` - `OrganisationService`, `OrganisationAccess`, `OrganisationMigration`, `MembershipState`, `OrganisationRole`, `OrganisationUid` |
| Installer | `script.php` - `migrateToOrganisations()` in postflight; five mail templates (`com_jed.organisation_request`, `_request_approved`, `_request_rejected`, `_invitation`, `_added`) |
| Transfers | `Transfer/TransferService` (`findOrganisation()`, org-aware `initiate()`/`force()`/`applyOwnership()`), site `TransferController`, admin `ExtensionController::forceTransfer()`, the Ownership tab |
| Access | site `JedHelper`, `LinkedExtensions`, VEL `JedBridge::listingContacts()`, abandonware `CaseService::mailOwner()`, admin `TicketModel`, `JedAccessHelper::countPendingListings()` |
| Listing forms | `forms/extension.xml` (admin), `forms/extensionform.xml` (site), new `Field/JedorganisationField` (admin + site subclass), `ExtensionUtilities::organisationForNewListing()`, `NewextensionModel`, `ExtensionformModel`, admin `ExtensionModel` |
| Site UI | `OrganisationController`, `OrganisationModel`, `OrganisationsModel`, views/tmpl `organisation` (default, edit) and `organisations`, `forms/organisation.xml`, `Router`, dashboard cards |
| Backend UI | `OrganisationsController` (incl. `migrate`), `OrganisationController`, `OrganisationsModel`, `OrganisationModel`, `OrganisationTable`, views/tmpl, `forms/organisation.xml`, `forms/filter_organisations.xml`, `jed.xml` submenu, `config.xml` (`organisation_invite_requires_acceptance`) |
| Registration | new `plugins/user/jedorganisation`, listed in `pkg_jed.xml` |
| Privacy | `PrivacyRemovalService`, `PrivacyExportService` (`jed_organisations`), `PrivacyDeterminations`, `ClaimService::CONTENT`, `plg_system_jedidentity::onUserAfterDelete()` |
| Action log | `JedActionLog::ORGANISATION_*`, `plg_actionlog_jed` |
| Help | `helpdocs/developers/organisations-and-ownership.md`, `helpdocs/jed-team/how-to/manage-organisations.md`, `change-owner.md` and the pages that linked to the old maintainer pages |

### Rollout checklist

1. Install the update package. On a site updated by copying files instead, use
   *System > Discover* to install **User - JED Organisation** and enable it; without it the
   registration form simply has no organisation step (everything else works).
2. Check *Components > JED > Organisations*: every previous owner should have a personal
   organisation. If the postflight step was skipped or timed out, press **Migrate owners** (super
   users only); it is idempotent and processes in batches.
3. Review the *Pending requests* filter: these are the former partial maintainers (S3).
4. Keep `#__jed_extensions_maintainers` for one release as the migration source, then drop it.

### Known limitations

- **Primary contact changed mid-transfer.** A pending transfer stores the user ids that must
  confirm. `TransferService::confirm()` re-checks that the confirming user is still an active
  administrator of their side's organisation, so a demoted or removed contact cannot complete it;
  a *new* primary contact cannot confirm an old link either - the JED team or an organisation
  administrator cancels the transfer and starts it again.
- **Link-check notices** go to the primary contact only (R17).
- **Untested runtime.** The code was syntax- and reference-checked without a PHP runtime; the
  acceptance criteria in section 9 must be run on a staging site before release.

