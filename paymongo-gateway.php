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
	$gateways[] = 'WC_Paymongo_Gateway';
	return $gateways;
}
 
/*
 * Note: It is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'paymongo_init_gateway_class' );
function paymongo_init_gateway_class() {
 
	class WC_Paymongo_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor
 		 */
 		public function __construct() {
 
            $this->id = 'paymongo'; // payment gateway plugin ID
            $this->has_fields = true; // in case custom credit card form is needed
            $this->method_title = 'PayMongo';
            $this->method_description = 'Description of PayMongo payment gateway';
         
            // Payments types
            $this->supports = array(
                'products'
            );
         
            // Method with all the options fields
            $this->init_form_fields();
         
            // Load settings.
            $this->init_settings();
            $this->icon = $this->get_option( 'icon', '' );
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
            $this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
            
            // Global values            
            $GLOBALS['private_key'] = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
            $GLOBALS['test_mode'] = $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $GLOBALS['source_id'] = $this->get_option('source_id');

            // Saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
         
            // Use to obtain token, not used for now
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
         
            // Register a webhook here
            add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
 
 		}
 
		/**
 		 * Plugin options
 		 */
 		public function init_form_fields(){

            $desc = '';
            $icon_url = $this->get_option( 'icon', '' );
            if ( $icon_url !== '' ) {
                $desc = '<img src="' . $icon_url . '" alt="' . $this->title . '" title="' . $this->title . '" />';
            }
 
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable PayMongo Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'PayMongo',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay using PayMongo',
                ),
                'icon' => array(
                    'title'       => 'Icon',
                    'type'        => 'text',
                    'desc_tip'    => 'If you want to show an image next to the gateway\'s name on the frontend, enter a URL to an image.',
                    'default'     => '',
                    'description' => $desc,
                    'css'         => 'min-width:300px;width:50%;',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_publishable_key' => array(
                    'title'       => 'Test Publishable Key',
                    'type'        => 'text'
                ),
                'test_private_key' => array(
                    'title'       => 'Test Private Key',
                    'type'        => 'text',
                ),
                'publishable_key' => array(
                    'title'       => 'Live Publishable Key',
                    'type'        => 'text'
                ),
                'private_key' => array(
                    'title'       => 'Live Private Key',
                    'type'        => 'text'
                ),
                'source_id' => array(                    
                    'type'        => 'hidden',
                    'readonly'    =>'readonly'
                )
            );
 
	 	}
 
		/**
		 * For custom credit card form
		 */
		public function payment_fields() {

            // ok, let's display some description before the payment form
            if ( $this->description ) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ( $this->testmode ) {
                    $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="https://developers.paymongo.com/docs/testing" target="_blank" rel="noopener noreferrer">documentation</a>.';
                    $this->description  = trim( $this->description );
                }
                // display the description with <p> tags etc.
                echo wpautop( wp_kses_post( $this->description ) );
            }

            // echo '<form action="" method="POST">
            //         <p style="font-size:15px;">Please select your payment method:</p>
            //         <input type="radio" id="gcash" name="paymongo_payment" value="gcash" checked>
            //         <label for="gcash" style="font-size:14px;">GCash</label><br>
            //         <input type="radio" id="grab_pay" name="paymongo_payment" value="grab_pay">
            //         <label for="card" style="font-size:14px;">Grab Pay</label><br>
            //     </form>';
        
            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            // echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
        
            // Add this action hook if you want your custom payment gateway to support it
            // do_action( 'woocommerce_credit_card_form_start', $this->id );
    
            // I recommend to use unique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            // echo '<div id="cardGroup">
            //         <div class="form-row form-row-wide">
            //             <label>Credit Card Number <span class="required">*</span></label>
            //             <input id="paymongo_ccNo" type="text" autocomplete="off">
            //         </div>
            //         <div class="form-row form-row-first">
            //             <label>Expiry Date <span class="required">*</span></label>
            //             <input id="paymongo_expdate" type="text" autocomplete="off" placeholder="MM / YY">
            //         </div>
            //         <div class="form-row form-row-last">
            //             <label>Card Code (CVC) <span class="required">*</span></label>
            //             <input id="paymongo_cvv" type="password" autocomplete="off" placeholder="CVC">
            //         </div>
            //         <div class="clear"></div>
            //     </div>';           
        
            // do_action( 'woocommerce_credit_card_form_end', $this->id );
        
            // echo '<div class="clear"></div></fieldset>';
            
		}
 
		/*
		 * Custom CSS and JS, in most cases required only for custom credit card form
		 */
	 	public function payment_scripts() {

            // we need JavaScript to process a token only on cart/checkout pages
            if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
                return;
            }
        
            // if our payment gateway is disabled, don't enqueue JS too
            if ( 'no' === $this->enabled ) {
                return;
            }
        
            // no reason to enqueue JavaScript if API keys are not set
            if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
                return;
            }
        
            // do not work with card details without SSL unless website is in a test mode
            if ( ! $this->testmode && ! is_ssl() ) {
                return;
            }
        
            // let's suppose it is our payment processor JavaScript that allows to obtain a token
            wp_enqueue_script( 'paymongo_js', 'https://api.paymongo.com/v1/payment_intents' );
        
            // and this is our custom JS in your plugin directory that works with token.js
            wp_register_script( 'woocommerce_paymongo', plugins_url( 'assets/js/paymongo.js', __FILE__ ), array( 'jquery', 'paymongo_js' ) );
        
            // in most payment processors PUBLIC KEY is needed to obtain a token
            wp_localize_script( 'woocommerce_paymongo', 'paymongo_params', array(
                'publishableKey' => $this->publishable_key
            ) );
                
            // This has bugs, because Credit card is not set yet
            // wp_enqueue_script( 'woocommerce_paymongo' );
	 	}
 
		/*
 		 * Fields validation
		 */
		public function validate_fields() {
            
            if( empty( $_POST[ 'billing_first_name' ]) ) {
                wc_add_notice(  'First name is required!', 'error' );
                return false;
            }
            return true;

		}
 
		/*
		 * Process payment here
		 */
		public function process_payment( $order_id ) {

            global $woocommerce;
            
            // Get order details
            $order = wc_get_order( $order_id );
            
            $headers = array(
                'Authorization' => 'Basic ' . base64_encode( $this->private_key ),
                'Content-Type'  => 'application/json',
                'timeout'       => 50,
            );
            
            $payment_source_url = 'https://api.paymongo.com/v1/sources';

            $source_data = json_encode(array(
                'data' => array(
                    'attributes' => array(
                        'type'      => 'gcash',
                        'amount'    => $this->testmode ? 10000 : (int)$order->get_total(),
                        'currency'  => get_woocommerce_currency(),
                        'redirect'  => array(
                            'success'   => $this->get_return_url( $order ),
                            'failed'    => wc_get_checkout_url()
                        )
                    )
                )
            ), JSON_FORCE_OBJECT);
                   
            /*
            * Array with parameters for API interaction
            */
            $source_payload = array(                                
                'headers'   => $headers,
                'body'      => $source_data
            );                        
        
            /*
            * API interaction could be built with wp_remote_post()
            */
            $response = wp_remote_post( $payment_source_url, $source_payload );
                           
            // echo'<script>console.log(RESPONSE::'.json_encode($link).')</script>';
            
            if( !is_wp_error( $response ) ) {
        
                $body = json_decode( $response['body'], true );
                
                $source_id = $body['data']['id'];
                $this->update_option('source_id', $source_id);

                if ( $body['data']['attributes']['status'] == 'pending' ) {                                    

                    // Redirect to the thank you page
                    return array(
                        'result' => 'success',
                        'redirect' => $body['data']['attributes']['redirect']['checkout_url']
                    );
                    
                } else {
                    wc_add_notice(  'Please try again.', 'error' );
                    return;
                }
        
            } else {
                wc_add_notice(  'Connection error.', 'error' );
                return;
            }
	 	}
 
		public function webhook() {
 
	 	}
 	}
}

