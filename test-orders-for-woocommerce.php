<?php

/**
  * The plugin bootstrap file
  *
  * @link              https://robertdevore.com
  * @since             1.0.0
  * @package           Test_Orders_For_WooCommerce
  *
  * @wordpress-plugin
  *
  * Plugin Name: Test Orders for WooCommerce®
  * Description: Adds a "Test Order" option to WooCommerce® checkout, bypassing the payment process.
  * Plugin URI:  https://github.com/robertdevore/test-orders-for-woocommerce/
  * Version:     1.0.0
  * Author:      Robert DeVore
  * Author URI:  https://robertdevore.com/
  * License:     GPL-2.0+
  * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
  * Text Domain: wc-test-orders
  * Domain Path: /languages
  * Update URI:  https://github.com/robertdevore/test-orders-for-woocommerce/
  */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

require 'vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/robertdevore/test-orders-for-woocommerce/',
	__FILE__,
	'test-orders-for-woocommerce'
);

// Set the branch that contains the stable release.
$myUpdateChecker->setBranch( 'main' );

// Define the plugin version.
define( 'TOWC_VERSION', '1.0.0' );

/**
 * Check if WooCommerce® is active and initialize the plugin.
 * 
 * @since  1.0.0
 * @return void
 */
function wc_test_orders_initialize() {
    if ( class_exists( 'WooCommerce' ) ) {
        add_filter( 'woocommerce_payment_gateways', 'wc_test_orders_add_gateway' );
        add_action( 'admin_menu', 'wc_test_orders_add_settings_page' );
        add_action( 'admin_enqueue_scripts', 'wc_test_orders_enqueue_scripts' );
    } else {
        add_action( 'admin_notices', 'wc_test_orders_missing_woocommerce_notice' );
    }
}
add_action( 'woocommerce_init', 'wc_test_orders_initialize' );

/**
 * Enqueue custom scripts and styles for the Test Orders settings page.
 *
 * @param string $hook The current admin page hook.
 * 
 * @since  1.0.0
 * @return void
 */
function wc_test_orders_enqueue_scripts( $hook ) {
    if ( 'woocommerce_page_wc-test-orders' !== $hook ) {
        return;
    }

    // Enqueue custom CSS for the plugin.
    wp_enqueue_style( 
        'test-orders-css', 
        plugins_url( 'assets/css/style.css', __FILE__ ), 
        [], 
        TOWC_VERSION 
    );
}

/**
 * Admin notice for missing WooCommerce® dependency.
 * 
 * @since  1.0.0
 * @return void
 */
function wc_test_orders_missing_woocommerce_notice() {
    echo '<div class="error"><p>' . esc_html__( 'Test Orders for WooCommerce® requires WooCommerce® to be active.', 'wc-test-orders' ) . '</p></div>';
}

/**
 * Adds a custom "Test Order" payment gateway to WooCommerce®.
 *
 * @param array $gateways Existing payment gateways.
 * 
 * @since  1.0.0
 * @return array Updated payment gateways.
 */
function wc_test_orders_add_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Test_Order';
    return $gateways;
}

/**
 * Defines the custom "Test Order" payment gateway class.
 * 
 * @since  1.0.0
 * @return void
 */
function wc_test_orders_include_gateway_class() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    class WC_Gateway_Test_Order extends WC_Payment_Gateway {

        /**
         * Constructor for the gateway.
         */
        public function __construct() {
            $this->id                 = 'test_order';
            $this->method_title       = esc_html__( 'Test Order', 'wc-test-orders' );
            $this->method_description = esc_html__( 'Use this option for placing test orders without payment.', 'wc-test-orders' );
            $this->has_fields         = false;

            // Load settings and initialize.
            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );

            // Admin settings save hook.
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        }

        /**
         * Initialize the settings form fields.
         * 
         * @since  1.0.0
         * @return void
         */
        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title'       => esc_html__( 'Enable/Disable', 'wc-test-orders' ),
                    'type'        => 'checkbox',
                    'label'       => esc_html__( 'Enable Test Order', 'wc-test-orders' ),
                    'default'     => 'yes'
                ],
                'title' => [
                    'title'       => esc_html__( 'Title', 'wc-test-orders' ),
                    'type'        => 'text',
                    'description' => esc_html__( 'This controls the title displayed during checkout.', 'wc-test-orders' ),
                    'default'     => esc_html__( 'Test Order', 'wc-test-orders' ),
                    'desc_tip'    => true,
                ],
                'description' => [
                    'title'       => esc_html__( 'Description', 'wc-test-orders' ),
                    'type'        => 'textarea',
                    'description' => esc_html__( 'This controls the description displayed during checkout.', 'wc-test-orders' ),
                    'default'     => esc_html__( 'Place a test order without payment.', 'wc-test-orders' ),
                ],
            ];
        }

        /**
         * Process the order based on admin settings.
         *
         * @param int $order_id The ID of the order being processed.
         * 
         * @since  1.0.0
         * @return array Result of the payment processing.
         */
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );

            // Get custom settings for the order status and stock reduction.
            $test_order_status = get_option( 'wc_test_order_status', 'completed' );
            $reduce_stock      = get_option( 'wc_test_order_reduce_stock', 'yes' );

            // Set order status.
            $order->update_status( $test_order_status, esc_html__( 'Test order processed.', 'wc-test-orders' ) );

            // Optionally reduce stock levels.
            if ( 'yes' === $reduce_stock ) {
                wc_reduce_stock_levels( $order_id );
            }

            // Clear the cart.
            WC()->cart->empty_cart();

            // Return success result.
            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            ];
        }
    }
}
add_action( 'plugins_loaded', 'wc_test_orders_include_gateway_class', 11 );

