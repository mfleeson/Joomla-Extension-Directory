# Listing fields reference

The same form is used to submit a new listing and to edit an existing one. Fields are grouped
in tabs.

## Common

| Field | Required | Notes |
|---|---|---|
| **Title** | Yes | The listing name. It must match your extension's **install name** (the name in its manifest and language files). |
| **Alias** | | The web address slug. Generated from the title if left empty. |
| **Category ID** | Yes | The main category. It decides where the listing appears and the breadcrumb shown on it. |
| **Categories** | | Additional categories the listing should also appear in. |
| **Tags** | | Free tags. Visitors can click a tag to find similar extensions. |

## Info

| Field | Notes |
|---|---|
| **Intro Text** | The short introduction shown at the top of the listing and on cards. |
| **Description** | The full description. |
| **License** | The licence **name**, e.g. "GPLv2 or later". |
| **Licence URL** | A page on your own site where the licence or terms of use can be read. Required in practice for **Paid** listings. |
| **Requires Registration** | Yes if people must register or sign in at your site to download. Visitors are told so next to the Download button. |
| **Type** | **Free**, **Paid**, **Freemium** (free with a paid upgrade) or **Cloud** (a cloud service). |
| **Extension Type** | What the package includes: Component, Module, Plugin, Specific (an extension-specific add-on). |
| **Joomla Versions** | The Joomla versions you support. These drive the "Compatible with" badges and the J4/J5/J6 browse lists. |

### Formatting the intro and description

The intro and description are written in **Markdown**: `**bold**`, `*italic*`, lists, headings,
and `[links](https://example.com)`. Raw HTML is removed. A single line break is kept as a line
break.

## Links

| Field | Notes |
|---|---|
| **Download Link** | Where visitors download the extension (checked as a download). See the download-link rules in [Before you submit](before-you-submit.md#6-download-link-rules). |
| **Support Link** | Where users get support. |
| **Demo Link** | A live demo. |
| **Documentation Link** | Your documentation. |
| **Git URL** | A **public** source repository. Shown as "Source code". |
| **Internal Download URL** | A private link the **JED team** can use to download the package (for example for a paid extension). Never shown to visitors. |
| **Download Key** | A key or code the JED team needs to use the internal download link, if any. Never shown to visitors. |
| **I can confirm that...** | Confirm that the extension integrates the Joomla Update System (or is an add-on managed by a third-party system), has been checked with JED Checker, and that its install name matches the listing name. |
| **Update URL** | The address of your **update server XML**. The JED reads it regularly and updates the **Version** on your listing automatically - see [Updating your extension](updating-your-extension.md#new-versions-the-update-server). |
| **Developer URL** | Your website. Shown as "Website". |
| **Developer Email** | A contact address. Shown to reviewers who say they have a problem with the extension. |
| **Changelog URL** | Your `changelog.xml` (the Joomla changelog format) or a changelog page. |

### Link checks on the form

Every address field is checked as you type:

1. **Format** - immediately, in your browser: it must start with `https://` or `http://`, contain
   a domain name (not an IP address), no spaces, no username/password, at most 255 characters.
2. **Does it answer?** - shortly after you stop typing, the JED asks the address and shows a
   notice underneath, for example:
   - "This link answered." / "This link answered, after redirecting to..."
   - "This link returns a downloadable file (zip)." / "This link returns a page rather than a
     file. That is fine if it is your download page."
   - "This is a valid update server. Newest version advertised: 2.3.1." / "This feed advertises
     2.3.0, which is older than the 2.3.1 on your listing." / "This looks like your extension's
     manifest rather than its update server feed."
   - "This is a valid changelog with 12 entries, newest 2.3.1."
   - "This repository is publicly reachable." / "This repository cannot be opened without
     logging in."
   - "Your server turned our check away with a 403. That is often bot protection rather than a
     broken link - please open it yourself to be sure."
   - "This domain name does not resolve. Check it for a typo."

   Use **Check again** to repeat the check.

These notices are **advice only**: a link that fails the check never stops you from saving. On
save, the format rules are enforced.

The links on published listings are also checked every few days - see
[Security, VEL and abandonware](security-vel-and-abandonware.md#broken-link-notices).

## Media

| Field | Notes |
|---|---|
| **Logo** | The listing image. |
| **Video (URL)** | One YouTube or Vimeo video, or an MP4, WebM or OGV file you host. Links to channels, user pages or playlists are not accepted. The video only loads when a visitor clicks it. |
| **Images (Up to 8)** | Screenshots: PNG, JPEG or GIF, by default at most **2 MB** and **4000 pixels** in width or height. Smaller copies are generated automatically. Each image has a status; you can remove an image by ticking it for deletion. |

## Organisation

The organisation the listing belongs to. Every active member of that organisation can edit the
listing and answer its reviews. On a new listing choose one of your organisations (or leave your
personal one); on an existing listing it is shown only - moving a listing to another organisation
is a handover. See [Organisations and ownership](organisations-and-ownership.md).

## Linked Extensions

| Field | Notes |
|---|---|
| **Add-on For** | The extension this one is an add-on for. Shows on **your** listing as "Add-on for *...*". It only appears on the other extension's page (under "Add-ons for this extension") once the JED team confirms it. |
| **Free/Paid Version** | The free or paid version of this same extension, listed separately. Both listings then show each other under "Also available as". You must own or maintain **both** listings; otherwise ask the JED team. |

## Files

Upload your **installation package** (zip, tar, gz, tgz or bz2). It is used by the JED team to
check your extension (JED Checker) and is not offered to visitors - visitors use your Download
link. Always upload your **latest** version. You can delete an old file by ticking it for
deletion.

## Fields you do not edit

Some information is managed by the JED and not on your form:

- the **Version** shown on the listing (from your update server);
- the **scores** and review count (from published reviews);
- the **owner** (change it with a handover);
- approval, blocking and deletion.
