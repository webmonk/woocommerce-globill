<?php
class WC_Globill_Woocommerce extends WC_Payment_Gateway_CC
{
	public function __construct()
	{
		$this->id = 'globill';
		//$this->icon = GLP_URL . 'images/buynow_blue.png';
		$this->has_fields  = true;
		$this->liveurl 		= 'https://securepayform.com/servicesAPI/SOAP/r2/Transact.cfc?wsdl';
		$this->method_title     = __( 'Globill' , 'woocommerce' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->supports = array(
            'products',
            'default_credit_card_form',
        );


		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];
		$this->merchant_id = $this->settings['merchant_id'];
		$this->website_id = $this->settings['website_id'];

		//add_action('init', array($this, 'check_voguepay_response'));
		if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		} else {
			add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		}
		add_action('woocommerce_receipt_globill', array($this, 'receipt_page'));

	}



	function init_form_fields(){
		$blog_title= get_bloginfo('name');

		$this->form_fields = array(
				'enabled' => array(
						'title' => __('Enable/Disable', 'woocommerce'),
						'type' => 'checkbox',
						'label' => __('Enable Globill Payment Module.', 'woocommerce'),
						'default' => 'yes'),
				'title' => array(
						'title' => __('Title:', 'woocommerce'),
						'type'=> 'text',
						'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
						'default' => __('Globill', 'woocommerce')),
				'description' => array(
						'title' => __('Description:', 'woocommerce'),
						'type' => 'textarea',
						'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
						'default' => __('Pay securely using Globill Payment gateway', 'woocommerce')),
				'website_id' => array(
						'title' => __('Password:', 'woocommerce'),
						'type' => 'text'),
				'merchant_id' => array(
						'title' => __('Merchant ID', 'woocommerce'),
						'type' => 'text',
						'description' => __('This is your Merchant ID obtainable from Globill', 'woocommerce')
			)
		);
	}



	public function admin_options()
	{
		echo '<h3>'.__('Globill Payment Gateway', 'woocommerce').'</h3>';
		echo '<table class="form-table">';
		// Generate the HTML For the settings form.
		$this->generate_settings_html();
		echo '</table>';

	}

	function generate_globill_form( $order_id ) 
	{
		global $woocommerce;

		$order = new WC_Order( $order_id );
					
		$globill_form = array(
			'mid' => $this->merchant_id,
  			'websiteID' => $this->website_id,
			'amount' => $order->order_total,
			'currencyPrice' => get_woocommerce_currency(),
  			'orderServiceDesc' => 'Payment from '.$order->billing_first_name. ' '.$order->billing_last_name,
  			'cardholderFirstname' => $order->billing_first_name,
  			'cardholderLastname' => $order->billing_last_name,
  			'cardholderPhone' => $order->billing_phone,
			'cardholderEmail' => $order->billing_email,
			'cardholderAddress' => $order->billing_address_1,
  			'cardholderCity' => $order->billing_city,
  			'cardholderPostcode' => $order->billing_postcode,
  			'cardholderRegion' => $order->billing_state,
  			'cardholderCountryCode' => $this->website_id,
			'redirect' => 1,
			'resultURL' => 'http://securepayform.com/tests/testPaymentResult.cfm'
		);

		$globill_form_args_array = array();
		foreach($globill_form as $key => $value)
		{
			$globill_form_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
		}

		wc_enqueue_js('
			jQuery("body").block({
					message: "<img src=\"' . esc_url( apply_filters( 'woocommerce_ajax_loader_url', $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif' ) ) . '\" alt=\"Redirecting&hellip;\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Voguepay to make payment.', 'woocommerce').'",
					overlayCSS:
					{
						background: "#fff",
						opacity: 0.6
					},
					css: {
				        padding:        20,
				        textAlign:      "center",
				        color:          "#555",
				        border:         "3px solid #aaa",
				        backgroundColor:"#fff",
				        cursor:         "wait",
				        lineHeight:		"32px"
				    }
				});
			jQuery("#submit_globill_payment_form").click();
		');

		return '<form action="'.$this->liveurl.'" method="post" id="voguepay_payment_form">
            ' . implode('', $globill_form_args_array) . '
				<input type="submit" class="button cancel" id="submit_globill_payment_form" value="'.__('Pay via Globill', 'woocommerce').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'woocommerce').'</a>
			</form>';

	}

	public function process_payment( $order_id ) {
        include_once( 'process-order.php' );

        // Instantiate the Order Object
        $order = new WC_Order( $order_id );

        $card_no = $_POST['globill-card-number'];
        $expiry = explode('/',$_POST['globill-card-expiry']);
        $cvc = $_POST['globill-card-cvc'];
		$clientObj = new SoapClient('https://securepayform.com/servicesAPI/SOAP/r2/Transact.cfc?wsdl', array(
			'trace'         => true,
            'exceptions'    => false)
        );

        // Assign the parameters for the soap request
        $params = array(
        	'MID'                   => $this->merchant_id,
            'password'              => $this->website_id,     //website_id is password               
            'orderID'               => 'Order'.$order_id,
            'customerIP'            => $order->customer_ip_address, // should be a real IP
            'amount'                => $order->order_total * 100, // 987 equals 9.87 dollars
            'currency'              => get_woocommerce_currency(), // currency codes in ISO 4217
            'cardHolderName'        => $order->billing_first_name. ' ' .$order->billing_last_name,
            'cardHolderAddress'     => $order->billing_address_1,
            'cardHolderZipcode'     => $order->billing_postcode,
            'cardHolderCity'        => $order->billing_city,
            'cardHolderState'       => $order->billing_state,
            'cardHolderCountryCode' => $order->billing_country, // country code in ISO 3166-1
            'cardHolderPhone'       => $order->billing_phone,
            'cardHolderEmail'       => $order->billing_email,
            'cardNumber'            => $card_no, 
            'cardSecurityCode'      => $cvc,  
            'cardExpireMonth'       => $expiry[0],
            'cardExpireYear'        => $expiry[1],
            'userVar1'              => '',
            'userVar2'              => 'www.absorbyourhealth.com',
            'userVar3'              => get_option('admin_email').',+1 910 401 2369'
            );
        // Send the request to the webservice
        $result = $clientObj->SaleTransaction($params);

        //wc_add_notice( $expiry[0].print_r($result,true), 'error' );

       if( $result->errorCode == 0 ) 
       {
            // Add the Success Message
            $order->add_order_note(  __( 'Globill payment completed', 'woocommerce' ) );

            // Mark as Payment Complete
            $order->payment_complete();

            // Reduce Stock
            $order->reduce_order_stock();

            // Empty the cart
            WC()->cart->empty_cart();

            // Return to the Success Page
            return array(
                'result'    => 'success',
                'redirect'  => $this->get_return_url( $order ),
            );
        } else {
                wc_add_notice( $result->errorMessage , 'error' );
        }
    } 

    public function parse_response( $response ) {
        // Get the body from the Response
        $body = wp_remote_retrieve_body( $response );

        // Will hold the Variables
        $vars = array();
        // Parse the URL
        wp_parse_str( $body, $vars );

        // Return the array with the vars
        return $vars;
    }

} 