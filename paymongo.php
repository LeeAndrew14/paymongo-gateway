<?php
/*
 * Plugin Name: PayMongo Payment Gateway
 * Plugin URI: https://github.com/LeeAndrew14/paymongo-gateway
 * Description: GCash, Grab Pay and Credit card payments.
 * Author: Lee Andrew
 * Author URI: https://github.com/LeeAndrew14
 * Version: 1.0.1
 */

// Registers Paymongo class as a WooCommerce payment gateway
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

add_filter( 'woocommerce_payment_gateways', 'paymongo_gateway_init' );
function paymongo_gateway_init( $gateways ) {

    if ( ! class_exists( 'WC_Payment_Gateway' ) || ! class_exists( 'WC_Payment_Gateway' ) ) return;

    // Include  Gateway Class    
    include_once( 'e-wallet/pm-e-wallet-gateway.php' );
    include_once( 'credit-card/pm-credit-card-gateway.php' );    

    $gateways[] = 'WC_EWallet_Gateway';
    $gateways[] = 'WC_Credit_Card_Gateway';

	return $gateways;
}
