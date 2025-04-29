<?php

use PhonePe\common\exceptions\PhonePeException;
use PhonePe\payments\v2\models\request\builders\StandardCheckoutPayRequestBuilder;
use PhonePe\payments\v2\standardCheckout\StandardCheckoutClient;

class PPEX_WC_PG_V2_Client implements PPEX_PG_Interface {

	private $standard_checkout_client;
	private $plugin_context;
	private $pg_v2_event_manager;

	public function __construct($standard_checkout_client, $plugin_context) {
		$this->standard_checkout_client = $standard_checkout_client;
		$this->plugin_context = $plugin_context;
		add_filter('script_loader_tag', array($this, 'defer_js_scripts'), 10, 3);
		add_action('wp_enqueue_scripts', array($this, 'enqueue_fingerprint_js'), 10);
	}

	public function init_txn($wc_order_id) {
		if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
			$order = new WC_Order($wc_order_id);
		} else {
			$order = new woocommerce_order($wc_order_id);
		}

		$order->set_payment_method_title(PPEX_PG_Constants::PHONEPE_PG_ID);
		$order->set_payment_method(PPEX_PG_Constants::PHONEPE_PG_TITLE);

		$amount_in_rupees = sanitize_text_field($order->get_total());
		$amount_in_paisa = sanitize_text_field(PPEX_Utils::convert_to_paisa($amount_in_rupees));

		$merchant_order_id = PPEX_Utils::make_merchant_transaction_id_unique_for_repeated_requests($wc_order_id);

		$order->set_transaction_id($merchant_order_id);
		$order->add_meta_data('ppex_order_type', PPEX_Constants::PG_V2_ORDER);
		$order->save_meta_data();
		$order->save();

		$redirect_url = $this->get_redirect_url_for_order($merchant_order_id);
		ppLogInfo('redirect url: ' . $redirect_url);
        $event = PPEX_Utils::create_event($this->plugin_context, PPEX_Constants::PAY_BUTTON_CLICKED_ON_MERCHANT_CHECKOUT);
        $event->data["amount"] = $amount_in_paisa;
        $event->setMerchantOrderId($merchant_order_id);

		try {
			$this->standard_checkout_client->sendEvent($event);
		} catch (Exception $exception) {
			ppLogError(json_encode($exception));
		}

