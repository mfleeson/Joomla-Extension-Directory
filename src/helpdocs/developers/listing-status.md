# Listing status, rejections and blocks

**My Dashboard > My Extensions** shows a status badge for each of your listings.

| Status | Meaning | Visible to the public? | What to do |
|---|---|---|---|
| **Awaiting approval** | Submitted; the JED team has not decided yet | No | Wait. You can keep improving it. |
| **Not approved** | Reviewed and turned down. The reviewer's notes are shown under the badge and were e-mailed to you. | No | Fix the problem and save the listing again - it goes back to *Awaiting approval*. |
| **Online** | Approved and public. A listing goes online automatically when it is first approved. | Yes | Keep it up to date. **Take offline** hides it. |
| **Offline** | Approved, but you (or another member of your organisation) took it offline | No | **Put online** shows it again. |
| **Blocked** | Taken offline by the JED team, with a public reason | A notice is shown instead of the listing | Fix the reason (see below), then open a ticket under *Unpublished Support*. |
| **Deleted** | Removed | No (the address answers "deleted") | Contact the JED team if you think this is a mistake. |

Only listings that are **approved and online** appear in lists, search and on your developer
profile.

## Taking a listing offline and back online

For an approved listing, the status cell in **My Dashboard > My Extensions** has a button:

- **Take offline** (on an online listing) - you are asked to confirm. The listing disappears from
  the directory and from search; its address answers "not found" to visitors. Nothing else changes:
  reviews, scores, tickets and your pending edits all stay.
- **Put online** (on an offline listing) - it is visible again straight away.

Every active member of the organisation that owns the listing can use the switch; each use is recorded for the JED team. When
you open your own offline listing on the site, a notice reminds you that only you and your
members of its organisation can see it, with a **Put online** button.

The switch is not available while a listing is awaiting approval or not approved. It is available
while a listing is **blocked**, but going online does not lift the block - visitors keep seeing the
block notice until the JED team lifts it.

## Rejections

When a submission (or an edit) is not accepted you receive an e-mail explaining the **reason code**,
and a message **"Not approved: *code* - *reason*"** with the reviewer's notes on the listing's ticket
in **My Tickets** (the ticket then shows *Awaiting User*). For a listing that has never been public,
the notes are also shown under its *Not approved* badge on your dashboard.

The listing is kept: correct the problem and save it again from your dashboard, and it is looked at
afresh - reply on the ticket to say what you changed. Rejecting an **edit** to an online listing
does not take the listing offline.

## Blocks

A block is the JED team's way of taking a listing offline **publicly, with a reason**. While a
listing is blocked:

- visitors see "**This listing is currently unavailable** - The JED team has taken this listing
  offline. It will be restored once the reason below has been resolved.", the reason title and
  code, and a link to the Knowledgebase article where there is one;
- any internal note the team wrote is **not** shown publicly;
- **you can still edit the listing** - the blocked page shows you an **Edit this listing**
  button;
- the block is lifted by the JED team, not by editing or by switching the listing online.

## Reason codes

The same codes are used for rejections and blocks. Each has a matching e-mail explaining what to
do; the Knowledgebase articles *Submission Error Codes* and *Unpublished Extensions Error Codes*
have the details.

| Code | Reason | Typical fix |
|---|---|---|
| **ER1** | `error_reporting(0)` found | Remove it; Joomla's Global Configuration controls error reporting |
| **NM1** | Install name does not match listing name | Make the manifest/language name and the listing title match |
| **NM2** | Extension-specific naming issue | Name it "*Extension* for *Parent Extension*" |
| **NM3** | Non-permitted words in name | Remove *component*, *module*, *plugin*, *extension* from the name |
| **TM2** | Use of the Joomla trademark | Use "*Name* for Joomla" or obtain trademark approval |
| **LK2** | Invalid download link | Point to the download or product page; follow the download-link rules |
| **ZP1** | Zip file issues | Re-package a valid, non-corrupt zip and upload it again |
| **LC1** | Licensing violation | GPL-compatible licence, no additional restrictions |
| **LC2** | Paid listing without licence link | Add a Licence URL |
| **LC3** | Licence link does not mention the extensions | Update your licence page |
| **LC4** | Invalid licence type | Use GNU/GPL or AGPL (LGPL for libraries only) |
| **US1** | Update server requirement not met | Implement the Joomla Update System and confirm it on the Links tab |
| **PE1** | Under investigation | Open a ticket under *Current Listing Support* |
| **VL1** | Listed on the Vulnerable Extension List | Release a fix and submit a fix notice - see [Security, VEL and abandonware](security-vel-and-abandonware.md) |
| **PV1** | No owner - the previous owner withdrew their account | Contact the JED team if you want to take the listing over |

After fixing anything that needs the team to look again, open a ticket and say so - the rejection
e-mails tell you which ticket type to use (*New Listing Support*, *Current Listing Support* or
*Unpublished Support*).
