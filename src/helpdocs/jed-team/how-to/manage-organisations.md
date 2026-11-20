# How to manage organisations

Listings belong to **organisations** (since 4.1). Every active member of an organisation can edit
all of its listings and answer their reviews; administrators also run the organisation; the
**primary contact** is the accountable person and is shown as the listings' owner. Developers
normally manage their organisations themselves on the site (**My Organisations**).

## Finding an organisation

**Components > JED > Organisations.** Search by name, code (`ORG-XXXX-XXXX`), contact e-mail or
primary contact. *With open requests* shows organisations whose administrators have requests
waiting. The listing count links to the Extensions list filtered by that organisation (search
`org:ORG-XXXX-XXXX`).

A listing's edit screen shows its organisation (tab **Organisation**) with the active members.

## Adding, removing and changing members as the JED team

Open the organisation. In **Members**:

- **Add member**: pick the user and the role. The JED team adds **directly** - no invitation - and
  the user is e-mailed.
- **Approve / Reject** waiting requests.
- **Make administrator / Make member**, **Remove**, **Withdraw invitation**.
- **Make primary contact**: the new primary contact must be an administrator; every listing of the
  organisation then names them as owner.

Every change is recorded in the **User Actions Log**.

The structural rules apply to the team too: the primary contact cannot be removed or demoted until
the role has been handed on.

## Disabling an organisation

**Disable** (toolbar, or *Status* on the edit screen) freezes the organisation: its members keep
seeing their listings, but nobody can act on its behalf until it is enabled again.

## Deleting an organisation

Only an organisation that owns no listings (including deleted ones) can be deleted. Transfer its
listings first (**Change the owner of a listing**).

## Creating an organisation for somebody

**New**, fill in the details and choose the **primary contact** (the user who will run it).

## After importing data

The JED3 import writes owners without organisations. **Migrate owners** (toolbar, Super Users)
gives every such owner a personal organisation and converts their maintainers; it is safe to run
more than once.
