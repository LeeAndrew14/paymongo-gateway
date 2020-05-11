<?php
/*
 * Plugin Name: PayMongo Payment Gateway
 * Plugin URI: 
 * Description: GCash, Grab Pay and Credit card payments.
 * Author: Lee Andrew
 * Author URI: 
 * Version: 1.0.1
 *

/*
 * Registers PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'paymongo_add_gateway_class' );
function paymongo_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_EWallet_Gateway';
    $gateways[] = 'WC_Credit_Card_Gateway';
	return $gateways;
}

include( plugin_dir_path( __FILE__ ) . 'e-wallet/pm-e-wallet-gateway.php');
include( plugin_dir_path( __FILE__ ) . 'credit-card/pm-credit-card-gateway.php');