/**
 * Add settings page to customize the test order options.
 * 
 * @since  1.0.0
 * @return void
 */
function wc_test_orders_add_settings_page() {
    add_submenu_page(
        'woocommerce',
        esc_html__( 'Test Orders Settings', 'wc-test-orders' ),
        esc_html__( 'Test Orders', 'wc-test-orders' ),
        'manage_options',
        'wc-test-orders',
        'wc_test_orders_render_settings_page'
    );
}

/**
 * Render the settings page.
 * 
 * @since  1.0.0
 * @return void
 */
function wc_test_orders_render_settings_page() {
    if ( isset( $_POST['wc_test_orders_save_settings'] ) && check_admin_referer( 'wc_test_orders_save_settings_action' ) ) {
        // Sanitize and save settings.
        $status       = sanitize_text_field( $_POST['wc_test_order_status'] );
        $reduce_stock = sanitize_text_field( $_POST['wc_test_order_reduce_stock'] );

        update_option( 'wc_test_order_status', $status );
        update_option( 'wc_test_order_reduce_stock', $reduce_stock );
        echo '<div class="updated"><p>' . esc_html__( 'Settings saved.', 'wc-test-orders' ) . '</p></div>';
    }

    // Fetch saved options with defaults.
    $status       = get_option( 'wc_test_order_status', 'completed' );
    $reduce_stock = get_option( 'wc_test_order_reduce_stock', 'yes' );
    ?>
    <div class="wrap">
        <h1>
            <?php esc_html_e( 'Test Orders Settings', 'wc-test-orders' ); ?>
            <a id="test-orders-support-btn" href="https://robertdevore.com/contact/" target="_blank" class="button button-alt" style="margin-left: 10px;">
                <span class="dashicons dashicons-format-chat" style="vertical-align: middle;"></span> <?php esc_html_e( 'Support', 'markdown-editor' ); ?>
            </a>
            <a id="test-orders-docs-btn" href="https://robertdevore.com/articles/test-orders-for-woocommerce/" target="_blank" class="button button-alt" style="margin-left: 5px;">
                <span class="dashicons dashicons-media-document" style="vertical-align: middle;"></span> <?php esc_html_e( 'Documentation', 'markdown-editor' ); ?>
            </a>
        </h1>
        <hr />
        <form method="post" action="">
            <?php wp_nonce_field( 'wc_test_orders_save_settings_action' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wc_test_order_status"><?php esc_html_e( 'Order Status', 'wc-test-orders' ); ?></label>
                    </th>
                    <td>
                        <select name="wc_test_order_status" id="wc_test_order_status">
                            <option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'wc-test-orders' ); ?></option>
                            <option value="processing" <?php selected( $status, 'processing' ); ?>><?php esc_html_e( 'Processing', 'wc-test-orders' ); ?></option>
                            <option value="on-hold" <?php selected( $status, 'on-hold' ); ?>><?php esc_html_e( 'On Hold', 'wc-test-orders' ); ?></option>
                            <option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending Payment', 'wc-test-orders' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wc_test_order_reduce_stock"><?php esc_html_e( 'Reduce Stock', 'wc-test-orders' ); ?></label>
                    </th>
                    <td>
                        <select name="wc_test_order_reduce_stock" id="wc_test_order_reduce_stock">
                            <option value="yes" <?php selected( $reduce_stock, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wc-test-orders' ); ?></option>
                            <option value="no" <?php selected( $reduce_stock, 'no' ); ?>><?php esc_html_e( 'No', 'wc-test-orders' ); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button( esc_html__( 'Save Settings', 'wc-test-orders' ), 'primary', 'wc_test_orders_save_settings' ); ?>
        </form>
    </div>
    <?php
}
