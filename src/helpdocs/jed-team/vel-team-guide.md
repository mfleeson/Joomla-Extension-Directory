# VEL team guide

The Vulnerable Extension List lives in **Components > Vulnerable Extension List**: *Dashboard*,
*Advisories*, *Reports*, *Feed Items*, *Feed Runs* and *CWE List*. (More technical material - the
API, CVE conventions, the feeds - is in the developer documentation under `docs/vel/`.)

## Reports and advisories

- A **report** is what somebody sent you (through the public form, by e-mail, or imported).
- An **advisory** is what you publish.

A report becomes part of an advisory when you attach it; several reports can feed one advisory;
an advisory can exist without a report (a feed brought it in, or you wrote it from a public
disclosure).

### Advisory states (workflow)

| State | Meaning | Who moves it |
|---|---|---|
| **Draft** | Being written | anyone with Edit |
| **Ready** | Complete and validated, waiting for publication | Edit State (draft -> ready and back) |
| **Published** | Public: on the site, in the feeds and API, on the JED listing | **Publish Advisory** permission |
| **Retired** | Withdrawn; the page remains with a notice | **Publish Advisory** permission |

### Public status

Independently, every advisory has a public **status**: **Live** (no fix), **Patched** (every
affected product has a fixed version) or **Abandoned** (no fix is coming). The status follows the
fixed versions automatically unless you pin it on the *Advisory* tab.

## Dashboard

The counters answer the morning's questions: **New reports**, **Unverified reports** (guests who
have not confirmed their e-mail), **Drafts**, **Ready to publish**, **Published, unpatched**,
**Published in total**, and under *Needs a decision*: **Disclosure within 7 days**, **Disclosure
date passed**, **Vendor response overdue**; plus **Feed items pending** and the latest feed runs.

## Reports

Open a report and decide:

| Button | Does |
|---|---|
| **Create advisory from this report** | A draft with one product and version range from the report, the CWE from the reporter's pick, a reference from their disclosure URL, and the reporter as a credit if they asked for one. The report, its participants and messages move onto the advisory. |
| **Attach** | Attach to an existing advisory (matches are suggested by extension name and listing) |
| **Mark triaged** / **Mark duplicate** / **Reject** / **Spam** | Spam also withdraws the **Report** privilege from the reporter's JED account, if they have one |

Report states: *Awaiting e-mail verification*, *New*, *Triaged*, *Accepted*, *Duplicate*,
*Rejected*, *Spam*. Prefer Reject or Spam to deleting - a deleted report leaves no record.

## Editing an advisory

Tabs:

- **Advisory** - title, description, solution, workaround, public status (and pin), exploited,
  discovery.
- **Affected Products** - one per product, all of **one vendor**. Type the JED listing's URL or alias
  and leave vendor/product blank to take them from the listing. Fill in the Joomla identity (type,
  element, plugin group, client) from the extension's update-server XML - the check API matches
  on it. Version ranges are inclusive; `*` means unbounded. Set **Fixed in version** when there is
  one.
- **Classification** - CWEs (type to search), the CVSS vector (3.1 or 4.0; for 4.0 also type the
  score from the FIRST calculator), or a severity by hand with a reason.
- **References**, **Credits** (public or internal), **Dates** (the automatic dates are shown).
- **Reports & Messages** - the thread. Reply with a visibility: *internal*, *reporter*, *vendor* or
  *all*; anything not internal is e-mailed. **Send vendor notification** writes to the JED
  listing's organisation members (owner included), any developer address on the reports and an address you add,
  with the report details the reporters agreed to share and the disclosure date. It sets *Vendor
  notified* and, if empty, the *Disclosure date* (today plus the configured disclosure days,
  default 90).
- **CVE** - the id and state; **Preview CVE JSON**, **Download full record** (for Vulnogram),
  **Download CNA container**; **Reserve a CVE id** and **Submit record to CVE Services** once
  credentials are configured.
- **History** - a revision per publication and per save while published.
- **Notes** - internal only; never exported, never in a revision.

## Publishing

- **Mark Ready** checks the advisory is complete: title, description, a product with a range, a
  CWE, and a CVSS score or a reasoned severity.
