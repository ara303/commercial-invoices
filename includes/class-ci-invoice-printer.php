<?php
/**
 * Commercial invoice printing.
 *
 * @package Commercial_Invoices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a print button to orders and renders the printable commercial invoice.
 */
class CI_Invoice_Printer {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_print_meta_box' ), 30 );
		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_action' ) );
		add_action( 'woocommerce_order_action_print_commercial_invoice', array( $this, 'handle_order_action' ) );
		add_action( 'admin_post_ci_generate', array( $this, 'render_print_page' ) );
	}

	/**
	 * Add a print meta box to order edit screens.
	 */
	public function add_print_meta_box() {
		$screens = array( 'shop_order', 'woocommerce_page_wc-orders' );

		foreach ( $screens as $screen ) {
			add_meta_box(
				'ci_print_invoice_actions',
				__( 'Commercial Invoice', 'commercial-invoices' ),
				array( $this, 'render_print_meta_box' ),
				$screen,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the print actions meta box.
	 */
	public function render_print_meta_box() {
		$order = Commercial_Invoices::get_current_order();

		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Order not found.', 'commercial-invoices' ) . '</p>';
			return;
		}
		?>
		<p>
			<a
				href="<?php echo esc_url( $this->get_print_url( $order->get_id() ) ); ?>"
				target="_blank"
				class="button button-primary"
				style="width:100%;text-align:center;"
			>Print commercial invoice</a>
		</p>
		<?php
	}

	/**
	 * Add an action to the WooCommerce order actions dropdown.
	 *
	 * @param array $actions Order actions.
	 * @return array
	 */
	public function add_order_action( $actions ) {
		$actions['print_commercial_invoice'] = __( 'Print commercial invoice', 'commercial-invoices' );
		return $actions;
	}

	/**
	 * Handle the WooCommerce order actions dropdown selection.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function handle_order_action( $order ) {
		wp_safe_redirect( $this->get_print_url( $order->get_id() ) );
		exit;
	}

	/**
	 * Build the URL for printing an invoice.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public function get_print_url( $order_id ) {
		$url = add_query_arg( [
				'action' => 'ci_generate',
				'id'     => $order_id,
			],
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url(
			$url,
			'ci_generate_' . $order_id,
			'csrf'
		);
	}
	
	/**
	 * Accepts price (ex: '1210.99') and formats it as '£1,210.99'.
	 * 
	 * Assumes GBP; not multi-currency aware.
	 *
	 * @param  int $price
	 * @return string
	 */
	private function format_price( $price ){
		return wc_price( $price, array( 'in_span' => false ) );
	}
	
	/**
	 * Accepts weight (ex: '12.003') and formats it as '12.003 kg'.
	 * 
	 * Unit given by `woocommerce_weight_unit` option, falls back to 'kg' if unset.
	 *
	 * @param  int $weight
	 * @return string
	 */
	private function format_weight( $weight ){
		return wc_format_decimal( $weight, 3 ) . ' ' . get_option( 'woocommerce_weight_unit', 'kg' );
	}

	/**
	 * Render the printable commercial invoice.
	 */
	public function render_print_page() {
		if ( ! isset( $_GET['id'] ) ) return;

		$order_id = absint( $_GET['id'] );

		if ( ! wp_verify_nonce( $_GET['csrf'], 'ci_generate_' . $order_id ) ) return;
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_shop_order', $order_id ) ) return;

		$order = wc_get_order( $order_id );

		if ( ! $order ) return;

		// Ensure the latest automatic values are available.
		$order_fields = new CI_Order_Fields();
		$order_fields->recalculate_and_store( $order_id );
		$order = wc_get_order( $order_id );

		$currency       = $order->get_currency();
		$total_quantity = $order->get_meta( CI_Order_Fields::QUANTITY_META_KEY );
		$net_weight     = $order->get_meta( CI_Order_Fields::NET_WEIGHT_META_KEY );
		$gross_weight   = $order->get_meta( CI_Order_Fields::GROSS_WEIGHT_META_KEY );
		$total_value    = $order->get_meta( CI_Order_Fields::DECLARED_VALUE_META_KEY );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<title>Commercial invoice - order <?php echo esc_html( $order->get_order_number() ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( COMMERCIAL_INVOICES_PLUGIN_URL . 'assets/css/print.css' ); ?>" type="text/css" media="all">
</head>
<body>
	<div class="ci-invoice-wrap">
		<div class="no-print">
			<button class="button" onclick="window.print();">Print</button>
		</div>

		<header class="ci-invoice-header">
			<div class="header-left">
				<h1>Commercial Invoice</h1>
				<p>Order number: <?php echo absint( $order->get_order_number() ); ?>
			</div>
			<div class="header-right">
				<?php printf( '<h3>Date of issue: %s</h3>', wc_format_datetime( $order->get_date_created() ) ); ?>
				<?php printf( '<p>Currency: %s (%s)</p>', esc_html( $currency ), get_woocommerce_currency_symbol( $currency ) ); ?>
			</div>
		</header>

		<section class="ci-addresses">
			<div>
				<h2>Exporter / Shipper</h2>
				<?php echo wp_kses_post( $this->get_store_address() ); ?>
			</div>
			<div class="spacer"></div>
			<div>
				<h2>Consignee / Importer</h2>
				<?php echo wp_kses_post( $this->get_destination_address( $order ) ); ?>
			</div>
		</section>

		<dl class="ci-summary">
			<?php
			$summary = array(
				array(
					'name' => 'Reason for Export',
					'value' => 'Goods Sold',
				),
				array(
					'name' => 'Total Item Quantity',
					'value' => $total_quantity,
				),
				array(
					'name' => 'Total Net Weight',
					'value' => $this->format_weight( $net_weight ),
				),
				array(
					'name' => 'Total Gross Weight',
					'value' => $this->format_weight( $gross_weight ),
				),
				array(
					'name' => 'Total Value',
					'value' => $this->format_price( $total_value ),
				),
			);

			foreach( $summary as $item ){
				printf( '<dt>%s</dt><dd>%s</dd>', esc_html( $item['name'] ), esc_html( $item['value'] ) );
			}
			?>
		</dl>

		<section class="ci-items">
			<table>
				<thead>
					<tr>
						<th>Qty</th>
						<th>Description</th>
						<th>Country of Origin</th>
						<th>HS Code</th>
						<th>Unit Value</th>
						<th>Unit Weight</th>
						<th>Net Weight</th>
						<th>Line Value</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$row           = 1;
					$running_net   = 0;

					foreach ( $order->get_items() as $item ) {
						if ( ! $item instanceof WC_Order_Item_Product ) {
							continue;
						}

						$product = $item->get_product();

						if( ! $product ){
							continue;
						}

						$qty         = (float) $item->get_quantity();
						$country     = $product->get_meta( CI_Product_Fields::COUNTRY_META_KEY );
						$hs_code     = $product->get_meta( CI_Product_Fields::HSCODE_META_KEY );

						$line_total  = (float) $item->get_total();
						$unit_price  = $qty > 0 ? $line_total / $qty : 0;
						$unit_weight = (float) $product->get_weight();
						$item_net    = $unit_weight * $qty;
						$gross_ratio = $gross_weight > 0 && $net_weight > 0 ? $gross_weight / $net_weight : 1;
						$item_gross  = $item_net * $gross_ratio;

						$running_net   += $item_net;
						?>
						<tr>
							<td><?php echo absint( $qty ); ?></td>
							<td><?php echo esc_html( $item->get_name() ); ?></td>
							<td><?php echo esc_html( $country ); ?></td>
							<td><?php echo esc_html( $hs_code ); ?></td>
							<td><?php echo esc_html( $this->format_price( $unit_price ) ); ?></td>
							<td><?php echo esc_html( $this->format_weight( $unit_weight ) ); ?></td>
							<td><?php echo esc_html( $this->format_weight( $item_net ) ); ?></td>
							<td><?php echo esc_html( $this->format_price( $line_total ) ); ?></td>
						</tr>
						<?php
						++$row;
					}
					?>
				</tbody>
				<tfoot>
					<tr>
						<th><?php echo absint( $total_quantity ); ?></th>
						<th colspan="5">&nbsp;</th>
						<th><?php echo esc_html( $this->format_weight( $running_net ) ); ?></th>
						<th><?php echo esc_html( $this->format_price( $total_value ) ); ?></th>
					</tr>
				</tfoot>
			</table>
		</section>

		<footer class="ci-invoice-footer">
			<div class="ci-signature-line">
				<p>Signature: _______________________________</p>
				<p>Date shipped: _______________________________</p>
			</div>
		</footer>
	</div>
	<script>
		// window.addEventListener( 'load', function() {
		// 	window.print();
		// } );
	</script>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Build the store address block.
	 *
	 * @return string
	 */
	private function get_store_address() {
		$base          = wc_get_base_location();
		$country_name  = '';
		if ( ! empty( $base['country'] ) && WC()->countries && isset( WC()->countries->countries[ $base['country'] ] ) ) {
			$country_name = WC()->countries->countries[ $base['country'] ];
		}

		$city     = get_option( 'woocommerce_store_city' );
		$postcode = get_option( 'woocommerce_store_postcode' );
		$city_row = implode( ', ', array_filter( array( $city, $postcode ) ) );

		$address = array(
			get_bloginfo( 'name' ),
			get_option( 'woocommerce_store_address' ),
			get_option( 'woocommerce_store_address_2' ),
			$city_row,
			$country_name,
			'EORI No.: GB841996780000'
		);

		$address = array_filter( $address );
		return implode( '<br>', array_map( 'esc_html', $address ) );
	}

	/**
	 * Build the formatted shipping address for an order.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private function get_destination_address( $order ) {
		$address = $order->get_formatted_shipping_address();
		if ( empty( $address ) ) {
			$address = $order->get_formatted_billing_address();
		}
		return $address;
	}
}
