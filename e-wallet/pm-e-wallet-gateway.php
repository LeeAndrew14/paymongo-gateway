<?php
/*
 * Note: It is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'e_wallet_init_gateway_class' );
function e_wallet_init_gateway_class() {
 
	class WC_EWallet_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor
 		 */
 		public function __construct() {
 
            $this->id = 'e_wallet'; // payment gateway plugin ID
            $this->has_fields = true; // in case custom credit card form is needed
            $this->method_title = 'E-Wallet';
            $this->method_description = 'For GCash and Grab Pay payment method';
         
            // Payments types
            $this->supports = array(
                'products'
            );
         
            // Method with all the options fields
            $this->init_form_fields();
         
            // Load settings.
            $this->init_settings();
            $this->icon = $this->get_option( 'icon' );
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
            $this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
            
            // Global values            
            $GLOBALS['private_key'] = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
            $GLOBALS['test_mode'] = $this->testmode = 'yes' === $this->get_option( 'testmode' );

            $GLOBALS['headers'] = array(
                'Authorization' => 'Basic ' . base64_encode( $this->private_key ),
                'Content-Type'  => 'application/json',
                'timeout'       => 50,
            );

            // Saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
         
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
            );
 
	 	}
 
		/**
		 * For selecting payment method
		 */
		public function payment_fields() {
            global $woocommerce, $post;

            // Display some description before the payment form
            if ( $this->description ) {
                // Instructions for test mode
                if ( $this->testmode ) {
                    $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="https://developers.paymongo.com/docs/testing" target="_blank" rel="noopener noreferrer">documentation</a>.';
                    $this->description  = trim( $this->description );
                }
                // Display the description with <p> tags etc.
                echo wpautop( wp_kses_post( $this->description ) );
            }

            echo '<div class="form-row form-row-wide"><br>
                    <p style="font-size:15px;">Please select your payment method:<span class="required">*</span></p>                    
                    <label for="gcash" style="font-size:14px;">
                        <input type="radio" id="gcash" name="e_wallet" value="gcash" checked/>
                        GCash
                    </label><br>                    
                    <label for="card" style="font-size:14px;">
                        <input type="radio" id="grab_pay" name="e_wallet" value="grab_pay"/>
                        Grab Pay
                    </label><br>
                </div>
                <div class="clear"></div>';
        }
        
        /*
		 * Custom CSS and JS, in most cases required only for custom credit card form
		 */
	 	public function payment_scripts() {
       
            // no reason to enqueue JavaScript if API keys are not set
            if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
                return;
            }
	 	}
 
		/*
		 * Process payment here
		 */
		public function process_payment( $order_id ) {
            // Get order details
            $order = wc_get_order( $order_id );                        

            $return_url = $this->get_return_url( $order );
    
            $type = $_POST[ 'e_wallet' ];

            $response = e_wallet_payment( $GLOBALS['headers'], $order, $return_url, $type );

            if( !is_wp_error( $response ) ) {
                
                $body = json_decode( $response['body'], true );
                
                $source_id = $body['data']['id'];
                
                session_start();
                $_SESSION['source_id'] = $source_id;                

                if ( $body['data']['attributes']['status'] == 'pending') {
                    // Redirect payment gateway
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

/**
 * GCash and Grab Pay
 */
function e_wallet_payment( $headers, $order, $return_url, $type ) {
    $payment_source_url = 'https://api.paymongo.com/v1/sources';

    $source_data = json_encode(array(
        'data' => array(
            'attributes' => array(
                'type'      => $type,
                'amount'    => $GLOBALS['test_mode'] ? 10000 : ( int )$order->get_total(),
                'currency'  => get_woocommerce_currency(),
                'redirect'  => array(
                    'success'   => $return_url,
                    'failed'    => wc_get_checkout_url()
                )
            )
        )
    ), JSON_FORCE_OBJECT);
            
    // Array with parameters for API interaction
    $source_payload = array(                                
        'headers'   => $headers,
        'body'      => $source_data
    );                        

    // API interaction could be built with wp_remote_post()
    $response = wp_remote_post( $payment_source_url, $source_payload );

    return $response;
}

add_action( 'woocommerce_thankyou', array('WC_EWallet_Create_Payment', 'ewallet_create_payment') );
class WC_EWallet_Create_Payment{
    public static function ewallet_create_payment( $order_id ) {

        global $woocommerce;

        $order = wc_get_order( $order_id );

        $payment_url = 'https://api.paymongo.com/v1/payments';

        session_start();

        $payment_data = json_encode(array(
            'data' => array(
                'attributes' => array(
                    'description'           => 'Barapido Mart Payment',
                    'statement_descriptor'  => 'Barapido Mart payment of product orders',
                    'amount'                => $GLOBALS['test_mode'] ? 10000 : (int)$order->get_total(),
                    'currency'              => get_woocommerce_currency(),
                    'source' => array(
                        'id'    => $_SESSION['source_id'],
                        'type'  => 'source'
                    )
                )
            )
        ), JSON_FORCE_OBJECT);        

        $payment_payload = array(
            'headers'   => $GLOBALS['headers'],
            'body'      => $payment_data
        );
                
        $payment = wp_remote_post($payment_url, $payment_payload);        

        $body = json_decode( $payment['body'], true );
        
        
        if ( !isset( $body['errors'] ) ) {
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
}
  