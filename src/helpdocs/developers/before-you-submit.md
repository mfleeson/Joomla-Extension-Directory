# Before you submit

A little preparation avoids most rejections. The JED team checks every submission against the
rules in the Knowledgebase and with **JED Checker**.

## 1. Have an account

Register on the JED (or sign in with the Joomla Identity Portal). The account that submits a
listing becomes its **owner**.

## 2. Check your package with JED Checker

Run your installation package through **JED Checker** (itself listed on the JED) and fix every
error. Extensions with errors are rejected. Common problems it finds:

- `error_reporting(0)` in your code (reason **ER1**);
- hidden files or folders such as `__MACOSX` or `.DS_Store` in the zip;
- missing `defined('_JEXEC')` checks and similar coding issues.

## 3. Name it correctly

The **install name** (the name in your XML manifest and language files) must match the **listing
name** (reason **NM1**). The naming conventions in the Knowledgebase article *Extension Names*
apply, in particular:

- no words such as *component*, *module*, *plugin* or *extension* in the name (**NM3**);
- extension-specific add-ons are named "*{Extension Name}* for *{Parent Extension}*" (**NM2**);
- "Joomla" may only appear as "*{Extension Name}* for Joomla" unless you have trademark approval
  (**TM2**);
- plugins are named "*{Type}* - *{Extension Name}*".

## 4. Licence

- The extension must be **GPL** (or GPL-compatible) licensed - GNU/GPL or AGPL; LGPL only for
  libraries (**LC1**, **LC4**).
- No additional restrictions on top of the GPL (for example limiting use to a number of domains).
  You may sell *support* per domain.
- A **paid** listing must link to a licence or terms page on your site (**LC2**), and that page
  must mention your extensions (**LC3**).

## 5. Joomla Update System

Your extension must integrate the **Joomla Update System** (an update server in the manifest),
or be an add-on managed by a third-party update system (**US1**). Have the address of your
**update server XML** ready - the JED also uses it to keep the version on your listing up to date
automatically. See the Knowledgebase article *The Update XML File*.

## 6. Download link rules

- Link directly to the download or to the product page; directly to the file if no registration
  is needed.
- Not to "extension installers", and not to distribution sites that also distribute non-GPL
  Joomla extensions.
- If you offer several versions (e.g. free and paid), the download link must lead to the version
  shown on the listing (**LK2**).

## 7. Backlinks

If your extension outputs a backlink: users must be able to remove it, it must not be obfuscated
(no base64), there may be only one, and it may only point to your distribution site.

## 8. Gather what you will need

- Title, a short introduction and a longer description (Markdown is supported - see the
  [fields reference](listing-fields-reference.md)).
- Category (one main category, optionally more).
- Links: download, support, documentation, demo, source code, licence, changelog, your website.
- A **logo** and up to **8 screenshots** (PNG, JPEG or GIF; by default up to 2 MB and 4000
  pixels wide or tall).
- Optionally a **video** link (a single YouTube or Vimeo video, or an MP4/WebM/OGV file).
- Your **installation package** (zip, tar, gz, tgz or bz2) to upload for the JED team's checks.

## 9. Limits

- You can have at most **3 listings awaiting approval** at any time. Wait for one to be reviewed
  before submitting another.
- The JED team can restrict an account (for example withdraw the right to submit). You will be
  told the reason if there is one.

Next: [Submitting a new extension](submitting-an-extension.md).
