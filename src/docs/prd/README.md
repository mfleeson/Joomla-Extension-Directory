# Fix PRDs

Product requirement documents for the defects found while writing the help documentation
(`helpdocs/`). Each PRD explains the problem as it showed up for users, why it happened in the code,
what was changed, and how it was verified.

| PRD | Issue | Area |
|---|---|---|
| [PRD-01](PRD-01-reject-listings.md) | Listings can't be rejected from the admin screens | Moderation, tickets |
| [PRD-02](PRD-02-first-approval-online.md) | New listings stay offline after approval | Moderation, e-mail |
| [PRD-03](PRD-03-developer-online-switch.md) | Developers can't take a listing offline themselves | Developer dashboard |
| [PRD-04](PRD-04-ticket-reply-redirect.md) | Sending a ticket reply lands on "component not found" | Tickets |
| [PRD-05](PRD-05-forced-ownership-transfer.md) | No screen for forcing an ownership transfer | Ownership |
| [PRD-06](PRD-06-ticket-return-and-pending.md) | Review actions on a ticket went to the Reviews list; the *Pending* icon showed after blocks and deletions (known issue #13) | Tickets, moderation |
| [PRD-07](PRD-07-jedchecker-integration.md) | The ticket's JED Checker tab always said JED Checker was not installed, and could not run the current JED Checker | Tickets, JED Checker |
| [PRD-08](PRD-08-jedchecker-joomla6-security-review.md) | JED Checker: Joomla 6 review and security fixes (HTML injection from packages, language-string override, web-reachable unpack folder, zip bombs) | com_jedchecker |

## Verification

No MySQL server is available in the development sandbox, so the changes were exercised with PHP 8.3
(WebAssembly build) running the real Joomla 6 libraries and the real com_jed classes against an
in-memory SQLite database built from com_jed's own `install.mysql.utf8.sql`. Mail was captured
instead of sent. 78 checks across the six PRDs pass; each PRD lists its own. These tests live
outside the repository (they depend on the sandbox tooling); the behaviour they check is listed in
each PRD's *Acceptance criteria* so it can be re-checked on a real installation.

## Shared changes

Two changes serve more than one PRD:

- **`Listing\BlockReasons`** (new) - one reader for the reason codes of `#__jed_block_reasons`, used
  by the Blocking tab's list field, the ticket's Reject action and the compare page's Reject panel
  (PRD-01).
- **`TicketHandlingTrait::recordOnLinkedTicket()`** (new) - writes a decision onto the most recent
  ticket about an item as an outgoing message and optionally moves the ticket's status. Used by
  rejections (PRD-01); available for other decisions.
