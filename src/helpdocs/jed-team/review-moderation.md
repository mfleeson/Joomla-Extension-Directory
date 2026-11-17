# Review moderation

Reviews and developer responses are moderated before they appear on the site.

## How reviews arrive

- A new review is saved **unpublished** and a **Review** ticket is opened ("New review submitted:
  *title*").
- An **edited** review is set back to **unpublished** (it disappears from the public page) - but no
  new ticket is opened, so check the Reviews list for unpublished reviews regularly.
- A **developer response** is saved as pending and a **Developer Response** ticket is opened ("A
  developer response was submitted to the review *title*").
- A **report** about a review arrives as a ticket of category *Reported Review*, allocated to the
  *Review Specialist* group, with the review linked.

## Moderating from the ticket (recommended)

Open the ticket. The **Linked Review** tab shows the review: title, author, the five scores with
their comments, the overall score and the body. Actions:

| Action | Effect |
|---|---|
| **Approve** | Publishes the review. The reviewer is e-mailed "Your review has been published"; the extension's scores are recalculated (via the job queue). |
| **Reject** | Unpublishes the review - it stays on record and can be published later. The reviewer is e-mailed "Your review was not published". |
| **Delete** | Deletes the review entirely (you are asked to confirm). For spam and abuse. |

For a **Developer Response** ticket, the **Linked Developer Response** tab shows the review title,
the reviewer, the response and its date. Actions: **Approve** (publishes the response under the
review) or **Delete** (rejects it; the developer cannot submit another for that review).

After the action you come back to the ticket. Reply if needed and close it. (The same actions in
the Reviews list keep you in the Reviews list.)

## Moderating from the Reviews list

**Components > JED > Reviews** lists every review. Columns include the extension, title, the
scores and comments, *I use this for*, flagged, IP address, created by/on and the published state.

- **Search** and filter by **Status** (use *Unpublished* for reviews waiting for a decision).
- **Change Status > Publish / Unpublish / Archive / Trash** works on the ticked reviews. Publishing
  and unpublishing e-mail the reviewer and trigger a score recalculation exactly as the ticket
  actions do.
- Click a title to edit a review. The edit form also holds the **Developer Response**, its
  **Responded On** date and **Response Status** (Published / Unpublished / Trashed), and the
  **Flagged** checkbox and **IP Address**.

## What to look for

Apply the review guidelines (Knowledgebase: *Submitting a Review*, *Edited, Removed or
Unpublished Reviews*). Reject or delete reviews that are:

- not based on actual use, one-liners, or about a commercial dispute;
- questions, support or feature requests, error messages, code or bug reports;
- containing links, bad language, self-promotion or spam;
- submitted through a VPN or IP-masking service.

Useful signals:

- the **IP address** of the review (kept only for a limited number of days - see
  [Automation](automation.md#privacy-retention)), to spot several reviews from one address;
- the reviewer's other activity on the **Ticket Help** tab;
- whether the reviewer is the developer (developers must not review their own extensions).

For developer responses: courteous, factual, about the review - not an argument, not private
details.

## Dealing with problem reviewers

- Restrict or ban the account in [User privileges](user-privileges.md) (withdraw **Review**, or ban
  for a period with a reason).
