# Updating your extension

## Editing your listing

1. Go to **My Dashboard > My Extensions**.
2. Click the **name** of the listing. The edit form opens with the same tabs as when you
   submitted it (see the [Listing fields reference](listing-fields-reference.md)).
3. Make your changes and choose **Submit**. **Cancel** leaves without saving.

Owners and accepted **maintainers** can edit a listing.

### What happens when you save

- Your changes to the listing's **details** - title, alias, main category, intro, description,
  licence, type, Joomla versions, links, logo, video and linked extensions - are stored as a new
  **revision**. **The public listing does not change yet**: it keeps showing the last approved
  details.
- Some things are stored straight away and are **not** held back for review: the **additional
  categories**, **tags**, **screenshots** (images added or deleted), **uploaded files** and the
  **maintainers** list.
- A ticket is opened for the JED team ("Listing *name* was edited by its developer") and you
  receive a *Listing Update Received* e-mail.
- The JED team compares your revision with the live listing and approves it. You are e-mailed
  "Your changes to *name* have been approved", and the changes go live. If your listing is
  offline, it stays offline.
- If the JED team does not accept the change, you are e-mailed the reason, and the reviewer's notes
  are posted on the listing's ticket in **My Tickets**. **An approved listing stays online** - only
  the edit is refused. Correct the point and save again.
- If your account is **trusted**, your edits are approved automatically.

If you save several times before the team gets to it, only your most recent revision is
reviewed - you do not need to wait between saves.

## New versions: the update server

The **Version** shown on your listing is not typed in on the form. It comes from your
**update server**:

1. On the **Links** tab, confirm the *Joomla Update System* statement (**Yes**) and enter your
   **Update URL** - the address of your update server XML.
2. The JED reads that address regularly. When it advertises a **newer version** than the one on
   your listing, the listing's version is updated **immediately** - no moderation is needed for a
   version bump.

Tips:

- Link the update XML of **this extension**, not a *collection* file listing several update
  servers - the JED cannot read a version out of a collection.
- Do not link your extension's manifest; it is not an update feed.
- The form's link check tells you what the JED sees, e.g. "This is a valid update server. Newest
  version advertised: 2.3.1" or "This feed contains no `<update>` entries, so Joomla will never
  offer an update from it."
- If your feed advertises an **older** version than the listing shows, the form warns that users
  are not being offered the version the JED shows.

If the JED has automated code audits switched on, a new version found this way can also trigger an
**automated audit** of the package; you are e-mailed the report. Audit results are never public.

## Screenshots, logo and video

On the **Media** tab:

- replace the **Logo**;
- add images with **Images (Up to 8)**, or tick existing ones for deletion;
- change the **Video (URL)**.

A new logo or video waits for approval with the rest of your revision; screenshots you add or
delete change straight away.

## Uploading a new package

On the **Files** tab, upload your latest installation package and tick old files for deletion.
The uploaded package is what the JED team checks; visitors download from your Download Link.

## Taking a listing offline or deleting it

**Offline:** use **Take offline** in **My Dashboard > My Extensions** (and **Put online** to bring it
back) - see [Listing status](listing-status.md#taking-a-listing-offline-and-back-online).

**Deleting** a listing is done by the JED team: open a support ticket under **Current Listing
Support**. See the Knowledgebase article *Editing & Deleting Listings*.

## Renaming your extension

Change the **Title** on the Common tab and save; the new name goes live once approved. Remember
that the listing name must match your extension's **install name**, so release the renamed
extension too. If you are asked by the JED team to rename (reason codes **NM1**, **NM2**,
**NM3**, **TM2**), follow the instructions in their e-mail - for some of these the team changes the
listing name for you once your package has been updated.

## When you cannot edit

| Message | Meaning |
|---|---|
| "Your account is not permitted to edit listings." | The JED team has withdrawn the edit privilege on your account. |
| "Your account is currently suspended..." | Your account is banned for now. |
| The listing is shown as checked out | Someone (you in another window, or a JED team member) has it open. Try again later. |

A **blocked** listing can still be edited - fixing the cause is exactly what is being asked of you.
A **deleted** listing cannot be edited.
