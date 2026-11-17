# Automation: scheduled tasks, link checks, update checks and audits

Much of the JED's routine work runs in the background as Joomla **Scheduled Tasks**
(**System > Scheduled Tasks**). The tasks come from the plugins *Task - JED* and *Task - VEL*, which
must be enabled. If a task is not scheduled, the feature it drives simply does not happen - check
this list after installing or moving the site.

## The tasks

| Task | Suggested schedule | What it does |
|---|---|---|
| **JED - Process Queue** | Every few minutes | Runs queued jobs: score recalculations, automated audits, on-demand link checks. Reclaims jobs stuck longer than the *Job Timeout* (default 900 s). *Batch Size* default 2 (audits are heavy). |
| **JED - Check Extension Updates** | Hourly or a few times a day | Reads each listing's update server (listings with the Joomla Update System confirmed and an update URL) and applies any newer version directly to the live listing; queues an audit for the new version. *Batch Size* default 50; *Force Recheck* ignores the cool-down. |
| **JED - Check Extension Links** | Every 30 minutes | Checks the links on public listings in rotation - see below. *Batch Size* default 200 (about three days for the whole catalogue); *Force* ignores the interval (one-off sweeps only). |
| **JED - Aggregate Hit Statistics** | Daily | Rolls the raw view/download log into daily per-listing figures and removes raw rows after 30 days. *Days to Recompute* default 2. |
| **JED - Apply Privacy Retention** | Daily | Removes personal data at the end of its retention period - see below. |
| **JED - Collect Abandonware Signals** | Daily | Opens abandonware cases from unattended links, failing update servers and (if enabled) long inactivity; expires grace periods; closes cases whose listing changed hands. Opens cases only - never marks an extension abandoned. *Batch Size* default 50 per signal. |
| **VEL - CNA feed** (`vel.feed_cna`) | Hourly | Polls the CVE Program for new and changed records |
| **VEL - NVD feed** (`vel.feed_nvd`) | Every 4 hours | Polls the NVD |
| **VEL - Mail fetch** (`vel.mailfetch`) | Every 5 minutes (only with IMAP) | Threads incoming mail into VEL cases |
| **VEL - Housekeeping** (`vel.housekeeping`) | Daily | Purges unverified guest reports, expires case links and similar |

Each run writes a one-line summary to the task log, e.g. "jed.linkcheck: checked 200 (ok 190, hard
4, soft 5, semantic 1), 2 developer tickets, 0 escalated, 1 recovered."

## Link checks

The periodic link check uses the same checks as the listing form. For each public listing it checks
the download link and update server (weighted most heavily), then website, support, demo,
documentation, licence, changelog and source repository links. Each link is checked about every
**72 hours**, with a pause between requests to the same host so that no developer's site is flooded.

Results: *Working*, *Gone* (hard failure), *Turned away* (e.g. bot protection), *Wrong content*
(e.g. a page where a file was expected).

Escalation counts **consecutive** weighted failures and resets on any success:

1. **3 failures** (~9 days): the **developer** is e-mailed and a **Link Check** ticket is opened for
   them. Developers are told first - most fix it in minutes.
2. **6 failures**: the listing is flagged **Left unattended** for the JED team, which feeds the
   abandonware *Dead links* signal.
3. When the link answers again, the ticket is annotated and **closed automatically**.

In **Extensions**, the **Links** filter shows *Has a broken link*, *Left unattended*, *Developer
notified* and *All links working*.

## Update checks

For listings with an update URL, the update check keeps the listing's **Version** current without
moderation and records the change as an (inactive) revision. If the update server keeps failing for
longer than the abandonware tolerance (60 days by default), an abandonware case can be opened.

## Automated audits

When configured (**JED > Options > Audit**: an Anthropic API key and model, and the Docker binary
path), a new version triggers an audit job: the package is downloaded and analysed in a sandbox
(static analysis with phpstan and an AI security review). Both reports are stored, e-mailed to the
developer (*audit report* mail template) and shown on the **JED Checker** tab of tickets about that
listing to users with **View Audit Results**. Audit results are never public.

## Scores

Publishing or unpublishing a review queues a score recalculation for its listing; **Recalculate
Score** on the listing queues one by hand. Both need *JED - Process Queue* to run.

## Privacy retention

*JED - Apply Privacy Retention* removes, whether or not anybody asked:

| Data | Default retention |
|---|---|
| The IP address a review was submitted from (the review stays) | 180 days |
| Ownership-transfer recipient lookups | 90 days |
| Form-time URL checks | 365 days |

0 keeps data indefinitely. Data export and erasure requests (Users > Privacy) are handled by the
*Privacy - JED* and *Privacy - VEL* plugins. When a listing owner's account is erased, the listing
is blocked with reason **PV1** ("No owner").

## Search index

Approvals, blocks, deletions, restores and state changes refresh the listing in Smart Search
automatically (via the Smart Search content plugin and the *Smart Search - JED* plugin). After a
large import, run **Index** in Smart Search once.

## Action log

With the *Action Log - JED* and *Action Log - VEL* plugins enabled, **Users > User Actions Log**
records listing approvals, rejections, blocks, unblocks, deletions, restores, maintainer changes,
ownership transfers, review publish/unpublish decisions, link-check transitions, and the VEL's
publish/retire/status/CVE decisions.
