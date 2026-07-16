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
		add_filter( 'manage_product_posts_columns', array( $this, 'add_columns' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_columns' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'hide_columns' ) );
	}

	/**
	 * Product fields to create inputs for and handle saving of.
	 * 
	 * Note: Manually replicated for displaying per line item.
	 * @see class-ci-invoice-printer.php
	 */
	public function product_fields(){
		return array(
			'ci_country_of_origin' => array( 
				'name' => 'County of Origin',
				'name_short' => 'C.o.O.',
				'type' => 'text',
				'placeholder' => 'e.g., China',
			),
			'ci_hs_code' => array(
				'name' => 'HS Code',
				'name_short' => 'HS Code',
				'type' => 'text',
			),
		);
	}

	public function render_quick_edit_fields(){
		?>
		<div class="fields--commercial-invoices">
			<?php
			$product_fields = $this->product_fields();

			foreach( $product_fields as $id => $fields ): 
				$placeholder = $fields['placeholder'] ?? ''; ?>
				<label>
					<span class="title"><?= $fields['name_short']; ?></span>
					<span class="input-text-wrap">
						<input type="text" name="<?= $id; ?>" class="text commercial_invoice_field" placeholder="<?= $placeholder; ?>" value="">
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

					<?php
					$product_fields = $this->product_fields();

					foreach( $product_fields as $id => $fields ){
						echo( "var value_$id = \$( '#post-' + post_id ).find( '.column-$id' ).text();\r\n" );
						echo( "if( value_$id ) \$( '.commercial_invoice_field[name=\"$id\"]' ).val( value_$id );\r\n" );
					}
					?>
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
			$columns[$id] = $fields['name'];
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
			$placeholder = $fields['placeholder'] ?? '';
			woocommerce_wp_text_input(
				array(
					'id'          => $id,
					'label'       => $fields['name'],
					'type'        => $fields['type'],
					'value'       => $product_object->get_meta( $id ),
					'placeholder' => $placeholder,
				)
			);
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

		echo '<div>';

		foreach( $product_fields as $id => $fields ){
			$row_id = "variation_{$id}_{$loop}";
			$name   = "variation_{$id}[{$loop}]";
			$value  = get_post_meta( $variation->ID, $id, true ); ?>
			<p class="form-row form-row-full">
				<label for="<?= $row_id; ?>"><?= $fields['name']; ?></label>
				<input type="<?= $fields['type']; ?>" id="<?= $row_id; ?>" name="<?= $name; ?>" value="<?= $value; ?>">
			</p>
			<?php 
		}

		echo '</div>';
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
