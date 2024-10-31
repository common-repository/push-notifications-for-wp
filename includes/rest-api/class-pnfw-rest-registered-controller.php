<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

require_once dirname(__FILE__ ) . '/class-pnfw-rest-controller.php';

abstract class PNFW_REST_Registered_Controller extends PNFW_REST_Controller {
	protected function rest_route_callback($request) {
		parent::rest_route_callback($request); // Oauth auth (if enabled)

		// Check token is registered
		if ($this->is_token_missing())
			$this->json_error('401', __("Token not registered.\nTo solve the problem please uninstall and reinstall the app.", 'push-notifications-for-wp'));

		// Update lang
		if (isset($this->lang))
			$this->update_token_lang();
	}

	private function update_token_lang() {
		global $wpdb;
		$push_tokens = $wpdb->get_blog_prefix().'push_tokens';
		$wpdb->update(
			$push_tokens,
			array(
				'lang' => $this->lang
			),
			array(
				'token' => $this->token,
				'os' => $this->os
			)
		);
	}
}
