# How to change the owner of a listing

Listings belong to **organisations** (since 4.1); the listing's *Owner* is the owning
organisation's **primary contact**. Ownership only ever changes through a **handover** between
organisations - either the developers' own, or a **forced transfer** by the JED team. The
*Organisation* and *Owner* fields on an existing listing's edit form are read-only, and approving a
revision never changes either.

To change **who can edit** a listing without moving it, change the organisation's members instead
(see [Manage organisations](manage-organisations.md)). To change the **owner named on the
listing**, make another administrator the organisation's primary contact.

## Preferred: the developers do it themselves

If both organisations are active, ask an **administrator of the current organisation** to use the
**Handovers** card on their dashboard with the receiving organisation's code (see the developer
guide [Organisations and ownership](../../developers/organisations-and-ownership.md)). Both primary
contacts confirm by e-mail link, the handover completes, everybody is notified. Nothing for you to
do.

## When the organisation cannot be reached: forced transfer

Typical for abandoned extensions that someone wants to adopt, or when everybody behind a listing
has lost access.

Permission needed: **Force Ownership Transfer** (`jed.transfer.force`).

1. Establish that the change is justified - note why in the ticket's **internal notes** or on the
   abandonware case.
2. **Components > JED > Extensions**, open the listing and go to the **Ownership** tab. It shows the
   **Organisation**, the **Current owner** (its primary contact) and any **Handover in progress**.
3. Under *Transfer ownership without the owner's confirmation*, enter **either** the receiving
   **organisation's code** (`ORG-XXXX-XXXX`) **or** the new owner's **account e-mail** - the listing
   then goes to that person's own organisation, created for them if they have none - and the
   **Reason** (required).
4. Choose **Transfer ownership now** and confirm. Unsaved changes on the other tabs are **not**
   saved - save them first if you made any.

What happens:

- the listing moves to the new organisation at once; the previous organisation's members lose all
  access;
- both primary contacts receive "*name* has been reassigned" with your reason;
- an open handover is called off, and its parties are told;
- the transfer is recorded as *forced* and appears in the **User Actions Log**;
- an open abandonware case for the listing is closed automatically by the next signal scan
  ("the listing has changed hands") - or close it yourself with *Taken over by a new maintainer*.

If something is wrong (no reason, no active organisation with that code, no usable account at that
address, the listing already belongs there, the listing is deleted, or you looked up too many
addresses today - the limit is 10 in 24 hours), you are returned to the edit screen with the error.

Reviews and developer responses stay as they are. The listing's *created by* (authorship record)
never changes.
