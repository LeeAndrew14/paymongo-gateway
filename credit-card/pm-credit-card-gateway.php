<?php
/**
 * Paymongo Credit Card Class
 */
class WC_Credit_Card_Gateway extends WC_Payment_Gateway {

    /**
     * Class constructor
     */
    public function __construct() {

        $this->id = 'credit_card'; // payment gateway plugin ID
        $this->has_fields = true; // in case custom credit card form is needed
        $this->method_title = 'PayMongo';
        $this->method_description = 'For different form of card payments';

        // Payments types
        $this->supports = array(            
            'product'
        );

        // Card fields
        $this->has_fields = true;

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

        $test_message = 'TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="https://developers.paymongo.com/docs/testing" target="_blank" rel="noopener noreferrer">documentation</a>.';

        $this->description = $this->testmode ? $test_message : $this->get_option( 'description' );

        // Saves the settings
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
    }

    /**
     * Plugin options
     */
    public function init_form_fields() {

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
                'default'     => 'Credit/Debit Card',
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
            )
        );

    }

    /**
     * Custom CSS and JS, in most cases required only for custom credit card form
     */
    public function payment_scripts() {
        // no reason to enqueue JavaScript if API keys are not set
        if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
            return;
        }

        // do not work with card details without SSL unless website is in a test mode
        if ( ! $this->testmode && ! is_ssl() ) {
            return;
        }

        wp_register_script( 'woocommerce_paymongo', plugins_url( 'paymongo-gateway/assets/js/paymongo.js' ) );
    }

    /**
     * Customize credit card payment field here
     */
    public function payment_fields() {

        if ( $this->description ) {
            // Display the description with <p> tags etc.
            echo wpautop( wp_kses_post( $this->description ) );
        }

        $cc_form = new WC_Payment_Gateway_CC();
        $cc_form->id = $this->id;
        $cc_form->supports = $this->supports;
        $cc_form->form();
    }

    /**
     * Fields validation
     */
    public function validate_fields() {

        if ( empty( $_POST[ 'billing_first_name' ]) ) {
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
        $return_url = $this->get_return_url( $order );
        $payment_desc = $this->get_option( 'payment_description' ) ? $this->get_option( 'payment_description' ) : ' ';

        list( $exp_month, $_, $exp_year ) = explode( ' ', $_POST['credit_card-card-expiry'] );

        $card_payload = [
            'card_number'    => str_replace( array(' ', '-' ), '', $_POST['credit_card-card-number'] ),
            'exp_month'    => ( int )$exp_month,
            'exp_year'  => ( int )$exp_year,
            'cvc'   => ( isset( $_POST['credit_card-card-cvc'] ) ) ? $_POST['credit_card-card-cvc'] : '',
        ];        

        // Card detail validation
        foreach ( $card_payload as $key => $detail ) {            
            if ( ! $detail ) {
                wc_add_notice( str_replace( '_', ' ', $key ) . ' is required.', 'error' );
                return;
            }
        }

        $headers = array(
            'Authorization' => 'Basic ' . base64_encode( $this->private_key ),
            'Content-Type'  => 'application/json',
            'timeout'       => 50,
        );

        $intent_id = $this->cc_payment_intent( $order, $headers, $payment_desc );

        $error_message = 'There was a problem with your payment, kindly try again or try using a different payment mode.';

        if ( $intent_id == 'api_error' ) {
            wc_add_notice( $error_message, 'error' );
            return;
        }

        $method_id = $this->cc_payment_method( $intent_id, $card_payload, $order, $headers );

        if ( is_array( $method_id ) ) {
            wc_add_notice( $method_id[0], $method_id[1] );
            return;
        } else if ( $method_id == 'api_error' ) {
            wc_add_notice( $error_message, 'error' );
            return;
        }

        $response = $this->payment_attach( $method_id, $intent_id, $headers );

        if ( $response == 'api_error' ) {
            wc_add_notice( $error_message, 'error' );
            return;
        }

        if ( in_array( 'connection_error' , [$intent_id, $method_id, $response] ) ) {
            wc_add_notice( 'Connection error. <br> Please try again.', 'error' );
            return;
        }
        
        $payment_intent_status = $response['data']['attributes']['status'];        

        if ( $payment_intent_status == 'succeeded' ) {
            // Payment received
            $order->payment_complete();
            
            wc_reduce_stock_levels( $order_id );
                    
            // some notes to customer (replace true with false to make it private)
            $order->add_order_note( 'Hey, your order is paid! Thank you!', true );

            // Empty cart
            $woocommerce->cart->empty_cart();

            // Redirect to the thank you page
            return array(
                'result' => 'success',
                'redirect' => $return_url
            );
        } else if ( $payment_intent_status == 'awaiting_next_action' ) {
            $redirect_url = $response['data']['attributes']['next_action']['redirect']['url'];
            $client_key = $response['data']['attributes']['client_key'];

            session_start();

            $_SESSION['3DS']            = true;
            $_SESSION['url']            = $redirect_url;
            $_SESSION['order_id']       = $order_id;
            $_SESSION['intent_id']      = $intent_id;
            $_SESSION['return_url']     = $return_url;
            $_SESSION['client_key']     = $client_key;
            $_SESSION['private_key']    = $this->private_key;    
            
            return array(
                'result' => 'success',
                'redirect' => $redirect_url
            );
            // echo isset($_POST['status']);
        } else {
            wc_add_notice( 'Please try again.', 'error' );
            return;
        }
    }

    /**
     * cc_payment_intent 
     * 
     * Paymongo PaymentIntent resource is used to track and handle different 
     * states of the payment until it succeeds.
     * 
     * more info @: https://bit.ly/2ThauOb
     * 
     * @param object $order to get total amount of order
     * @param array $headers authorization and content type header for post request
     * @param string $payment_desc Paymongo payment dashboard description
     */
    public function cc_payment_intent( $order, $headers, $payment_desc ) {
        $payment_intent_url = 'https://api.paymongo.com/v1/payment_intents';

        $total = $order->get_total() * 100;

        // A positive integer with minimum amount of 10000. 10000 is the smallest unit in cents. More info: https://bit.ly/2Tny5ga
        if ( $total < 10000 ) {  
            wc_add_notice(  'Minimum transaction should be equal or higher than 100 PHP', 'error' );
            return;
        }

        $intent_data = json_encode( [
            'data' => [
                'attributes' => [
                    'amount'                    => $total,
                    'payment_method_allowed'    => ['card'],
                    'description'               => $payment_desc,
                    'statement_descriptor'      => $payment_desc,
                    'payment_method_options'    => [
                        'card' => ['request_three_d_secure' => 'automatic']
                    ],
                    'currency' => 'PHP'
                ]
            ]
        ]);

        $intent_payload = array(
            'headers'   => $headers,
            'body'      => $intent_data,
        );

        $intent = wp_safe_remote_post( $payment_intent_url, $intent_payload );
        
        if ( ! is_wp_error( $intent ) ) {
            $body = json_decode( $intent['body'], true );
            
            if ( $body == NULL ) {
                wc_get_logger()->add( 'paymongo-gateway', 'Payment Intent ' . wc_print_r( $intent['response'], true ) );

                return 'api_error';
            } else if ( isset( $body['errors'] ) ) {            
                wc_get_logger()->add( 'paymongo-gateway', 'Payment Intent ' . wc_print_r( $intent['body'], true ) );

                return 'api_error';            
            } else {            
                return $body['data']['id'];
            }
        } else {        
            return 'connection_error';
        }
    }

    /**
     * cc_payment_method
     * 
     * PaymentMethod resource describes which payment method was used to fulfill a payment
     * 
     * more info @: https://bit.ly/2xY7LSd
     * 
     * @param string $intent_id id from payment intent
     * @param array $card_payload consumer's credit card info
     * @param object $order Woocommerce object for order details
     * @param array $headers authorization and content type header for post request
     */
    public function cc_payment_method( $intent_id, $card_payload, $order, $headers ) {
        $payment_method_url = 'https://api.paymongo.com/v1/payment_methods';

        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

        $method_data = json_encode( [
            'data' => [
                'attributes' => [
                    'type'      => 'card',
                    'details'   => $card_payload,
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
                ]
            ]
        ]);

        $method_payload = array(
            'headers'   => $headers,
            'body'      => $method_data,
        );

        $method = wp_safe_remote_post( $payment_method_url, $method_payload );
        
        if ( ! is_wp_error( $method ) ) {
            $body = json_decode( $method['body'], true );

            if ( $body == NULL ) {
                wc_get_logger()->add( 'paymongo-gateway', 'Payment Method ' . wc_print_r( $method['response'], true ) );

                return 'api_error';
            }
            if ( isset( $body['errors'] ) ) {            
                // Validation of payment method
                if ( isset( $body['errors'][0]['code'] ) ) {                
                    $error_code = $body['errors'][0]['code'];

                    if ( $error_code == 'parameter_format_invalid' ) {
                        return ['Credit card format is invalid.', 'error'];
                    } else if ( $error_code == 'parameter_invalid' ) {
                        $attribute = $body['errors'][0]['source']['attribute'];                    

                        if ( $attribute == 'exp_month' ) {
                            return ['Credit card month must be between 1 and 12.', 'error'];
                        } else if ( $attribute == 'exp_year' ) {
                            return ['Credit card year must be at least this year or no later than 50 years from now.', 'error'];
                        }
                    } else if ( $error_code == 'parameter_above_maximum' ) {
                        return ['The value for CVC cannot be more than 3 characters.', 'error'];
                    } else if ( $error_code == 'parameter_below_minimum' ) {
                        return ['The value for CVC cannot be less than 3 characters.', 'error'];
                    } else {
                        wc_get_logger()->add( 'paymongo-gateway', 'Payment Method ' . wc_print_r( $method['body'], true ) );

                        return 'api_error';
                    }
                } else {
                    wc_get_logger()->add( 'paymongo-gateway', 'Payment Method ' . wc_print_r( $method['body'], true ) );

                    return 'api_error';
                }
            } else {
                return $body['data']['id'];
            }
        } else {
            return 'connection_error';
        }
    }

    /**
     * payment_attach
     * 
     * attaching PaymentIntent to finalize payment
     * 
     * @param string $method_id id from payment method
     * @param string $intent_id id from payment intent
     * @param array $headers authorization and content type header for post request
     */
    public function payment_attach( $method_id, $intent_id, $headers ) {
        $payment_attached_url = 'https://api.paymongo.com/v1/payment_intents/' . $intent_id . '/attach';

        $attach_data = json_encode( [
            'data' => [
                'attributes' => [
                    'client_key' => 'card',
                    'payment_method' => $method_id,
                ]
            ]
        ]);

        $attach_payload = [
            'headers'   => $headers,
            'body'      => $attach_data,
        ];

        $attach = wp_safe_remote_post( $payment_attached_url, $attach_payload );
        
        if ( ! is_wp_error( $attach ) ) {
            $body = json_decode( $attach['body'], true );
            
            if ( $body == NULL ) {            
                wc_get_logger()->add( 'paymongo-gateway', 'Payment Attach '.wc_print_r( $attach['response'], true ) );

                return 'api_error';
            } else if ( isset( $body['errors'] ) ) {            
                wc_get_logger()->add( 'paymongo-gateway', 'Payment Attach '.wc_print_r( $attach['body'], true ) );

                return 'api_error';            
            } else {
                return $body;
            }
        } else {        
            return 'connection_error';
        }
    }
}

