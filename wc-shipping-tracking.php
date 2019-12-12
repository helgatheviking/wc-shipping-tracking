<?php
/*
Plugin Name: Shipping Tracking Number for WooCommerce
Plugin URI: http://www.kathyisawesome.com/
Description: Add a tracking number via AJAX on the orders overview screen
Version: 1.0
Author: Kathy Darling
Author URI: http://kathyisawesome.com
Requires at least: 5.3
Tested up to: 5.3

Copyright: © 2010 Kathy Darling.
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

*/


/**
 * The Main WC_Shipping_Tracking class
 **/
if ( ! class_exists( 'WC_Shipping_Tracking' ) ) :

class WC_Shipping_Tracking {

	/**
	 * @var $id - the input name.
	 */
	public static $id = 'wc_shipping_tracking';   


	/**
	 * @var $meta - the order meta key.
	 */
	public static $meta = '_shipping_tracking_number';   

	/**
	 * @var $label - The input label, to be translated later.
	 */
	public static $label = '';   

	/**
	 * @var WC_Shipping_Tracking - the single instance of the class
	 */
	public static $version = '1.0.0';   

	/**
	 * WC_Shipping_Tracking Constructor
	 *
	 * @access public
     * @return WC_Shipping_Tracking
	 */
	public static function init() {

		// Load translation files.
		add_action( 'init', array( __CLASS__, 'load_plugin_textdomain' ) );	

		self::$label = __( 'Tracking Number', 'wc-shipping-tracking' );

		// Display on admin single order page.
		add_filter( 'woocommerce_admin_shipping_fields' , array( __CLASS__, 'admin_shipping_fields' ) );

		// Display on order overview page.
		add_action( 'woocommerce_order_details_after_customer_details' , array( __CLASS__, 'after_customer_details' ), 90 );
		
		// Display meta key in email.
		add_filter('woocommerce_email_order_meta_keys', array( __CLASS__, 'email_order_meta_keys' ) );

		// Replace shipping column with custom.
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'shop_order_columns' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_shop_order_columns' ) );

		// Load scripts.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_script' ) );
		
		// Ajax callback for saving tracking info.
		add_action( 'wp_ajax_save_tracking_number', array( __CLASS__, 'save_tracking_number' ) );

	}


	/*-----------------------------------------------------------------------------------*/
	/* Plugin Functions */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Make the plugin translation ready
	 *
	 * @return void
	 */
	public static function load_plugin_textdomain() {
		load_plugin_textdomain( 'wc-shipping-tracking' , false , dirname( plugin_basename( __FILE__ ) ) .  '/languages/' );
	}

	/**
	 * Display value in single Admin Order 
	 *
	 * @var  array $fields
	 * @return  array
	 */
	public static function admin_shipping_fields( $fields ) {

		$key = str_replace( '_shipping_', '', self::$meta );

		$fields[$key] = array(
			'label'	=> self::$label,
			'class' => '_shipping_tracking_number_field _shipping_state_field'
		);
		return $fields;
	}

	/**
	 * Display meta in my-account area Order overview
	 *
	 * @var  object $order
	 * @return  null
	 */
	public static function after_customer_details( $order ){
		
		$value = $order->get_meta( self::$meta, true );

		if( $value ){
			echo '<dt>' . self::$label . ':</dt><dd>' . $value . '</dd>';
		}

	}

	/**
	 * Add the field to order emails
	 * @var array $keys
	 * @return array
	 * @since  1.0
	 **/

	public static function email_order_meta_keys( $keys ) {
	    $keys[self::$label] = self::$meta;
	    return $keys;
	}

	/**
	 * Define custom columns for orders
	 * @param  array $existing_columns
	 * @return array
	 */
	public static function shop_order_columns( $columns ) {
		$keys = array_keys( $columns );
		$values = array_values( $columns );

		$i = array_search( 'shipping_address', $keys );
		if( $i ){
			$keys[$i] = 'shipping_address_custom';
			$values[$i] = __( 'Ship to', 'woocommerce' );
			$columns = array_combine( $keys, $values );
		}
		
		return $columns;
	}


