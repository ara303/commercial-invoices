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
 * Adds and saves the Country of Origin and HS Code fields on products.
 */
class CI_Product_Fields {

	/**
	 * Meta keys.
	 */
	const COUNTRY_META_KEY = '_ci_country_of_origin';
	const HSCODE_META_KEY  = '_ci_hs_code';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_fields' ) );

		// Variation support.
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );

		// Quick edit support.
		add_action( 'woocommerce_product_quick_edit_start', array( $this, 'render_quick_edit_fields' ) );
		add_action( 'woocommerce_product_bulk_and_quick_edit', array( $this, 'save_quick_edit_fields' ), 10, 2 );
		add_action( 'admin_footer-edit.php', array( $this, 'quick_edit_populate' ) );

		// Bulk edit support.
		add_action( 'woocommerce_product_bulk_edit_start', array( $this, 'render_quick_edit_fields' ) );

		// Hidden list-table column that carries the meta value into the row's DOM so quick edit can read it.
		add_filter( 'manage_product_posts_columns', array( $this, 'add_inline_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_inline_column' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'hide_inline_column' ) );
	}

	public function render_quick_edit_fields(){
		?>
		<div class="fields--commercial-invoices">
			<label>
				<span class="title">C.o.O.</span>
				<span class="input-text-wrap">
					<input type="text" name="_country_of_origin" class="text country_of_origin" placeholder="e.g., China" value="">
				</span>
			</label>
			<label>
				<span class="title">HS Code</span>
				<span class="input-text-wrap">
					<input type="text" name="_hs_code" class="text hs_code" placeholder="e.g., 08081000" value="">
				</span>
			</label>
			<br class="clear" />
		</div>
		<?php
	}

	public function save_quick_edit_fields( $post_id, $post ){
		if( isset( $_REQUEST['_country_of_origin'] ) ){
			$country = sanitize_text_field( wp_unslash( $_REQUEST['_country_of_origin'] ) );
			
			update_post_meta( $post_id, self::COUNTRY_META_KEY, $country );
		}

		if( isset( $_REQUEST['_hs_code'] ) ){
			$hs_code = sanitize_text_field( wp_unslash( $_REQUEST['_hs_code'] ) );
			
			update_post_meta( $post_id, self::HSCODE_META_KEY, $hs_code );
		}
	}

	public function quick_edit_populate() {
		global $typenow;
		if ( 'product' !== $typenow ) {
			return;
		}
		?>
		<script type="text/javascript">
			jQuery( function( $ ) {
				var $orig_edit = inlineEditPost.edit;
				inlineEditPost.edit = function( id ) {
					// Run the original WP quick edit first so the row is rendered.
					$orig_edit.apply( this, arguments );

					var post_id = 0;
					if ( 'object' === typeof id ) {
						post_id = parseInt( inlineEditPost.getId( id ), 10 );
					} else {
						post_id = parseInt( id, 10 );
					}
					if ( ! post_id ) {
						return;
					}

					var countryValue = $( '#post-' + post_id ).find( '.column-_ci_country_inline' ).text();
					if ( countryValue ) {
						$( '.inline-edit-row input[name="_country_of_origin"]' ).val( countryValue );
					}

					var hscodeValue = $( '#post-' + post_id ).find( '.column-_ci_hs_code_inline' ).text();
					if ( hscodeValue ) {
						$( '.inline-edit-row input[name="_hs_code"]' ).val( hscodeValue );
					}
				};
			});
		</script>
		<?php
	}

	/**
	 * Register an invisible column used solely to surface the meta value in the row DOM for quick edit.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_inline_column( $columns ) {
		$columns['_ci_country_inline'] = 'Country of Origin';
		$columns['_ci_hs_code_inline'] = 'HS Code';
		return $columns;
	}

	/**
	 * Output the country-of-origin meta into the inline column cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_inline_column( $column, $post_id ) {
		if ( '_ci_country_inline' === $column ) {
			echo esc_html( get_post_meta( $post_id, self::COUNTRY_META_KEY, true ) );
		}

		if ( '_ci_hs_code_inline' === $column ) {
			echo esc_html( get_post_meta( $post_id, self::HSCODE_META_KEY, true ) );
		}
	}

	/**
	 * Hide the helper column on the product list screen.
	 */
	public function hide_inline_column() {
		$screen = get_current_screen();
		if ( $screen && 'edit-product' === $screen->id ) {
			echo '<style>th.column-_ci_country_inline, td.column-_ci_country_inline, th.column-_ci_hs_code_inline, td.column-_ci_hs_code_inline{display:none !important;}</style>';
		}
	}

	/**
	 * Render product fields.
	 */
	public function render_fields() {
		global $product_object;

		$country = $product_object ? $product_object->get_meta( self::COUNTRY_META_KEY ) : '';
		$hs_code = $product_object ? $product_object->get_meta( self::HSCODE_META_KEY ) : '';

		echo '<div class="options_group options_group--commercial-invoices">';

		woocommerce_wp_text_input(
			array(
				'id'          => self::COUNTRY_META_KEY,
				'label'       => 'Country of Origin',
				'type'        => 'text',
				'value'       => $country,
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => self::HSCODE_META_KEY,
				'label'       => 'HS Code',
				'type'        => 'text',
				'value'       => $hs_code,
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

		if ( isset( $_POST[ self::HSCODE_META_KEY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$product->update_meta_data(
				self::HSCODE_META_KEY,
				sanitize_text_field( wp_unslash( $_POST[ self::HSCODE_META_KEY ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			);
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
		$hs_code = get_post_meta( $variation->ID, self::HSCODE_META_KEY, true );
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
			<label for="variable_ci_hs_code_<?php echo esc_attr( $loop ); ?>">HS Code</label>
			<input
				type="text"
				id="variable_ci_hs_code_<?php echo esc_attr( $loop ); ?>"
				name="variable_ci_hs_code[<?php echo esc_attr( $loop ); ?>]"
				value="<?php echo esc_attr( $hs_code ); ?>"
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

		if ( isset( $_POST['variable_ci_hs_code'][ $loop ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$weight = wc_clean( wp_unslash( $_POST['variable_ci_hs_code'][ $loop ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta(
				$variation_id,
				self::HSCODE_META_KEY,
				sanitize_text_field( $weight )
			);
		}
	}
}
