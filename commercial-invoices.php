<?php
/**
 * Plugin Name: Commercial Invoices
 *
 * @package Commercial_Invoices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'COMMERCIAL_INVOICES_VERSION', '1.0.0' );
define( 'COMMERCIAL_INVOICES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'COMMERCIAL_INVOICES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'COMMERCIAL_INVOICES_PLUGIN_FILE', __FILE__ );

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
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 */
	private function includes() {
		require_once COMMERCIAL_INVOICES_PLUGIN_DIR . 'includes/class-ci-product-fields.php';
		require_once COMMERCIAL_INVOICES_PLUGIN_DIR . 'includes/class-ci-order-fields.php';
		require_once COMMERCIAL_INVOICES_PLUGIN_DIR . 'includes/class-ci-invoice-printer.php';
	}

	/**
	 * Hook into WordPress / WooCommerce.
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'check_woocommerce' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

		// Initialise feature classes.
		add_action( 'plugins_loaded', array( $this, 'load_features' ) );
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'commercial-invoices',
			false,
			dirname( plugin_basename( COMMERCIAL_INVOICES_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Load feature classes once plugins are loaded.
	 */
	public function load_features() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		new CI_Product_Fields();
		new CI_Order_Fields();
		new CI_Invoice_Printer();
	}

	/**
	 * Show an admin notice if WooCommerce is not active.
	 */
	public function check_woocommerce() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
		}
	}

	/**
	 * Admin notice text.
	 */
	public function woocommerce_missing_notice() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Commercial Invoices requires WooCommerce to be installed and active.', 'commercial-invoices' )
		);
	}

	/**
	 * Declare compatibility with WooCommerce HPOS.
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', COMMERCIAL_INVOICES_PLUGIN_FILE, true );
		}
	}

	/**
	 * Get the current order object from the edit screen globals.
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
}

Commercial_Invoices::get_instance();
