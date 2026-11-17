# How to recalculate an extension's score

An extension's scores (overall and the five dimensions) and its review count are recalculated
automatically whenever a review of it is published or unpublished. If the numbers look wrong -
for example after editing reviews directly or importing data - recalculate by hand:

1. **Components > JED > Extensions**, open the listing.
2. Choose **Recalculate Score** in the toolbar (permission: **Edit**).
3. "Score recalculation has been queued for this extension."

The recalculation runs with the next **JED - Process Queue** scheduled task (see
[Automation](../automation.md)); if that task is not running, nothing happens.

## How the score is calculated

The default algorithm (plugin *JED - Score Average*) takes the listing's **published** reviews and
averages each dimension - Functionality, Ease of Use, Support, Documentation, Value for Money -
across them; the **overall** score is the average of the five dimension averages. The review count
is the number of published reviews.

A different scoring algorithm can be installed as another plugin in the `jed` plugin group with a
lower ordering; it then takes precedence over the default.
