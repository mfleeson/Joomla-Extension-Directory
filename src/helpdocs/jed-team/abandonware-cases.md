# Abandonware cases

The **JED Abandonware** component turns signs that an extension is no longer maintained into a
careful, recorded decision. It lives in **Components > JED Abandonware** with two screens:
**Cases** and **Reports**.

The principle: **an extension is never marked abandoned without first writing to its developer
and waiting out a grace period.** An extension with no release for three years may simply be
finished.

## Where cases come from

| Source ("Raised by") | How |
|---|---|
| **Public report** | A signed-in user files the *Report an Abandoned Extension* form |
| **Dead links** | The periodic link check flagged the listing as left unattended (links failing long after the developer was told) |
| **Update server failing** | The listing's update server has been failing for longer than the tolerance (default 60 days) |
| **Long inactivity** | Nothing changed on the listing for a very long time (default 1095 days) - **off** by default |

Signals are collected by the scheduled task *JED - Collect Abandonware Signals* (daily), or on
demand with **Collect signals now** on the Cases list. There is **at most one open case per
listing**: further signals and reports about the same listing are added to the existing case.
A case can also concern an extension the JED never listed (from a public report).

Each new case opens one **Abandonware** ticket in the JED ticket queue ("Possible abandonware:
*name*") with an **Open Case** action.

## Case statuses

| Status | Meaning | Can move to |
|---|---|---|
| **Received** | New, nobody looked yet | Being reviewed, Owner contacted, Resolved, Dismissed |
| **Being reviewed** | Someone is assessing it | Owner contacted, Resolved, Dismissed |
| **Owner contacted** | The owner was written to; grace period running | Grace period expired, Abandoned, Resolved, Dismissed |
| **Grace period expired** | No answer within the grace period (set automatically by the daily task) | Abandoned, Resolved, Dismissed |
| **Abandoned** | Concluded: no longer maintained | Resolved, Dismissed |
| **Resolved** / **Dismissed** | Closed | - |

## The Cases list

Columns: extension (with *Not listed in the JED* or *Listing #id*, and *On the public list* badge),
signals, owner contacted (with *Grace period until / ended*), assigned to, status.

- **Search** by extension, developer or URL; `ID:` prefix for a case id.
- Filters: **Status** (*Open cases*, *Awaiting owner contact*, *Closed cases*, or a single status),
  **Raised by**, **Assignee** (*Anyone*, *Unassigned*, a person).
- Sort: newest/oldest first, extension name, **grace period ending soonest**, status.

## Working a case

Open a case. You see the **Signals** (each source with its details, e.g. "The Download link
(...) has failed 7 consecutive checks since ..."), **Failing links** evidence, the **Public reports**
(with reporter and consent status), the case facts (raised by, ticket, listing last changed, update
server error, marked, resolved), and the **Workflow** panel.

Editable fields: **Extension name**, **Last known version**, **Extension URL**, **Developer**,
**Linked listing ID** (link the case to a JED listing - this ties it to the listing's owner, link
checks and update-server state; 0 for something the JED does not list), **Identity key**,
**Assigned to** and **Internal notes** (never public). **Save** / **Save & Close**.

### 0. Take the case

**Take this case** assigns it to you.

### 1. Contact the owner

Permission: **Contact Owner**.

- Set the **Grace period (days)** (default from the options, 30) and optionally a **Note about the
  contact attempt**.
- Choose **Write to the owner and start the grace period**. The listing's owner receives "Is
  *name* still maintained?", explaining what prompted the enquiry and the date by which to reply or
  update the listing. The status becomes *Owner contacted*.
- **Write again and restart the grace period** repeats this.
- If the case is **not linked to a listing** there is no owner to write to automatically: contact the
  developer yourself and record what you did in the note - that still counts as the contact
  attempt.

### 2. Mark as abandoned

Permission: **Mark as Abandoned**. Only possible once a **contact attempt is recorded** and the case
is in *Owner contacted* or *Grace period expired*.

- Decide whether to tick **Show on the public abandoned list** - a public statement about somebody's
  product. Leave it off to mark the case internally only. (The default comes from the option
  *Publish marked cases by default*, off by default.)
- Choose **Mark as abandoned** and confirm.

Marking does **not** block the listing or change its visibility in the directory. If the listing
should also be withdrawn, block it separately with a reason (see
[Unpublish, block or delete](how-to/unpublish-block-or-delete.md)).

### 3. Close the case

Permission: **Resolve Case**. Choose an **Outcome** and add a **Closing note**, then **Close this
case**:

| Outcome | Use when |
|---|---|
| **Developer responded** | The owner answered |
| **Still maintained** | Evidence shows it is maintained after all |
| **Taken over by a new maintainer** | Someone adopted it (see [Change the owner](how-to/change-owner.md)) |
| **Duplicate of another case** | |
| **Listing removed** | The listing was deleted |
| **Abusive report** | The report was made in bad faith |

Closing a case **removes it from the public list** whatever it said before, closes its ticket, and
frees the listing so that a future signal opens a fresh case.

Cases close **automatically** when the listing changes hands through a completed ownership
transfer ("Closed automatically: the listing has changed hands").

## The Reports list

Every public report (and those imported from JED3), with the extension, reporter (and how many
reports this account has filed), the reason given, the case it belongs to and its status.

- **Search** by extension, developer, reporter name or e-mail.
- **Origin** filter: *Filed here*, *Imported from JED3*, *No consent recorded*.

Reporters are limited to 5 reports per 24 hours and cannot report the same extension again while
their earlier report's case is open. Reporter identities are never shown publicly.

## Options

**Components > JED Abandonware > Options**:

| Option | Default | Meaning |
|---|---|---|
| Grace period (days) | 30 | How long a developer has to answer |
| Contact owners automatically | off | Reserved - contacting is deliberately a human step |
| Publish marked cases by default | off | Whether *Mark as abandoned* also publishes by default |
| Dead links signal | on | Open a case for listings flagged as unattended by the link check |
| Failing update server signal / tolerance | on / 60 days | |
| Long inactivity signal / threshold | off / 1095 days | The weakest signal - choose the threshold before switching it on |
| Signals per scan | 50 | Listings each signal considers per run |

The contact e-mail is the mail template *Abandonware: Contact the Developer* (System > Mail
Templates).
