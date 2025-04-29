<?php

/**
 * Payment Gateway for PhonePe.
 *
 * This class defines all code necessary for phonepe payment gateway.
 *
 * @since      1.0.0
 * @author     Sevengits <sevengits@gmail.com>
 */

class WC_PhonePe_Gateway extends WC_Payment_Gateway {
 
    /**
     * Class constructor
     */
    public function __construct() {

        $this->id = 'sg-phonepe'; // payment gateway plugin ID
        $this->icon= apply_filters('phonepe_icon', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/phonepe.svg'); // URL of the icon that will be displayed on checkout page near your gateway name
        $this->has_fields = false; // in case you need a custom credit card form
        $this->method_title = 'WC-PhonePe';
        $this->method_description = 'Pay Securely using UPI and Cards  using PhonePe payment gateway'; // will be displayed on the options page
     
        
        $this->supports = array(
            'products'
        );
     
        // Method with all the options fields
        $this->init_form_fields();
     
        // Load the settings.
        $this->init_settings();
        $this->title = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled = $this->get_option( 'enabled' );
        $this->mode = $this->get_option( 'testmode' );
        $this->merchant_id =  $this->get_option( 'merchant_id' );
        $this->salt_key =  $this->get_option( 'phonepe_salt_key' );
        $this->salt_key_index =  $this->get_option( 'phonepe_salt_key_index' );
     
        // This action hook saves the settings
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
     
        // We need custom JavaScript to obtain a token
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
     
        // You can also register a webhook here
         add_action( 'woocommerce_api_phonepe-payment-complete', array( $this, 'webhook' ) );

    }

   /**
     * Plugin options, we deal 
     */
    public function init_form_fields(){

        $this->form_fields = array(
            'enabled' => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable PhonePe',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => __('Title','wc-phonepe'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.','wc-phonepe'),
                'default'     => 'Pay Securely using UPI and Cards - Powered by PhonePe',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description','wc-phonepe'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.','wc-phonepe'),
                'default'     => 'Pay with your UPI.',
            ),
            'merchant_id' => array(
                'title'       => __('Merchant Id','wc-phonepe'),
                'type'        => 'text',
                'description' => 'For Test Mode : PGTESTPAYUAT',
            ),
            'phonepe_salt_key' => array(
                'title'       => __('SaltKey','wc-phonepe'),
                'type'        => 'text',
                'description' => 'For Test Mode : 099eb0cd-02cf-4e2a-8aca-3e6c6aff0399',
                
            ),
            'phonepe_salt_key_index' => array(
                'title'       => __('SaltKey Index','wc-phonepe'),
                'type'        => 'text',
                'description' => 'For Test Mode : 1',
            ),
            
