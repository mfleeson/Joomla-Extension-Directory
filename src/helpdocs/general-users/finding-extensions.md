# Finding extensions

There are several ways into the directory. All of them end up on the same extension cards and
the same extension pages.

## The main menu

The JED main menu (along the top of the site) opens submenus when you click or hover over a
heading. The standard menu contains:

| Menu | What it shows |
|---|---|
| **Browse Extensions > Top Rated** | Every extension, best rating first, then most reviews |
| **Browse Extensions > Most Reviewed** | Every extension, most reviews first |
| **Browse Extensions > New** | Only extensions added in the **last 30 days**, newest first |
| **Browse Extensions > Recently Updated** | Every extension, most recently updated first |
| **Browse Extensions > Compatible with J4 / J5 / J5 (with b/c plugin) / J6** | Only extensions that state compatibility with that Joomla version |
| **Search** | Site search (see below) |
| **Vulnerable Extensions** | The Vulnerable Extensions List, with **Live**, **Patched** and **Abandoned** entries - see [Checking the VEL](vulnerable-extensions-list.md) |

Your site's menu may differ slightly; the JED team can change it.

## Browsing by category

The **category list** shows the top-level categories as cards, each with its sub-categories
underneath. Categories are sorted by how many published extensions they contain. Open a
category to see its extensions (and its own sub-categories).

When you browse a single category, the page heading reads "*Category* Extensions" and the
category's position in the tree is shown underneath it.

## The extensions list: search, filters and sorting

Every list of extensions has the same tools above it:

- **Search box** - searches the **extension name**. To jump straight to one extension when you
  know its number, type `id:` followed by the number, e.g. `id:1234`.
- **Search Tools** - opens the filters:
  - **Joomla Version** - *Joomla 4*, *Joomla 5* or *Joomla 6* (or *Any Joomla version*).
- **Sort by** - *Title ascending/descending*, *Top Rated*, *Most Reviewed*, *Newest* or
  *Recently Updated*.
- **Display Number** - 18, 36, 54 or 72 extensions per page.
- **Clear** - removes your search and filters.

A new search or filter always starts again from page 1.

> The list pages under *Browse Extensions* are fixed to their own order (for example *Top
> Rated*). You can still search and filter within them.

## Reading an extension card

Each card in a list shows:

- the extension's logo and name, and **By** *developer*;
- a **favourite** button (only when you are signed in - see
  [Dashboard and favourites](dashboard-and-favourites.md));
- the star rating and number of reviews ("Not yet reviewed" if there are none);
- **Compatible with:** the Joomla versions the developer states (a "(b/c)" mark means it needs
  Joomla's backward-compatibility plugin);
- **Contains:** component, module, plugin, template, language, library, package;
- the type: **Free**, **Paid**, **Free with paid upgrade** (freemium) or **Cloud service**;
- a short description and the date it was added.

Click the card to open the [extension page](extension-page.md).

## Developer profiles

Click a developer's name on an extension page to see their **Developer Profile** with all their
published extensions. An extension page also shows **More by** *developer* near the bottom,
with a link to **See all extensions by** *developer*.

## Site search

The site search uses Joomla's Smart Search, which indexes every public extension. You can find extensions by words in their name and description and narrow the results by category,
type (free/paid/freemium/cloud), extension type, Joomla version, licence and developer.
Developer e-mail addresses are never included in the search index.

## What you will not find

Only listings that are approved and online appear. Listings that are waiting for approval, were
not approved, were taken offline by their developer or were deleted are not shown. Listings the
JED team has **blocked** still open, but show a notice explaining why they are unavailable - see
[Reading an extension page](extension-page.md#blocked-listings).
