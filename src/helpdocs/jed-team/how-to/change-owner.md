# How to change the owner of a listing

Ownership only ever changes through a **handover** - either the developers' own, or a **forced
transfer** by the JED team. The *Owner* field on an existing listing's edit form is read-only, and
approving a revision never changes the owner.

## Preferred: the developers do it themselves

If both the current owner and the new owner are active, ask the **current owner** to use the
**Handovers** card on their dashboard (see the developer guide
[Maintainers and ownership](../../developers/maintainers-and-ownership.md)). Both confirm by e-mail
link, the handover completes, both are notified. Nothing for you to do.

## When the owner cannot be reached: forced transfer

Typical for abandoned extensions that someone wants to adopt, or when an owner has lost access to
their account.

Permission needed: **Force Ownership Transfer** (`jed.transfer.force`).

1. Establish that the change is justified - note why in the ticket's **internal notes** or on the
   abandonware case.
2. Make sure the new owner has an **activated, unblocked** account on the JED.
3. **Components > JED > Extensions**, open the listing and go to the **Ownership** tab. It shows the
   **Current owner** and any **Handover in progress**.
4. Under *Transfer ownership without the owner's confirmation*, enter the **New owner's account
   e-mail** and the **Reason** (both required).
5. Choose **Transfer ownership now** and confirm. Unsaved changes on the other tabs are **not**
   saved - save them first if you made any.

"The listing now belongs to *name*. The previous and the new owner have both been e-mailed the
reason."

What happens:

- the listing's owner changes at once; the previous owner loses all access (they do not become a
  maintainer);
- both owners receive "*name* has been reassigned" with your reason;
- an open handover is called off, and its parties are told;
- if the new owner was a maintainer, they are now simply the owner;
- the transfer is recorded as *forced* and appears in the **User Actions Log**;
- an open abandonware case for the listing is closed automatically by the next signal scan
  ("the listing has changed hands") - or close it yourself with *Taken over by a new maintainer*.

If something is wrong (no reason, no usable account at that address, the address is the current
owner's, the listing is deleted, or you looked up too many addresses today - the limit is 10 in 24
hours), you are returned to the edit screen with the error.

Reviews and developer responses stay as they are. The listing's *created by* (authorship record)
never changes.
