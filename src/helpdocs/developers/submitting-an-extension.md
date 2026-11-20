# Submitting a new extension

Make sure you have been through [Before you submit](before-you-submit.md) first.

## Start the submission

Sign in, go to **My Dashboard** and choose **Submit an extension** in the *My Extensions* card
(or open the **Submit a New Extension** page from the menu).

## Step 1 - let the JED read your manifest (optional)

The first page offers three ways to start:

| Option | What happens |
|---|---|
| **Read from the package** | Drop your installation zip on the upload area (or click to choose the file) and choose **Read the package**. The JED reads the manifest inside it. |
| **Read from GitHub** | Enter the address of a **public GitHub repository** and choose **Read the repository**. The JED reads the manifest of the repository's **latest release**. |
| **Fill in the form by hand** | Skip this step and enter every detail yourself. |

When the package or repository is read successfully, the page shows what was detected - the
**title**, **extension type**, **update server URL**, **developer URL**, **developer e-mail** and
**changelog URL** - and a **Continue** button appears. These values are pre-filled in the form in
step 2; you can still change them.

If reading fails you see "The manifest could not be read" (or a more specific message, such as no
release found on GitHub, or no valid Joomla manifest in the package). Check the package, or fill
the form in by hand.

## Step 2 - complete the listing form

The form has one tab per group of fields. The full list, with what each field is for, is in the
[Listing fields reference](listing-fields-reference.md). In short:

| Tab | Contents |
|---|---|
| **Common** | Title (required), alias, main category (required), additional categories, tags |
| **Info** | Intro text, description, licence name and licence URL, whether registration is required to download, type (Free/Paid/Freemium/Cloud), extension types included, Joomla versions |
| **Links** | Download, support, demo, documentation, Git and internal download links, download key, the Joomla Update System confirmation, update URL, developer URL and e-mail, changelog URL |
| **Media** | Logo, video link, up to 8 images |
| **Organisation** | The organisation that will own the listing - your personal organisation or one you are a member of (see [Organisations and ownership](organisations-and-ownership.md)) |
| **Linked Extensions** | "Add-on For" and "Free/Paid Version" |
| **Files** | Your installation package, for the JED team's checks |

While you type a web address, the form checks it (see
[Link checks on the form](listing-fields-reference.md#link-checks-on-the-form)). These checks are
advisory: they never stop you saving.

Choose **Submit**.

## What happens after you submit

1. Your listing is created **awaiting approval**. It is not public yet.
2. A **ticket** is opened for the JED team, and you receive two e-mails: a general
   *Confirmation of Submission* for the ticket and *Listing Submission Received* for the listing.
3. You are taken to the edit form of your new listing. You can keep improving it while you wait -
   each save simply updates what the JED team will review.
4. The JED team reviews the listing (usually running JED Checker against the package you
   uploaded, or against your download link).
5. You are e-mailed the decision:
   - **"Your extension *name* is now listed"** - approved. The listing is **online** straight away;
     you can take it offline from your dashboard at any time.
   - **"Your extension *name* was not accepted"**, or a specific message for the reason code
     (for example *NM1 - Install Name Doesn't Match Listing Name*). The reviewer's notes are posted
     on your listing's ticket in **My Tickets** and shown on your dashboard. The listing is **not
     deleted** - correct the point, save the listing again from your dashboard, and it will be
     looked at afresh.

If your account is marked as **trusted** by the JED team, the submission is approved automatically
and you get the approval e-mail straight away.

Follow the status in **My Dashboard > My Extensions** - see [Listing status](listing-status.md).

## If you cannot submit

| Message | Meaning |
|---|---|
| "You already have 3 listings awaiting approval..." | Wait until one of your pending submissions has been reviewed. |
| "Your account is not permitted to submit listings." | The JED team has withdrawn the submit privilege on your account. |
| "Your account is currently suspended: *reason*" | Your account is banned for now. |

Open a support ticket (*New Listing Support*) if you have questions about a submission.
