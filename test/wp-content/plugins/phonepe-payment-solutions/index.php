<?php

/**
 * Plugin Name: PhonePe Payment Solutions
 * Plugin URI: https://github.com/PhonePe/
 * Description: Using this plugin you can accept payments through PhonePe. After activating this plugin, you can see the PhonePe option linked to the checkout page of woocommerce site. On configuring with the provided Merchant credentials, you can enable this plugin in Preprod/Prod environment.
 * Version: 3.0.1
 * Author: PhonePe
 * Requires PHP: 5.6
 */

require_once __DIR__ . '/debug.php';
require_once __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use PhonePe\payments\v2\standardCheckout\StandardCheckoutClient;

$woocommerce_b2bpg_configs_json = file_get_contents(__DIR__ . '/config.json');
$woocommerce_b2bpg_configs = json_decode($woocommerce_b2bpg_configs_json, true);

define('B2BPG_WOOCOMMERCE_PLUGIN_VERSION', $woocommerce_b2bpg_configs['major'] . '.' . $woocommerce_b2bpg_configs['minor'] . '.' . $woocommerce_b2bpg_configs['patch']);
if (!defined('STAGE_AVAIALABLE')) {
  define('STAGE_AVAIALABLE', $woocommerce_b2bpg_configs['snapshot']);
}

if (!defined('PPEX_WC_PG_PLUGIN_DIR_LANGUAGES')) {
  define('PPEX_WC_PG_PLUGIN_DIR_LANGUAGES', dirname(plugin_basename(__FILE__)) . 'languages/');
}

if (!defined('PPEX_WC_BUSINESS_DASHBOARD_LINK')) {
  define('PPEX_WC_BUSINESS_DASHBOARD_LINK', 'To get started, simply grab your <strong>Client ID</strong> and <strong>API Key</strong> from the <strong>Developer Settings</strong> page on your <a href="https://business.phonepe.com/developer-settings/api-keys/" target="_blank" aria-label="Plugin Additional Links" style="color:purple;">PhonePe Dashboard</a>.');
}

if (!defined('PPEX_WC_ONBOARDING_LINK')) {
  define('PPEX_WC_ONBOARDING_LINK', 'New to PhonePe PG? <a href="https://www.phonepe.com/business-solutions/payment-gateway/register/" target="_blank" aria-label="Plugin Additional Links" style="color:purple;">Register here!</a>');
}

if (!defined('PPEX_WC_HELP_CENTRE_LINK')) {
  define('PPEX_WC_HELP_CENTRE_LINK', 'Need help? Contact our <a href="https://business.phonepe.com/faq/" target="_blank" aria-label="Plugin Additional Links" style="color:purple;">support</a> team — we are here to assist!');
}

if (!defined('PPEX_WC_PG_ICON_URL')) {
  define('PPEX_WC_PG_ICON_URL', 'https://imgstatic.phonepe.com/images/online-merchant-assets/plugins/woocommerce/2529/405/payment_gateway_logo.png');
}

add_action('plugins_loaded', 'ppex_woocommerce_phonepe_init', 0);

