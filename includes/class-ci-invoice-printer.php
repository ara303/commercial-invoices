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
		add_action( 'admin_post_ci_print_commercial_invoice', array( $this, 'render_print_page' ) );
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
		<p class="description"><b>Save changes</b> to the order (use the blue <em>Update</em> button to the right) in order for the invoice to be filled with the most up-to-date data.</p>
		<?php
	}

	/**
	 * Add an action to the WooCommerce order actions dropdown.
	 *
	 * @param array $actions Order actions.
	 * @return array
	 */
	public function add_order_action( $actions ) {
		$actions['print_commercial_invoice'] = __( 'Print Commercial Invoice', 'commercial-invoices' );
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
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => 'ci_print_commercial_invoice',
					'order_id' => $order_id,
				),
				admin_url( 'admin-post.php' )
			),
			'ci_print_invoice_' . $order_id
		);
	}

	/**
	 * Render the printable commercial invoice.
	 */
	public function render_print_page() {
		if ( ! isset( $_GET['order_id'] ) ) {
			wp_die( esc_html__( 'No order specified.', 'commercial-invoices' ) );
		}

		$order_id = absint( $_GET['order_id'] );

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'ci_print_invoice_' . $order_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'commercial-invoices' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_shop_order', $order_id ) ) {
			wp_die( esc_html__( 'You do not have permission to view this invoice.', 'commercial-invoices' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'commercial-invoices' ) );
		}

		// Ensure the latest automatic values are available.
		$order_fields = new CI_Order_Fields();
		$order_fields->recalculate_and_store( $order_id );
		$order = wc_get_order( $order_id );

		$hs_code          = $order->get_meta( CI_Order_Fields::HS_CODE_META_KEY );
		$total_quantity   = $order->get_meta( CI_Order_Fields::QUANTITY_META_KEY );
		$net_weight       = $order->get_meta( CI_Order_Fields::NET_WEIGHT_META_KEY );
		$gross_weight     = $order->get_meta( CI_Order_Fields::GROSS_WEIGHT_META_KEY );
		$declared_value   = $order->get_meta( CI_Order_Fields::DECLARED_VALUE_META_KEY );
		$currency         = $order->get_currency();
		$store_address    = $this->get_store_address();
		$shipping_address = $this->get_formatted_shipping_address( $order );

		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<title>Commercial invoice - order: <?php echo esc_html( $order->get_order_number() ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( COMMERCIAL_INVOICES_PLUGIN_URL . 'assets/css/print.css' ); ?>" type="text/css" media="all">
</head>
<body>
	<div class="ci-invoice-wrap">
		<div class="no-print">
			<button class="button" onclick="window.print();">Print</button>
		</div>

		<header class="ci-invoice-header">
			<h1>Commercial invoice - order #<?php echo esc_html( $order->get_order_number() ); ?></h1>
			<?php
			printf(
				'<h4 class="ci-invoice-date">Date created: %s</h4>',
				esc_html( wc_format_datetime( $order->get_date_created() ) )
			);
			?>
		</header>

		<section class="ci-addresses">
			<div class="ci-address-box">
				<h2>Exporter (Sender)</h2>
				<?php echo wp_kses_post( $store_address ); ?>
			</div>
			<div class="ci-address-box">
				<h2>Consignee (Recipient)</h2>
				<?php echo wp_kses_post( $shipping_address ); ?>
			</div>
		</section>

		<section class="ci-summary">
			<table>
				<tbody>
					<tr>
						<th><?php esc_html_e( 'HS Code', 'commercial-invoices' ); ?></th>
						<td><?php echo esc_html( $hs_code ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Total Quantity', 'commercial-invoices' ); ?></th>
						<td><?php echo esc_html( $total_quantity ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Total Net Weight', 'commercial-invoices' ); ?></th>
						<td><?php echo esc_html( wc_format_decimal( $net_weight, 3 ) ); ?> kg</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Total Gross Weight', 'commercial-invoices' ); ?></th>
						<td><?php echo esc_html( wc_format_decimal( $gross_weight, 3 ) ); ?> kg</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Declared Value', 'commercial-invoices' ); ?></th>
						<td><?php echo wp_kses_post( wc_price( $declared_value, array( 'currency' => $currency ) ) ); ?></td>
					</tr>
				</tbody>
			</table>
		</section>

		<section class="ci-items">
			<table>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Qty', 'commercial-invoices' ); ?></th>
						<th><?php esc_html_e( 'Name', 'commercial-invoices' ); ?></th>
						<th><?php esc_html_e( 'Country of Origin', 'commercial-invoices' ); ?></th>
						<th><?php esc_html_e( 'HS Code', 'commercial-invoices' ); ?></th>
						<th><?php esc_html_e( 'Value', 'commercial-invoices' ); ?></th>
						<th><?php esc_html_e( 'Unit Weight (kg)', 'commercial-invoices' ); ?></th>
						<th><?php esc_html_e( 'Net Weight (kg)', 'commercial-invoices' ); ?></th>
						<th><?php esc_html_e( 'Gross Weight (kg)', 'commercial-invoices' ); ?></th>
						<th><?php esc_html_e( 'Line Value', 'commercial-invoices' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$row      = 1;
					$running_net   = 0;
					$running_gross = 0;

					foreach ( $order->get_items() as $item ) {
						if ( ! $item instanceof WC_Order_Item_Product ) {
							continue;
						}

						$product      = $item->get_product();
						$qty          = (float) $item->get_quantity();
						$unit_weight  = (float) $product->get_meta( CI_Product_Fields::WEIGHT_META_KEY );
						if ( $unit_weight === 0 ) {
							$unit_weight = (float) $product->get_weight();
						}
						$item_net     = $unit_weight * $qty;
						// Apply same gross-weight ratio used at order level.
						$gross_ratio  = $gross_weight > 0 && $net_weight > 0 ? $gross_weight / $net_weight : 1;
						$item_gross   = $item_net * $gross_ratio;
						$line_total   = (float) $item->get_total();
						$unit_price   = $qty > 0 ? $line_total / $qty : 0;
						$country      = $product->get_meta( CI_Product_Fields::COUNTRY_META_KEY );

						$running_net   += $item_net;
						$running_gross += $item_gross;
						?>
						<tr>
							<td><?php echo esc_html( wc_format_decimal( $qty ) ); ?></td>
							<td><?php echo esc_html( $item->get_name() ); ?></td>
							<td><?php echo esc_html( $country ); ?></td>
							<td><?php echo esc_html( $hs_code ); ?></td>
							<td><?php echo wp_kses_post( wc_price( $unit_price, array( 'currency' => $currency ) ) ); ?></td>
							<td><?php echo esc_html( wc_format_decimal( $unit_weight, 2 ) ); ?></td>
							<td><?php echo esc_html( wc_format_decimal( $item_net, 2 ) ); ?></td>
							<td><?php echo esc_html( wc_format_decimal( $item_gross, 2 ) ); ?></td>
							<td><?php echo wp_kses_post( wc_price( $line_total, array( 'currency' => $currency ) ) ); ?></td>
						</tr>
						<?php
						++$row;
					}
					?>
				</tbody>
				<tfoot>
					<tr>
						<th><?php echo esc_html( wc_format_decimal( $total_quantity ) ); ?></th>
						<th colspan="4">&nbsp;</th>
						<th><?php echo esc_html( wc_format_decimal( $running_net, 2 ) ); ?> kg</th>
						<th><?php echo esc_html( wc_format_decimal( $running_gross, 2 ) ); ?> kg</th>
						<th>&nbsp;</th>
						<th><?php echo wp_kses_post( wc_price( $declared_value, array( 'currency' => $currency ) ) ); ?></th>
					</tr>
				</tfoot>
			</table>
		</section>

		<footer class="ci-invoice-footer">
			<div class="ci-signature-line">
				<p><?php esc_html_e( 'Signature: _______________________________', 'commercial-invoices' ); ?></p>
				<p><?php esc_html_e( 'Date shipped: _______________________________', 'commercial-invoices' ); ?></p>
			</div>
		</footer>
	</div>
	<script>
		window.addEventListener( 'load', function() {
			// Auto-open the browser print dialog.
			// window.print();
		} );
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
	private function get_formatted_shipping_address( $order ) {
		$address = $order->get_formatted_shipping_address();
		if ( empty( $address ) ) {
			$address = $order->get_formatted_billing_address();
		}
		return $address;
	}
}
