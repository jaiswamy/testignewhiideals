<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://sevengits.com
 * @since      1.0.0
 * @subpackage Phonepe/admin
 */

class Phonepe_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}
	function sgpe_wc_add_to_gateways( $gateways ) {
			
		$gateways[] = 'WC_PhonePe_Gateway';
		return $gateways;

	}
	function sgpe_wc_gateway_init()
	{

	require_once SGPPY_PLUGIN_PATH . 'includes/class-phonepe-gateway.php';
	}
}
