<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

require_once dirname(__FILE__ ) . '/class-pnfw-rest-registered-controller.php';

abstract class PNFW_REST_Activated_Controller extends PNFW_REST_Registered_Controller {
	protected function rest_route_callback($request) {
		parent::rest_route_callback($request);

		// Check token is activated
		if (!$this->is_token_activated())
			$this->json_error('401', __('Your email needs to be verified. Go to your email inbox and find the message from us asking you to confirm your address. Or make sure your email address is entered correctly.', 'push-notifications-for-wp'));
	}

	private function is_token_activated() {
		return PNFW_DB()->is_token_activated($this->token, $this->os);
	}
}
