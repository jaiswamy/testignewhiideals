<?php

/**
 * PPEX_Utils
 */
if (!class_exists('PPEX_Utils')) {
	class PPEX_Utils {

		public static function convert_to_paisa($amount_in_rupees) {
			return ($amount_in_rupees * 100);
		}

		public static function generate_checksum($base64_encoded_payload, $key, $index, $endpoint) {
			$string_to_be_hashed = $base64_encoded_payload . $endpoint .  $key;
			$hashed_string = self::generate_hashed_string($string_to_be_hashed, $index);
			return $hashed_string;
		}

		public static function generate_hashed_string($string_to_be_hashed, $index) {
			$sha256_hash = hash('sha256', $string_to_be_hashed);
			$hashed_string = $sha256_hash . "###" . $index;
			return $hashed_string;
		}

		public static function generate_checksum_for_callback($payload, $merchant_key, $key_index) {
			$hash_string = hash('sha256', $payload . $merchant_key);
			$hashed_string = $hash_string . "###" . $key_index;
			return $hashed_string;
		}

        public static function get_base_url($environment) {
            switch ($environment) {
                case PPEX_Constants::PRODUCTION:
                    return PPEX_Constants::PPBASE_URL_PROD;
                case PPEX_Constants::UAT:
                    return PPEX_Constants::PPBASE_URL_UAT;
                default:
                    return PPEX_Constants::PPBASE_URL_STAGE;
            }
        }

        public static function get_base_events_url($environment) {
            switch ($environment) {
                case PPEX_Constants::PRODUCTION:
                    return PPEX_Constants::PPBASE_URL_PROD_EVENTS;
                case PPEX_Constants::UAT:
                    return PPEX_Constants::PPBASE_URL_UAT_EVENTS;
                default:
                    return PPEX_Constants::PPBASE_URL_STAGE_EVENTS;
            }
        }

        public static function get_base_webhook_url($environment) {
            switch ($environment) {
                case PPEX_Constants::PRODUCTION:
                    return PPEX_Constants::PPBASE_URL_PROD_WEBHOOK;
                case PPEX_Constants::UAT:
                    return PPEX_Constants::PPBASE_URL_UAT_WEBHOOK;
                default:
                    return PPEX_Constants::PPBASE_URL_STAGE_WEBHOOK;
            }
        }

        public static function get_script($environment) {
            switch ($environment) {
                case PPEX_Constants::PRODUCTION:
                    return PPEX_Constants::PROD_SCRIPT;
                case PPEX_Constants::UAT:
                    return PPEX_Constants::UAT_SCRIPT;
                default:
                    return PPEX_Constants::STAGE_SCRIPT;
            }
        }

        public static function get_appintent_request_script($environment) {
            switch ($environment) {
                case PPEX_Constants::PRODUCTION:
                    return PPEX_Constants::APPINTENT_PROD_SCRIPT;
                case PPEX_Constants::UAT:
                    return PPEX_Constants::APPINTENT_UAT_SCRIPT;
                default:
                    return PPEX_Constants::APPINTENT_STAGE_SCRIPT;
            }
        }

		public static function make_merchant_transaction_id_unique_for_repeated_requests($wc_order_id) {
			return self::append_timestamp($wc_order_id);
		}

		public static function append_timestamp($wc_order_id) {
			return $wc_order_id . date("YmdHis");
		}

		public static function get_merchant_transaction_id_from_unique_transaction_id($merchant_transaction_id) {
			return substr($merchant_transaction_id, 0, -14);
		}

        public static function create_event($plugin_context, $event_name) {
            $event = new \PhonePe\common\eventHandler\Event();
            $event_data = array(
				"platform" => $plugin_context->get_x_source_platform(),
				"platformVersion" => $plugin_context->get_x_source_platform_version(),
				"pluginVersion" => $plugin_context->get_x_source_version(),
				"flowType" => PPEX_PG_Constants::FLOW_TYPE,
				"userOperatingSystem" => filter_var($_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_STRING)
            );
            $event->setMerchantOrderId(PPEX_Constants::NON_TRANSACTIONAL_EVENT);
            $event->setEventName($event_name);
            $event->setEventTime(date("ymdHis"));
            $event->setData($event_data);
            return $event;
        }
	}
}
