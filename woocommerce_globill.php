<?php
/*
Plugin Name: WooCommerce Globill Payment Gateway
Plugin URI: http://codemypain.com
Description: Globill payment gateway plugin for woocommerce
Version: 1.0.1
Author: Isaac Oyelowo
Author URI: http://www.isaacoyelowo.com
*/

/*
#begin plugin
*/

// define plugin directory

define( 'GLP_URL', plugin_dir_url( __FILE__ ) );
function globill_woocommerce() 
{
	require_once dirname(__FILE__) . '/class/globill.class.php';
	if( !is_admin() )
	{
		new WC_Globill_Woocommerce();
	}
}
function add_globill_gateway( $methods )
{
	$methods[] = 'WC_Globill_Woocommerce';
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_globill_gateway' );
add_action( 'plugins_loaded', 'globill_woocommerce', 0 );

?>