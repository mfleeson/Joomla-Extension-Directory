# Ticket management

The ticket system is the JED team's single work queue. Tickets are opened by users and developers
on the site, and automatically by the JED itself (submissions, edits, reviews, responses, link
checks, abandonware cases, VEL advisories).

## Every ticket has

| Field | Values |
|---|---|
| **Subject** | |
| **Type** (category) | *Unknown, Extension, Review, Joomla Site Issue, New Listing Support, Current Listing Support, Site Technical Issues, Unpublished Support, Reported Review, Reported Extension* |
| **Status** - the progress of the work | **New**, **Awaiting User**, **Awaiting JED**, **Resolved**, **Closed**, **Updated** |
| **Origin** | *Registered User* or *Joomla! Team* |
| **Allocated Group** | *Any, Team Leadership, Listing Specialist, Review Specialist, Support Specialist* |
| **Allocated To** | A team member |
| **Linked item** | What the ticket is about: an extension, review, developer response, link check, abandonware case, VEL advisory - or nothing (*Other*) |
| **Messages** | The conversation - incoming (from the user) and outgoing (from the team) |
| **Internal notes** | The team's own notes, never shown to the user |

Reports filed from a listing are pre-set to *Reported Extension* and the *Listing Specialist*
group; reports about a review to *Reported Review* and the *Review Specialist* group. Other new
tickets arrive unassigned.

When a ticket is created, the user receives the *Confirmation of Submission* e-mail and the same
text is stored as the first outgoing message.

## The Tickets list

**Components > JED > Tickets**. Columns: type, subject, created on/by, status, allocated group,
allocated to, linked item type, ID.

- **Search** - by subject or type; `id:123` for a ticket number.
- Filters: **state**, **Origin**, **Type**.
- Sort by any column.

The **JED - Open Tickets** administrator module shows the most recent open tickets on the JED
dashboard (configure how many; default 20).

## Working a ticket

Click the subject. At the top is the header: **Subject**, **Category**, **Ticket Status**,
**Origin**, and **Assigned** (group and person) - change these directly.

### Ticket tab

- **Message History** - every message, newest open; outgoing and incoming are styled differently.
- **Message Templates** - pick a canned reply to start from (see below).
- **Compose Message** - subject and message; **Send Message** e-mails it to the person who opened
  the ticket, stores it as an outgoing message, saves the **Status** shown in the header if you (or
  the template) changed it, and brings you back to the ticket with "The message has been sent and
  added to the ticket." If the e-mail could not be sent, the message is still stored and a warning
  names the mail error.
- **Created By** - who opened the ticket and when.
- **Linked Item Type** - a reminder that the linked item and its actions are on their own tab.
- **Internal notes** (team only): earlier notes, and **Add Note** with a **Summary** (required) and
  the **Note**, saved with the save button next to *Add Note*.

### Linked *item* tab

Shows the item the ticket is about and the **actions** you can take on it (depending on your
permissions):

| Ticket type | Shows | Actions |
|---|---|---|
| Extension | The listing; whether a newer revision is pending, with a **Compare** link | **Approve**, **Reject** (asks for a reason and notes) |
| Review | Title, author, scores and comments, overall score, body | **Approve**, **Reject** (unpublish), **Delete** |
| Developer Response | Review title, reviewer, response, date | **Approve**, **Delete** |
| Link Check | The listing | none - closes itself when the link recovers |
| Abandonware | The case with its signals | **Open Case** (in the Abandonware component) |
| Vulnerability | The advisory's public details | **Block Listing** (VL1) / **Unblock Listing** |
| Other | nothing | none |

Actions act on the **real item** (the listing, the review...), not on the ticket. An action that
needs input - **Reject** - opens a small form first (reason and notes); the others ask for
confirmation where it matters. After the action you come back to the ticket, with a message saying
what was done.

A rejection also writes its reason and notes on the ticket and sets it to *Awaiting User*. For the
other actions, remember to update the ticket's status yourself.

### JED Checker tab

Shown when the ticket resolves to a listing (extension, review, link-check or abandonware tickets).
**Run JED Checker** runs the JED Checker rules against the listing's package - the one uploaded to
the listing, else the one at its internal download URL, else its public download link. The tab
badge shows *errors/warnings* (red if there are errors). Each rule lists its findings or "Nothing
found"; findings show file paths from the package name down (e.g. `mycomponent_1.2.zip/site/foo.php`).
A rule that could not run on the package is marked with a grey **!** and says why. The other rules'
results still count.

The **JED Checker** component must be installed **and enabled** on the site, in a version that has
its programming interface (`CheckerApi`). If it is not usable, the tab says why - *not installed*,
*installed but disabled* (enable it in **System > Manage > Extensions**), or *version X has no
programming interface* (update JED Checker) - and **Run JED Checker** is not shown. The installed
version is shown on the tab. If JED Checker cannot read a package (not an archive, damaged), the tab
shows its reason.

With **View Audit Results** permission, the latest **JED Automated Audit** for the listing
(static analysis and security review) is shown here too.

### Ticket Help tab

Who opened the ticket and what else the JED knows about them: name (with a *Blocked* badge if the
Joomla account is blocked), username, e-mail, registration and last visit; their **privileges**
(with a link to User Privileges); their **listings** with version and status; and their **other
tickets**.

### Publishing tab

Created on, modified by/on, ID and similar record details.

### Toolbar

**Save**, **Save & Close**, **Save & New**, **Save as Copy**, **Close**. Save the ticket after
changing its status, category or assignment.

## Message templates (canned replies)

The **Message Templates** list offers every com_jed mail template marked as a canned reply - one
for each listing reason code (ER1, NM1, NM2, NM3, TM2, LK2, ZP1, LC1-LC4, US1, PE1), plus *JED
Checker Errors*, *Hidden Files or Folders*, *Plugin Install Name*, *Backlink Issues*, *Listed on
VEL*, *Broken Links*, *Misdirected Support Request* and *Not Enough Detail*.

Picking one fills the message **subject** and **text**, and for most of them also sets the
**Status** drop-down to *Awaiting User*. Edit the text (replace placeholders such as *[XXX]* or
*[Your Name]*), then **Send Message** - the status is saved together with the message.

Templates are edited in **System > Mail Templates** - see [Mail templates](mail-templates.md).

## Typical flow

1. Assign yourself (**Assigned**), or the right group, and **Save**.
2. Review the linked item, run JED Checker if relevant, read the Ticket Help.
3. Act on the item (Approve / Reject / Block...).
4. Reply: pick a template or write a message, set the **Status** (*Awaiting User* if you asked them
   something, *Resolved* or *Closed* when done), and **Send Message**.
5. **Close** - or **Save & Close** if you changed the category or the assignment.

> **Send Message** saves the message and the **Status**. Changes to the other header fields
> (category, origin, assignment) are saved with **Save**.

## Replying from the site

Team members with **Access Administration Interface** on com_jed also see **every** ticket in the
site's *My Tickets* and can reply there; their replies can be marked **Internal** (visible to the
JED team only).

## Good practice

- Keep one conversation per ticket; ask users to reply on the same ticket.
- Record anything the next team member needs in an **internal note**, not in a message.
- Use *Misdirected Support Request* for people asking for help with an extension - the JED team
  does not provide extension support.
- Security details never belong in a JED ticket: refer reporters to the VEL form.

See the [Ticket response playbook](ticket-response-playbook.md) for what to do with each kind of
ticket.
