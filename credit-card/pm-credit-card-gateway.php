<?php
/*
 * Paymongo Credit Card Class
 */
class WC_Credit_Card_Gateway extends WC_Payment_Gateway {

    /**
     * Class constructor
     */
    public function __construct() {

        $this->id = 'credit_card'; // payment gateway plugin ID
        $this->has_fields = true; // in case custom credit card form is needed
        $this->method_title = 'Credit/Debit Card';
        $this->method_description = 'For different form of card payments';

        // Payments types
        $this->supports = array(
            'default_credit_card_form',
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

        // Use to obtain token, not used for now
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
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
            )
        );

    }

    /*
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

        $return_url = $this->get_return_url( $order );

        list($exp_month, $_, $exp_year) = explode( ' ', $_POST['credit_card-card-expiry'] );

        $card_payload = [
            'card_number'    => str_replace( array(' ', '-' ), '', $_POST['credit_card-card-number'] ),
            'exp_month'    => ( int )$exp_month,
            'exp_year'  => ( int )$exp_year,
            'cvc'   => ( isset( $_POST['credit_card-card-cvc'] ) ) ? $_POST['credit_card-card-cvc'] : '',
        ];

        $intent_id = cc_payment_intent( $order );
        $method_id = cc_payment_method( $intent_id, $card_payload, $order );

        // Validation of payment method
        if ( isset( $method_id[0]['code'] ) ) {
            $error_code = $method_id[0]['code'];

            if ( $error_code == 'parameter_format_invalid' ) {
                wc_add_notice(  'Credit card format is invalid.', 'error' );
            } else if ( $error_code == 'parameter_invalid' ) {
                $attribute = $method_id[0]['source']['attribute'];

                if ( $attribute == 'exp_month' ) {
                    wc_add_notice(  'Credit card month must be between 1 and 12.', 'error' );
                } else if ( $attribute == 'exp_year' ) {
                    wc_add_notice(  'Credit card year must be at least this year or no later than 50 years from now.', 'error' );
                }
            } else if ( $error_code == 'parameter_above_maximum' ) {
                wc_add_notice(  'The value for CVC cannot be more than 3 characters', 'error' );
            } else if ( $error_code == 'parameter_below_minimum' ) {
                wc_add_notice(  'The value for CVC cannot be less than 3 characters', 'error' );
            }
        }

        $response = payment_attach( $method_id, $intent_id );

        if( !is_wp_error( $response ) ) {

            $body = $response['data']['attributes']['status'];

            if ( $body == 'succeeded' ) {
                // Payment received
                $order->payment_complete();
                
                wc_reduce_stock_levels($order_id);
                        
                // some notes to customer (replace true with false to make it private)
                $order->add_order_note( 'Hey, your order is paid! Thank you!', true );

                // Empty cart
                $woocommerce->cart->empty_cart();

                // Redirect to the thank you page
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url( $order )
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
 */
function cc_payment_intent( $order ) {
    $payment_intent_url = 'https://api.paymongo.com/v1/payment_intents';

    $intent_data = json_encode( [
        'data' => [
            'attributes' => [
                'amount'                    => $GLOBALS['test_mode'] ? 10000 : ( int )$order->get_total(),
                'payment_method_allowed'    => ['card'],
                'description'               => 'Barapido Mart Payment',
                'statement_descriptor'      => 'Barapido Mart product payment',
                'payment_method_options'    => [
                    'card' => ['request_three_d_secure' => 'automatic']
                ],
                'currency' => 'PHP'
            ]
        ]
    ]);

    $intent_payload = array(
        'headers'   => $GLOBALS['headers'],
        'body'      => $intent_data,
    );

    $intent = wp_remote_post( $payment_intent_url, $intent_payload );

    $body = json_decode( $intent['body'], true );

    if ( ! isset( $body['errors'] ) ) {
        return $body['data']['id'];
    } else {
        return $body;
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
 */
function cc_payment_method( $intent_id, $card_payload, $order ) {
    $payment_method_url = 'https://api.paymongo.com/v1/payment_methods';

    $customer_name = $order->get_billing_first_name().' '.$order->get_billing_last_name();

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
        'headers'   => $GLOBALS['headers'],
        'body'      => $method_data,
    );

    $method = wp_remote_post( $payment_method_url, $method_payload );

    $body = json_decode( $method['body'], true);

    if ( ! isset( $body['errors'] ) ) {
        return $body['data']['id'];
    } else {
        return $body['errors'];
    }
}

/**
 * payment_attach
 * 
 * attaching PaymentIntent to finalize payment
 * 
 * @param string $method_id id from payment method
 * @param string $intent_id id from payment intent
 */
function payment_attach( $method_id, $intent_id ) {
    $payment_attached_url = 'https://api.paymongo.com/v1/payment_intents/'.$intent_id.'/attach';

    $attach_data = json_encode( [
        'data' => [
            'attributes' => [
                'client_key' => 'card',
                'payment_method' => $method_id,
            ]
        ]
    ]);

    $attach_payload = [
        'headers'   => $GLOBALS['headers'],
        'body'      => $attach_data,
    ];

    $attach = wp_remote_post( $payment_attached_url, $attach_payload );

    $body = json_decode( $attach['body'], true);

    if ( ! isset( $body['errors'] ) ) {
        return $body;
    } else {        
        return $body['errors'];
    }
}