            'phonepe_environment' => array(
                'title'       => __('Mode','wc-phonepe'),
                'type'        => 'select',
                'options'		=> array(
                    'test' => __('Test Mode','wc-phonepe'),
                    'live' => __('Live Mode','wc-phonepe'),
                ),
            ),
            
        );

    }

   /**
    * You will need it if you want your custom credit card form 
    */
   public function payment_fields() {

   }

   /*
    * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
    */
    public function payment_scripts() {

    }

   /*
     * Fields validation
    */
   public function validate_fields() {

   }

   /*
    * We're processing the payments here
    */
   public function process_payment( $order_id ) {

    global $woocommerce;
 
	// we need it to get any order detailes
	$order = wc_get_order( $order_id );
    if($this->mode=='live'){
        //live mode
        $endpoint = 'https://api.phonepe.com/apis/hermes/pg/v1/pay'; 
    }else{
        //test mode
        $endpoint = 'https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/pay'; 
    }
    // get phonepe varibles from settings
    $merchant_id         = $this->merchant_id;
    $salt_key            = $this->salt_key;
    $salt_key_index      = $this->salt_key_index;
    $transaction_id      = "WC".time();
   
    $rcurl        =get_site_url().'/wc-api/phonepe-payment-complete/?order_id='.$order_id.'&transaction_id='.$transaction_id;
	$call_backurl= add_query_arg( '_wpnonce', wp_create_nonce( 'wcpestatus_'.$transaction_id), $rcurl );
    
    /*
 	 * Array with parameters for API interaction
	 */
	$data = array(

        "merchantId" 	    => sanitize_text_field($merchant_id),
		"merchantTransactionId"	    =>  sanitize_text_field($transaction_id) ,
        "amount"            => sanitize_text_field($order->get_total() * 100),// in paisa
        "email"             => sanitize_email($order->get_billing_email()),
        "merchantUserId"    =>  sanitize_text_field($order->get_customer_id()),
        "redirectUrl"       => $this->get_return_url( $order ),
        "redirectMode"      =>  "REDIRECT",
        "callbackUrl"       =>  $call_backurl,
        "paymentInstrument"    =>  array('type'=> "PAY_PAGE")  
	);
    
    $encrypted_data= base64_encode(wp_json_encode($data));

    $xvarify = hash('sha256',$encrypted_data ."/pg/v1/pay".$salt_key)."###".$salt_key_index  ; 
   
    $post_data = wp_json_encode(array("request" => $encrypted_data));
    /*
	 *  API interaction could be built with wp_remote_post()
 	 */
      
     
      $options = array(
        'body'        =>    $post_data,
        'method'      =>    'POST',
        'sslverify'   =>    false,
        'data_format' =>    'body',
        'user-agent'  =>    'woo-plugin',
        'cookies'     => array(),
        'headers'     => array(
            'Content-Type'          => 'application/json',
            'Content-Length'        =>  strlen($post_data),
            'X-VERIFY'              =>  $xvarify,
          
            
        ),

      );
    
	 $response = wp_remote_post( $endpoint,$options );

    
	 if( !is_wp_error( $response ) ) {
 
		 $body = json_decode( $response['body'], true );
        
		 // it could be different depending on your payment processor
		 if ( $body['code'] == 'PAYMENT_INITIATED' ) {
            $redirect_url=$body['data']['instrumentResponse']['redirectInfo']["url"];
           // error_log(print_r($redirect_url,true));
			$order->update_status( 'pending', '', true );
			 
			// some notes to customer (replace true with false to make it private)
		    $order->add_order_note( 'PhonePe transaction id is '.$transaction_id." and transaction status ".$body['code']);
        
			// Empty cart
			$woocommerce->cart->empty_cart();
            
			// Redirect to the thank you page
			return array(
				'result' => 'success',
				'redirect' =>  $redirect_url
			);
 
		 } else {
			wc_add_notice(  $body['message'], 'error' );
			return;
		}
 
	} else {
		wc_add_notice(  'Connection error.', 'error' );
		return;
	}
 

    }

   /*
    * In case you need a webhook
    */
   public function webhook() {
  
   
    $order_id = isset($_REQUEST['order_id']) ? sanitize_text_field($_REQUEST['order_id']) : null;
    if (is_null($order_id)) return;
    $transaction_id = isset($_REQUEST['transaction_id']) ? sanitize_text_field($_REQUEST['transaction_id']) : null;
    if (is_null($transaction_id)) return;

    $salt_key            = $this->salt_key;
    $salt_key_index      = $this->salt_key_index;
    $merchant_id         = $this->merchant_id;
    if($this->mode=='live'){
        //live mode
        $endpoint = 'https://api.phonepe.com/apis/hermes/pg/v1/status/'.$merchant_id."/".$transaction_id; 
    }else{
        //test mode
        $endpoint = 'https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/status/'.$merchant_id."/".$transaction_id; 
    }
    $xvarify = hash('sha256', "/pg/v1/status/" . $merchant_id  . "/" . $transaction_id.$salt_key)."###" . $salt_key_index; 
    
    $options = array(
        'method'      =>    'GET',
        'sslverify'   =>    false,
        'user-agent'  =>    'woo-plugin',
        'cookies'     => array(),
        'headers'     => array(
            'Content-Type'          => 'application/json',
            'X-VERIFY'              => $xvarify,
            'X-MERCHANT-ID' => $merchant_id,
        ),

      );
    
	 $response = wp_remote_get( $endpoint,$options );
   
     $body = json_decode( $response['body'], true );
      if($body['success']==true){
        // Payment transaction is successfull.
        if ( $body['code'] == 'PAYMENT_SUCCESS' ) {
            $order = wc_get_order($order_id);
            $order->payment_complete();
            wc_reduce_stock_levels($order_id);
            $order->add_order_note( 'PhonePe transaction id is '.$transaction_id." and transaction status ".$body['code']);
        }
        }else{
        $order = wc_get_order($order_id);
        // add response message in as note.
		$order->add_order_note( 'PhonePe :'.$body['message']);
      }  



    }
}
?>