# Mail templates and canned replies

All e-mails the JED, the VEL and Abandonware send are Joomla **mail templates**. Edit them in
**System > Mail Templates** (filter by component). Each template has a subject and body per
language and uses `{PLACEHOLDERS}` that are filled in when the mail is sent.

## Canned replies on tickets

The **Message Templates** picker on the ticket screen lists the com_jed templates marked as
canned replies. Choosing one fills the reply's subject and text; most also set the ticket status to
*Awaiting User*. Always read and adapt the text before sending - some contain placeholders such as
`[XXX]` or `[Your Name]` that you must replace by hand.

### Reason-code replies (also used as rejection e-mails)

| Template | Code |
|---|---|
| Extension: ER1 - error_reporting(0) Found | ER1 |
| Extension: NM1 - Install Name Doesn't Match Listing Name | NM1 |
| Extension: NM2 - Extension Specific Naming Issue | NM2 |
| Extension: NM3 - Non-Permitted Words in Name | NM3 |
| Extension: TM2 - Use of the Joomla Trademark | TM2 |
| Extension: LK2 - Invalid Download Link | LK2 |
| Extension: ZP1 - Zipfile Issues | ZP1 |
| Extension: LC1 - Licensing Violation | LC1 |
| Extension: LC2 - Paid Listing without License Link | LC2 |
| Extension: LC3 - License Link does not Mention Extensions | LC3 |
| Extension: LC4 - Invalid License Type | LC4 |
| Extension: US1 - Update Server Requirement | US1 |
| Extension: PE1 - Under Investigation | PE1 |

When a listing is **rejected** with a reason code, the developer is sent the template registered
for that code in the block-reasons table (the ones above), or *"Your extension {EXTENSIONNAME} was
not accepted"* if the code has none. The reason-code templates have no place for the reviewer's
notes, so the rejection also writes a "Not approved: *code*" message with the notes on the listing's
ticket.

### Other replies

*Extension - JED Checker Errors*, *Extension - Hidden Files or Folders*, *Extension - Plugin Install
Name*, *Extension - Backlink Issues*, *Extension - Listed on VEL*, *Extension - Broken Links*,
*Ticket - Misdirected Support Request*, *Ticket - Not Enough Detail*, *Review Report Received*,
*Extension Report Received*.

## Automatic notifications

### Listings and reviews (com_jed)

| Template | Sent when | Placeholders |
|---|---|---|
| Confirmation of Submission (`com_jed.ticket_confirmation`) | Any ticket is created; also stored as the ticket's first message | |
| Listing Submission Received | A new listing is submitted | |
| Listing Update Received | A listing is edited | |
| Your extension {EXTENSIONNAME} is now listed | A listing's **first** approval (manual or automatic) - it goes online | EXTENSIONNAME, SITENAME |
| Your changes to {EXTENSIONNAME} have been approved (`com_jed.listing_update_approved`) | An edit to an already listed listing is approved; its online/offline state is unchanged | EXTENSIONNAME, SITENAME |
| Your extension {EXTENSIONNAME} was not accepted | A revision is rejected with a code that has no template of its own | EXTENSIONNAME, REASONCODE, REASONNOTES |
| Your review has been published / was not published | A review is published / unpublished | EXTENSIONNAME, REASONNOTES |
| A link on your extension has stopped working (`com_jed.link_broken`) | The link check escalates to the developer | SITENAME, EXTENSIONNAME, LINKTYPE, URL, REASON, SINCE, TICKETLINK |
| Audit report (`com_jed.audit_report`) | An automated audit completes | SITENAME, EXTENSIONNAME, EXTENSIONVERSION, PHPSTANREPORT, CLAUDEREPORT |
| Please confirm that this account is yours (`com_jed.identity_claim`) | Someone claims an old account via the Identity Portal | SITENAME, NAME, LINK, HOURS |

### Ownership handovers (com_jed)

*Confirm handing over {EXTENSIONNAME}* (to the owner), *{OTHERPARTY} would like to hand
{EXTENSIONNAME} over to you* (to the recipient), *{EXTENSIONNAME} has changed hands*, *The handover
of {EXTENSIONNAME} was called off*, *The handover of {EXTENSIONNAME} has lapsed*, *{EXTENSIONNAME}
has been reassigned* (forced transfer, with REASONNOTES). Parties are always named, never shown by
e-mail address.

### Abandonware

*Abandonware: Contact the Developer* - "Is {EXTENSIONNAME} still maintained?", sent by **Contact
owner** on a case.

### VEL

`com_vel.report_verify` (guest confirms e-mail), `com_vel.report_received`, `com_vel.fix_received`,
`com_vel.message` (case messages), `com_vel.vendor_notification`, `com_vel.advisory_published`.

## Tips

- Keep the reason-code templates consistent with the Knowledgebase articles *Submission Error
  Codes* and *Unpublished Extensions Error Codes*.
- Templates installed by com_jed are refreshed from the component's defaults when the component is
  updated - keep a copy of any wording changes you make.
- The template *{EXTENSIONNAME} has been unpublished: security advisory* (`com_jed.extension_vl1_vulnerable`)
  exists for VL1 blocks but is not sent automatically - use it as a canned reply.
