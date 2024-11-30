# Test Orders for WooCommerce®

Test Orders for WooCommerce® is a lightweight plugin that adds a "Test Order" option to your WooCommerce store, allowing you to place test orders without requiring payment. 

Perfect for staging environments or testing new WooCommerce setups.

---

## Features

- **Test Order Payment Gateway:** Adds a "Test Order" option to your checkout.
- **Customizable Settings:** Configure the order status and whether to reduce stock levels for test orders.
- **Seamless WooCommerce Integration:** Built using WooCommerce hooks and standards.
- **Developer-Friendly:** Easily extensible for advanced use cases.
- **Localization Ready:** Fully translatable with `.mo`/`.po` files.

---

## Installation

### From the WordPress Admin Dashboard:

1. Download the latest release from [GitHub](https://github.com/robertdevore/test-orders-for-woocommerce/).
2. Go to **Plugins > Add New > Upload Plugin**.
3. Upload the downloaded ZIP file and activate the plugin.
4. Ensure WooCommerce® is active.

### Manual Installation:

1. Upload the `test-orders-for-woocommerce` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Ensure WooCommerce® is active.

---

## Usage

### Enabling Test Orders

1. Go to **WooCommerce > Settings > Payments**.
2. Enable the "Test Order" payment gateway.

### Customizing Test Order Behavior

1. Navigate to **WooCommerce > Test Orders**.
2. Configure the following options:
   - **Order Status:** Choose the default status for test orders (`Completed`, `Processing`, `On Hold`, or `Pending`).
   - **Reduce Stock:** Determine whether stock levels should decrease when processing test orders.

### Example Use Case

Use the "Test Order" gateway in staging environments to verify order workflows, email notifications, and other post-purchase functionalities without actual payments.

---

## Development

### Prerequisites

- **WordPress® Version:** 5.0 or higher  
- **WooCommerce® Version:** 4.0 or higher  

### Code Structure

- **Custom Payment Gateway:** Implements WooCommerce's `WC_Payment_Gateway` class.
- **Admin Settings Page:** Accessible via **WooCommerce > Test Orders**.

### Extending the Plugin

Developers can customize plugin behavior by using filters and actions. For example, modify the default test order status:

```php
add_filter( 'wc_test_order_status', function( $status ) {
    return 'on-hold';
});
```

### Updating the Plugin

This plugin uses [YahnisElsts/Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) for automatic updates via GitHub.

* * *

## Security

- **Input Sanitization:** All user inputs are sanitized using WordPress functions.
- **Nonce Protection:** Nonces are used for all settings forms to prevent CSRF attacks.
- **Escaping Outputs:** Proper escaping is applied to all outputs in the admin panel.
* * *