	/**
	 * Output custom columns for coupons
	 * @param  string $column
	 */
	public static function render_shop_order_columns( $column ) {
		global $post, $the_order;

		switch ( $column ) {
			case 'shipping_address_custom' :

				$address = $the_order->get_formatted_shipping_address();

				if ( $address ) {
					echo '<a target="_blank" href="' . esc_url( $the_order->get_shipping_address_map_url() ) . '">' . esc_html( preg_replace( '#<br\s*/?>#i', ', ', $address ) ) . '</a>';
					if ( $the_order->get_shipping_method() ) {
						/* translators: %s: shipping method */
						echo '<span class="description">' . sprintf( __( 'via %s', 'woocommerce' ), esc_html( $the_order->get_shipping_method() ) ) . '</span>'; // WPCS: XSS ok.
					}

					// Display the tracking info.
					$value = $the_order->get_meta( self::$meta, true );

					$show_input = $value ? "hide" : "show";
					$show_edit = $value ? "show" : "hide";

					echo '<p><small class="wc_shipping_tracking no-link">';
					echo '<strong>' . self::$label . ': </strong><span class="' . self::$id . '_value">' . $value . '</span>';
					echo '<input data-order_id="' . $the_order->get_id() . '" data-original="' . esc_attr( $value ) . '" type="text" name="' . self::$id . '" class="no-link ' . self::$id . '_input ' . $show_input . '" value="' . esc_attr( $value ) . '">';
					echo '<a href="#" class="no-link edit_'. self::$id . ' ' . $show_edit .'">&nbsp;[' . __( 'Edit', 'woocommerce' ) . ']</a>';
					echo '<span class="no-link ' . self::$id . '_loader"></span>';
					echo '</small></p>';


				} else {
					echo '&ndash;';
				}
					
			break;
		}
	}	


	/**
	 * Enqueue our scripts
	 *
	 * @return null
	 * @since  1.0
	 */
	public static function admin_enqueue_script( $hook ) {
		global $post_type;

		if ( 'edit.php' === $hook && 'shop_order' === $post_type ) {
			wp_enqueue_script( self::$id, self::plugin_url() . '/assets/js/wc-shipping-tracking.js', false, time() );

			$l10n = array( 'security' => wp_create_nonce( 'wc-shipping-tracking' ) );

			wp_localize_script( self::$id, strtoupper( self::$id ) . '_PARAMS', $l10n );

			self::admin_head_styles();
		}
	}

	/**
	 * Load a little style
	 *
	 * @return string
	 * @since  1.0
	 */
	public static function admin_head_styles() { ?>

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
	public static function save_tracking_number() {
		
		check_ajax_referer( 'wc-shipping-tracking', 'security' );

		if( isset( $_POST[self::$id] ) && trim( $_POST[self::$id] ) != "" && isset( $_POST['order_id'] ) ){
			$value = sanitize_text_field( $_POST[self::$id] );
		
			$order_id = intval( $_POST['order_id'] );
			$order = wc_get_order( $order_id );

			if( $order instanceof WC_Order ) {
				$order->update_meta_data( self::$meta, $value );
				$order->save();
			}

			echo $value;
		}

		
		die(); 
	}


	/*-----------------------------------------------------------------------------------*/
	/* Helper Functions */
	/*-----------------------------------------------------------------------------------*/


	/**
	 * Get the plugin url.
	 *
	 * @return string
	 * @since  1.0
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 * @since  1.0
	 */
	public static function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

} //end class: do not remove or there will be no more guacamole for you

endif; // end class_exists check

// Launch the whole plugin
add_action( 'woocommerce_loaded', array( 'WC_Shipping_Tracking', 'init' ) );