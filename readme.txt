=== Adoology for WooCommerce ===
Contributors: adoology, amadulhaque8
Tags: woocommerce, orders, ecommerce, fraud, automation
Requires at least: 6.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Tested up to: 7.1
Stable tag: 0.1.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WooCommerce to Adoology for catalog, customer, order, and inventory synchronization.

== Description ==

Connect your WooCommerce store with Adoology.

An Adoology account and workspace API key are required for synchronization and remote event delivery. WooCommerce 8.0 or newer must be installed and active.

Features:
* WooCommerce order synchronization
* Product synchronization
* Customer synchronization
* Incomplete order detection
* Fraud and bot protection
* Adoology order form

== External services ==

= Adoology =

This plugin connects to the Adoology service (https://adoology.com), using https://api.adoology.com by default. Adoology synchronizes store data, manages WooCommerce webhooks, and receives checkout-recovery and risk events. A merchant can configure a different public HTTPS API origin on the Connection page; requests then go to that configured service.

Data is sent under these conditions:

* When a merchant selects Connect Store, the plugin sends the store name, store URL, WooCommerce REST API URL, timezone, currency, weight unit, plugin version, and synchronization settings. API requests use the saved workspace API key for authentication.
* After the merchant approves WooCommerce's authorization screen, WooCommerce sends read/write API credentials and signed connection state to Adoology. Adoology uses that authorized access to import products, variations, inventory, customers, and orders, including customer names, contact details, billing/shipping addresses, purchased items, totals, and order status. Backend-managed WooCommerce webhooks deliver changes in these records to Adoology.
* Connection checks run when requested and hourly while connected. Viewing the Sync page requests recent sync runs; selecting Sync Products Now requests a product import. These calls send the connection identifier and authentication headers. Disconnecting or uninstalling requests removal of the remote connection and its managed credentials/webhooks.
* Incomplete-order tracking is disabled by default. When enabled by the merchant, checkout activity queues events containing browser/checkout/session identifiers, available customer name, phone, email and address, product/variation identifiers, quantities, amounts, currency, landing page, form stage, timestamps, lifecycle status, and associated order identifiers. Events are delivered to the connected workspace by background jobs, including retries after failed delivery.
* Fraud protection is enabled by default. When an order is assessed, or an order-form submission is blocked, the plugin queues risk events containing order or product identifiers, risk score, action, and risk signals. These events can be delivered even when incomplete-order tracking is disabled.
* Checkout and risk events include the request's direct IP address and user agent, plus anonymous/session identifiers. They are delivered only when a store connection and workspace API key are configured. Local encrypted checkout data and queued events follow the configured retention period; removing local data does not erase data already delivered to Adoology.

The plugin bundles its CSS and JavaScript locally. Storefront tracking requests first go to the store's own WordPress REST API; server-side background jobs then deliver queued events to Adoology.

== Installation ==

1. Install and activate WooCommerce 8.0 or newer.
2. Upload and activate the complete plugin ZIP, including its vendor directory.
3. Save your Adoology workspace API key and select Connect Store.
4. Approve WooCommerce API access and configure synchronization.
5. Review the store privacy policy and obtain any required consent before enabling incomplete-order tracking.

== Changelog ==

= 0.1.8 =
* Use WordPress asset queues for order-form and admin styles and disconnect confirmation.
* Align translations and dependency metadata with the WordPress.org plugin slug.
* Include Composer manifests in release archives and document external data transfers.
