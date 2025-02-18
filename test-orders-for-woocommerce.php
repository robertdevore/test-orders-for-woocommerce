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

// Check if Composer's autoloader is already registered globally.
if ( ! class_exists( 'RobertDevore\WPComCheck\WPComPluginHandler' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use RobertDevore\WPComCheck\WPComPluginHandler;

new WPComPluginHandler( plugin_basename( __FILE__ ), 'https://robertdevore.com/why-this-plugin-doesnt-support-wordpress-com-hosting/' );

// Define the plugin version.
define( 'TOWC_VERSION', '1.0.0' );

/**
 * Load plugin text domain for translations
 * 
 * @since 1.1.0
 * @return void
 */
function towc_load_textdomain() {
    load_plugin_textdomain( 
        'wc-test-orders', 
        false, 
        dirname( plugin_basename( __FILE__ ) ) . '/languages/'
    );
}
add_action( 'plugins_loaded', 'towc_load_textdomain' );

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

    // Enqueue custom JS for the plugin.
    wp_enqueue_script(
        'test-orders-js',
        plugins_url( 'assets/js/script.js', __FILE__ ),
        [ 'jquery' ],
        TOWC_VERSION,
        true
    );

    // Localize the script with AJAX URL and nonce.
    wp_localize_script( 'test-orders-js', 'wcTestOrders', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'delete_test_orders_nonce' ),
    ] );

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
                    'title'   => esc_html__( 'Enable/Disable', 'wc-test-orders' ),
                    'type'    => 'checkbox',
                    'label'   => esc_html__( 'Enable Test Order', 'wc-test-orders' ),
                    'default' => 'yes'
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

            // Add _payment_method meta for "Test Order".
            $order->update_meta_data( '_payment_method', $this->id );
            $order->save();

            // Set order status.
            $test_order_status = get_option( 'wc_test_order_status', 'completed' );
            $order->update_status( $test_order_status, esc_html__( 'Test order processed.', 'wc-test-orders' ) );

            // Optionally reduce stock levels.
            if ( get_option( 'wc_test_order_reduce_stock', 'yes' ) === 'yes' ) {
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
        <h2><?php esc_html_e( 'Delete Test Orders', 'wc-test-orders' ); ?></h2>
        <p>
            <?php esc_html_e( 'Click the button below to delete all test orders.', 'wc-test-orders' ); ?>
        </p>
        <button id="delete-test-orders-btn" class="button button-primary">
            <?php esc_html_e( 'Delete Test Orders', 'wc-test-orders' ); ?>
        </button>
        <div id="test-orders-progress" style="display:none; margin-top: 20px;">
            <div style="background: #ddd; width: 100%; height: 20px; border-radius: 5px;">
                <div id="test-orders-progress-bar" style="background: #0073aa; height: 100%; width: 0%; border-radius: 5px;"></div>
            </div>
            <p id="test-orders-status-message" style="margin-top: 15px; color: #0073aa; font-weight: bold;"></p>
            <p id="test-orders-progress-text" style="margin-top: 10px;"><?php esc_html_e( 'Progress: 0%', 'wc-test-orders' ); ?></p>
        </div>
    </div>
    <?php
}

/**
 * AJAX handler to delete test orders.
 *
 * @since 1.1.0
 * @return void
 */
function wc_test_orders_delete_test_orders() {
    check_ajax_referer( 'delete_test_orders_nonce', 'nonce' );

    $batch_size    = 10;
    $offset        = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
    $total_deleted = isset( $_POST['total_deleted'] ) ? intval( $_POST['total_deleted'] ) : 0;
    $total_scanned = isset( $_POST['total_scanned'] ) ? intval( $_POST['total_scanned'] ) : 0;

    // Only calculate total_scanned on the first request.
    if ( $offset === 0 || $total_scanned === 0 ) {
        $total_query = new WP_Query( [
            'post_type'      => 'shop_order',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => [ 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending' ],
            'meta_query'     => [
                [
                    'key'     => '_payment_method',
                    'value'   => 'test_order',
                    'compare' => '=',
                ],
            ],
        ] );
        
        $total_scanned = $total_query->found_posts;
    }

    // Fetch a batch of test orders.
    $args = [
        'post_type'      => 'shop_order',
        'posts_per_page' => $batch_size,
        'offset'         => $offset,
        'fields'         => 'ids',
        'post_status'    => [ 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending' ],
        'meta_query'     => [
            [
                'key'     => '_payment_method',
                'value'   => 'test_order',
                'compare' => '=',
            ],
        ],
    ];

    $orders        = get_posts( $args );
    $deleted_count = 0;

    foreach ( $orders as $order_id ) {
        wp_delete_post( $order_id, true ); // Force delete the order.
        $deleted_count++;
    }

    // Update total deleted count.
    $total_deleted += $deleted_count;

    // Check if more orders are remaining.
    $has_more = ( $offset + $batch_size ) < $total_scanned;

    if ( $total_scanned === 0 ) {
        wp_send_json_success( [
            'deleted_count'       => 0,
            'total_deleted'       => 0,
            'total_scanned'       => $total_scanned,
            'next_offset'         => 0,
            'has_more'            => false,
            'progress_percentage' => 100,
            'message'             => __( 'No test orders found. Debug: ' . print_r( $total_query->request, true ), 'wc-test-orders' ),
        ] );
    }    

    wp_send_json_success( [
        'deleted_count'       => $deleted_count,
        'total_deleted'       => $total_deleted,
        'total_scanned'       => $total_scanned,
        'next_offset'         => $offset + $batch_size,
        'has_more'            => $has_more,
        'progress_percentage' => $total_scanned > 0 ? round( ( $total_deleted / $total_scanned ) * 100 ) : 0,
        'message'             => __( 'Deleting test orders...', 'wc-test-orders' ),
    ] );
}
add_action( 'wp_ajax_wc_test_orders_delete_test_orders', 'wc_test_orders_delete_test_orders' );

/**
 * Function to retrieve the payment method of a WooCommerce order.
 *
 * @param int $order_id WooCommerce Shop Order ID.
 * 
 * @since  1.0.0
 * @return string|null The payment method title or null if not found.
 */
function get_order_payment_method( $order_id ) {
    // Check if WooCommerce is active.
    if ( ! class_exists( 'WooCommerce' ) ) {
        return null;
    }

    // Load the order.
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return null;
    }

    // Retrieve payment method information.
    return $order->get_payment_method_title();
}

