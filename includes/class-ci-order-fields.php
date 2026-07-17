<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CI_Order_Fields {
	/**
	 * Meta keys.
	 */
	const QUANTITY_META_KEY     = '_ci_quantity';
	const TOTAL_VALUE_META_KEY  = '_ci_total_value';
	const NET_WEIGHT_META_KEY   = '_ci_net_weight';
	const GROSS_WEIGHT_META_KEY = '_ci_gross_weight';

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
		add_action( 'woocommerce_saved_order_items', array( $this, 'calculate_order_data' ), 10, 2 );
	}

	/**
	 * Add the commercial invoice meta box to order edit screens.
	 */
	public function add_meta_box() {
		$screens = array( 'shop_order', 'woocommerce_page_wc-orders' );

		foreach ( $screens as $screen ) {
			add_meta_box(
				'ci_order_invoices',
				'Commercial Invoices',
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
		$commercial_invoices = new Commercial_Invoices();
		$order               = $commercial_invoices::get_current_order();

		if ( ! $order ) {
			echo '<p>Order not found.</p>';
			return;
		}

		$quantity     = $this->calculate_quantity( $order );
		$total_value  = $this->calculate_total_value( $order );
		$net_weight   = $this->calculate_net_weight( $order );
		$gross_weight = $this->calculate_gross_weight( $order, $net_weight );
		$currency     = $order->get_currency();
		?>

		<dl class="ci-order-summary">
			<?php
			$packaging_allowance = (float) apply_filters( 'ci_packaging_allowance_percentage', 10, $order );
			
			$summary = array(
				'Qty'            => absint( $quantity ),
				'Value'          => $commercial_invoices->format_price( $total_value, $currency ),
				'Weight (net)'   => $commercial_invoices->format_weight( $net_weight ),
				'Packaging %'    => floatval( $packaging_allowance ),
				'Weight (gross)' => $commercial_invoices->format_weight( $gross_weight ),
			);
			
			foreach( $summary as $key => $value ){
				printf( '<dt>%s:</dt><dd>%s</dd>', esc_html( $key ), esc_html( $value ) );
			}
			?>
		</dl>
		<style>
			.ci-order-summary {
				display: grid;
				grid-template-columns: fit-content(200px) 1fr;
				column-gap: 1em;
			}

			.ci-order-summary dd {
				margin: 0;
			}
		</style>
		<?php
		do_action( 'commercial_invoice_meta_box_after' );
	}

	/**
	 * Calculate the order data.
	 *
	 * @param int $order_id Order ID.
	 */
	public function calculate_order_data( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || $this->is_saving ) {
			return;
		}

		$this->is_saving = true;

		$quantity     = $this->calculate_quantity( $order );
		$total_value  = $this->calculate_total_value( $order );
		$net_weight   = $this->calculate_net_weight( $order );
		$gross_weight = $this->calculate_gross_weight( $order, $net_weight );

		$order->update_meta_data( self::QUANTITY_META_KEY, $quantity );
		$order->update_meta_data( self::TOTAL_VALUE_META_KEY, $total_value );
		$order->update_meta_data( self::NET_WEIGHT_META_KEY, $net_weight );
		$order->update_meta_data( self::GROSS_WEIGHT_META_KEY, $gross_weight );
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
	 * Calculate total value.
	 *
	 * Defaults to the goods value (subtotal after discounts). Override via the
	 * `ci_calculated_total_value` filter.
	 *
	 * @param WC_Order $order Order object.
	 * @return float
	 */
	public function calculate_total_value( $order ) {
		$total_value = $order->get_subtotal() - $order->get_discount_total();

		return (float) apply_filters( 'ci_calculated_total_value', wc_format_decimal( $total_value ), $order );
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

			$unit_weight = (float) $product->get_weight();
			$quantity    = (float) $item->get_quantity();
			$net_weight += $unit_weight * $quantity;
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
		$packaging_allowance = (float) apply_filters( 'ci_packaging_allowance_percentage', 10, $order );
		$default_gross       = $net_weight * ( 1 + $packaging_allowance / 100 );

		return (float) apply_filters( 'ci_calculated_gross_weight', wc_format_decimal( $default_gross ), $net_weight, $order );
	}
}
