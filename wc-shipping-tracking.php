<?php
/**
 * Plugin Name: Shipping Tracking Number for WooCommerce
 * Plugin URI: http://www.kathyisawesome.com/
 * Description: Add a tracking number via AJAX on the orders overview screen
 * Version: 1.1.0
 * Author: Kathy Darling
 * Author URI: http://kathyisawesome.com
 * Requires at least: 5.3
 * Tested up to: 5.5
 * 
 * Copyright: © 2020 Kathy Darling.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * 
 */

namespace WC_Shipping_Tracking;

define( 'WC_SHIPPING_TRACKING_VERSION', '1.1.0' );

/**
 * WC_Shipping_Tracking attach hooks and filters
 */
function add_hooks_and_filters() {

	// Load translation files.
	add_action( 'init', __NAMESPACE__ . '\load_plugin_textdomain' );

	// Display on admin single order page.
	add_filter( 'woocommerce_admin_shipping_fields' , __NAMESPACE__ . '\admin_shipping_fields' );

	// Display on order overview page.
	add_action( 'woocommerce_order_details_after_customer_details' , __NAMESPACE__ . '\after_customer_details', 90 );
	
	// Display meta key in email.
	add_filter('woocommerce_email_order_meta_fields', __NAMESPACE__ . '\email_order_meta_fields', 10, 3 );

	// Replace shipping column with custom.
	add_filter( 'manage_edit-shop_order_columns', __NAMESPACE__ . '\shop_order_columns', 20 );
	add_action( 'manage_shop_order_posts_custom_column', __NAMESPACE__ . '\render_shop_order_columns' );

	// Load scripts.
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\admin_enqueue_script' );
	
	// Ajax callback for saving tracking info.
	add_action( 'wp_ajax_save_tracking_number', __NAMESPACE__ . '\save_tracking_number' );

}
add_action( 'woocommerce_loaded', __NAMESPACE__  . '\add_hooks_and_filters' );


/*-----------------------------------------------------------------------------------*/
/* Plugin Functions */
/*-----------------------------------------------------------------------------------*/

/**
 * Make the plugin translation ready
 *
 * @return void
 */
function load_plugin_textdomain() {
	\load_plugin_textdomain( 'wc-shipping-tracking' , false , dirname( plugin_basename( __FILE__ ) ) .  '/languages/' );
}

/**
 * Display value in single Admin Order 
 *
 * @param  array $fields
 * @return  array
 */
function admin_shipping_fields( $fields ) {

	$fields['tracking_number'] = array(
		'label'	=> __( 'Tracking Number', 'wc-shipping-tracking' ),
		'class' => '_shipping_tracking_number_field _shipping_state_field'
	);
	return $fields;
}

/**
 * Display meta in my-account area Order overview
 *
 * @param  object $order
 * @return  null
 */
function after_customer_details( $order ) {
	
	$value = $order->get_meta( '_shipping_tracking_number', true );

	if ( $value ) { ?>
		<dt><?php esc_html_e( 'Tracking Number:', 'wc-shipping-tracking' ); ?></dt><dd><?php echo esc_html( $value ); ?></dd>
	<?php
	}

}

/**
 * Add the field to order emails
 * 
 * @param array $fields = array( 'label' => string, 'value' => mixed )
 * @param bool     $sent_to_admin If should sent to admin.
 * @param WC_Order $order         Order instance.
 * @return array
 * @since  1.0
 **/
function email_order_meta_fields( $fields, $sent_to_admin, $order ) {
	$fields[] = array( 
		'label' => esc_html__( 'Tracking Number', 'wc-shipping-tracking' ),
		'value' => $order->get_meta( '_shipping_tracking_number', true )
	);
    return $fields;
}

/**
 * Define custom columns for orders
 * @param  array $existing_columns
 * @return array
 */
function shop_order_columns( $columns ) {
	$keys = array_keys( $columns );
	$values = array_values( $columns );

	$i = array_search( 'shipping_address', $keys );
	if ( $i ) {
		$keys[$i] = 'shipping_address_custom';
		$values[$i] = __( 'Ship to', 'wc-shipping-tracking' );
		$columns = array_combine( $keys, $values );
	}
	
	return $columns;
}

/**
 * Output custom columns for coupons
 * @param  string $column
 */
