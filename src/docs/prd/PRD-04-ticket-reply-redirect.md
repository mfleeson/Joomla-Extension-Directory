# PRD-04: Sending a ticket reply from the administrator

| | |
|---|---|
| Status | Implemented |
| Component | com_jed (administrator ticket controller) |
| Users affected | JED team |
| Related | PRD-01 (ticket screen actions) |

## 1. Summary

When a team member chose **Send Message** on a ticket, the reply was e-mailed and stored, but the
browser landed on a "component not found" page. The code still pointed at `com_tickets`, the
component the ticket system lived in before it was merged into com_jed. Sending now returns to the
ticket with a confirmation, applies the status chosen with a message template, and reports a mail
failure instead of hiding it. The JED Checker tab's **Run JED Checker** had a related crash and was
fixed too.

## 2. The problem

In `administrator/components/com_jed/src/Controller/TicketController.php`:

| Where | Defect | Effect |
|---|---|---|
| `sendMessage()` redirect | `index.php?option=com_tickets&view=ticket&layout=edit&id=...` | "Component not found" after every reply |
| `sendMessage()` permission | `authorise('core.edit', 'com_tickets')` | Checked against an asset that no longer exists - Joomla falls back to the global permissions, so the result did not follow com_jed's permission settings |
| `getTemplate()` permission | same | The template picker could return nothing for team members whose rights are set on com_jed only |
| `sendMessage()` result of the mail | ignored | A failed e-mail looked like a success |
| `sendMessage()` empty subject/body | HTTP 400 error page | A slip became an error page |
| Template status | The picker sets the *Status* drop-down, but only **Save** stores it | The status the template intended was usually lost |
| `jedcheck()` | `$model->getDatabase()` - a protected method | Fatal error when running JED Checker from a ticket |

## 3. Goals

- Sending a reply returns to the same ticket with a clear result.
- Permissions are checked against com_jed, consistently with internal notes and the JED Checker tab.
- The status chosen with a template is applied when the message is sent.
- Mail failures are reported; empty messages are a warning, not an error page.

## 4. Requirements

| # | Requirement |
|---|---|
| R1 | After **Send Message**, redirect to `option=com_jed&view=ticket&layout=edit&id={ticket}`. |
| R2 | `sendMessage()` and `getTemplate()` require `core.manage` on `com_jed` (the JED team's right). |
| R3 | If the posted form's *Status* differs from the stored one (e.g. set by a template), it is saved with the message, through `TicketTable` so Resolved/Closed transitions are logged. |
| R4 | Messages: "The message has been sent and added to the ticket." / "The ticket status is now *Status*." / "The message has been added to the ticket, but the e-mail could not be sent: *error*" / "Write a subject and a message before sending." |
| R5 | Run JED Checker works. |

## 5. Solution

- `getTemplate()`: `authorise('core.manage', 'com_jed')`.
- `sendMessage()` rewritten: token check; `core.manage` on com_jed (403 otherwise); recipient is the
  ticket creator, never a posted value (unchanged); empty subject/body -> warning and back to the
  ticket; send via `JedemailHelper::sendEmail()` and check its error string; store the message
  (unchanged); `applyStatusFromForm()` (new, validates 0-5, stores through `TicketTable`); messages;
  redirect to the com_jed ticket.
- `jedcheck()`: the database comes from the container instead of the model's protected
  `getDatabase()`.
- Language: `COM_JED_TICKET_MESSAGE_SENT`, `_MESSAGE_EMPTY`, `_MESSAGE_STORED_MAIL_FAILED`,
  `COM_JED_TICKET_STATUS_SET`.

Note: other header fields (category, assignment) are still saved with **Save**, not with Send
Message.

## 6. Permissions and security

- `core.manage` on com_jed, matching internal notes and JED Checker.
- Token checked; recipient derived from the ticket.

## 7. Verification

| Check | Result |
|---|---|
| Send Message works for a JED team member | PASS |
| The reply is e-mailed to the ticket creator | PASS |
| The reply is stored on the ticket as outgoing | PASS |
| It returns to com_jed's ticket screen, not com_tickets | PASS |
| The status chosen with the template is applied | PASS |
| The team member is told it was sent (and the new status) | PASS |
| An empty message comes back to the ticket with a warning | PASS |
| Somebody without *Access Administration Interface* on com_jed is refused (403) | PASS |

## 8. Acceptance criteria

1. Open a ticket, pick the template *Ticket - Not Enough Detail*, choose **Send Message**: the
   ticket reopens with "The message has been sent..." and "The ticket status is now Awaiting User.",
   and the message is in the Message History.
2. With the mail server down, the message is still stored and a warning names the mail error.
3. **Run JED Checker** on an extension ticket shows results instead of an error.