add_action( 'woocommerce_review_order_before_submit', 'finalize_payment_process' );
function finalize_payment_process( $order_id ) {        
    session_start();

    $nonce = wp_create_nonce( 'jsforwp_likes_reset' );
    $html_file = plugins_url( 'paymongo-gateway/credit-card/paymongo_3d_secure.html' );    

    if ( ! empty( $_SESSION ) ) {
        if ( isset( $_SESSION['3DS'] ) ) {
            wp_localize_script( 'woocommerce_paymongo', 'paymongo_params', array(
                'key'               => $_SESSION['private_key'],
                'url'               => $_SESSION['url'],
                'nonce'             => $nonce,
                'ajax_url'          => admin_url( 'admin-ajax.php' ),
                'html_file'         => $html_file,
                'client_key'        => $_SESSION['client_key'],
                'return_url'        => $_SESSION['return_url'],
                'checkout_url'      => wc_get_checkout_url(),                
                'payment_intent_id' => $_SESSION['intent_id'],
            ));
            
            wp_enqueue_script( 'woocommerce_paymongo' );   
            session_destroy();
        }        
    }
}

// add_action( 'woocommerce_before_checkout_process', 'finalize_payment_process_1' );
// function finalize_payment_process_1( $order_id ) {
//     session_start();
    
//     $html_file = plugins_url( 'paymongo-gateway/credit-card/paymongo_3d_secure.html' );
//     $url = plugins_url( __FILE__ );
    
//     if ( ! empty( $_SESSION ) ) {
//         if ( isset( $_SESSION['3DS'] ) ) {
//             wp_localize_script( 'woocommerce_paymongo', 'paymongo_params', array(
//                 'key'               => $_SESSION['private_key'],
//                 'url'               => $_SESSION['url'],
//                 'p_url'             => $url,
//                 'html_file'         => $html_file,
//                 'client_key'        => $_SESSION['client_key'],
//                 'return_url'        => $_SESSION['return_url'],
//                 'checkout_url'      => wc_get_checkout_url(),                
//                 'payment_intent_id' => $_SESSION['intent_id'],
//             ));
                        
//             wp_enqueue_script( 'woocommerce_paymongo' );
//             session_destroy();     
//         }        
//     }
// }

function finalize_payment_process_2() {
    echo 'proceed here';
}
add_action( 'wp_ajax_finalize_payment_process_2', 'finalize_payment_process_2' );