function render_shop_order_columns( $column ) {
	global $post, $the_order;

	switch ( $column ) {
		case 'shipping_address_custom' :

			$address = $the_order->get_formatted_shipping_address();

			if ( $address ) : ?>

				<a target="_blank" href="<?php echo esc_url( $the_order->get_shipping_address_map_url() );?>"><?php echo esc_html( preg_replace( '#<br\s*/?>#i', ', ', $address ) ); ?></a>

				<?php
				if ( $the_order->get_shipping_method() ) :

					// Display the tracking info.
					$value = $the_order->get_meta( '_shipping_tracking_number', true );

					print_r($value);

					$show_input = $value ? 'hide' : 'show';
					$show_edit  = $value ? 'show' : 'hide';

					/* translators: %s: shipping method */
					?>
					<span class="description"><?php echo sprintf( esc_html__( 'via %s', 'wc-shipping-tracking' ), esc_html( $the_order->get_shipping_method() ) ); ?></span>

				<?php endif; ?>

				<p class="no-link"><small class="wc_shipping_tracking" >
					<strong><?php echo esc_html__( 'Tracking Number', 'wc-shipping-tracking' ); ?></strong>
					<span class="wc_shipping_tracking_value"><?php echo esc_html( $value ); ?></span>
					
					<input data-order_id="<?php echo esc_attr( $the_order->get_id() );?>" data-original="<?php echo esc_attr( $value );?>" type="text" name="wc_shipping_tracking" class="wc_shipping_tracking_input <?php echo esc_attr( $show_input ); ?>" value="<?php echo esc_attr( $value );?>" />
				
					<button href="#" class="button edit_wc_shipping_tracking <?php echo esc_attr( $show_edit ); ?>"><?php esc_html_e( 'Edit', 'wc-shipping-tracking' ); ?></button>
					<span class="wc_shipping_tracking_loader"></span>
				</small></p>

			<?php


			else :
				echo '&ndash;';
			endif;
				
		break;
	}
}	


/**
 * Enqueue our scripts
 *
 * @return null
 * @since  1.0
 */
function admin_enqueue_script( $hook ) {
	global $post_type;

	if ( 'edit.php' === $hook && 'shop_order' === $post_type ) {
		wp_enqueue_script( 'wc_shipping_tracking', plugins_url( 'assets/js/wc-shipping-tracking.js', __FILE__ ), false, WC_SHIPPING_TRACKING_VERSION );

		$l10n = array( 'security' => wp_create_nonce( 'wc-shipping-tracking' ) );

		wp_localize_script( 'wc_shipping_tracking', 'WC_SHIPPING_TRACKING_PARAMS', $l10n );

		admin_head_styles();
	}
}

/**
 * Load a little style
 *
 * @return string
 * @since  1.0
 */
function admin_head_styles() { ?>

	<style type="text/css">';
		.wc_shipping_tracking { position: relative; } 
		.wc_shipping_tracking .hide {display: none;} 
		.wc_shipping_tracking .show {display:inline-block;}
		.wc_shipping_tracking_loader{
			position: relative;
			display: none;
			background: url( <?php echo WC()->plugin_url(); ?>/assets/images/wpspin.gif ) center no-repeat; 
			height: 16px; 
			width: 16px; 
			vertical-align: baseline; 
			margin-left: 2px; 
		}

		@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
			.wc_shipping_tracking_loader:after {
				background-image: url(<?php echo WC()->plugin_url(); ?>/assets/images/wpspin.gif@2x.gif);
				background-size: 16px 16px;
			}
		}

		</style>

	<?php
}

/**
 * Ajax callback
 *
 * @return string
 * @since  1.0
 */
function save_tracking_number() {
	
	check_ajax_referer( 'wc-shipping-tracking', 'security' );

	$response = '';

	if ( isset( $_POST['wc_shipping_tracking'] ) && '' !== trim( $_POST['wc_shipping_tracking'] ) && isset( $_POST['order_id'] ) ) {

		$value = sanitize_text_field( $_POST['wc_shipping_tracking'] );

		$order_id = intval( $_POST['order_id'] );

		$order = wc_get_order( $order_id );

		if ( $order instanceof \WC_Order ) {
			$order->update_meta_data( '_shipping_tracking_number', $value );
			$result = $order->save();

			if( $result ) {
				$response = $value;
			}
		}	
	}
	wp_send_json( $response ); 
}