function ppex_woocommerce_phonepe_init() {
  if (!class_exists('WC_Payment_Gateway')) return;

  if (session_status() == PHP_SESSION_NONE) {
    session_start([
      'read_and_close' => true,
    ]);
  }

  /**
   * Localisation
   */
  load_plugin_textdomain('wc-phonepe', false, PPEX_WC_PG_PLUGIN_DIR_LANGUAGES);

  if (isset($_GET['phonepe_response'])) {
    add_action('the_content', 'phonepe_show_message');
  }

  function phonepe_show_message($content) {
    $type = isset($_GET['type']) ? htmlentities(sanitize_text_field($_GET['type'])) : '';
    $phonepe_response = isset($_GET['phonepe_response']) ? htmlentities(urldecode($_GET['phonepe_response'])) : '';
    
    return '<div class="phonepe_response box ' . $type . '-box">' . $phonepe_response . '</div>' . $content;
  }

  // Gateway class
  class WC_PhonePe extends WC_Payment_Gateway {

    public static $directory_path;
    public static $directory_url;
    public static $plugin_basename = '';
    public static $version = '';

    private $merchant_context;
    private $plugin_context;
    private $wc_b2b_pg_client;
    private $network_manager;
    private $paypage_loading_mode;
    private $standard_checkout_client;
    private $pg_v2_client;

    public function __construct() {
      $this->init_plugin_vars();
      $this->require_all_common_files();
      $this->require_client_implementation();


      $this->id = PPEX_PG_Constants::PHONEPE_PG_ID;
      $this->method_title = PPEX_PG_Constants::PHONEPE_METHOD_TITLE;


      $this->has_fields = false;
      $this->supports = ['products'];
      $this->init_settings();
      $this->init_form_fields();

      $this->title = 'PhonePe Payment Solutions';
      $this->method_description =  'Pay Securely using UPI, Cards, or NetBanking <br/> <br/>' . PPEX_WC_BUSINESS_DASHBOARD_LINK . '<br/> ' . PPEX_WC_ONBOARDING_LINK . '<br/> ' . PPEX_WC_HELP_CENTRE_LINK;
      $this->description = 'All UPI apps, Debit and Credit Cards, and NetBanking accepted | Powered by PhonePe';

      $this->icon = PPEX_WC_PG_ICON_URL;

      $this->merchant_context = new PPEX_Merchant_Context(
        isset($this->settings['merchantIdentifier']) ? $this->settings['merchantIdentifier'] : null,
        isset($this->settings['saltKey']) ? $this->settings['saltKey'] : null,
        isset($this->settings['Index']) ? $this->settings['Index'] : null,
      );

      $this->plugin_context = new PPEX_Plugin_Context(
        PPEX_PG_Constants::PLUGIN_SOURCE_HEADER,
        PPEX_PG_Constants::WOOCOMMERCE,
        WOOCOMMERCE_VERSION,
        B2BPG_WOOCOMMERCE_PLUGIN_VERSION,
        isset($this->settings['envType']) ? $this->settings['envType'] : null,
        isset($this->settings['payPageFlag']) ? $this->settings['payPageFlag'] : null,
      );

      $this->network_manager = new PPEX_PG_Network_Manager(new PPEX_WC_Http_Client());

      $this->wc_b2b_pg_client = new PPEX_WC_PG_Client($this->merchant_context, $this->plugin_context);

      if (isset($this->settings['clientSecret'])) {
        $ppex_wc_pg_v2_http_client = new PPEX_WC_Http_Client_V2();
        $this->standard_checkout_client = PhonePe\payments\v2\standardCheckout\StandardCheckoutClient::getInstance($this->settings['clientId'], $this->settings['clientVersion'], $this->settings['clientSecret'], $this->plugin_context->get_environment(), true, $ppex_wc_pg_v2_http_client);
        $this->pg_v2_client = new PPEX_WC_PG_V2_Client($this->standard_checkout_client, $this->plugin_context);
      }
      $this->init_hooks();
      $this->check_order_recieved();
    }

    private function require_all_common_files() {
      $files = glob(self::$directory_path . '/root-common/*.php');
      foreach ($files as $file) {
        require_once($file);
      }

      $files = glob(self::$directory_path . '/common/*.php');
      foreach ($files as $file) {
        require_once($file);
      }
    }

    private function require_client_implementation() {
      require_once self::$directory_path . 'PPEX_WC_PG_Client.php';
      require_once self::$directory_path . 'PPEX_WC_PG_V2_Client.php';
    }

    private function init_plugin_vars() {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';

      self::$directory_path = plugin_dir_path(__FILE__);
      self::$directory_url  = plugin_dir_url(__FILE__);
      self::$plugin_basename = plugin_basename(__FILE__);
      self::$version = get_file_data(__FILE__, ['Version' => 'Version'], 'plugin')['Version'];
    }

    public function check_order_recieved() {
      if (is_order_received_page()) {
        global $wp;
        $order_id  = absint($wp->query_vars['order-received']);
        $payment_method = get_post_meta($order_id, '_payment_method', true);
        if ($payment_method == PPEX_PG_Constants::PHONEPE_PG_ID) {
          add_filter('the_title', 'woo_title_order_received', 10, 2);
          function woo_title_order_received($title, $id) {
            if (function_exists('is_order_received_page') && is_order_received_page() && get_the_ID() === $id) { ?>
              <script>
                jQuery('ul.woocommerce-thankyou-order-details li.woocommerce-order-overview__payment-method.method strong').text("<?php echo esc_attr(PPEX_PG_Constants::PAYMENT_METHOD_NAME); ?>");
              </script>
        <?php
            }
            return $title;
          }
        }
      }
    }

    public function init_form_fields() {
      $woocommerce_phonepe_settings = get_option('woocommerce_phonepe_settings');
      $isClientSecretPresent =  isset($woocommerce_phonepe_settings['clientSecret']) && !empty($woocommerce_phonepe_settings['clientSecret']);
      $isSaltKeyPresent = isset($woocommerce_phonepe_settings['saltKey']) && !empty($woocommerce_phonepe_settings['saltKey']);
      if ($isClientSecretPresent || $isSaltKeyPresent == false) {
        $this->form_fields = array(
          'enabled' => array(
            'title' => __('Enable/Disable'),
            'type' => 'checkbox',
            'label' => __('Enable PhonePe Payments.'),
            'default' => 'no'
          ),
          'clientId' => array(
            'title' => __('Client Id <span style="color: red;" title="This field is mandatory">*</span>'),
            'type' => 'text',
            'description' => __('Client Id Provided by PhonePe'),
            'desc_tip' => true
          ),
          'clientSecret' => array(
            'title' => __('API Key <span style="color: red;" title="This field is mandatory">*</span>'),
            'type' => 'text',
            'description' => __('API Key Provided by PhonePe'),
            'desc_tip' => true
          ),
          'clientVersion' => array(
            'title' => __('Client Version <span style="color: red;" title="This field is mandatory">*</span>'),
            'type' => 'text',
            'description' => __('Client Version Provided by PhonePe'),
            'desc_tip' => true
          ),
          'envType' => array(
            'title' => __('Environment'),
            'default' => PPEX_Constants::PRODUCTION,
            'type' => 'select',
            'options' => STAGE_AVAIALABLE ? array(PPEX_Constants::UAT, PPEX_Constants::PRODUCTION, PPEX_Constants::STAGE) : array(PPEX_Constants::UAT, PPEX_Constants::PRODUCTION),
            'description' => __('Environment type for PhonePe'),
            'desc_tip' => true
          ),
          'payPageFlag' => array(
            'title' => __('Payment page open mode'),
            'default' => 'Open on top of the current page',
            'type' => 'select',
            'options' => array('Open on top of the current page', 'Redirect to a full-length payment page'),
            'description' => __('Both modes have the same set of features. In some cases, page load performance is better when redirected to a full-length page.'),
            'desc_tip' => true
          ),
        );
      } else {
        $this->form_fields = array(
          'enabled' => array(
            'title' => __('Enable/Disable'),
            'type' => 'checkbox',
            'label' => __('Enable PhonePe Payments.'),
            'default' => 'no'
          ),
          'merchantIdentifier' => array(
            'title' => __('Merchant Id'),
            'type' => 'text',
            'description' => __('Merchant Id Provided by PhonePe'),
            'desc_tip' => true
          ),
          'saltKey' => array(
            'title' => __('Salt Key'),
            'type' => 'text',
            'description' => __('Salt Key Provided by PhonePe'),
            'desc_tip' => true
          ),
          'Index' => array(
            'title' => __('Salt Key Index'),
            'type' => 'text',
            'description' => __('Salt Key Index Provided by PhonePe'),
            'desc_tip' => true
          ),
          'envType' => array(
            'title' => __('Environment'),
            'default' => PPEX_Constants::PRODUCTION,
            'type' => 'select',
            'options' => STAGE_AVAIALABLE ? array(PPEX_Constants::UAT, PPEX_Constants::PRODUCTION, PPEX_Constants::STAGE) : array(PPEX_Constants::UAT, PPEX_Constants::PRODUCTION),
            'description' => __('Environment type for PhonePe'),
            'desc_tip' => true
          ),
          'payPageFlag' => array(
            'title' => __('Payment page open mode'),
            'default' => 'Open on top of the current page',
            'type' => 'select',
            'options' => array('Open on top of the current page', 'Redirect to a full-length payment page'),
            'description' => __('Both modes have the same set of features. In some cases, page load performance is better when redirected to a full-length page.'),
            'desc_tip' => true
          ),
        );
      }
    }

    public function ppex_preprocess_phonepe_payment_fields($options) {

      // Check if clientId and clientSecret are present in the options
      if (isset($options['clientId']) && isset($options['clientSecret']) && isset($options['clientVersion'])) {
        switch ($options['envType']){
            case 0: $environemnt = PPEX_Constants::UAT; break;
            case 1: $environemnt = PPEX_Constants::PRODUCTION; break;
            case 2: $environemnt = PPEX_Constants::STAGE; break;
            default: $environemnt = PPEX_Constants::PRODUCTION;
        }
        try {
          $ppex_wc_pg_v2_http_client = new PPEX_WC_Http_Client_V2();
          $standard_checkout_client = PhonePe\payments\v2\standardCheckout\StandardCheckoutClient::getInstance($options['clientId'], $options['clientVersion'], $options['clientSecret'], $environemnt, true, $ppex_wc_pg_v2_http_client);
          $OAuthToken = $standard_checkout_client->getAuthHeadersToken();

          // call webhook API
          $callback_url = site_url() . '/index.php/wp-json/wp-phonepe/v2/callback';

          $url = PPEX_Utils::get_base_webhook_url($environemnt) . PPEX_Constants::WEBHOOK_ENDPOINT;
          $headers = array();
          $headers['Authorization'] = $OAuthToken;
          $headers['Content-Type'] = 'application/json';
          $username = generate_username(PPEX_Constants::WEBHOOK_CREDENTIAL_LENGTH);
          // Generate an 12-character random password
          $password = generate_password(PPEX_Constants::WEBHOOK_CREDENTIAL_LENGTH);

          // Set the POST data
          $data = [
              "webhooks" => [
                  [
                      "channel" => [
                          "type" => "HTTPS",  // Change from HTTP to HTTPS as per the contract
                          "url" => $callback_url,
                          "username" => $username,
                          "password" => $password,
                          "description" => "WooCommerce Plugin Webhook"
                      ],
                      "events" => [
                          PPEX_Constants::CHECKOUT_ORDER_COMPLETED,
                          PPEX_Constants::CHECKOUT_ORDER_FAILED
                      ]
                  ]
              ]
          ];
          $postData = json_encode($data);
          $webhook_respose = $ppex_wc_pg_v2_http_client::postRequest($url, $postData, $headers);
          ppLogInfo("webhook response: ");
          ppLogInfo(json_encode($webhook_respose));

          $options['username'] = $username;
          $options['password'] = $password;
          $event = PPEX_Utils::create_event($this->plugin_context, PPEX_Constants::CHANGES_SAVED_AND_PLUGIN_ACTIVATED);
          $event->data['webhookResponse'] = $webhook_respose;
          try {
              $standard_checkout_client->sendEvent($event);
          } catch (Exception $exception) {
              ppLogError(json_encode($exception));
          }

        } catch (Exception $exception) {
          ppLogError(json_encode($exception));
          $options['clientId'] = "";
          $options['clientSecret'] = "";
          $options['clientVersion'] = "";
          $code =$exception->getCode();

          if($code == PPEX_PG_Constants::CLIENT_NOT_FOUND) {
              add_action('admin_notices', function () {
                  echo '<div class="notice notice-error"><p>Incorrect credentials, Please try again with correct credentials. Refer logs for more information. </p></div>';
              });
          }else {
              add_action('admin_notices', function () {
                  echo '<div class="notice notice-error"><p>Unexpected error occured while saving credentials, refer logs for more information. </p></div>';
              });
          }

          remove_action( 'admin_notices', 'settings_saved_notice' );
        }
      }

      return $options;
    }
    public function send_activation_event() {
      if($this->ppex_is_pg_v2_enabled() == false) {
				$ppex_event = new PPEX_Event();
				$ppex_event->set_event_type("CHANGES_SAVED_AND_PLUGIN_ACTIVATED");
				$ppex_event->set_merchant_id($this->get_merchant_context()->get_merchant_id());
				$this->network_manager->post_event($ppex_event, $this->get_merchant_context(), $this->get_plugin_context());
      }
    }

    public function init_hooks() {
      add_action('woocommerce_api_' . $this->id, array($this, 'check_phonepe_response'));
      add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'send_activation_event'));
      add_filter('sanitize_option_woocommerce_phonepe_settings', array($this, 'ppex_preprocess_phonepe_payment_fields'), 10, 1);

      if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
      } else {
        add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
      }
      wp_enqueue_style('phonepe-styles', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/style.css');
    }

    /**
     *  There are no payment fields for phonepe, but we want to show the description if set.
     **/
    public function payment_fields() {
      if ($this->description) echo wpautop(wptexturize($this->description));
    }

    /**
     * Receipt Page
     **/
    public function receipt_page($order_id) {
      echo '<p>' . __('Thank you, please wait while we confirm your orders.') . '</p>';
      try {
        if ($this->ppex_is_pg_v2_enabled()) {
          $this->pg_v2_client->render_payment_ui($order_id);
        } else {
          $this->wc_b2b_pg_client->render_payment_ui($order_id);
        }
      } catch (Exception $error) {
        ppLogError($error);
        echo "We're sorry, an unexpected error has occurred. Please try again later.";

        if($this->ppex_is_pg_v2_enabled()) {
          $event = PPEX_Utils::create_event($this->plugin_context, PPEX_Constants::PAYPAGE_NOT_RENDERED);
          $event->data['code'] = $error->getCode();
          $event->data['message'] = $error->getMessage();
          $event->data['state'] = PPEX_Constants::FAILURE;
					try {
            $this->standard_checkout_client->sendEvent($event);
					} catch (Exception $exception) {
						ppLogError(json_encode($exception));
					}
        } else {
					$ppex_failure_event = new PPEX_Event();
					$ppex_failure_event->set_event_type(PPEX_Constants::PAYPAGE_NOT_RENDERED);
					$ppex_failure_event->set_merchant_id($this->merchant_context->get_merchant_id());
					$ppex_failure_event->set_state('FAILURE');
					$ppex_failure_event->set_code($error->getCode());
					$ppex_failure_event->set_message($error->getMessage());
					$this->network_manager->post_event($ppex_failure_event, $this->merchant_context, $this->plugin_context);
        }
      }
    }

    /**
     * Process the payment and return the result
     **/
    public function process_payment($order_id) {
      if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
        $order = new WC_Order($order_id);
      } else {
        $order = new woocommerce_order($order_id);
      }
      return array(
        'result' => 'success',
        'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, $order->get_checkout_payment_url(true)))
      );
    }

    /**
     * Check for valid phonepe server callback // response processing //
     **/
    public function check_phonepe_response() {
      ppLogInfo(json_encode($_GET['merchant_transaction_id']));
      try {
				if ($this->ppex_is_pg_v2_enabled()) {
					$this->pg_v2_client->check_phonepe_response($_GET['merchant_transaction_id']);
				} else {
					$this->wc_b2b_pg_client->check_phonepe_response($_GET['merchant_transaction_id']);
				}
      }catch (Exception $exception) {
          ppLogError(json_encode($exception));
      }
    }

    public function ppex_is_pg_v2_enabled() {
      return isset($this->settings['clientSecret']);
    }

    public function get_wc_b2b_pg_client() {
      return $this->wc_b2b_pg_client;
    }

    public function get_pg_v2_client() {
      return $this->pg_v2_client;
    }

    public function get_merchant_context() {
      return $this->merchant_context;
    }

    public function get_plugin_context() {
      return $this->plugin_context;
    }

    public function get_network_manager() {
      return $this->network_manager;
    }

    public function get_standard_checkout_client() {
      return $this->standard_checkout_client;
    }

    /*
		 * End phonepe Essential Functions
		 **/
  }

  /**
   * Add the Gateway to WooCommerce
   **/
  function add_phonepe_gateway_to_payment_options($methods) {
    $methods[] = 'WC_phonepe';
    return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'add_phonepe_gateway_to_payment_options');


  /**
   * Declare compatibility to checkout blocks
   */

  add_action('before_woocommerce_init', 'ppex_declare_cart_checkout_blocks_compatibility');
  function ppex_declare_cart_checkout_blocks_compatibility() {
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
      // Declare compatibility for 'cart_checkout_blocks'
      \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
  }

  //   Hook the custom function to the 'woocommerce_blocks_loaded' action
  add_action('woocommerce_blocks_loaded', 'ppex_register_order_approval_payment_method_type');

  function ppex_register_order_approval_payment_method_type() {
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
      return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'block/PPEX_WC_BLOCK_CHECKOUT.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
      'woocommerce_blocks_payment_method_type_registration',
      function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
        // Register an instance of PPEX_WC_BLOCK_CHECKOUT
        $payment_method_registry->register(new PPEX_WC_BLOCK_CHECKOUT);
      }
    );
  }

    /*
    ** To create shortcut to PhonePe plugin specific settings for marchants 
    */

  function pp_settings_link($links) {
    $url = esc_url(add_query_arg(
      'page',
      'wc-settings',
      get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=phonepe'
    ));

    $settings_link = "<a href='$url'>" . __('Settings') . '</a>';

    array_push(
      $links,
      $settings_link
    );
    return $links;
  }

  add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pp_settings_link');

  /*
	** To create link below description on plugin listing page
	*/

  add_filter('plugin_row_meta', 'ppex_plugin_row_meta', 10, 2);
  function ppex_plugin_row_meta($links, $file) {
    if (plugin_basename(__FILE__) == $file) {
      $row_meta = array(
        'New to PhonePe PG? Register here!'    => PPEX_WC_ONBOARDING_LINK
      );
      return array_merge($links, $row_meta);
    }
    return (array) $links;
  }

  /*
  ** To create shortcut to PhonePe plugin specific settings for marchants in woocommerce menu
  */

  add_action('admin_menu', 'ppex_settings_add_plugin_page');

  function ppex_settings_add_plugin_page() {
    add_submenu_page(
      'woocommerce',
      __('PhonePe settings', 'phonepe-payment-solutions'), // page_title
      __('PhonePe settings', 'phonepe-payment-solutions'), // menu_title
      'manage_options', // capability
      'phonepe-settings', // menu_slug
      'ppex_settings_navigation', // function
      100
    );
  }

  /**
   * @return void
   */
  function ppex_settings_navigation() {
    wp_redirect(get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=phonepe');
  }

  register_deactivation_hook(
    __FILE__,
    'ppex_fire_plugin_deactivated_event'
  );

  function ppex_fire_plugin_deactivated_event() {
    // TDDO: condition to differenciate pg v1 and v2 events

    $wc_phonepe = new WC_PhonePe();
    $ppex_event = new PPEX_Event();

    if($wc_phonepe->ppex_is_pg_v2_enabled()) {
			$event = PPEX_Utils::create_event($wc_phonepe->get_plugin_context(), PPEX_Constants::PLUGIN_DEACTIVATED);
			try {
			 $wc_phonepe->get_standard_checkout_client()->sendEvent($event);
			} catch (Exception $exception) {
				ppLogError(json_encode($exception));
			}
    }else {
			$ppex_event->set_event_type(PPEX_Constants::PLUGIN_DEACTIVATED);
			$ppex_event->set_merchant_id($wc_phonepe->get_merchant_context()->get_merchant_id());
			$wc_phonepe->get_network_manager()->post_event($ppex_event, $wc_phonepe->get_merchant_context(), $wc_phonepe->get_plugin_context());
		}
  }


  function simulate_as_not_rest($is_rest_api_request) {
    if (empty($_SERVER['REQUEST_URI'])) {
      return $is_rest_api_request;
    }

    if (strpos($_SERVER['REQUEST_URI'], '/index.php/wp-json/' . 'wp-phonepe/v1/callback') !== false) {
      return false;
    }

    if (strpos($_SERVER['REQUEST_URI'], '/index.php/wp-json/' . 'wp-phonepe/v2/callback') !== false) {
      return false;
    }

    if (strpos($_SERVER['REQUEST_URI'], '/index.php/wp-json/' . 'wp-phonepe/v1/check-pending-status') !== false) {
      return false;
    }

    return $is_rest_api_request;
  }

  add_filter('woocommerce_is_rest_api_request', 'simulate_as_not_rest');

  function register_routes() {
    register_rest_route(
      'wp-phonepe/v1',
      'callback',
      array(
        'methods'  => 'POST',
        'callback' => 'handle_callback',
        'permission_callback' => '__return_true',
      )
    );

    register_rest_route(
      'wp-phonepe/v2',
      'callback',
      array(
        'methods'  => 'POST',
        'callback' => 'handle_pg_v2_callback',
        'permission_callback' => '__return_true',
      )
    );

    register_rest_route(
      'wp-phonepe/v1',
      'check-pending-status',
      array(
        'methods'  => 'POST',
        'callback' => 'check_pending_status',
        'permission_callback' => '__return_true',
      )
    );
  }

  add_action('rest_api_init', 'register_routes');

  function handle_callback() {
		$wc_phonepe = new WC_PhonePe();

		$merchant_id = $wc_phonepe->get_merchant_context()->get_merchant_id();
		$merchant_key = $wc_phonepe->get_merchant_context()->get_salt_key();
		$key_index = $wc_phonepe->get_merchant_context()->get_salt_index();
		$payload = file_get_contents('php://input');
		$headers = filter_var($_SERVER['HTTP_X_VERIFY'], FILTER_SANITIZE_STRING);
		$payload = json_decode($payload, true);
		$decoded_payload = $payload['response'];
		$ppex_pg_callback = $wc_phonepe->get_network_manager()->handle_callback($decoded_payload, $headers, $merchant_key, $key_index);

		$wc_phonepe->get_wc_b2b_pg_client()->handle_callback_response($ppex_pg_callback);
  }

  function handle_pg_v2_callback() {

    $wc_phonepe = new WC_PhonePe();
    if (!$wc_phonepe->ppex_is_pg_v2_enabled()) {
      ppLogError('PG V2 is not enabled, aborted callback processing');
    }

    $payload = file_get_contents('php://input');
    $headers = getallheaders();

    $phonepe_woocommerce_config = get_option('woocommerce_phonepe_settings');
    $username = $phonepe_woocommerce_config['username'];
    $password = $phonepe_woocommerce_config['password'];

    $ppex_pg_v2_callback = new PPEX_PG_V2_Callback($headers, $payload, $username, $password);
    return $wc_phonepe->get_pg_v2_client()->handle_callback_response($ppex_pg_v2_callback);
  }

  add_action('woocommerce_order_item_add_action_buttons', 'wc_order_item_add_action_buttons_callback', 10, 1);
  function wc_order_item_add_action_buttons_callback($order) {
    $payment_method = $order->get_payment_method();
    if ($payment_method == 'PhonePe Payment Solutions') {
      $label = esc_html__('Custom', 'woocommerce');
      $slug  = 'refund';

      ?>

      <script>
        document.addEventListener('DOMContentLoaded', function() {
          var refundButton = document.querySelector('.refund-items');

          if (refundButton) {
            refundButton.textContent = 'PhonePe PG Refund';

            var tooltipContent = 'Click here to request a refund';

            refundButton.setAttribute('title', tooltipContent);
            refundButton.classList.add('refund-tooltip');

            refundButton.setAttribute('id', 'phonepe-refund-button');
            refundButton.classList.remove('refund-items');

            phonepeRefundButton = document.getElementById('phonepe-refund-button');

            phonepeRefundButton.addEventListener('click', function() {
              // Create the mini dialog box
              console.log('reahced in event listener');

              var targetDiv = document.querySelector('.wc-order-bulk-actions');
              var siteURL = window.location.origin;

              var step1_img = document.createElement('img');
              step1_img.src = 'https://imgstatic.phonepe.com/images/online-merchant-assets/plugins/woocommerce/1903/1759/Refund_Journey_1.jpg';
              step1_img.style.width = '40vw';
              step1_img.alt = 'Step 1: Login to PhonePe Dashboard';

              var step2_img = document.createElement('img');

              step2_img.src = 'https://imgstatic.phonepe.com/images/online-merchant-assets/plugins/woocommerce/3264/2944/Refund_Journey_2.png';
              step2_img.style.width = '40vw';
              step2_img.alt = 'Step 2: Select the Transaction TID: Txxxxxxxxxxxxxx';

              var step3_img = document.createElement('img');

              step3_img.src = 'https://imgstatic.phonepe.com/images/online-merchant-assets/plugins/woocommerce/3264/2944/Refund_Journey_3.png';
              step3_img.style.width = '40vw';
              step3_img.alt = 'Step 3: Initiate the Refund';

              var heading = document.createElement('h1');
              heading.textContent = 'Steps to Create a Refund';

              var subheading = document.createElement('h2');
              subheading.textContent = 'Follow these steps to create a refund for a transaction';


              var step1_text = document.createElement('h3');
              step1_text.textContent = 'Step 1: Login to PhonePe Dashboard';
              step1_text.style.alignContent = 'center';

              var login_link = document.createElement('a');
              login_link.textContent = 'PhonePe Business Dashboard Login';
              login_link.href = 'https://business.phonepe.com/login';
              login_link.target = '_blank';

              var linebreak = document.createElement("br");

              var orderNumberDiv = document.querySelector('.woocommerce-order-data__meta.order_number');


              var merchant_tid = orderNumberDiv.innerText.match(/\d+/)[0];

              var step2_text = document.createElement('h3');
              step2_text.textContent = 'Step 2: Select the Transaction TID: ' + merchant_tid;

              var step3_text = document.createElement('h3');
              step3_text.textContent = 'Step 3: Initiate the Refund';

              var step4_text = document.createElement('h3');
              step4_text.innerHTML = '[Optional] Step 4: Restock the product <br>';

              var step4_subtext = document.createElement('h4');
              step4_subtext.innerHTML = 'I. Go to \'Product\' Page <br>' +
                'II. Select the \'Product\' which is refunded <br>' +
                'III. In the \'Inventory\' section, increase the \'quantity\'';

              targetDiv.appendChild(heading);
              targetDiv.appendChild(subheading);

              targetDiv.appendChild(step1_text);
              targetDiv.appendChild(login_link);
              targetDiv.appendChild(linebreak);
              targetDiv.appendChild(step1_img);

              targetDiv.appendChild(step2_text);
              targetDiv.appendChild(step2_img);

              targetDiv.appendChild(step3_text);
              targetDiv.appendChild(step3_img);

              targetDiv.appendChild(step4_text);
              targetDiv.appendChild(step4_subtext);

              refundButton.disabled = true;
            });
          }

        });
      </script>
    <?php
    }
  }

  function check_pending_status() {
    try {
			$wc_phonepe = new WC_PhonePe();

			global $wpdb;

			$pending_orders_query = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}posts where post_type = %s and post_status = %s;", 'shop_order', 'wc-pending'));

			foreach ($pending_orders_query as $order) {
				$wc_order_id = $order->ID;
				$order = wc_get_order($wc_order_id);
				$merchant_transaction_id = $order->get_transaction_id();
				if ($order->get_payment_method() != PPEX_PG_Constants::PAYMENT_METHOD_NAME) {
					continue;
				}

				$ppex_order_type = $order->get_meta('ppex_order_type');
				if ($ppex_order_type == PPEX_Constants::PG_V2_ORDER && $wc_phonepe->ppex_is_pg_v2_enabled()) {
					// check status using pg v2 client
					$wc_phonepe->get_pg_v2_client()->check_pending_status($merchant_transaction_id);
				} else {
					// check status using pg v1 client
					$wc_phonepe->get_wc_b2b_pg_client()->check_pending_status($merchant_transaction_id);
				}
			}
		}catch (Exception $exception){
            ppLogError("Exception: " . json_encode($exception));
		}
  }

  function phonepe_support_menu_item() {
    add_menu_page(
      'PhonePe PG Support',
      'PhonePe PG Support ',
      'manage_options',
      'phonepe-support',
      'render_phonepe_pg_support_page',
      'dashicons-format-chat',
      30
    );
  }
  add_action('admin_menu', 'phonepe_support_menu_item');

  // Add submenus to the PhonePe Support menu item
  function phonepe_support_submenu_items() {
    add_submenu_page(
      'phonepe-support',
      'Download Error Logs',
      'Download Error Logs',
      'manage_options',
      'phonepe-support-logs',
      'ppex_download_logs_callback'
    );

    add_submenu_page(
      'phonepe-support',
      'Compose Error Report',
      'Compose Error Report',
      'manage_options',
      'phonepe-support-mail',
      'phonepe_support_report_mail'
    );
  }

  add_action('admin_menu', 'phonepe_support_submenu_items');

  function render_phonepe_pg_support_page() {
    $wc_phonepe = new WC_PhonePe();
    $ppex_event = new PPEX_Event();

    if($wc_phonepe->ppex_is_pg_v2_enabled()) {
            $event = PPEX_Utils::create_event($wc_phonepe->get_plugin_context(), PPEX_Constants::PHONEPE_PG_SUPPORT_CLICKED);
			try {
			 $wc_phonepe->get_standard_checkout_client()->sendEvent($event);
			} catch (Exception $exception) {
				ppLogError(json_encode($exception));
			}
    }else {
			$ppex_event->set_event_type(PPEX_Constants::PHONEPE_PG_SUPPORT_CLICKED);
			$ppex_event->set_merchant_id($wc_phonepe->get_merchant_context()->get_merchant_id());
			$wc_phonepe->get_network_manager()->post_event($ppex_event, $wc_phonepe->get_merchant_context(), $wc_phonepe->get_plugin_context());
		}

    echo "<p><strong>How to Report Bugs with PhonePe Payments Gateway</strong></p>

		<p>At times, you may encounter unexpected issues or glitches while using our payment gateway. To enhance your experience and facilitate swift issue resolution, we've introduced a new, easy-to-use, three-step process for reporting bugs. This feature allows you to gather error logs, create a detailed report, and send it directly to us. Let's take you through the steps involved:</p>
		
		<ol>
			<li>
				<strong>Step 1: Get Error Logs</strong><br>
				Your first task is to download the logs that provide crucial insights into any issues you've encountered. To do this, simply click on the <code>Download Error Logs</code> link available under <code>PhonePe PG Support</code> tab in the sidebar of WordPress admin dashboard. This action will trigger the download of a zip file containing the error logs for your perusal.
			</li>
			<li>
				<strong>Step 2: Generate Report Email</strong><br>
				Once you've downloaded the error logs, the next step is to generate a bug report. This is as simple as clicking on the <code>Compose Error Report</code> link, also located available under <code>PhonePe PG Support</code> tab in the sidebar of WordPress admin dashboard. Clicking this link will open up your default email application, pre-populating it with an email addressed and other relevant information required to debug the issue.
			</li>
			<li>
				<strong>Step 3: Attach the Logs and Send the Email</strong><br>
				The final step is to attach the zip file you downloaded in step 1 to the email generated in step 2. Ensure that the <code>errorLogs.zip</code> file is attached before sending the email. This will provide our technical team with all the details they need to analyze and resolve the issue as quickly as possible.
			</li>
		</ol>
		
		<p>And there you have it! Reporting bugs is now as easy as 1-2-3.</p>
		";
  }

  function phonepe_support_report_mail() {

    $wc_phonepe = new WC_PhonePe();
    $ppex_event = new PPEX_Event();

    if($wc_phonepe->ppex_is_pg_v2_enabled()) {
            $event = PPEX_Utils::create_event($wc_phonepe->get_plugin_context(), PPEX_Constants::COMPOSE_ERROR_REPORT_CLICKED);
			try {
				$wc_phonepe->get_standard_checkout_client()->sendEvent($event);
			} catch (Exception $exception) {
				ppLogError(json_encode($exception));
			}
		}else {
			$ppex_event->set_event_type(PPEX_Constants::COMPOSE_ERROR_REPORT_CLICKED);
			$ppex_event->set_merchant_id($wc_phonepe->get_merchant_context()->get_merchant_id());
			$wc_phonepe->get_network_manager()->post_event($ppex_event, $wc_phonepe->get_merchant_context(), $wc_phonepe->get_plugin_context());
		}

    $url = "mailto:" . PPEX_Constants::MERCHANT_SUPPORT_EMAIL_ID . "?subject=Request for PhonePe PG Support | " . $wc_phonepe->get_merchant_context()->get_merchant_id() . " | Woocommerce &body=Dear Support Team, %0d%0a I am writing to inform you about a technical issue I am currently facing with our payment gateway. Please find the key details below: %0d%0a MerchantID:  " . $wc_phonepe->get_merchant_context()->get_merchant_id() . " %0d%0a Marketplace: Woocommerce " . $wc_phonepe->get_plugin_context()->get_x_source_platform_version() . " %0d%0a Plugin Version: " . $wc_phonepe->get_plugin_context()->get_x_source_version() . " %0d%0a Environment: " . $wc_phonepe->get_plugin_context()->get_environment() . " %0d%0a Description of the Issue: %0d%0a [Provide a brief description of the issue here] %0d%0a %0d%0a Additional Details: %0d%0a For WooCommerce Marketplace: %0d%0a Please find attached the errorLogs.zip file for your reference. %0d%0a Download errorLogs using the 'Download Error Logs' button in the 'PhonePe PG Support' in sidebar of wordpress dashboard. %0d%0a %0d%0a For Transactional Issues: Share transaction ID/s related to the issue: %0d%0a [Insert Transaction ID/ List of transaction ID here]. %0d%0a For Scenario specific Issues: %0d%0a Attach a full-page screenshot or a screen recording which illustrates the issue. The incident took place at [insert time of the issue here]. %0d%0a %0d%0a Please look into the issue. %0d%0a Kind Regards, %0d%0a [Your Name] %0d%0a Merchant ID: " . $wc_phonepe->get_merchant_context()->get_merchant_id() . " ";

    echo '<script>window.location.href = "' . $url . '";</script>';
    return;
  }


  function ppex_download_logs_callback() {
    $wc_phonepe = new WC_PhonePe();
    $ppex_event = new PPEX_Event();
    if($wc_phonepe->ppex_is_pg_v2_enabled()) {
			$event = PPEX_Utils::create_event($wc_phonepe->get_plugin_context(), PPEX_Constants::DOWNLOAD_ERROR_LOGS_CLICKED);
			try {
				$wc_phonepe->get_standard_checkout_client()->sendEvent($event);
			} catch (Exception $exception) {
				ppLogError(json_encode($exception));
			}
    } else {
			$ppex_event->set_event_type(PPEX_Constants::DOWNLOAD_ERROR_LOGS_CLICKED);
			$ppex_event->set_merchant_id($wc_phonepe->get_merchant_context()->get_merchant_id());
			$wc_phonepe->get_network_manager()->post_event($ppex_event, $wc_phonepe->get_merchant_context(), $wc_phonepe->get_plugin_context());
    }
    $log_directory = WP_CONTENT_DIR . '/uploads/wc-logs/';
    $logs = glob($log_directory . '/*.log');

    $filteredLogs = array();
    foreach ($logs as $log) {
      if (strpos($log, "phonepe") == true || strpos($log, "fatal-error") == true || strpos($log, "PHONEPE_CHECKOUT_V2") == true) {
        $filteredLogs[] = $log;
      }
    }


    $zip = new ZipArchive();
    $zipName = tempnam(sys_get_temp_dir(), 'zip');

    if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
      foreach ($filteredLogs as $log) {
        $logFilename = basename($log);
        $logFilePath = $log_directory . $logFilename;
        $zip->addFile($logFilePath, $logFilename);
      }

      $zip->close();
      // flush();
      ob_clean();

      $logs_file_name = $wc_phonepe->get_merchant_context()->get_merchant_id() . "-logs.zip";
      header('Content-Type: application/zip');
      header('Content-Disposition: attachment; filename="' . $logs_file_name . '"');
      header('Content-Length: ' . filesize($zipName));
      readfile($zipName);

      // Delete the temporary zip file
      unlink($zipName);
    } else {
      echo 'Failed to create the zip file.';
    }
  }

  function generate_username($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
    $str = '';
    $max = mb_strlen($keyspace, '8bit') - 1;
    if ($max < 1) {
      throw new Exception('$keyspace must be at least two characters long');
    }
    for ($i = 0; $i < $length; ++$i) {
      $str .= $keyspace[random_int(0, $max)];
    }
    return $str;
  }

  function generate_password($length = 20) {
    $length = min(max($length, 8), 20);

    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';

    // Start with at least one letter and one number
    $password = $letters[random_int(0, strlen($letters) - 1)] . $numbers[random_int(0, strlen($numbers) - 1)];

    // Fill the rest of the password with random characters from both sets
    $allChars = $letters . $numbers;
    for ($i = 2; $i < $length; $i++) {
      $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }

    // Shuffle the characters to ensure randomness
    $password = str_shuffle($password);

    return $password;
  }


  function dashboard_status() {
    $plugin_dir = plugin_dir_url(__FILE__);
    global $typenow, $wp_query;
    if (in_array($typenow, wc_get_order_types('order-meta-boxes'))) { ?>
      <p class="button pp_check_dashboard_status">Check Status</p>
      <script>
        jQuery('.pp_check_dashboard_status').click(function() {
          jQuery.ajax({
            type: 'POST',
            url: '<?php echo esc_url(site_url()); ?>' + '/index.php/wp-json/wp-phonepe/v1/check-pending-status',
            contentType: "application/x-www-form-urlencoded; charset=UTF-8",
            enctype: 'multipart/form-data',
            data: 'id=null',
            success: function(result) {
              window.location.reload();
            },
            error: function(error) {
              window.location.reload();
            }
          });
        })
      </script>
<?php
    }
  }
  add_action('restrict_manage_posts', 'dashboard_status');
}


?>