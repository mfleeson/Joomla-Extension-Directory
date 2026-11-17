# How to handle a security advisory on a listing

When the VEL team publishes an advisory that names a JED listing, they can hand the JED team a
**Vulnerability** ticket: "a published advisory names this listing; block it or confirm the fixed
version is listed". Blocking is the **JED team's** decision - the VEL never blocks anything itself.

The ticket carries only **public** information about the advisory - nothing confidential crosses
from the VEL into the ticket queue.

## Steps

1. Open the ticket. The **Linked Vulnerability** tab shows the advisory: title, status, severity,
   the affected products and their fixed versions.
2. Decide:

   **The advisory is Live (no fix) and the listing offers the vulnerable version**
   - On the **Linked Vulnerability** tab, choose **Block Listing**. Confirm "Block this listing as 'Listed on the Vulnerable Extension
     List'?". The listing is blocked with reason **VL1**, and the internal note records the advisory
     reference (`VEL-0042`).
   - Tell the developer: reply on the ticket or open one for them using the template *Extension -
     Listed on VEL* - they must release a fix and submit a fix notice to the VEL.

   **The advisory is Patched**
   - Check the listing's **Version** is at or above the fixed version (the update check normally
     takes care of this). If not, ask the developer to update their update server/listing.
   - No block is needed.

3. When the fix is confirmed by the VEL (the advisory becomes *Patched*) and the listing shows the
   fixed version, open the ticket again and choose **Unblock Listing** (or **Unblock** on the
   listing's edit screen). Both actions bring you back to the ticket.
4. Set the ticket status and close it.

The public listing shows a red or amber security banner while an advisory names it, regardless of
the block.