- **Publish** (Publish Advisory permission) warns if the disclosure date is in the future, and can
  open a **JED ticket** asking the JED team to block the listing (reason VL1) or to confirm the
  fixed version is listed. Blocking is the JED team's decision - the VEL never blocks anything
  itself. See [Handle a security advisory on a listing](how-to/handle-vel-ticket.md).
- **Publish again** re-publishes after changes.

Once published, the advisory appears in the list (on its status tab), the RSS/Atom feeds, the
JSON API and dataset, and as a warning banner on the JED listing.

## Retiring

**Retire** with a reason - *Duplicate of another advisory*, *Not a vulnerability*, *CVE rejected*
or *Other*. The page stays up with a withdrawal notice; the dataset carries the advisory as
withdrawn for a year so checkers drop it.

## Fix notices from developers

Developers use **Submit a fix notice** / **Tell us this is fixed**. A fix notice arrives as a report
of kind *fix notice*. Verify the fixed version (download it), then set **Fixed in version** on the
product; the status moves to Patched automatically. Tell the JED team if the listing was blocked.

## Feeds

**Feed Items** is the review queue for records from the CVE Program (CNA feed) and the NVD. A
Joomla CNA record that matches no advisory can become a draft on its own (option). Everything else
waits for you:

- **Create a draft advisory**;
- **Fill what our advisory lacks** - adds CVSS, CWEs, references and ranges we do not have; never
  overwrites;
- **Ignore this record** - it comes back if the record changes.

Core Joomla records are listed for the record only. **Feed Runs** shows every run; **Run CVE feed
now** / **Run NVD feed now** run them by hand (permission *Run Feeds*); **Seed CNA Back Catalogue**
is a one-off after installation. **CWE List** holds the weakness catalogue (**Refresh from MITRE**).

## Mail

Every VEL e-mail carries `[VEL-0042]` or `[VELR-00123]` in the subject. Replies land in the case
when inbound mail is enabled (IMAP polling or the provider webhook). A mail that belongs to no case
becomes a new report. Guests get a private case link that expires after 180 days and is renewed by
the next mail.

## Privacy

Reporter identities, attachments and correspondence are visible only with **Case
Correspondence**. The privacy plugin exports and erases by account and by e-mail address; erasure
anonymises the person and deletes their attachments while keeping the disclosure record.

## Options (Components > Vulnerable Extension List > Options)

| Area | Key settings (defaults) |
|---|---|
| Policy | Vendor first response (14 days), disclosure period (90 days), whether a CVSS is required to publish |
| Intake | Allow guest reports (yes), guest e-mail verification (yes), report rate limit (5 per 24 hours), purge unverified after 7 days, captcha, attachment size (10 MB) and types, PGP public key, contact e-mail |
| E-mail | From address/name, inbound mode (off / IMAP / webhook) and its settings |
| CVE | CNA details, CVE record options, CVE Services environment and credentials |
| Feeds | CNA and NVD feed switches, limits, look-back periods, NVD API key, auto-create drafts |
| Public | List path (`/vulnerable-extensions`), list length, show CVSS in list, legacy JSON feed |

## The public list menu

The JED main menu's **Vulnerable Extensions** item opens the list on *Live*; its **Live**,
**Patched** and **Abandoned** children open the matching tabs. Each is a *VEL: Vulnerable Extensions
List* menu item with a different **Default tab** option; the tabs on the page link to these menu
items. The list must stay at `/vulnerable-extensions`, because advisory URLs, e-mails and CVE
records use that path.

The **JSON Feed** child is a *VEL: JSON Feed (legacy)* menu item with the alias `json-feed`, so the
legacy feed keeps its address `/vulnerable-extensions/json-feed`. The feed answers at that address
even without the menu item. Under **Options > Public**, *Serve the legacy JSON feed* switches it off
(it then answers *410 Gone*) and *Legacy feed sunset date* announces the end date in the feed and
its `Sunset` header; after that date it answers *410 Gone* as well. The list page's *JSON feed
(legacy)* link disappears at the same moment.
