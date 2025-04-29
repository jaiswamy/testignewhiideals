<?php

/**
 * PPEX_WC_Http_Client_V2
 */

use PhonePe\common\exceptions\PhonePeException;
use PhonePe\common\utils\HttpResponse;

if (!class_exists('PPEX_WC_Http_Client_V2')) {
	class PPEX_WC_Http_Client_V2 {

		/**
		 * @param PPEX_Api_Request
		 * @return PPEX_Api_Response
		 */

		public static function getRequest($url, $headers) {
			$modified_headers = self::modifyMandatoryHeaders($headers);
			$args = array(
				'headers'     => $modified_headers,
			);

			$response = wp_remote_get($url, $args);
			$http_code = wp_remote_retrieve_response_code($response);
			$body = json_decode(wp_remote_retrieve_body($response), true);
			$httpResponse = new HttpResponse();
			$httpResponse->setResponse(json_encode($body));

			if ($http_code == 200)
				return $httpResponse;
			else {
				ppLogError("GET issue " . json_encode($body) . " url: " . $url);
				throw new PhonePeException($body['message'], $http_code, $body['code'], $body['data']);
			}
		}

		/**
		 * @param PPEX_Api_Request
		 * @return PPEX_Api_Response
		 */

		public static function postRequest($url, $body, $headers) {
			if(strpos($url, "/identity-manager/v1/oauth/token") !== false){
				$modified_headers = $headers;
			}else {
				$modified_headers = self::modifyMandatoryHeaders($headers);
			}

			$args = array(
				'headers'     => $modified_headers,
				'body'        => $body,
			);

			$response = wp_remote_post($url, $args);

			$http_code = wp_remote_retrieve_response_code($response);
			$body = json_decode(wp_remote_retrieve_body($response), true);
			$httpResponse = new HttpResponse();
			$httpResponse->setResponse(json_encode($body));


			if ($http_code == 200)
				return $httpResponse;
			else {
				ppLogError("POST issue " . json_encode($body) . " url: " . $url);
				throw new PhonePeException($body['message'], $http_code, $body['code'], $body['data']);
			}
		}

		public static function modifyMandatoryHeaders($headers){
			$modified_headers = array();
			$modified_headers['X-SOURCE'] = PPEX_PG_Constants::PLUGIN_SOURCE_HEADER;
			$modified_headers['X-SOURCE-VERSION'] = B2BPG_WOOCOMMERCE_PLUGIN_VERSION;
			$modified_headers['X-SOURCE-PLATFORM'] = PPEX_PG_Constants::WOOCOMMERCE;
			$modified_headers['X-SOURCE-PLATFORM-VERSION'] = WOOCOMMERCE_VERSION;
			$modified_headers['X-MERCHANT-DOMAIN'] = esc_url(site_url());
			$modified_headers['X-SOURCE-CLIENT-BROWSER-FINGERPRINT'] = ($_COOKIE['browserFingerprint']) ?? "DEFAULT";
			$modified_headers['accept'] = $headers['accept'] ?? "application/json";
			$modified_headers['Content-Type'] = $headers['Content-Type'];
			$modified_headers['Authorization'] = $headers['Authorization'];
			return $modified_headers;
		}
	}
}
