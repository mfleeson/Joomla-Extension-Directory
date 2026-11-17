# JED team: overview

This part of the help is for the people who run the directory: the JED team (listing, review and
support specialists, team leadership), the VEL team, and the site administrators who keep the
installation going.

## Where things are in the administrator

| Menu | What it is for |
|---|---|
| **Components > JED** | The JED dashboard (place modules such as *JED - Open Tickets* on it) |
| **Components > JED > Extensions** | All listings: moderation, editing, history, blocking, deletion - see [Extension management](extension-management.md) |
| **Components > JED > Categories** | The JED category tree (Joomla's category manager for `com_jed`) |
| **Components > JED > Reviews** | All reviews and developer responses - see [Review moderation](review-moderation.md) |
| **Components > JED > Tickets** | The support and workflow ticket queue - see [Ticket management](ticket-management.md) |
| **Components > JED > User Privileges** | Per-user restrictions, bans and trusted status - see [User privileges](user-privileges.md) |
| **Components > JED Abandonware > Cases / Reports** | Abandonware cases and the public reports behind them - see [Abandonware cases](abandonware-cases.md) |
| **Components > Vulnerable Extension List** | The VEL team's workspace - see [VEL team guide](vel-team-guide.md) |
| **System > Mail Templates** | Canned replies and notification e-mails - see [Mail templates](mail-templates.md) |
| **System > Scheduled Tasks** | Link checks, update checks, the job queue and other automation - see [Automation](automation.md) |
| **Users > User Actions Log** | Who approved, rejected, blocked, deleted, transferred... |

The **Extensions** list toolbar also has shortcut buttons to **Tickets** and **Extensions**.

## How the work flows

Almost everything that needs a person arrives as a **ticket**:

| Event | Ticket type | Your decision |
|---|---|---|
| A developer submits a new listing | Extension | Approve or reject the listing |
| A developer edits a listing | Extension | Approve or reject the revision |
| Someone writes a review | Review | Approve (publish), reject (unpublish) or delete |
| A developer responds to a review | Developer Response | Approve (publish) or delete |
| A user reports a listing or a review | Extension / Review, category *Reported Extension* / *Reported Review* | Investigate and act |
| A user opens a support ticket | Other | Reply |
| A listing's links stop working (after the developer was told) | Link Check | Nothing to approve; the ticket closes itself when the link recovers |
| An abandonware case opens | Abandonware | Work the case in the Abandonware component |
| The VEL publishes an advisory naming a listing | Vulnerability | Block the listing (VL1) or confirm the fixed version is listed |

See the [Ticket response playbook](ticket-response-playbook.md) for what to do with each.

## A suggested daily routine

1. **Tickets** - filter by *Status: New* and your **Allocated Group**; work the oldest first.
2. **Extensions** - look for the orange **Pending** icon (a revision waiting for a decision) and
   compare/approve anything not already handled through a ticket.
3. **Reviews** - filter by status *Unpublished* for reviews waiting for moderation.
4. **Abandonware > Cases** - check *Awaiting owner contact* and cases whose grace period has
   expired.
5. Check **Scheduled Tasks** ran (link check, update check, queue worker) - see
   [Automation](automation.md).

## Principles worth remembering

- **Every listing change is a revision.** Neither a developer nor you (through the admin edit form)
  changes the live listing directly - a revision must be **approved**. Version bumps found on a
  developer's update server are the exception: they are applied directly.
- **Four separate switches decide visibility** (see [Extension management](extension-management.md#2-how-visibility-works)):
  *approved* (JED team), *state* (online/offline - the developer's, switched from their dashboard;
  a listing's first approval puts it online), *blocked* (JED team) and *deleted*. Blocking never
  touches the online/offline state, and vice versa.
- **Always give a reason.** Rejections and blocks require a reason code from the shared list;
  forced ownership transfers, privilege changes and bans require a written reason. The developer or
  user is told.
- **Nothing internal leaks.** Internal notes on tickets, block notes, audit results and VEL
  correspondence are never shown publicly.

## How-to guides

- [Approve or reject a listing](how-to/approve-or-reject-a-listing.md)
- [Rename an extension](how-to/rename-an-extension.md)
- [Unpublish, block or delete a listing](how-to/unpublish-block-or-delete.md)
- [Change the owner of a listing](how-to/change-owner.md)
- [Manage maintainers](how-to/manage-maintainers.md)
- [Link an add-on or a free/paid version](how-to/linked-extensions.md)
- [Change a listing's category](how-to/change-category.md)
- [Restore an older version of a listing](how-to/restore-older-version.md)
- [Recalculate an extension's score](how-to/recalculate-score.md)
- [Handle a security advisory on a listing](how-to/handle-vel-ticket.md)
