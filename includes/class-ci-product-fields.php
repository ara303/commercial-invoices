<?php
/**
 * Product fields for Commercial Invoices.
 *
 * @package Commercial_Invoices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds and saves the Country of Origin and commercial-invoice Weight fields on products.
 */
class CI_Product_Fields {

	/**
	 * Meta keys.
	 */
	const COUNTRY_META_KEY = '_ci_country_of_origin';
	const WEIGHT_META_KEY  = '_ci_unit_weight';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_product_options_shipping', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_fields' ) );

		// Variation support.
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );
	}

	/**
	 * Render product fields in the Shipping product data tab.
	 */
	public function render_fields() {
		global $product_object;

		$country = $product_object ? $product_object->get_meta( self::COUNTRY_META_KEY ) : '';
		$weight  = $product_object ? $product_object->get_meta( self::WEIGHT_META_KEY ) : '';

		echo '<div class="options_group">';

		woocommerce_wp_text_input(
			array(
				'id'          => self::COUNTRY_META_KEY,
				'label'       => __( 'Country of Origin', 'commercial-invoices' ),
				'desc_tip'    => true,
				'description' => __( 'Country where this product is manufactured or produced.', 'commercial-invoices' ),
				'type'        => 'text',
				'value'       => $country,
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => self::WEIGHT_META_KEY,
				'label'       => __( 'Invoice Unit Weight (kg)', 'commercial-invoices' ),
				'desc_tip'    => true,
				'description' => __( 'Weight used for commercial invoice calculations. Falls back to the WooCommerce shipping weight if empty.', 'commercial-invoices' ),
				'type'        => 'number',
				'value'       => $weight,
				'custom_attributes' => array(
					'step' => '0.001',
					'min'  => '0',
				),
			)
		);

		echo '</div>';
	}

	/**
	 * Save product fields.
	 *
	 * @param WC_Product $product Product object.
	 */
	public function save_fields( $product ) {
		if ( isset( $_POST[ self::COUNTRY_META_KEY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$product->update_meta_data(
				self::COUNTRY_META_KEY,
				sanitize_text_field( wp_unslash( $_POST[ self::COUNTRY_META_KEY ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			);
		}

		if ( isset( $_POST[ self::WEIGHT_META_KEY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$weight = wc_clean( wp_unslash( $_POST[ self::WEIGHT_META_KEY ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$product->update_meta_data( self::WEIGHT_META_KEY, '' === $weight ? '' : wc_format_decimal( $weight ) );
		}
	}

	/**
	 * Render fields on a product variation.
	 *
	 * @param int        $loop           Variation loop index.
	 * @param array      $variation_data Variation data.
	 * @param WP_Post    $variation      Variation post object.
	 */
	public function render_variation_fields( $loop, $variation_data, $variation ) {
		$country = get_post_meta( $variation->ID, self::COUNTRY_META_KEY, true );
		$weight  = get_post_meta( $variation->ID, self::WEIGHT_META_KEY, true );
		?>
		<div class="form-row form-row-first">
			<label for="variable_ci_country_<?php echo esc_attr( $loop ); ?>">
				<?php esc_html_e( 'Country of Origin', 'commercial-invoices' ); ?>
			</label>
			<input
				type="text"
				id="variable_ci_country_<?php echo esc_attr( $loop ); ?>"
				name="variable_ci_country[<?php echo esc_attr( $loop ); ?>]"
				value="<?php echo esc_attr( $country ); ?>"
			/>
		</div>
		<div class="form-row form-row-last">
			<label for="variable_ci_weight_<?php echo esc_attr( $loop ); ?>">
				<?php esc_html_e( 'Invoice Unit Weight (kg)', 'commercial-invoices' ); ?>
			</label>
			<input
				type="number"
				step="0.001"
				min="0"
				id="variable_ci_weight_<?php echo esc_attr( $loop ); ?>"
				name="variable_ci_weight[<?php echo esc_attr( $loop ); ?>]"
				value="<?php echo esc_attr( $weight ); ?>"
			/>
		</div>
		<?php
	}

	/**
	 * Save variation fields.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Variation loop index.
	 */
	public function save_variation_fields( $variation_id, $loop ) {
		if ( isset( $_POST['variable_ci_country'][ $loop ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta(
				$variation_id,
				self::COUNTRY_META_KEY,
				sanitize_text_field( wp_unslash( $_POST['variable_ci_country'][ $loop ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			);
		}

		if ( isset( $_POST['variable_ci_weight'][ $loop ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$weight = wc_clean( wp_unslash( $_POST['variable_ci_weight'][ $loop ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $variation_id, self::WEIGHT_META_KEY, '' === $weight ? '' : wc_format_decimal( $weight ) );
		}
	}
}