add_action( 'woocommerce_thankyou', array('WC_Create_Payment', 'paymongo_create_payment') );
class WC_Create_Payment{
    public static function paymongo_create_payment($order_id) {

        global $woocommerce;

        $order = wc_get_order( $order_id );

        $payment_url = 'https://api.paymongo.com/v1/payments';

        $headers = array(
            'Authorization' => 'Basic ' . base64_encode( $GLOBALS['private_key'] ),
            'Content-Type'  => 'application/json',
            'timeout'       => 50,
        );

        $payment_data = json_encode(array(
            'data' => array(
                'attributes' => array(
                    'description'           => 'test2',
                    'statement_descriptor'  => 'test3',
                    'amount'                => $GLOBALS['test_mode'] ? 10000 : (int)$order->get_total(),
                    'currency'              => get_woocommerce_currency(),
                    'source' => array(
                        'id'    => $GLOBALS['source_id'],
                        'type'  => 'source'
                    )
                )
            )
        ), JSON_FORCE_OBJECT);

        $payment_payload = array(
            'headers'   => $headers,
            'body'      => $payment_data
        );
                
        $payment = wp_remote_post($payment_url, $payment_payload);

        $body = json_decode( $payment['body'], true );

        $status = $body['data']['attributes']['status'];

        if ( $status == 'paid') {
            // Payment received
            $order->payment_complete();
            
            wc_reduce_stock_levels($order_id);
                    
            // some notes to customer (replace true with false to make it private)
            $order->add_order_note( 'Hey, your order is paid! Thank you!', true );

            // Empty cart
            $woocommerce->cart->empty_cart();
        }
    }
}