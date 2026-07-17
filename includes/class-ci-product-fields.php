<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Adds and saves the Country of Origin and HS Code fields on products.
 */
class CI_Product_Fields {
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

		// Add (and hide) columns because that's the WP Core way to populate quick edit values.
		add_filter( 'manage_product_posts_columns', array( $this, 'add_columns' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_columns' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'hide_columns' ) );
	}

	/**
	 * Build base associative array of product fields to create and save for all edit means.
	 * 
	 * Use filter `ci_product_fields` with key for ID and value an array.
	 */
	public static function product_fields(){
		$fields = array(
			'ci_country_of_origin' => array( 
				'label'       => 'Country of Origin',
				'label_short' => 'C.o.O.',
				'placeholder' => 'e.g., China',
			),
			'ci_hs_code' => array(
				'label'       => 'HS Code',
				'label_short' => 'HS Code',
			),
		);

		return apply_filters( 'ci_product_fields', $fields );
	}

	public function render_quick_edit_fields(){
		?>
		<div class="fields--commercial-invoices">
			<?php
			$product_fields = $this->product_fields();

			foreach( $product_fields as $id => $fields ): 
				$placeholder = $fields['placeholder'] ?? ''; ?>
				<label>
					<span class="title"><?php echo esc_html( $fields['label_short'] ); ?></span>
					<span class="input-text-wrap">
						<input type="text" name="<?php echo esc_attr( $id ); ?>" class="text commercial_invoice_field" placeholder="<?php echo esc_attr( $placeholder ); ?>" value="">
					</span>
				</label>
			<?php endforeach; ?>
			<br class="clear" />
		</div>
		<?php
	}

	public function save_quick_edit_fields( $post_id, $post ){
		$product_fields = $this->product_fields();

		foreach( $product_fields as $id => $fields ){
			if( isset( $_REQUEST[$id] ) ){
				$value = sanitize_text_field( wp_unslash( $_REQUEST[$id] ) );

				update_post_meta( $post_id, $id, $value );
			}
		}
	}

	public function quick_edit_populate() {
		global $typenow;
		if ( 'product' !== $typenow ) {
			return;
		}

		$field_ids = array_keys( $this->product_fields() );
		?>
		<script type="text/javascript">
			jQuery( function( $ ) {
				var fieldIds = <?php echo wp_json_encode( $field_ids ); ?>;

				var $orig_edit = inlineEditPost.edit;
				inlineEditPost.edit = function( id ) {
					// Run the original WP quick edit first so the row is rendered.
					$orig_edit.apply( this, arguments );

					var post_id = 'object' === typeof id
						? parseInt( inlineEditPost.getId( id ), 10 )
						: parseInt( id, 10 );

					if ( ! post_id ) {
						return;
					}

					var $row = $( '#post-' + post_id );

					fieldIds.forEach( function( fieldId ) {
						var value = $row.find( '.column-' + fieldId ).text();
						if ( value ) {
							$( '.commercial_invoice_field[name="' + fieldId + '"]' ).val( value );
						}
					} );
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
	public function add_columns( $columns ) {
		$product_fields = $this->product_fields();

		foreach( $product_fields as $id => $fields ){
			$columns[$id] = $fields['label'];
		}

		return $columns;
	}

	/**
	 * Output the country-of-origin meta into the inline column cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_columns( $column, $post_id ) {
		$product_fields = $this->product_fields();

		foreach( $product_fields as $id => $fields ){
			if ( $id === $column ) {
				echo esc_html( get_post_meta( $post_id, $id, true ) );
			}
		}
	}

	/**
	 * Hide the helper column on the product list screen.
	 */
	public function hide_columns() {
		$screen = get_current_screen();
		
		if ( $screen && 'edit-product' === $screen->id ) {
			$product_fields = $this->product_fields();

			foreach( $product_fields as $id => $fields ){
				echo( "<style>th.column-$id, td.column-$id { display: none !important; }</style>\r\n" );
			}
		}
	}

	/**
	 * Render product fields.
	 */
	public function render_fields() {
		global $product_object;

		echo '<div class="options_group options_group--commercial-invoices">';

		$product_fields = $this->product_fields();

		foreach( $product_fields as $id => $fields ){
			woocommerce_wp_text_input( array(
				'id'          => $id,
				'label'       => $fields['label'],
				'value'       => $product_object->get_meta( $id ),
				'placeholder' => $fields['placeholder'] ?? '',
			) );
		}

		echo '</div>';
	}

	/**
	 * Save product fields.
	 *
	 * @param WC_Product $product Product object.
	 */
	public function save_fields( $product ) {
		$product_fields = $this->product_fields();

		foreach( $product_fields as $id => $fields ){
			if( isset( $_POST[$id] ) ){
				$product->update_meta_data( $id, sanitize_text_field( wp_unslash( $_POST[$id] ) ) );
			}
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
		$product_fields = $this->product_fields();

		foreach( $product_fields as $id => $fields ){
			woocommerce_wp_text_input( array(
				'id'            => "variation_{$id}[{$loop}]",
				'label'         => $fields['label'],
				'wrapper_class' => 'form-row form-row-full',
				'value'         => get_post_meta( $variation->ID, $id, true ),
				'placeholder'   => $fields['placeholder'] ?? '',
				'description'   => 'Leave blank to inherit value from parent product.'
			) );
		}
	}

	/**
	 * Save variation fields.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Variation loop index.
	 */
	public function save_variation_fields( $variation_id, $loop ) {
		$product_fields = $this->product_fields();

		foreach( $product_fields as $id => $fields ){
			$field_name = "variation_{$id}";

			if( isset( $_POST[$field_name][$loop] ) ){
				update_post_meta( $variation_id, $id, sanitize_text_field( wp_unslash( $_POST[$field_name][$loop] ) ) );
			}
		}
	}
}