		try {
			$standard_checkout_pay_request = StandardCheckoutPayRequestBuilder::builder()
				->merchantOrderId($merchant_order_id)
				->amount($amount_in_paisa)
				->redirectUrl($redirect_url)
				->message("WooCommerce PhonePe PG Plugin")
				->build();

			try {
				$standard_checkout_pay_response = $this->standard_checkout_client->pay($standard_checkout_pay_request);
			} catch (PhonePeException $exception) {
				ppLogError("Exception in pay call");
				ppLogError(json_encode($exception));
				switch ($exception->getCode()) {
					case PPEX_PG_Constants::INTERNAL_SECURITY_BLOCK_1:
						if ($exception->getData()['Transacting_URL'] != null && $exception->getData()['Onboarding_URL'] != null) {
							$transactingUrlString = $exception->getData()['Transacting_URL'];
							$onboardingUrlData = $exception->getData()['Onboarding_URL'];

							if (is_array($onboardingUrlData)) {
								// If it's an array, join the URLs with commas to create a single string
								$onboardingUrlString = implode(', ', $onboardingUrlData);
							} else {
								// If it's a single URL, assign it to the onboardingUrlString
								$onboardingUrlString = $onboardingUrlData;
							}
							$order->add_order_note("PhonePe Payment Solutions:  Payment Request Failed" . " \n error message: " . $exception->getMessage() . "\n Transacting URL: " . $transactingUrlString . "\n Onboarding URL: " . $onboardingUrlString);
						}
						break;

					case PPEX_PG_Constants::INTERNAL_SECURITY_BLOCK_2:
						if ($exception->getData()['Transacting_IP_Address'] != null && $exception->getData()['Onboarding_IP_Address'] != null) {
							$transactingIPString = $exception->getData()['Transacting_IP_Address'];
							$onboardingIPData = $exception->getData()['Onboarding_IP_Address'];

							if (is_array($onboardingIPData)) {
								$onboardingIPString = implode(', ', $onboardingIPData);
							} else {
								$onboardingIPString = $onboardingIPData;
							}
							$order->add_order_note("PhonePe Payment Solutions: Payment Request Failed " . "\n error message: " . $exception->getMessage() . "\n Transacting IP Address: " . $transactingIPString . " \n Onboarding IP Address: " . $onboardingIPString);
						}

						break;

					case PPEX_PG_Constants::INTERNAL_SECURITY_BLOCK_4:
						if ($exception->getData()['Transacting_Package_Name'] != null && $exception->getData()['Onboarding_Package_Name'] != null) {
							$transactingPackageString = $exception->getData()['Transacting_Package_Name'];
							$onboardingPackageData = $exception->getData()['Onboarding_Package_Name'];

							if (is_array($onboardingPackageData)) {
								$onboardingPackageString = implode(', ', $onboardingPackageData);
							} else {
								$onboardingPackageString = $onboardingPackageData;
							}
							$order->add_order_note("PhonePe Payment Solutions: Payment Request Failed " . "\n Error Message: " . $exception->getMessage() . "\n Transacting Package Name: " . $transactingPackageString . " \n Onboarding Package Name: " . $onboardingPackageString);
						}
						break;

					default:
						$order->add_order_note("PhonePe Payment Solutions: Payment Request Failed " . "\n error message: " . $exception->getMessage());
						break;
				}

				if ($exception->getData() != null) {
					$msg = 'Transaction could not be initiated because of ' . $exception->getMessage() . '. Please try again.';
				} else {
					$msg = 'Transaction could not be initiated because of Network issue. Please check network connectivity.';
				}

				$this->status_update_for_order(PPEX_Constants::FAILED, $wc_order_id, $merchant_order_id, $msg);

				// Redirection after phonepe payments response.
				if ('' == $this->redirect_page_id || 0 == $this->redirect_page_id) {
					if ('success' == $this->msg['class']) {
						$redirect_url = $order->get_checkout_order_received_url();
					} else {
						$redirect_url = $order->get_view_order_url();
					}
				} else {
					$redirect_url = get_permalink($this->redirect_page_id);
				}

				$redirect_url = add_query_arg(
					array(
						'phonepe_response' => urlencode($this->msg['message']),
						'type' => $this->msg['class']
					),
					$redirect_url
				);
				wp_redirect($redirect_url);
				exit;
			}
			$event->setMerchantOrderId($merchant_order_id);
			$event->setEventName(PPEX_Constants::PAYMENT_REQUEST_TRIGGERED_FROM_PLUGIN);
			$event->setEventTime(date("ymdHis"));

			try {
				$this->standard_checkout_client->sendEvent($event);
			} catch (Exception $exception) {
				ppLogError(json_encode($exception));
				$event->data["code"] = $exception->getCode();
				$event->data["message"] = $exception->getMessage();
			}
			$event->setMerchantOrderId($merchant_order_id);
			$event->setEventName(PPEX_Constants::PAYMENT_RESPONSE_RECEIVED_AT_PLUGIN);
			$event->setEventTime(date("ymdHis"));
			try {
				$this->standard_checkout_client->sendEvent($event);
			} catch (Exception $exception) {
				ppLogError(json_encode($exception));
			}

			return array(
				"redirect_url" => $standard_checkout_pay_response->getRedirectUrl(),
				"merchant_order_id" => $merchant_order_id,
			);
		} catch (Exception $exception) {
			ppLogError(json_encode($exception));
		}
	}
	public function render_payment_ui($wc_order_id) {
		$pay_response = $this->init_txn($wc_order_id);

		if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
			$order = new WC_Order($wc_order_id);
		} else {
			$order = new woocommerce_order($wc_order_id);
		}
		$paypage_url = $pay_response['redirect_url'];
		$merchant_order_id = $pay_response['merchant_order_id'];
		$amount_in_rupees = sanitize_text_field($order->get_total());
		$amount_in_paisa = sanitize_text_field(PPEX_Utils::convert_to_paisa($amount_in_rupees));




		$img_src = PPEX_PG_Constants::LOADER_GIF;
		$callback_url = esc_url_raw($this->get_redirect_url_for_order($order->get_transaction_id()));

		if (!is_checkout_pay_page()) return;
		ppLogInfo('enqueue script called');

		$environment = $this->plugin_context->get_environment();
		$checkout_url = get_option('woocommerce_checkout_page_id');

		wp_register_script('ppex_callback_script', plugin_dir_url(__FILE__) . '/js/callback.js', array('ppex_pg_script'), null, null);
		wp_localize_script('ppex_callback_script', 'ppex_data', array(
			'img_src' => $img_src,
			'site_url' => esc_url(site_url()),
			'defult_callback_url' => $callback_url,
			'checkout_url' => esc_url(get_the_permalink($checkout_url)) . '/' . $wc_order_id . '/?phonepe_response=Your+payment+is+cancelled.&type=error',
			'payment_method_name' => esc_html(PPEX_PG_Constants::PAYMENT_METHOD_NAME),
			'redirect_pay_url' => esc_url($paypage_url),
			'paypage_loading_mode' => $this->plugin_context->get_paypage_loading_mode()
		));

		ppLogInfo($environment);

		if ($environment == PPEX_Constants::PRODUCTION) {
			$pg_script_source = PPEX_Constants::PROD_SCRIPT;
		} else if ($environment == PPEX_Constants::STAGE) {
			$pg_script_source = PPEX_Constants::STAGE_SCRIPT;
		} else {
			$pg_script_source = PPEX_Constants::UAT_SCRIPT;
		}

		wp_register_script('ppex_pg_script', $pg_script_source, null, null);
		wp_enqueue_script('ppex_pg_script');
		wp_enqueue_script('ppex_callback_script');
        $event = PPEX_Utils::create_event($this->plugin_context, PPEX_Constants::PLUGIN_HAS_LAUNCHED_PAY_PAGE);
        $event->data["amount"] = $amount_in_paisa;
        $event->setMerchantOrderId($merchant_order_id);
		try {
			$this->standard_checkout_client->sendEvent($event);
		} catch (Exception $exception) {
			ppLogError(json_encode($exception));
		}
	}
	public function handle_callback_response($ppex_pg_v2_callback) {
		try {
			$response = $this->standard_checkout_client->verifyCallbackResponse($ppex_pg_v2_callback->getHeaders(), $ppex_pg_v2_callback->getPayload(), $ppex_pg_v2_callback->getUsername(), $ppex_pg_v2_callback->getPassword());
		}catch (Exception $exception) {
			ppLogError(json_encode($exception));
		}

		$unique_merchant_transaction_id = $response->getPayload()->getMerchantOrderId();
		$order_id = PPEX_Utils::get_merchant_transaction_id_from_unique_transaction_id($unique_merchant_transaction_id);
		$order = wc_get_order($order_id);
		$amount = sanitize_text_field($order->get_total());
		$amount_in_paisa = sanitize_text_field($amount * 100);
		$amount_returned_in_paisa =  $response->getPayload()->getAmount();
        $event = PPEX_Utils::create_event($this->plugin_context, PPEX_Constants::CALLBACK_RECIEVED_AT_PLUGIN);
        $event->data["amount"] = $amount_in_paisa;
        $event->data["callbackType"] = $response->getType();
        $event->setMerchantOrderId($unique_merchant_transaction_id);
		try {
			$this->standard_checkout_client->sendEvent($event);
		} catch (Exception $exception) {
			ppLogError(json_encode($exception));
		}

		if ($order && ( ($order->get_status() == 'processing') || ( $order->get_status() == 'completed') ))  { // TODO: move 'processing' string to constants
			return "Payment is Successful";
		}

		if ($amount_returned_in_paisa < 0) {
			return  "Order amount cannot be negative";
		}
		if ($amount_in_paisa != $amount_returned_in_paisa) {
			$msg = "Amount mismatch!";
			$this->status_update_for_order(PPEX_PG_Constants::PG_V2_FAILED, $order_id, $unique_merchant_transaction_id, $msg);
			return "Amount mismatch!";
		}

		$this->status_update_for_order($response->getType(), $order_id, $unique_merchant_transaction_id);
	}

	private function get_redirect_url_for_order($merchant_transaction_id) {
		$query = [
			'merchant_transaction_id' => $merchant_transaction_id
		];
		return add_query_arg($query,  WC()->api_request_url('phonepe'));
	}

	public function check_pending_status($merchant_order_id) {
		$wc_order_id = PPEX_Utils::get_merchant_transaction_id_from_unique_transaction_id($merchant_order_id);
		try {
			$response = $this->standard_checkout_client->getOrderStatus($merchant_order_id);
			$state = $response->getState();
			$this->status_update_for_order($state, $wc_order_id, $merchant_order_id);
		} catch (exception $exception) {
			ppLogError(json_encode($exception));
		}
	}

	public function check_phonepe_response($merchant_transaction_id) {
		ppLogInfo('mtid: ' . $merchant_transaction_id);
		$wc_order_id = PPEX_Utils::get_merchant_transaction_id_from_unique_transaction_id($merchant_transaction_id);

		if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
			$order = new WC_Order($wc_order_id);
		} else {
			$order = new woocommerce_order($wc_order_id);
		}

		$amount_in_rupees = sanitize_text_field($order->get_total());
		$amount_in_paisa = sanitize_text_field(PPEX_Utils::convert_to_paisa($amount_in_rupees));
		$order->set_payment_method_title(PPEX_PG_Constants::PAYMENT_METHOD_NAME);
		$order->set_payment_method(PPEX_PG_Constants::PAYMENT_METHOD_NAME);  //admin panel


		$retry_counter = 0;
		$backoff = 1;
		do {
			try {
				$response = $this->standard_checkout_client->getOrderStatus($merchant_transaction_id);
				$retry_counter++;
                $event = PPEX_Utils::create_event($this->plugin_context, PPEX_Constants::PLUGIN_STATUS_CHECK);
                $event->data["amount"] = $amount_in_paisa;
                $event->data["st
                ate"] = $response->getState();
                $event->setMerchantOrderId($merchant_transaction_id);

				try {
					$this->standard_checkout_client->sendEvent($event);
				} catch (Exception $exception) {
					ppLogError(json_encode($exception));
				}

				sleep($backoff);
				$backoff = $backoff * 2 + 1;
			} catch (Exception $exception) {
				ppLogError(json_encode($exception));
			}
		} while (($response->getState() == PPEX_PG_Constants::PG_V2_PENDING || $response->getState() == PPEX_Constants::SERVER_ERROR) && ($retry_counter < PPEX_Constants::MAX_RETRY_COUNT));


		$state = $response->getState();

		if ($state != PPEX_Constants::TXN_NOT_FOUND) {
			$amount_returned = $response->getAmount();
		} else {
			ppLogError("Transaction not found for merchant_transaction_id " . $merchant_transaction_id);
			wp_redirect(site_url());
			exit;
		}


		if ($state == PPEX_PG_Constants::PG_V2_COMPLETED && $amount_in_paisa == $amount_returned) {
			$this->status_update_for_order($state, $wc_order_id, $merchant_transaction_id);
			$redirect_url = $order->get_checkout_order_received_url();
		} else {
			if ($amount_in_paisa != $amount_returned) {
				$msg .= "Amount mismatch!";
			} else if (isset($state) && $state == PPEX_PG_Constants::PG_V2_FAILED) {
				$msg = 'Your payment has failed.';
			} else {
				$msg .= ' Please contact seller if money has been deducted.';
			}

			$this->status_update_for_order($state, $wc_order_id, $merchant_transaction_id, $msg);
			$redirect_url = wc_get_checkout_url();
			ppLogError('failure (or max limit of status check exceeded) url: ' . $redirect_url);
		}

		$redirect_url = add_query_arg(
			array(
				'phonepe_response' => urlencode($this->msg['message']),
				'type' => $this->msg['class']
			),
			$redirect_url
		);

		wp_redirect($redirect_url);
        $event = PPEX_Utils::create_event($this->plugin_context, PPEX_Constants::PLUGIN_HAS_GIVEN_CONTROL_BACK_TO_MERCHANT);
        $event->data["amount"] = $amount_in_paisa;
        $event->setMerchantOrderId($merchant_transaction_id);
		try {
			$this->standard_checkout_client->sendEvent($event);
		} catch (Exception $exception) {
			ppLogError(json_encode($exception));
		}

		exit;
	}

	private function status_update_for_order($type, $wc_order_id, $merchant_transaction_id, $msg = "") {
		global $woocommerce;

		$order = wc_get_order($wc_order_id);

		if ($order == false) return;

		// order marked completed will not be modified by status check or callback
		if ($order->status == 'completed' || $order->status == 'processing') {
			return;
		}

		if ($type == PPEX_PG_Constants::CHECKOUT_ORDER_COMPLETED || $type == PPEX_PG_Constants::PG_V2_COMPLETED) {
			$this->msg['message'] = "Your payment is successful.";
			$this->msg['class'] = 'success';
			$order->payment_complete($merchant_transaction_id);
			$order->add_order_note("PhonePe Payment Solutions: Your payment is successful - merchant transaction id: " . $merchant_transaction_id);
			if ($woocommerce->cart) $woocommerce->cart->empty_cart();
			return;
		} else if ($type == PPEX_PG_Constants::CHECKOUT_ORDER_FAILED || PPEX_PG_Constants::PG_V2_FAILED) {
			$this->msg['class'] = 'error';
			$this->msg['message'] = $msg;
			$order->update_status('failed');
			$order->add_order_note("PhonePe Payment Solutions: Payment Transaction Failed" . ' - merchant transaction id: ' . $merchant_transaction_id);
		} else {
			$order->update_status('wc-pending', 'Pending');
		}
	}

	public function defer_js_scripts($tag, $handle, $src) {
		$defer = array(
			'ppex_pg_script',
			'ppex_callback_script'
		);

		if (in_array($handle, $defer)) {
			return '<script src="' . $src . '" defer="defer" type="text/javascript"></script>' . "\n";
		}

		return $tag;
	}

	public function enqueue_fingerprint_js() {
		wp_register_script('minified_fingerprint_js', plugins_url('/js/fp.min.js', __FILE__), null, null);
		wp_enqueue_script('minified_fingerprint_js');

		wp_register_script('fingerprint_js', plugins_url('/js/fingerprint.js', __FILE__), null, null);
		wp_enqueue_script('fingerprint_js');
	}
}
