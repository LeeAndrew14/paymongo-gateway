<?php
/*
 * Paymongo E-Wallet Class
 */
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

        $test_message = 'TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="https://developers.paymongo.com/docs/testing" target="_blank" rel="noopener noreferrer">documentation</a>.';

        $this->description = $this->testmode ? $test_message : $this->get_option( 'description' );        

        // Saves the settings
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
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
            ),'payment_description' => array(
                'title'       => 'Payment Description',
                'type'        => 'text',
                'description' => 'This controls the description which the merchant sees in Paymongo dashboard.',                
                'desc_tip'    => true,
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
            // Display the description with <p> tags etc.
            echo wpautop( wp_kses_post( $this->description ) );
        }

        echo '<div class="form-row form-row-wide"><br>
                <p style="font-size:15px;"><span class="required">*</span>
                    Please select your payment method:
                </p>
                <label for="gcash" style="font-size:14px;">
                    <input type="radio" id="gcash" name="e_wallet" value="gcash" checked/>
                    GCash
                </label><br>                    
                <label for="grab_pay" style="font-size:14px;">
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
        $type = $_POST[ 'e_wallet' ];
        $order = wc_get_order( $order_id );    
        $return_url = $this->get_return_url( $order );
        $payment_desc = $this->get_option( 'payment_description' ) ? $this->get_option( 'payment_description' ) : ' ';

        $headers = array(
            'Authorization' => 'Basic ' . base64_encode( $this->private_key ),
            'Content-Type'  => 'application/json',
            'timeout'       => 50,
        );

        $response = e_wallet_payment( $headers, $order, $return_url, $type );

        if ( !is_wp_error( $response ) ) {

            $body = json_decode( $response['body'], true );            

            if ( ! isset($body['errors'] ) ) {
                $source_id = $body['data']['id'];

                session_start();
                $_SESSION['source_id'] = $source_id;
                $_SESSION['private_key'] = $this->private_key;
                $_SESSION['payment_desc'] = $payment_desc;

                if ( $body['data']['attributes']['status'] == 'pending' ) {
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
                 foreach( $body['errors'] as $error  ) {                     
                     wc_add_notice( $error['detail'], 'error' );
                 }                 
                 return;
            }
        } else {
            wc_add_notice(  'Connection error. <br> Please try again.', 'error' );
            return;
        }
    }
}

/**
 * e_wallet_payment
 * 
 * Paymongo e-wallet post request to authorize GCash or Grab Pay payment
 * 
 * @param array $headers authorization and content type header for post request
 * @param object $order Woocommerce object for order details
 * @param string $return_url Woocommerce default thank you URL
 * @param string $type Paymongo payment type gcash or grab_pay
 */
function e_wallet_payment( $headers, $order, $return_url, $type ) {
    $payment_source_url = 'https://api.paymongo.com/v1/sources';    

    $customer_name = $order->get_billing_first_name().' '.$order->get_billing_last_name();

    $total = $order->get_total() * 100;

    // A positive integer with minimum amount of 10000. 10000 is the smallest unit in cents. More info: https://bit.ly/2Tny5ga
    if ( $total < 10000 ) {  
        wc_add_notice(  'Minimum transaction should be equal or higher than 100 PHP', 'error' );
        return;
    }

    $source_data = json_encode([
        'data' => [
            'attributes' => [
                'type'      => $type,
                'amount'    => $total,
                'currency'  => get_woocommerce_currency(),
                'billing'   => [
                    'address' => [
                        'line1'         => $order->get_billing_address_1(),
                        'line2'         => $order->get_billing_address_2(),
                        'city'          => $order->get_billing_city(),
                        'state'         => $order->get_billing_state(),
                        'county'        => $order->get_billing_country(),
                        'postal_code'   => $order->get_billing_postcode(),
                    ],
                    'name'  => $customer_name,
                    'email' => $order->get_billing_email(),
                ],
                'phone' => $order->get_billing_phone(),
                'redirect'  => [
                    'success'   => $return_url,
                    'failed'    => wc_get_checkout_url(),
                ],                
            ]
        ]
    ]);    
            
    // Array with parameters for API interaction
    $source_payload = array(                                
        'headers'   => $headers,
        'body'      => $source_data
    );                        

    // API interaction could be built with wp_remote_post()
    $response = wp_remote_post( $payment_source_url, $source_payload );

    return $response;
}

/**
 * Finalize order after GCash or Grab pay payment process
 */
add_action( 'woocommerce_thankyou', array('WC_EWallet_Create_Payment', 'ewallet_create_payment') );
class WC_EWallet_Create_Payment{
    public static function ewallet_create_payment( $order_id ) {

        global $woocommerce;

        $order = wc_get_order( $order_id );
        $status = $order->get_status();

        if ( $status == 'processing' ) {
            return;
        }

        $payment_url = 'https://api.paymongo.com/v1/payments';

        session_start();

        $payment_data = json_encode([
            'data' => [
                'attributes' => [
                    'description'           => $_SESSION['payment_desc'],
                    'statement_descriptor'  => $_SESSION['payment_desc'],
                    'amount'                => $order->get_total() * 100,
                    'currency'              => get_woocommerce_currency(),
                    'source' => [
                        'id'    => $_SESSION['source_id'],
                        'type'  => 'source'
                    ]
                ]
            ]
        ]);

        $headers = array(
            'Authorization' => 'Basic ' . base64_encode( $_SESSION['private_key'] ),
            'Content-Type'  => 'application/json',
            'timeout'       => 50,
        );

        $payment_payload = array(
            'headers'   => $headers,
            'body'      => $payment_data
        );

        $response = wp_remote_post( $payment_url, $payment_payload );

        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( $response['body'], true );

            if ( isset( $body['errors'] ) ) {
                $order->add_order_note( 'Something went wrong while processing this order, please check woocommerce logs', true );

                wc_get_logger()->add( 'paymongo-gateway', 'E-Wallet '.wc_print_r( $response['body'], true ) );

                wp_redirect( wc_get_checkout_url() );
            } else {
                $status = $body['data']['attributes']['status'];

                if ( $status == 'paid' ) {
                    // Payment received
                    $order->payment_complete();

                    wc_reduce_stock_levels( $order_id );

                    // some notes to customer (replace true with false to make it private)
                    $order->add_order_note( 'Hey, your order is paid! Thank you!', true );

                    // Empty cart
                    $woocommerce->cart->empty_cart();

                    session_destroy();
                }
            }
        }
    }
}

/**
 * Prompt error message if e-wallet transaction failed
 */
add_action( 'woocommerce_thankyou', ['Error_Message', 'payment_error_message'] );
class Error_Message {
    public static function payment_error_message( $order_id ) {
        
        $order = wc_get_order( $order_id );
        $status = $order->get_status();

        if ( $status == 'processing' ) {
            return;
        } else {
            wc_add_notice( __( 'Something went wrong while processing your order, don\'t worry no charges has been made.
                <br> Kindly try again or try using a different payment mode.', 'woocommerce' ), 'error' );
        }        
    }
}
