# Adoology for WooCommerce

Adoology for WooCommerce connects a WooCommerce store to an Adoology workspace for catalog, customer, order, and inventory synchronization. It also provides durable storefront event delivery, opt-in incomplete-order tracking, local fraud protection, and a cart-independent landing-page order form.

Current plugin version: `0.1.0`

## Contents

- [Features](#features)
- [Requirements](#requirements)
- [Architecture](#architecture)
- [Installation](#installation)
- [Backend preparation](#backend-preparation)
- [Connecting a store](#connecting-a-store)
- [Admin pages](#admin-pages)
- [Configuration](#configuration)
- [Synchronization](#synchronization)
- [Incomplete-order tracking](#incomplete-order-tracking)
- [Fraud protection](#fraud-protection)
- [Landing-page order form](#landing-page-order-form)
- [Background processing](#background-processing)
- [Security](#security)
- [Privacy and data retention](#privacy-and-data-retention)
- [REST endpoints](#rest-endpoints)
- [Local storage](#local-storage)
- [Logging and monitoring](#logging-and-monitoring)
- [Disconnecting and uninstalling](#disconnecting-and-uninstalling)
- [Troubleshooting](#troubleshooting)
- [Development](#development)

## Features

- Adoology workspace authentication with `dc_` workspace API keys.
- Native WooCommerce authorization for read/write API access.
- Secure connection state signed with HMAC-SHA256.
- Backend-managed WooCommerce webhooks and initial synchronization.
- Catalog, variation, customer, order, and inventory synchronization through the Adoology API.
- Manual product synchronization and recent sync-run visibility.
- Durable encrypted event outbox with leases, retries, and dead-letter status.
- Opt-in incomplete checkout tracking for classic and block checkout.
- Incomplete-order lifecycle: `started`, `incomplete`, `converted`, `recovered`, and `expired`.
- Local fraud scoring for classic checkout, Checkout Blocks, and Adoology order forms.
- Cart-independent order form available as a shortcode and dynamic block.
- WooCommerce payment, order, stock, and HPOS compatibility.
- WordPress privacy-policy text, personal-data exporter, and personal-data eraser.
- Signed backend callback for revoking the exact WooCommerce API key during disconnect.
- Multisite-aware data cleanup. Network activation is intentionally rejected; activate per site.

## Requirements

| Component | Minimum or requirement |
| --- | --- |
| WordPress | 6.0 or newer |
| PHP | 7.4 or newer |
| WooCommerce | 8.0 or newer |
| PHP extension | OpenSSL with AES-256-GCM support |
| Store URL | Publicly reachable; HTTPS strongly recommended |
| Adoology API | Public HTTPS URL with valid DNS and TLS |
| WordPress REST API | Must be reachable on the store domain |
| Background tasks | WP-Cron or a real cron runner; WooCommerce Action Scheduler is used when available |
| Permissions | WordPress user with `manage_woocommerce` |

Connection requests reject unsafe, private, malformed, redirected, or versioned API base URLs. Enter the API origin only, for example:

```text
https://api.adoology.com
```

Do not enter:

```text
https://api.adoology.com/v1
https://api.adoology.com/api
http://localhost:8000
```

Use a public HTTPS tunnel for local integration testing.

## Architecture

The plugin and Adoology backend divide responsibilities:

| Responsibility | Owner |
| --- | --- |
| Store credentials and local settings | WordPress plugin |
| WooCommerce authorization URL | Adoology backend |
| WooCommerce API credential storage | Adoology backend, encrypted at rest |
| Native WooCommerce webhook provisioning | Adoology backend |
| Initial and manual synchronization | Adoology backend queue workers |
| Checkout tracking and local risk assessment | WordPress plugin |
| Durable storefront event delivery | WordPress plugin to `POST /v1/events` |
| Recovery automation and remote analytics | Adoology workspace |

### Connection sequence

1. Merchant saves the Adoology API origin and a `dc_` workspace API key.
2. Plugin sends store metadata to `POST /v1/channel-connections`.
3. Backend creates a connecting WooCommerce channel and returns:
   - `data.id`: channel connection ULID.
   - `meta.redirect_uri`: WooCommerce authorization URL.
   - `meta.webhook_secret`: one-time connection secret.
4. Plugin validates the authorization URL, store host, scope, callback host, and signed state before redirecting.
5. Merchant approves read/write access on WooCommerce's native authorization screen.
6. WooCommerce posts `key_id`, consumer key, consumer secret, permissions, and signed state to `POST /woocommerce/callback` on the backend.
7. Backend verifies the HMAC state, stores credentials, and queues provisioning on `channel-sync`.
8. Backend verifies WooCommerce, provisions native webhooks, and starts initial synchronization.
9. Plugin reads status and sync runs from the backend.

The authorization state format is:

```text
{connection_ulid}.{hmac_sha256(connection_ulid, webhook_secret)}
```

Plugin and backend releases that implement this contract must be deployed together.

## Installation

### WordPress admin

1. Package this repository as `adoology-connector.zip` with `adoology-connector.php` at the archive's plugin root.
2. Open **Plugins > Add New Plugin > Upload Plugin**.
3. Upload the archive and select **Install Now**.
4. Activate **Adoology for WooCommerce**.
5. Confirm the new **Adoology** menu appears in WordPress admin.

### Manual installation

Copy the plugin into:

```text
wp-content/plugins/adoology-connector/
```

Then activate it from WordPress admin or WP-CLI:

```bash
wp plugin activate adoology-connector
```

No Composer or npm installation is required by the plugin package. Runtime PHP and JavaScript are committed directly.

### Multisite

Do not network-activate the plugin. Network activation is rejected intentionally because each site needs its own API key, connection, options, tables, and authorization lifecycle. Activate it separately on each site.

## Backend preparation

Before connecting a store, the Adoology backend must be operational.

### Required production configuration

```dotenv
APP_NAME=Adoology
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.adoology.com
APP_FRONTEND_URL=https://app.adoology.com
QUEUE_CONNECTION=redis
CACHE_STORE=redis
```

Also configure the backend database, Redis, Passport keys, mail, and Laravel application key. `APP_URL` generates the WooCommerce callback URL. `APP_FRONTEND_URL` generates the browser return URL after authorization.

Run backend migrations and keep Horizon plus Laravel's scheduler running. The `channel-sync` queue is required for WooCommerce provisioning and synchronization.

Verify the backend before connecting:

```bash
curl --fail https://api.adoology.com/up
php artisan horizon:status
php artisan queue:failed
```

### Create a workspace API key

The plugin requires a workspace key beginning with `dc_`. Create it from the Adoology application, or through the API with an authenticated, verified user who has `api_keys.manage` permission:

```bash
curl -X POST https://api.adoology.com/v1/api-keys \
  -H "Authorization: Bearer YOUR_LOGIN_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"name":"WooCommerce Store"}'
```

Copy `meta.plaintext_key` from the response. Plaintext is returned only when the key is created.

## Connecting a store

1. Open **Adoology > Connection**.
2. Enter the API origin, normally `https://api.adoology.com`.
3. Enter the workspace API key beginning with `dc_`.
4. Select **Save Credentials**.
5. Select **Connect Store**.
6. Sign in as a WooCommerce manager if requested.
7. Review and approve read/write access.
8. After returning from authorization, open **Adoology > Connection** and select **Test Connection**.
9. Open **Adoology > Sync** to inspect recent runs or start a product sync.

The API key is encrypted before storage and is never rendered back into HTML. Leaving the key field blank preserves the stored value.

Changing the API URL requires disconnecting first. Changing it also removes the old workspace key, so a new key must be entered.

## Admin pages

All pages require `manage_woocommerce`.

| Page | Purpose |
| --- | --- |
| Dashboard | Connection state, queue counts, incomplete/recovered orders, flagged orders, API errors, and latest sync time |
| Connection | API URL, workspace key, connection status, connection test, and disconnect action |
| Sync | Manual product synchronization and recent backend sync runs |
| Incomplete Orders | Latest 100 tracked checkout records and lifecycle state |
| Fraud Protection | Latest 50 orders at or above the flag threshold |
| Order Form | Shortcode and block usage examples |
| Settings | Tracking, retention, fraud, and order-form controls |
| Logs | Latest 100 event outbox records and failed-event retry action |

Operational PHP logs are also available under **WooCommerce > Status > Logs** using source:

```text
adoology-connector
```

## Configuration

Default values are installed when the plugin activates.

| Setting | Option | Default | Admin range or behavior |
| --- | --- | --- | --- |
| API URL | `adoology_api_base_url` | `https://api.adoology.com` | Public HTTPS origin; no `/v1` suffix |
| Incomplete-order tracking | `adoology_tracking_enabled` | Disabled | Enable only after privacy/consent review |
| Mark incomplete after | `adoology_incomplete_timeout_minutes` | 30 minutes | 5 to 1,440 minutes |
| Incomplete data expiry | `adoology_incomplete_expire_days` | 7 days | 1 to 90 days |
| Fraud protection | `adoology_fraud_enabled` | Enabled | Covers classic, Store API, and order-form flows |
| Attempts per 10 minutes | `adoology_fraud_rate_limit` | 5 | 2 to 100 |
| Duplicate-order window | `adoology_duplicate_window_minutes` | 60 minutes | 5 to 1,440 minutes |
| Flag threshold | `adoology_fraud_flag_threshold` | 30 | 1 to 100 |
| Hold threshold | `adoology_fraud_hold_threshold` | 60 | 1 to 100; normalized to at least flag threshold |
| Block threshold | `adoology_fraud_block_threshold` | 90 | 1 to 100; normalized to at least hold threshold |
| Landing-page order form | `adoology_order_form_enabled` | Enabled | Disabling hides shortcode/block output and rejects submissions |

New connections also send these synchronization defaults to Adoology:

```text
product_auto_sync = true
inventory_auto_sync = true
channel_product_add_sync = true
```

## Synchronization

After WooCommerce authorization, Adoology owns remote API verification, native webhook provisioning, and initial imports. The plugin does not require merchants to create WooCommerce API keys or webhook subscriptions manually.

Expected synchronized domains include:

- Products and variations.
- Inventory and stock state.
- Customers.
- Orders.
- Store metadata such as currency, timezone, REST URL, weight unit, and plugin version.

Use **Adoology > Sync > Sync Products Now** for a manual full product sync. This calls:

```text
POST /v1/channel-connections/{connection}/sync-products
```

Recent runs come from:

```text
GET /v1/channel-connections/{connection}/sync-runs
```

The backend queue worker must consume `channel-sync`; otherwise connections remain in `provisioning` and sync runs do not advance.

## Incomplete-order tracking

Incomplete-order tracking is disabled by default. When enabled, the plugin tracks classic checkout, WooCommerce Checkout Blocks, and the Adoology order form.

### Lifecycle

| State | Meaning |
| --- | --- |
| `started` | Checkout identity and first snapshot stored |
| `incomplete` | No activity for the configured timeout |
| `submitting` | Order-form submission has atomically claimed the checkout |
| `submitting_recovery` | Previously incomplete checkout is being submitted |
| `converted` | Order completed before becoming incomplete |
| `recovered` | Order completed after becoming incomplete |
| `expired` | Retention deadline passed without conversion |

Stale `submitting` claims are released after 15 minutes. Lifecycle processing runs hourly, so transitions can occur after the exact configured timeout rather than precisely at it.

### Captured data

When tracking is enabled, snapshots can include:

- Random anonymous and checkout identifiers.
- WooCommerce session identifier.
- Name, phone, email, address, city, postcode, and country.
- Product, variation, quantity, value, and currency.
- Landing page and form stage.
- Direct peer IP address and user agent in event context.

Customer snapshot payloads and event payloads are encrypted locally with AES-256-GCM.

Tracking uses same-origin REST requests and a two-hour signed capture token bound to random browser identifiers. Requests are limited to 60 per IP per minute and 300 new local checkout rows globally per minute.

The browser uses these same-site identifier cookies:

```text
adoology_anonymous_id
adoology_checkout_id
```

They contain random UUIDs, not contact details.

### Emitted lifecycle events

```text
checkout.started
checkout.incomplete
checkout.converted
checkout.recovered
```

Events are queued locally and delivered in batches to `POST /v1/events`.

## Fraud protection

Fraud protection runs locally before or during order creation.

### Signals

| Signal | Score effect |
| --- | --- |
| Honeypot filled | +100 |
| Missing or suspicious automation user agent | +35 |
| Invalid short phone | +20 |
| Invalid submitted email | +20 |
| Direct-IP velocity above configured limit | Starts at +15 and grows, capped at +50 |
| Recent matching customer and product order | +45 |

The direct peer address in `REMOTE_ADDR` is used. Forwarded headers are intentionally not trusted by this plugin.

### Actions

| Action | Result |
| --- | --- |
| `allow` | Order proceeds normally |
| `flag` | Order proceeds and receives an order note |
| `hold` | Order is moved to `on-hold` |
| `block` | Checkout is rejected before order creation |

Risk metadata is stored on WooCommerce orders:

```text
_adoology_risk_score
_adoology_risk_action
_adoology_risk_signals
_adoology_normalized_phone
```

Risk telemetry can emit:

```text
risk.assessed
order.blocked
```

Fraud scoring is a configurable operational aid, not a substitute for payment-gateway fraud controls or merchant review.

## Landing-page order form

The order form creates a normal WooCommerce order without using the cart.

### Shortcode

Minimum usage:

```text
[adoology_order_form product_id="123"]
```

Custom title:

```text
[adoology_order_form product_id="123" title="Order today"]
```

Delivery options:

```text
[adoology_order_form product_id="123" title="Order today" delivery_options="standard:Standard Delivery:5.00,pickup:Pickup:0"]
```

### Shortcode attributes

| Attribute | Required | Default | Description |
| --- | --- | --- | --- |
| `product_id` | Yes | `0` | Purchasable WooCommerce product ID |
| `title` | No | `Order now` | Form heading |
| `delivery_options` | No | Standard delivery and pickup | Comma-separated `key:Label:cost` entries; cost is optional and defaults to zero |

Delivery configuration is signed before being rendered and verified during submission, preventing browser-side delivery-price changes.

### Block editor

Add the **Adoology Order Form** dynamic block and configure:

- Product ID.
- Title.

The block uses default delivery options. Use the shortcode when custom delivery choices or costs are required.

### Order behavior

- Supports simple and variable products.
- Validates purchasability, variation ownership, quantity, stock, sold-individually rules, and WooCommerce add-to-cart filters.
- Requires name, phone, address, and city; email and postcode are optional.
- Uses the WooCommerce store base country.
- Adds the selected delivery option as a shipping line.
- Calculates normal WooCommerce totals.
- Reserves stock while payment is pending.
- Stores preferred payment as metadata, then sends the customer to WooCommerce's secure order-pay page.
- Completes free orders immediately.
- Redirects held orders to the order-received page.
- Atomically claims the checkout ID to prevent duplicate orders from repeated submissions.
- Uses the same fraud controls as normal checkout.

At least one WooCommerce payment gateway must be enabled. Actual gateway availability and payment happen on WooCommerce's order-pay flow.

## Background processing

| Hook | Schedule | Purpose |
| --- | --- | --- |
| `adoology_connection_health_check` | Hourly | Refresh backend connection state and remove expired revocation material |
| `adoology_process_events` | Every five minutes plus immediate single actions | Deliver queued storefront events |
| `adoology_cleanup_events` | Daily | Purge expired outbox data |
| `adoology_incomplete_order_lifecycle` | Hourly | Mark incomplete, recovered, and expired checkout states |

Single event-delivery actions use WooCommerce Action Scheduler when available and initialized, with WP-Cron fallback.

For reliable production processing, invoke WordPress cron from the server rather than depending only on visitor traffic:

```cron
* * * * * cd /path/to/wordpress && wp cron event run --due-now --quiet
```

If a system cron is configured, set this in `wp-config.php`:

```php
define('DISABLE_WP_CRON', true);
```

Do not disable WP-Cron unless another runner is active.

### Event outbox behavior

- Payloads are encrypted before database storage.
- Workers claim up to 25 rows with a five-minute lease.
- Abandoned processing leases return to `retrying`.
- Delivery uses stable event IDs and idempotency keys.
- Failed events use exponential backoff up to one day.
- Events become terminal `failed` after eight attempts.
- **Adoology > Logs > Retry Failed Events** resets failed and retrying rows.

## Security

### Credentials

- Workspace API key and webhook secrets use AES-256-GCM authenticated encryption.
- Encryption keys derive from WordPress authentication salts, secure-auth salts, site ID, and option-specific context.
- Existing credentials are never rendered into admin HTML.
- Changing WordPress salts invalidates encrypted values; reconnect after intentional salt rotation.

### API transport

- API origin must use HTTPS.
- User info, query strings, and fragments are rejected in the configured API origin.
- `/api`, `/api/v1`, and `/v1` suffixes are rejected.
- Requests use WordPress safe HTTP APIs with unsafe URLs rejected and redirects disabled.
- Write requests carry idempotency keys.
- Only transport failures, HTTP 429, and HTTP 5xx responses are retried, with bounded delays.

### WooCommerce authorization

- Plugin accepts only its own store's canonical `/wc-auth/v1/authorize` URL.
- Required scope is exactly `read_write`.
- Callback origin must match the configured Adoology API origin.
- State uses HMAC-SHA256 over the connection ULID.
- Backend accepts the callback once while the connection is in `connecting` state.

### Local endpoints

- Checkout capture requires same-origin `Origin` or `Referer` and a short-lived signed token.
- Capture routes are rate limited.
- Order forms require WordPress nonces and signed delivery configuration.
- Admin actions require `manage_woocommerce` and action-specific nonces.
- Key revocation requires a timestamped backend HMAC and exact key ID plus consumer-key hash.

### Logging

Tokens, secrets, signatures, API keys, bearer credentials, and Woo consumer credentials are redacted before WooCommerce logging.

## Privacy and data retention

Incomplete-order tracking is opt-in and disabled by default. Merchants are responsible for updating their privacy policy and obtaining consent required by their jurisdiction before enabling it.

The plugin adds suggested text under WordPress's privacy-policy guide and registers:

- Personal-data exporter: **Adoology incomplete orders**.
- Personal-data eraser: **Adoology incomplete orders**.

The local eraser deletes matching encrypted checkout rows and locally queued events identified by email. Data already delivered to the Adoology workspace must be erased from Adoology separately.

### Retention behavior

- Active incomplete rows expire after the configured number of days.
- Expired rows have encrypted customer data cleared.
- Converted, recovered, and expired rows have customer data cleared after the configured retention interval.
- Unsent and failed event rows older than the configured incomplete-data retention are deleted.
- Sent event rows are deleted after seven days.
- Failed event payloads older than 30 days are blanked; normal cleanup usually deletes them earlier when retention is shorter.

When tracking is disabled, order-form submission still stores the minimum local lifecycle record needed for idempotency and order correlation, but customer snapshot data is not retained and checkout lifecycle events are not emitted.

## REST endpoints

Routes are registered under the store's WordPress REST API.

| Method | Route | Caller | Protection |
| --- | --- | --- | --- |
| POST | `/wp-json/adoology/v1/checkout-token` | Storefront browser | Same-origin check, rate limit, no-store response |
| POST | `/wp-json/adoology/v1/checkout` | Storefront browser | Tracking enabled, same-origin check, rate limit, signed capture token |
| POST | `/wp-json/adoology/v1/revoke-key` | Adoology backend | HMAC signature, connection match, five-minute timestamp window, key ID and consumer hash |

These routes are internal integration contracts. They are not intended for direct merchant use.

### Backend API routes used by the plugin

The plugin automatically prefixes these paths with `/v1`:

```text
POST   /channel-connections
GET    /channel-connections/{connection}
PATCH  /channel-connections/{connection}
DELETE /channel-connections/{connection}
POST   /channel-connections/{connection}/sync-products
GET    /channel-connections/{connection}/sync-runs
POST   /events
```

## Local storage

### Database tables

The plugin creates two site-prefixed tables:

| Table | Purpose |
| --- | --- |
| `{prefix}adoology_events` | Encrypted durable event outbox, delivery attempts, leases, and errors |
| `{prefix}adoology_incomplete_orders` | Checkout lifecycle, encrypted customer snapshot, product/value context, risk score, and order correlation |

Schema version is `1.1.0`. `dbDelta()` runs during activation and when a schema version mismatch is detected.

### WooCommerce order metadata

Depending on enabled features, orders may contain:

```text
_adoology_checkout_id
_adoology_delivery_option
_adoology_preferred_payment_method
_adoology_risk_score
_adoology_risk_action
_adoology_risk_signals
_adoology_normalized_phone
_adoology_risk_event_sent
```

The plugin declares compatibility with WooCommerce High-Performance Order Storage.

## Logging and monitoring

### Adoology Logs page

**Adoology > Logs** shows event ID, name, status, attempts, redacted error, creation time, and delivery time for the latest 100 outbox rows.

Common statuses:

```text
pending
processing
retrying
sent
failed
```

### WooCommerce logs

Open **WooCommerce > Status > Logs** and select source `adoology-connector` for transport, encryption, scheduling, and API errors.

### Useful WP-CLI commands

```bash
wp plugin status adoology-connector
wp cron event list
wp cron event run --due-now
wp option get adoology_connection_state --format=json
```

Do not print `adoology_api_token` or encrypted secret options in shared logs or support tickets.

## Disconnecting and uninstalling

### Disconnect

Use **Adoology > Connection > Disconnect** before deactivation or deletion.

Disconnect performs these actions:

1. Requests deletion of the backend channel connection with an idempotency key.
2. Backend removes managed WooCommerce webhooks.
3. Backend calls the signed local revocation endpoint.
4. Plugin removes the exact WooCommerce API key using key ID and consumer-key hash.
5. Plugin clears connection state, webhook secret, workspace API key, and legacy artifacts.

Short-lived revocation signing material is retained for safe backend retries and expires after one day.

### Deactivation

Deactivation stops plugin cron hooks. It does not disconnect the store, delete data, or remove the remote connection. Reactivation repairs required schedules.

### Uninstall

Uninstall attempts a bounded remote disconnect, clears schedules and plugin options, removes legacy managed Woo webhooks/API keys, and drops both plugin tables.

If the backend cannot be reached, API URL, workspace key, connection ID, and disconnect idempotency state are preserved for possible recovery after reinstall. Because an inactive plugin cannot receive an asynchronous key-revocation callback, disconnect before uninstalling.

## Troubleshooting

### API URL is rejected

- Enter origin only: `https://api.adoology.com`.
- Confirm public DNS resolves from the WordPress server.
- Confirm TLS certificate is valid.
- Remove `/api` or `/v1` suffixes.
- Private IPs, localhost, credentials in URLs, query strings, and fragments are rejected.

Test from the WordPress host:

```bash
curl --fail --show-error https://api.adoology.com/up
```

### Connect Store button is disabled

Save a valid workspace API key beginning with `dc_`, then reload the Connection page.

### Invalid WooCommerce authorization URL

Check all of these:

- Backend and plugin connection-security versions were deployed together.
- Backend returned `meta.webhook_secret` and an HMAC-signed `user_id` state.
- Backend `APP_URL` matches the configured API origin.
- Store `home_url()` matches the authorization URL host and path.
- API DNS is resolvable from WordPress.

### WooCommerce callback returns 404

Likely causes:

- State signature does not match the stored backend webhook secret.
- Connection is no longer in `connecting` state.
- Authorization URL is stale or was already used.
- Plugin and backend versions use different callback contracts.

Start a new connection rather than editing callback parameters manually.

### Connection stays in provisioning

Check backend workers:

```bash
php artisan horizon:status
php artisan queue:failed
```

Confirm Horizon consumes `channel-sync` and can reach the WooCommerce REST API.

### Events stay pending or retrying

- Confirm WP-Cron or the server cron runner works.
- Open **WooCommerce > Status > Scheduled Actions** and search for `adoology`.
- Confirm the store remains connected and its workspace key is valid.
- Check **Adoology > Logs** and WooCommerce logs.
- Use **Retry Failed Events** after correcting the underlying failure.

### Stored credential cannot be decrypted

WordPress salts likely changed or OpenSSL AES-256-GCM is unavailable. Save a new workspace API key and reconnect. Do not attempt to convert encrypted option values manually.

### Order form is not visible

- Confirm **Landing-page order form** is enabled.
- Confirm `product_id` references a purchasable product.
- For variable products, ensure purchasable in-stock variations exist.
- Editors see a product-selection warning; storefront visitors receive no output for invalid products.

### Order form cannot create or pay for an order

- Enable at least one WooCommerce payment gateway.
- Confirm product stock and purchasability.
- Confirm required name, phone, address, and city fields are present.
- Review fraud thresholds and flagged-order logs.
- Check WooCommerce logs for source `adoology-connector`.

### Wrong page after WooCommerce approval

Set backend `APP_FRONTEND_URL` to the deployed Adoology application. Authorization returns to:

```text
{APP_FRONTEND_URL}/channels/{connection_id}
```

The backend callback may still have succeeded; use **Test Connection** in WordPress to confirm.

## Development

### Repository layout

The plugin is structured as a Composer package with PSR-4 autoloading (`Adoology\` → `src/`) and a PHPUnit unit test suite.

```text
adoology-connector.php                 Plugin bootstrap: header, constants, Composer autoload
composer.json                          Package metadata, autoloading, dev dependencies
phpunit.xml.dist                       PHPUnit configuration
src/Plugin.php                         Registration, activation, deactivation, scheduling
uninstall.php                          Remote/local teardown and table cleanup
assets/js/checkout-tracker.js          Checkout identity and snapshot capture
assets/js/order-form-block.js          Dynamic block editor registration
src/ApiClient.php                      Safe authenticated backend HTTP client
src/Connection.php                     Connection lifecycle and URL validation
src/Crypto.php                         AES-256-GCM secret encryption
src/Database.php                       Local schema installation and upgrades
src/Events.php                         Durable encrypted event outbox
src/Fraud.php                          Local checkout risk assessment
src/IncompleteOrders.php               Checkout lifecycle and privacy tools
src/KeyRevocation.php                  Signed backend key-revocation endpoint
src/Logger.php                         Redacting WooCommerce logger
src/Options.php                        Non-autoloaded plugin option helpers/defaults
src/OrderForm.php                      Shortcode, block rendering, and order creation
src/Scheduler.php                      Action Scheduler and WP-Cron abstraction
src/Settings.php                       Admin pages and settings
src/Webhooks.php                       Dormant legacy local-webhook implementation
src/RestController.php                 Dormant legacy stock-push endpoint
tests/                                 PHPUnit unit tests (Brain Monkey)
```

`src/Webhooks.php` and `src/RestController.php` are present in the package but are intentionally not registered by `Plugin::init()`. Current production architecture uses backend-managed native WooCommerce webhooks. Do not register the dormant classes unless the backend integration contract is deliberately changed and tested.

### Installation and tests

Install dependencies and run the unit test suite:

```bash
composer install
composer test
```

### Syntax checks

Run PHP syntax validation:

```bash
composer lint
```

Run JavaScript syntax validation:

```bash
for file in assets/js/*.js; do
    node --check "$file" || exit 1
done
```

Unit tests cover crypto round-trips, option handling, log redaction, API URL/token validation, fraud scoring, webhook signature validation, and event payload sanitization. Integration behavior should also be verified against a disposable WordPress/WooCommerce site and a compatible Adoology backend.

### Release checklist

1. Update plugin version in the header and `ADOOLOGY_VERSION` together.
2. Verify backend callback and connection contracts remain compatible.
3. Run all PHP and JavaScript syntax checks.
4. Test activation, connection, Woo authorization, initial sync, event delivery, order form, disconnect, deactivation, and uninstall.
5. Test classic checkout and Checkout Blocks.
6. Test with HPOS enabled.
7. Test mobile and desktop order-form layouts.
8. Review privacy-policy language and default consent behavior.
9. Package only runtime files; exclude `.git` and local tooling.
