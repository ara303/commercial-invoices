<?php
/**
 * Order fields for Commercial Invoices.
 *
 * @package Commercial_Invoices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds and saves HS Code (manual) and automatic commercial-invoice values on orders.
 */
class CI_Order_Fields {

	/**
	 * Meta keys.
	 */
	const HS_CODE_META_KEY          = '_ci_hs_code';
	const QUANTITY_META_KEY         = '_ci_quantity';
	const NET_WEIGHT_META_KEY       = '_ci_net_weight';
	const GROSS_WEIGHT_META_KEY     = '_ci_gross_weight';
	const DECLARED_VALUE_META_KEY   = '_ci_declared_value';
	const NONCE_ACTION              = 'ci_save_order_fields';
	const NONCE_NAME                = 'ci_order_fields_nonce';

	/**
	 * Flag to prevent recursive saves.
	 *
	 * @var bool
	 */
	private $is_saving = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 30 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_order_fields' ), 10, 2 );
		add_action( 'woocommerce_saved_order_items', array( $this, 'recalculate_on_items_change' ), 10, 2 );
	}

	/**
	 * Add the commercial invoice meta box to order edit screens.
	 */
	public function add_meta_box() {
		$screens = array( 'shop_order', 'woocommerce_page_wc-orders' );

		foreach ( $screens as $screen ) {
			add_meta_box(
				'ci_order_invoice_data',
				__( 'Commercial Invoice Data', 'commercial-invoices' ),
				array( $this, 'render_meta_box' ),
				$screen,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the meta box.
	 */
	public function render_meta_box() {
		$order = Commercial_Invoices::get_current_order();

		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Order not found.', 'commercial-invoices' ) . '</p>';
			return;
		}

		// Display live calculated values without persisting on every page load.
		$quantity       = $this->calculate_quantity( $order );
		$net_weight     = $this->calculate_net_weight( $order );
		$gross_weight   = $this->calculate_gross_weight( $order, $net_weight );
		$declared_value = $this->calculate_declared_value( $order );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<p>
			<label for="<?php echo esc_attr( self::HS_CODE_META_KEY ); ?>">
				<strong><?php esc_html_e( 'HS Code', 'commercial-invoices' ); ?></strong>
			</label>
			<input
				type="text"
				id="<?php echo esc_attr( self::HS_CODE_META_KEY ); ?>"
				name="<?php echo esc_attr( self::HS_CODE_META_KEY ); ?>"
				value="<?php echo esc_attr( $order->get_meta( self::HS_CODE_META_KEY ) ); ?>"
				style="width:100%;"
			/>
		</p>
		<p class="description">
			<?php esc_html_e( 'Manual input. Used on the printed commercial invoice.', 'commercial-invoices' ); ?>
		</p>

		<hr />

		<p>
			<strong><?php esc_html_e( 'Quantity:', 'commercial-invoices' ); ?></strong>
			<?php echo esc_html( wc_format_decimal( $quantity ) ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Net Weight (kg):', 'commercial-invoices' ); ?></strong>
			<?php echo esc_html( wc_format_decimal( $net_weight, 3 ) ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Gross Weight (kg):', 'commercial-invoices' ); ?></strong>
			<?php echo esc_html( wc_format_decimal( $gross_weight, 3 ) ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Declared Value:', 'commercial-invoices' ); ?></strong>
			<?php echo wp_kses_post( wc_price( $declared_value, array( 'currency' => $order->get_currency() ) ) ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'These values are calculated automatically from the order data and updated when the order is saved.', 'commercial-invoices' ); ?>
		</p>
		<?php
	}

	/**
	 * Save the HS Code and recalculate automatic values when an order is saved.
	 *
	 * @param int     $order_id Order ID.
	 * @param WP_Post $post     Post object (may be null on HPOS).
	 */
	public function save_order_fields( $order_id, $post = null ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( isset( $_POST[ self::HS_CODE_META_KEY ] ) ) {
			$order->update_meta_data(
				self::HS_CODE_META_KEY,
				sanitize_text_field( wp_unslash( $_POST[ self::HS_CODE_META_KEY ] ) )
			);
		}

		$this->calculate_and_update_order( $order );
	}

	/**
	 * Recalculate automatic values after order items are changed.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $action   Action performed.
	 */
	public function recalculate_on_items_change( $order_id, $action ) {
		$this->recalculate_and_store( $order_id );
	}

	/**
	 * Recalculate and store the automatic commercial-invoice values.
	 *
	 * @param int $order_id Order ID.
	 */
	public function recalculate_and_store( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$this->calculate_and_update_order( $order );
	}

	/**
	 * Calculate automatic values and persist them on the given order object.
	 *
	 * @param WC_Order $order Order object.
	 */
	private function calculate_and_update_order( $order ) {
		if ( $this->is_saving ) {
			return;
		}

		$this->is_saving = true;

		$quantity       = $this->calculate_quantity( $order );
		$net_weight     = $this->calculate_net_weight( $order );
		$gross_weight   = $this->calculate_gross_weight( $order, $net_weight );
		$declared_value = $this->calculate_declared_value( $order );

		$order->update_meta_data( self::QUANTITY_META_KEY, $quantity );
		$order->update_meta_data( self::NET_WEIGHT_META_KEY, $net_weight );
		$order->update_meta_data( self::GROSS_WEIGHT_META_KEY, $gross_weight );
		$order->update_meta_data( self::DECLARED_VALUE_META_KEY, $declared_value );
		$order->save();

		$this->is_saving = false;
	}

	/**
	 * Calculate total quantity of physical items in the order.
	 *
	 * @param WC_Order $order Order object.
	 * @return float
	 */
	public function calculate_quantity( $order ) {
		$quantity = 0;

		foreach ( $order->get_items() as $item ) {
			if ( is_callable( array( $item, 'get_quantity' ) ) ) {
				$quantity += (float) $item->get_quantity();
			}
		}

		return (float) apply_filters( 'ci_calculated_quantity', $quantity, $order );
	}

	/**
	 * Calculate net weight from product weights and quantities.
	 *
	 * @param WC_Order $order Order object.
	 * @return float
	 */
	public function calculate_net_weight( $order ) {
		$net_weight = 0;

		foreach ( $order->get_items() as $item ) {
			if( ! $item instanceof WC_Order_Item_Product ){
				continue;
			}
			
			$product = $item->get_product();
			if( ! $product ){
				continue;
			}
			$unit_weight = $product->get_meta( CI_Product_Fields::WEIGHT_META_KEY );
			if( $unit_weight === '' ){
				$unit_weight = (float) $product->get_weight();
			} else {
				$unit_weight = (float) $unit_weight;
			}

			$quantity     = (float) $item->get_quantity();
			$net_weight  += $unit_weight * $quantity;
		}

		return (float) apply_filters( 'ci_calculated_net_weight', wc_format_decimal( $net_weight ), $order );
	}

	/**
	 * Calculate gross weight.
	 *
	 * Defaults to net weight plus a packaging allowance. Override via the
	 * `ci_calculated_gross_weight` filter.
	 *
	 * @param WC_Order $order       Order object.
	 * @param float    $net_weight  Calculated net weight.
	 * @return float
	 */
	public function calculate_gross_weight( $order, $net_weight ) {
		$default_gross = $net_weight * 1.1; // 10% packaging allowance.

		return (float) apply_filters( 'ci_calculated_gross_weight', wc_format_decimal( $default_gross ), $net_weight, $order );
	}

	/**
	 * Calculate declared value.
	 *
	 * Defaults to the goods value (subtotal after discounts). Override via the
	 * `ci_calculated_declared_value` filter.
	 *
	 * @param WC_Order $order Order object.
	 * @return float
	 */
	public function calculate_declared_value( $order ) {
		$declared_value = $order->get_subtotal() - $order->get_discount_total();

		return (float) apply_filters( 'ci_calculated_declared_value', wc_format_decimal( $declared_value ), $order );
	}
}
