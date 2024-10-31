<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class PNFW_WP_API {

	function __construct() {
		add_action('rest_api_init', array($this, 'rest_api_init'));
	}

/* Actions & Hooks */
	public function rest_api_init() {
		// Register token
		require_once(dirname(__FILE__) . '/class-pnfw-rest-register-controller.php');
		$controller = new PNFW_REST_Register_Controller;
		$controller->register_routes();

		// Unregister token
		require_once(dirname(__FILE__) . '/class-pnfw-rest-unregister-controller.php');
		$controller = new PNFW_REST_Unregister_Controller;
		$controller->register_routes();

		// Categories
		require_once(dirname(__FILE__) . '/class-pnfw-rest-categories-controller.php');
		$controller = new PNFW_REST_Categories_Controller;
		$controller->register_routes();

		// Token activate
		require_once(dirname(__FILE__) . '/class-pnfw-rest-activate-controller.php');
		$controller = new PNFW_REST_Activate_Controller;
		$controller->register_routes();

		// Posts
		require_once(dirname(__FILE__) . '/class-pnfw-rest-posts-controller.php');
		$controller = new PNFW_REST_Posts_Controller;
		$controller->register_routes();

	}
}

new PNFW_WP_API();
