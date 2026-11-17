# How to rename an extension

A listing's name must match the extension's **install name** (the name in its manifest and
language files). Renames therefore usually follow a new release by the developer.

## When the developer asks (or you asked them - codes NM1, NM2, NM3, TM2)

1. Check that the new name follows the naming conventions (Knowledgebase: *Extension Names*) and
   that the developer's current package uses it (run **JED Checker** from the ticket, or check the
   package on the listing's **Files** tab).
2. Open **Components > JED > Extensions** and click the listing's title.
3. On the **Common** tab change **Title**. Change **Alias** too if the web address should follow
   the new name - be aware that changing the alias changes the listing's URL.
4. Choose **Save & Close**. This creates a **pending revision**; the live name has not changed yet.
5. In the Extensions list, click the orange **Pending** icon for the listing. The Compare page
   highlights the changed title.
6. Choose **Approve**. The new name is now live and in the search index; the developer receives
   "Your changes to *new name* have been approved".
7. Reply on the developer's ticket and close it.

## When the developer renames it themselves

Developers can change the title in their own edit form. It arrives as a normal pending edit with
an Extension ticket - compare and approve it as usual (see
[Approve or reject a listing](approve-or-reject-a-listing.md)).

## Things to check

- If another listing links to this one as **Add-on For** or **Free/Paid Version**, nothing needs
  changing - links are by listing, not by name.
- If the listing appears in an open **VEL advisory** or **abandonware case** under its old name,
  let the VEL team know / update the case's *Extension name*.
