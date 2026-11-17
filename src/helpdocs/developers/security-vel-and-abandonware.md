# Security, the VEL and abandonware

This page covers the automated and team processes that concern your listings beyond ordinary
moderation: vulnerability reports, broken links, abandonware enquiries and automated audits.

## When a vulnerability is reported in your extension

Vulnerabilities are handled by the **VEL team**, separately from the JED team.

1. Someone reports a vulnerability to the VEL (privately - nothing is published).
2. The VEL team verifies it and **notifies you** ("the vendor") by e-mail. The notification goes to
   the listing's **owner and maintainers**, any developer address the reporter gave, and any
   other address the team adds. It includes the details the reporter agreed to share and the
   **planned disclosure date** (by default 90 days after notification).
3. **Reply to that e-mail** - replies are threaded into the case automatically (every VEL mail
   carries a reference like `[VEL-0042]` or `[VELR-00123]` in the subject). You may also get a
   private case link where you can read and reply to the conversation.
4. Fix the issue and release a fixed version.
5. Tell the VEL team with a **fix notice** (below).
6. The VEL team publishes an **advisory**. If you released a fix, it is published as **Patched**
   with your fixed version and users are told to update.

If you do not respond or do not fix it, the advisory can be published as **Live** (no fix) when the
disclosure date passes, or as **Abandoned** if no fix will come.

### Effect on your JED listing

- While a published advisory names your listing, the listing shows a **security warning**
  banner: red for an unpatched (*Live*) advisory, amber for a *Patched* one ("fixed in
  *version*").
- For an unpatched advisory the VEL team may ask the JED team to **block** the listing with
  reason **VL1 - Listed on the Vulnerable Extension List**. The listing is then unavailable until
  the fix is confirmed and the block is lifted.

### Submitting a fix notice

Use **Submit a fix notice** (link on the Vulnerable Extensions List page) or **Tell us this is
fixed** on the advisory's page (the advisory reference is then filled in for you).

| Field | Notes |
|---|---|
| **Your name**, **Your e-mail address** (required), **Organisation** | Your contact details |
| **Advisory or CVE** | The VEL reference (e.g. `VEL-0042`) or CVE id, if you have one |
| **Extension name** (required) | |
| **Affected up to version** | The last affected version |
| **Fixed in version** (required) | The first version that contains the fix |
| **Download / patch URL** | Where the fixed version can be obtained |
| **Release notes / changelog URL** | Where the fix is described publicly - make it clear to your users that this is a **security** release and they must update |
| **What was fixed** (required) | Briefly, what the issue was and how the fix addresses it |
| **Consent** (required) | Consent to the VEL team processing the notice |

Choose **Send the fix notice**. The advisory is updated once the VEL team has confirmed the fix.
If your listing was blocked with VL1, open a ticket under **Unpublished Support** once the
advisory shows as patched.

### Reporting a vulnerability you found in your own extension

You may also use the **Report a vulnerability** form with "You are: the extension's developer" and
"I am the developer" - for example to get a CVE assigned for a fix you have already released.

## Broken link notices

The JED checks the links on every published listing every few days - the download link and update
server most importantly, then the website, support, demo, documentation, licence, changelog and
source repository links.

- A single failure is ignored. After **three checks in a row** fail (roughly nine days), you
  receive an e-mail "A link on your extension *name* has stopped working" and a ticket is opened
  for you with the address, what the checker saw and when it first failed.
- **Fix or update the link** on your listing. The ticket **closes by itself** once the link answers
  again.
- If the link works for you, the checker may be blocked by your server's bot protection - reply
  on the ticket.
- If the link keeps failing (six checks in a row), the listing is flagged to the JED team as
  possibly unattended, which can start an abandonware enquiry (below).

## Abandonware enquiries

If the JED sees signs that an extension may no longer be maintained - links that stay broken, an
update server that keeps failing, a very long period without changes, or a report from a user -
the JED team opens a case. Before anything is published:

1. The team **e-mails the owner**: "Is *extension* still maintained?", explaining what prompted
   it. This is not an accusation and nothing has been published.
2. You have a **grace period** (30 days by default) to reply or update the listing.
3. If you respond (or update the listing), the JED team closes the case as *Developer responded*
   or *Still maintained*.
4. Only if nobody answers can the extension be marked as abandoned, and only then - as a separate
   decision - shown on the public **Abandoned Extensions** list.

If you have stopped maintaining an extension, consider **handing it over** to someone who will
continue it - see [Maintainers and ownership](maintainers-and-ownership.md). A completed handover
closes an open abandonware case automatically.

If your extension is on the abandoned list and you maintain it (or want to take it over), contact
the JED team - an entry can be removed.

## Automated audits

When the JED detects a new version of your extension through your update server, it can run an
**automated audit** of the package (static analysis and a security review) if this is enabled on
the site. The report is e-mailed to you so you can act on anything it finds. Audit results are
**never public**; only authorised JED team members can see them.
