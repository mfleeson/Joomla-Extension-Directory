# Ticket response playbook

What to do with each kind of ticket. Each entry: how to recognise it, what to check, what to do,
and the canned reply to use.

## New listing submitted

*Type Extension - "New listing submitted: name"*

1. **JED Checker** tab: run it. Errors mean a rejection (template *Extension - JED Checker Errors*).
2. Check against *Submission Requirements Made Easy*: name matches install name and naming rules
   (NM1/NM2/NM3/TM2), licence (LC1-LC4), download link (LK2), zip (ZP1), update server (US1),
   hidden files, backlinks, `error_reporting(0)` (ER1).
3. **All good** - **Approve** on the Linked Extension tab. The listing goes online and the developer
   is told. Set the ticket to *Resolved*.
4. **Problem** - **Reject** with the matching reason code and notes saying exactly what to correct.
   The developer gets the code's e-mail, your notes appear on the ticket, and the ticket goes to
   *Awaiting User*. See [Approve or reject a listing](how-to/approve-or-reject-a-listing.md#3b-reject).
   For something that is not a reason to reject (a question, a small clarification), reply with a
   message instead.

## Listing edited by its developer

*Type Extension - "Listing name was edited by its developer"*

1. Compare the revision with the live listing (Pending icon in the Extensions list).
2. Look particularly at changes to the **name**, **links**, **licence**, **type** and **linked
   extensions**.
3. **Approve**, or **Reject** with a reason code and notes. An online listing stays online either way
   - a rejection refuses only the edit.

## New review

*Type Review - "New review submitted: title"*

Check against the review guidelines; **Approve**, **Reject** (unpublish) or **Delete** (spam,
abuse). No reply is usually needed - the reviewer is e-mailed automatically. Close the ticket.

## Developer response

*Type Developer Response*

Courteous and relevant - **Approve**. Abusive, private details, commercial dispute - **Delete**.
Close the ticket.

## Reported review

*Category Reported Review, allocated to Review Specialist*

1. Read the report and the linked review.
2. If the review breaks the guidelines: unpublish or delete it (Reviews list, or the review's own
   ticket if it is still open).
3. Reply to the reporter with the outcome (template *Review Report Received* is the
   acknowledgement). Close.

Do not remove honest negative reviews.

## Reported extension

*Category Reported Extension, allocated to Listing Specialist*

1. Investigate the claim (rule breach, broken links, copied code, trademark, licence...).
2. If confirmed: contact the developer (open or reuse a ticket with the matching template), and if
   necessary **block** the listing with the right reason code - see
   [Unpublish, block or delete](how-to/unpublish-block-or-delete.md).
3. Reply to the reporter (template *Extension Report Received* acknowledges; then the outcome).

## New / Current / Unpublished Listing Support

*Opened by developers.* Typical requests and responses:

| Request | Do |
|---|---|
| "I fixed the problem, please check again" | Compare the newest revision, JED Checker, approve or reply again |
| "Please rename my listing" | [Rename an extension](how-to/rename-an-extension.md) |
| "Please take my listing offline" | Developers can do this themselves: *My Dashboard > My Extensions > Take offline*. Point them to it. |
| "Please delete my listing" | [Delete](how-to/unpublish-block-or-delete.md) |
| "My listing was approved but I can't see it" | Check its status: if *Offline*, the developer (or a maintainer) took it offline - *Put online* on their dashboard |
| "Please unblock my listing" (VEL resolved link, fixed broken links...) | Verify, then **Unblock** |
| Ownership questions, adopting an abandoned listing | [Change the owner](how-to/change-owner.md) |
| Free/paid or add-on links between listings of different owners | [Linked extensions](how-to/linked-extensions.md) |

## Support requests about using an extension

Reply with *Ticket - Misdirected Support Request* and close. The JED team does not provide extension
support.

## Not enough information

Reply with *Ticket - Not Enough Detail* (sets *Awaiting User*).

## Site technical issues

Reproduce, record the details in an **internal note**, and pass to the site administrators. Reply
to the user when fixed.

## Link Check ticket

*Type Link Check - "name: the Download link has stopped working"*

Opened automatically **for the developer** after three failed checks in a row; it closes itself
when the link answers again. Normally nothing to do. If the developer replies that the link works,
the checker may be blocked by their server's bot protection - check the link yourself and add an
internal note. Listings left unattended for longer are flagged (Extensions list filter *Links >
Left unattended*) and can open an abandonware case.

## Abandonware ticket

*Type Abandonware - "Possible abandonware: name"*

Choose **Open Case** and work it in the Abandonware component - see
[Abandonware cases](abandonware-cases.md). The ticket closes with the case.

## Vulnerability ticket

*Type Vulnerability - from the VEL team*

See [Handle a security advisory on a listing](how-to/handle-vel-ticket.md).
