# Site setup and administration

For the administrators who install and configure the JED site.

## What is installed

The **pkg_jed** package installs:

| Type | Name | Purpose |
|---|---|---|
| Component | **JED** (`com_jed`) | The directory, reviews, tickets, dashboard, user privileges |
| Component | **Vulnerable Extension List** (`com_vel`) | Advisories, reports, feeds, API |
| Component | **JED Abandonware** (`com_abandonware`) | Abandonware cases and the public list |
| Component | `com_apps` | The Joomla "Install from Web" apps service |
| Module (site) | **JED - Extensions List** (`mod_jed_extensions`) | A browse list (Top Rated, Most Reviewed, Recently Added, Recently Updated, New & Noteworthy) as a module |
| Module (admin) | **JED - Open Tickets** (`mod_jed_opentickets`) | Latest open tickets on the JED dashboard |
| Template (site) | **joomla** | The joomla.org site template, including the *dropdown* menu layout |
| Plugins | Sample data: *JED*, *JED Main Menu*, *JED Knowledgebase*, *JED3 Migration* | See below |
| Plugins | *Smart Search - JED*, *JED - Score Average*, *Task - JED*, *Action Log - JED*, *System - JED Identity*, *Privacy - JED* | |
| Plugins | *Task - VEL*, *Privacy - VEL*, *Action Log - VEL* | |

After installing: enable the plugins, create the scheduled tasks (see [Automation](automation.md)),
set permissions (see [Permissions and roles](permissions-and-roles.md)) and set the component
options below.

The **JED Checker** component must also be installed for the ticket screen's JED Checker tab to
work.

## Component options

**Components > JED > Options**:

| Tab | Settings |
|---|---|
| **JED3 Configuration** | Host, user, password, database name and table prefix of the old JED3 database - only for the migration |
| **Integration** | Save history / history limit |
| **Images** | *Use CDN* and *CDN URL* for images imported from JED3; maximum upload size (default 2048 KB) and dimensions (default 4000 px); sizes of the small (360x188) and large (720x376) copies generated from uploads |
| **Extension Audit** | Anthropic API key and model, Docker binary path - leave empty to disable automated audits |
| **Permissions** | See [Permissions and roles](permissions-and-roles.md) |

For the VEL options see the [VEL team guide](vel-team-guide.md#options-components--vulnerable-extension-list--options);
for Abandonware see [Abandonware cases](abandonware-cases.md#options).

## Sample data

**System > Sample Data** offers the JED plugins. Only Super Users can run them.

| Sample data | Does | Warning |
|---|---|---|
| **JED Sample Data** | Loads test categories, users, extensions, images, reviews and tickets (six steps) | Replaces existing JED data - **test sites only** |
| **JED Main Menu** | Creates the *JED Main Menu* and its menu module (position-1, dropdown layout) from `plugins/sampledata/jed_menu/menu.json` | Running it again removes the menu's items and recreates them from the file |
| **JED Knowledgebase** | Creates the Knowledgebase categories and articles (the JED policy articles) from `knowledgebase.json` | Running it again rebuilds everything below the Knowledgebase category, including anything added by hand |
| **JED3 Migration** | Imports a JED3 database: users, developers, categories, extensions, images, files, favourites, reviews, tags, linked extensions, user privileges and bans, tickets, listing and status history; with com_vel installed also the legacy VEL reports | Replaces the existing JED data |

### Running the JED3 migration

1. Restore the JED3 database on the **same database server** as the new site.
2. In **JED > Options > JED3 Configuration** set the JED3 **database name** and **table prefix**.
3. **System > Sample Data > JED3 Migration**, start it. It runs in around 70 steps, each its own
   request, so a timeout in one step can be retried without restarting. Step 1 checks that the
   source database is configured and reachable.
4. Afterwards: run Smart Search **Index**, check the tag report in the site's log folder
   (`jed_tag_migration_report.txt`), and decide on *Use CDN* for the old images.

## The front-end menu

The **JED Main Menu** sample data builds the standard menu (Home, Browse Extensions, Search,
Community, Support, Vulnerable Extensions, Login/Register). Edit `menu.json` and run the sample data
again to change it, or edit the menu in **Menus** directly.

Menu item types you can use:

| Component | Menu item type | Options |
|---|---|---|
| JED | **Extensions** | *Browse List* (Top Rated, Most Reviewed, Recently Added - last 30 days, Recently Updated, New & Noteworthy - most viewed in 14 days) and *Joomla Version* (J4, J5, J5 with B/C plugin, J6, J6 with B/C plugin) |
| JED | **List of Categories**, **Category List** | Category browsing |
| JED | **Single Extension**, **Developer Profile** | |
| JED | **Submit a New Extension**, **My Dashboard** | For signed-in users |
| JED | **Reviews**, **Single Review**, **ReviewForm** | |
| JED | **JED Tickets**, **New Ticket** | |
| VEL | **VEL: Vulnerable Extensions List** | *Default tab* (Live, Patched, Abandoned, All). One item per tab gives tab-specific menu entries; the list page links its tabs to them. |
| VEL | **VEL: Single Advisory**, **VEL: Report a Vulnerability**, **VEL: Fix Notice** | |
| VEL | **VEL: JSON Feed (legacy)** | The legacy JSON feed. Give it the alias `json-feed` under the *Vulnerable Extensions* item to keep `/vulnerable-extensions/json-feed`. |
| Abandonware | **Abandonware: Abandoned Extensions**, **Abandonware: Report an Extension** | |

The VEL list must stay at **`/vulnerable-extensions`** - advisory links, e-mails, CVE records and
the legacy JSON feed all use that path.

### The dropdown menu layout

The *joomla* template includes a **dropdown** layout for *Menu* modules (**Layout: joomla >
dropdown**). Headings open their sub-menu on click; menu items that have children keep their own
link and get a separate toggle button. Set *Show Sub-menu Items* to **Yes**.

## Modules

- **JED - Extensions List** - choose a *List*, *How Many*, optionally a *Category*, *Show Short
  Description*, and a *"See all" Menu Item* to link to the matching browse page.
- **JED - Open Tickets** (administrator) - place it on the JED dashboard.

## Signing in with the Joomla Identity Portal

*System - JED Identity* ties accounts to their Joomla Identity Portal GUID, answers the portal's
pings (for example a data removal - set the *Ping secret*), and provides a CLI command that moves
old accounts onto GUIDs. Users who sign in with the portal and already had a JED account use the
*Claim Your Old JED Account* page (see the
[general users guide](../general-users/README.md#signing-in-with-the-joomla-identity-portal)).

## Smart Search

Enable *Smart Search - JED* and the *Content - Smart Search* plugin, then run **Index** once.
Listings are re-indexed automatically on every approval, block, deletion and state change. The
index includes category, type, extension type, Joomla versions, licence, developer and popularity,
but never developer e-mail addresses. Access is inherited from the listing's category.
