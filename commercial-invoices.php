<?php
/**
 * Plugin Name: Commercial Invoices
 * Requires Plugins: woocommerce
 */
if ( ! defined( 'ABSPATH' ) ) exit; 

define( 'COMMERCIAL_INVOICES_VERSION', '1.0.0' );

/**
 * Main plugin class.
 */
class Commercial_Invoices {

	/**
	 * Single instance of the class.
	 *
	 * @var Commercial_Invoices|null
	 */
	private static $instance = null;

	/**
	 * Get class instance.
	 *
	 * @return Commercial_Invoices
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->load_files();
		$this->load_hooks();
	}

	/**
	 * Load files.
	 */
	private function load_files() {
		$plugin_dir = plugin_dir_path( __FILE__ );
		require_once $plugin_dir . 'includes/class-ci-product-fields.php';
		require_once $plugin_dir . 'includes/class-ci-order-fields.php';
		require_once $plugin_dir . 'includes/class-ci-invoice-printer.php';
	}

	/**
	 * Load hooks.
	 */
	private function load_hooks() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
	}

	/**
	 * Load plugin textdomain.
	 */
	public function init() {
		load_plugin_textdomain(
			'commercial-invoices',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);

		new CI_Product_Fields();
		new CI_Order_Fields();
		new CI_Invoice_Printer();
	}

	/**
	 * Declare compatibility with WooCommerce HPOS.
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	/**
	 * -----------------
	 * Utility functions
	 * -----------------
	 */

	/**
	 * Use edit screen globals to retrieve current order.
	 *
	 * @return WC_Order|false
	 */
	public static function get_current_order() {
		global $theorder, $post;

		if ( is_a( $theorder, 'WC_Order' ) ) {
			return $theorder;
		}

		if ( ! empty( $post ) ) {
			return wc_get_order( $post );
		}

		return false;
	}
	
	/**
	 * Accepts price (ex: '1210.99') and formats it with thousands-separators and currency symbol.
	 *
	 * @param  int|float $price
	 * @param  string    $currency
	 * @return string
	 */
	public static function format_price( $price, $currency = 'GBP' ){
		return wc_price( $price, array( 'in_span' => false, 'currency' => $currency ) );
	}
	
	/**
	 * Accepts weight (ex: '12.003') and formats it (ex: '12.003 kg').
	 * 
	 * Unit taken from `woocommerce_weight_unit` option, or 'kg' if unset.
	 *
	 * @param  int $weight
	 * @return string
	 */
	public static function format_weight( $weight ){
		return wc_format_decimal( $weight, 3 ) . ' ' . get_option( 'woocommerce_weight_unit', 'kg' );
	}
}

Commercial_Invoices::get_instance();
