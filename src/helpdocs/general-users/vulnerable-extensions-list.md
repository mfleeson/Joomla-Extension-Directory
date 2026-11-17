# Checking the Vulnerable Extensions List (VEL)

The **Vulnerable Extensions List** is where the Joomla VEL team publishes **security advisories**
about third-party Joomla extensions, templates and libraries. If you run a Joomla site, check it
regularly - or let your site check it for you (see the end of this page).

The VEL is about **security**. Its *Abandoned* tab lists **vulnerabilities** that will never be
fixed because the vendor has gone. That is different from the JED's
[Abandoned Extensions list](abandoned-extensions.md), which is about extensions that are no longer
maintained, whether or not they have a security problem.

## Opening the list

Use **Vulnerable Extensions** in the main menu. The list opens on the **Live** tab. The submenu
also has entries that open the list directly on each tab:

| Menu entry | Tab it opens on |
|---|---|
| **Vulnerable Extensions** | Live |
| **Live** | Live |
| **Patched** | Patched |
| **Abandoned** | Abandoned |

## The tabs

| Tab | Meaning | What you should do |
|---|---|---|
| **Live** | A vulnerability with **no fix available yet** | Uninstall the extension, or restrict access to it, until a fix is released |
| **Patched** | A fixed version exists for every affected product | **Update** to the fixed version (or later) |
| **Abandoned** | The vendor is gone or silent - **no fix is coming** | Remove the extension and find an alternative |
| **All** | Every published advisory, with a Status column | |

Each tab shows how many advisories it contains. Switching tabs keeps your search and filters.

## Searching and filtering

Above the list:

- **Search** - title, description, vendor, product, CVE...
- **Severity** - *Critical*, *High and above*, *Medium and above*, or one level only.
- **CVSS from / CVSS to** - a CVSS score range from 0 to 10.
- **Type** - component, module, plugin, template, library, package, file or language.
- **Clear** - removes the filters (shown when a filter is active).

Click the **Title**, **CVSS** or **Published** column heading to sort; click again to reverse.

## Reading the list

Each row shows:

- the advisory **title**, its **VEL reference** (for example `VEL-0042`), the **CVE id** if there
  is one, and an **Exploited in the wild** badge when the vulnerability is known to be used in
  attacks;
- the affected **Products** and the **Vendor**;
- the **CVSS** score and the **Severity** (*Low*, *Medium*, *High*, *Critical*, or *Not rated*);
- on the *All* tab, the **Status** (Live / Patched / Abandoned);
- the **Published** date.

## An advisory page

Open an advisory to see:

- **Affected products** - each product with its vendor, the **affected versions**, the version
  it is **fixed in** (or *No fix available*), and links to its **JED listing**, the **supplier
  website** and **Get the fix**;
- **What to do** - the team's advice, for example "Update to version 2.4.1 or later" or "No fixed
  version is available. Uninstall the extension, or restrict access to it, until the vendor
  releases a fix.";
- **Workaround**, if there is one;
- **Description** and **References**;
- **Classification** - the weakness (CWE), the severity and CVSS, and whether it is exploited;
- **Timeline** - reported, vendor notified, fixed, published, last updated;
- **Credits** - who found and reported it (only people who agreed to be credited);
- **Machine-readable** - the advisory as JSON, the CVE record, and the NVD entry.

Buttons at the bottom: **Tell us this is fixed** (for developers - see
[Security, VEL and abandonware](../developers/security-vel-and-abandonware.md)) and **Report a
vulnerability**.

### Withdrawn advisories

If an advisory was withdrawn (for example because it turned out not to be a vulnerability) its
page says **"This advisory has been withdrawn"**, with the date and the reason. It no longer
appears in the list, the feeds or the check API; the page is kept so links keep working.

## The warning on JED listings

When a published advisory names an extension that is listed in the JED, the extension's JED page
shows a warning banner (see [Reading an extension page](extension-page.md)). A listing may also
be taken offline by the JED team while an unpatched advisory stands.

## Feeds and the API

Links at the top of the list page:

- **RSS feed** - the 50 newest advisories. The feed follows the tab you are on, so the Patched
  tab gives you a feed of patched advisories. Filters can be added to the feed address, for
  example `?format=feed&type=rss&status=live&severity=high,critical`. An Atom feed is also
  available (`type=atom`).
- **JSON API** - a public, read-only API for tools and monitoring services (advisory list, single
  advisories, CVE records, the complete dataset, and a "check my extensions" endpoint).

## Let your site check for you

A Joomla plugin, **System - Vulnerable Extension Check**, checks the extensions installed on a
site against the VEL and warns on the administrator control panel when one is affected. It can
work in two modes:

- **Dataset** (recommended) - downloads the complete list and matches on your own site. Nothing
  about your site is sent anywhere.
- **Check** - sends your list of installed extensions and versions to the VEL, which answers and
  keeps nothing.

Enable **Warn about patched advisories** to also be warned when you are running a version older
than a fix.

## Found a vulnerability?

See [Reporting a vulnerability](reporting-a-vulnerability.md).
