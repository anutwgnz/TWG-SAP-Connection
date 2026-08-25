# TWG SAP Connection

WordPress/WooCommerce plugin that connects to SAP Business One Service Layer to sync products and orders.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- WooCommerce (required for product sync and Action Scheduler background jobs)
- SAP credentials configured under **SAP Connection Settings** in wp-admin

## Current Status

| Area | Status |
|------|--------|
| Nightly SAP → JSON download | Action Scheduler daily at **midnight** (`twg_sap_scheduled_download`) |
| Nightly JSON → Woo sync | Action Scheduler daily at **1:00 AM** (`twg_sap_scheduled_sync`) — includes orphan drafting |
| Dry-run product download | Action Scheduler + WP-CLI (`dry-run enqueue`, `status`, `run-now`) — test download only |
| Product audit CLI | Read-only JSON vs Woo comparison (`audit report`, `orphans`, `preview`) |
| Product reconciliation CLI | Manual preview/apply (`reconcile-products`) |
| Legacy WP-Cron midnight hook | Cleared on activation — replaced by Action Scheduler |
| Order sync | Active via admin **Order Sync** page |
| History Logs | Active |

## Why Woo Products May Be Missing From JSON

| Cause | Explanation |
|-------|-------------|
| SAP filter | Download only includes `U_PRX_WbAv = Y` and `Valid = tYES` |
| JSON depletion | `sap_sync_products()` removes processed items from JSON during sync; file is repopulated by the midnight download job |
| Stale JSON | Sync ran before download, or download failed |

Previously, orphan drafting only ran via manual CLI (`reconcile-products --apply`) and the sync job was not scheduled. **The nightly 1 AM sync job now drafts orphans automatically.**

## Nightly Production Pipeline (Action Scheduler)

Two daily recurring jobs, **1 hour apart**:

| Time | Hook | Action |
|------|------|--------|
| Midnight | `twg_sap_scheduled_download` | `fetch_meta_sap()` + `fetch_products()` → JSON files |
| 1:00 AM | `twg_sap_scheduled_sync` | Snapshot JSON SKUs → `sap_sync_products()` → draft Woo SKUs not in JSON |

```text
Midnight:  SAP → JSON (brands, types, sectors, product_codes.json)
    │
    │  (1 hour)
    ▼
1 AM:      JSON → WooCommerce
           ├── Update/create products in JSON
           ├── Set status publish/private from SAP flags
           └── Draft Woo SKUs missing from JSON snapshot
```

### Product status rules

| Scenario | Result |
|----------|--------|
| SKU in JSON | Product updated; status `publish` or `private` via `U_Pub_Flg` + `U_PRX_WbAv` |
| SKU in Woo, not in JSON | Set to **draft** during sync job |
| Draft product returns in JSON | Updated and status restored (`publish`/`private`) on sync job |

Status changes are logged under `Woo-Product` and `Woo-Product-Reconcile` in `sap_sync_logs/`.

### Process Action Scheduler jobs

```bash
wp action-scheduler run --group=twg-sap-connection
```

Recommended server cron (every 5 minutes):

```bash
*/5 * * * * cd /var/www/html && wp action-scheduler run --group=twg-sap-connection
```

## Production Schedule CLI (Manual Runs)

Same orchestrators as the nightly jobs — run from SSH without waiting for midnight.

```bash
# Check next run times and last results
wp twg-sap-connection schedule status

# Run immediately (synchronous)
wp twg-sap-connection schedule run_download_now
wp twg-sap-connection schedule run_sync_now
wp twg-sap-connection schedule run_full_now

# Queue for background processing
wp twg-sap-connection schedule enqueue_download
wp twg-sap-connection schedule enqueue_sync
wp action-scheduler run --group=twg-sap-connection
```

**Recommended manual catch-up:**

```bash
wp twg-sap-connection schedule run-full-now
```

## Dry-Run Product Download (Test Only)

Fetches SAP data to JSON **without** WooCommerce changes. Separate from production schedule.

```bash
wp twg-sap-connection dry-run enqueue
wp twg-sap-connection dry-run status
wp twg-sap-connection dry-run run-now
```

## Product Audit CLI (Read Only)

```bash
wp twg-sap-connection audit report
wp twg-sap-connection audit report --compare-sap --limit=50
wp twg-sap-connection audit report --format=json --output=audit_report.json
wp twg-sap-connection audit orphans
wp twg-sap-connection audit preview --sku=01547330
```

## Product Reconciliation CLI (Manual)

The nightly sync job drafts orphans automatically. Use these for manual preview/apply outside the schedule:

```bash
wp twg-sap-connection reconcile-products
wp twg-sap-connection reconcile-products --apply
```

## Logging

- Log files: `{ABSPATH}/sap_sync_logs/sap_logs_{Y_m_d}.log`
- Admin page: **SAP Connection → History Logs**
- Production jobs log under `Cron-Job` prefix

## Admin Settings

| Setting | Default filename |
|---------|------------------|
| Product Codes JSON URL | `product_codes.json` |
| Product Brand JSON URL | `product_brands.json` |
| Product Type JSON URL | `product_types.json` |
| Product Sector JSON URL | `product_sectors.json` |

JSON files: `wp-content/uploads/SAP_Connection/`

## Plugin Structure

```text
includes/
  Jobs/
    ScheduledDownload.php   # Nightly SAP → JSON
    ScheduledSync.php       # Nightly JSON → Woo + reconcile
    SchedulerSupport.php    # AS scheduling helpers
    DryRunDownload.php      # Test download job
  Cron/
    ScheduleCliCommand.php
    WpCli.php
    WpCron.php              # Legacy WP-Cron callbacks only
  Admin/
    Product.php
    Common.php
```

## Deployment

```bash
cd /path/to/wp-content/plugins/twg-sap-connection
composer install --no-dev
```

WP-CLI required for manual schedule, audit, and dry-run commands.

After plugin update or re-activation, Action Scheduler jobs are ensured on `init` via `SchedulerSupport::ensure_scheduled()`.
