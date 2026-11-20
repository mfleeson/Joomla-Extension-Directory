# Permissions and roles

The JED uses Joomla's access control. Permissions are set per user group in
**Components > JED > Options > Permissions** (and likewise for *Vulnerable Extension List* and
*JED Abandonware*). Four groups are expected: **Developer**, **JED team**, **VEL team** and
**Super Admin**; how they map to permissions is configured per installation.

## Global rights vs. rights over one listing

Two different kinds of rule apply:

- **Global rights** (this page): *may this group approve anything at all?* These are Joomla
  permissions.
- **Rights over one listing**: *may this person touch this extension?* Answered by the listing's
  **organisation**: its active members may edit it, its administrators may also hand it over -
  not by a permission. Membership of one organisation gives no rights over another's listings.

## JED (`com_jed`) permissions

| Permission | What it allows |
|---|---|
| **Configure ACL & Options** (`core.admin`) | Component options, permissions, *Preferences* button |
| **Access Administration Interface** (`core.manage`) | Use the JED in the administrator. On the site, it is what separates the JED team (sees **every** ticket, internal notes and internal messages) from developers (their own tickets only). Also needed to reply on tickets (**Send Message**, message templates), for the JED Checker tab and for internal notes. |
| **Create** (`core.create`) | Create listings/reviews/tickets in the administrator |
| **Delete** (`core.delete`) | Soft-delete and restore listings; delete reviews and developer responses |
| **Edit** (`core.edit`) | Edit listings and reviews in the administrator; *Recalculate Score*; the **Edit** button on public listing pages |
| **Edit State** (`core.edit.state`) | Publish/unpublish/archive/trash listings (the online/offline state) and reviews; approve/reject reviews and approve developer responses |
| **Edit Own** (`core.edit.own`) | Lets developers edit the listings of their organisations, and their own reviews. (Taking such an approved listing online/offline needs no permission - being an active member of the owning organisation is enough.) |
| **Approve Listings** (`jed.approve`) | Promote a pending revision to live, or reject it with a reason; switch the active revision |
| **Block Listings** (`jed.block`) | Block and unblock listings - deliberately separate from Edit State |
| **Force Ownership Transfer** (`jed.transfer.force`) | The listing's **Ownership** tab: move a listing to a new owner without the current owner's confirmation |
| **View Audit Results** (`jed.audit.view`) | See automated audit results (never public) |
| **Ban Users** (`jed.user.ban`) | Change user privileges, ban and unban users |

The **Tickets** list toolbar reads the separate *ticket* permission section (Create, Delete, Edit,
Edit State, Edit Own).

## VEL (`com_vel`) permissions

| Permission | What it allows |
|---|---|
| Manage, Create, Edit, Edit State, Delete | Normal editing of advisories and reports (Edit State moves draft <-> ready) |
| **Publish Advisory** (`vel.publish`) | Publish and retire advisories |
| **CVE Records** (`vel.cve`) | Generate CVE JSON, record/reserve CVE ids, submit to CVE Services |
| **Run Feeds** (`vel.feeds`) | Run the CNA and NVD feeds by hand, accept or ignore feed items |
| **Case Correspondence** (`vel.communicate`) | Read and write correspondence; see reporter identities and attachments |
| **JED Actions** (`vel.jed`) | Open a JED ticket about a listing, e.g. to request a block |

Suggested: the VEL team gets Manage, Create, Edit, Edit State, Case Correspondence, Run Feeds and
JED Actions; the VEL lead additionally Publish Advisory and CVE Records.

## Abandonware (`com_abandonware`) permissions

| Permission | What it allows |
|---|---|
| Manage / Edit | Open and work cases |
| **Contact Owner** | Write to a listing's owner about a case and start the grace period |
| **Mark as Abandoned** | Conclude that an extension is no longer maintained |
| **Resolve Case** | Close a case with an outcome, including dismissing a report as abuse |

## Per-user restrictions

Independently of groups, individual accounts can be restricted, banned or trusted in
**Components > JED > User Privileges** - see [User privileges](user-privileges.md).
