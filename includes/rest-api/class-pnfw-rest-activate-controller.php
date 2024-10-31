<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

require_once dirname(__FILE__ ) . '/class-pnfw-rest-controller.php';

class PNFW_REST_Activate_Controller extends PNFW_REST_Controller {
	public function __construct() {
		parent::__construct('activate');
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->route,
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'activate_token'),
				'permission_callback' => '__return_true',
				'args' => array(
					'activation_code' => array(
						'required' => true,
						'sanitize_callback' => function($value, $request, $param) {
							return sanitize_text_field($value);
						}
					)
				)
			)
		);
	}

	public function activate_token($request) {
		// Enforce HTTP method
		if (!is_null($http_method) && strtoupper($http_method) !== $request->get_method())
			return new WP_Error('pnfw_not_able_to_activate_account', __('We were not able to activate your account. Please contact support.', 'push-notifications-for-wp'));

		$activation_code = $request->get_param('activation_code');

		global $wpdb;
		$table_name = $wpdb->get_blog_prefix() . 'push_tokens';

		$user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table_name WHERE activation_code = %s", $activation_code));

		if (!isset($user_id)) {
			return new WP_Error('pnfw_not_able_to_activate_account', __('We were not able to activate your account. Please contact support.', 'push-notifications-for-wp'));
		}

		$res = $wpdb->update($table_name, array('active' => true), array('activation_code' => $activation_code));

		if (!$res) {
	        return new WP_Error('pnfw_not_able_to_activate_account', __('We were not able to activate your account. Please contact support.', 'push-notifications-for-wp'));
		}

		$this->send_admin_email($user_id);

		return new WP_REST_Response(array('message' => __('Thank you for activating your account.', 'push-notifications-for-wp')), 200);
	}

	private function send_admin_email($user_id) {
		$user = get_userdata($user_id);

		// The blogname option is escaped with esc_html on the way into the database in sanitize_option
		// we want to reverse this for the plain text arena of emails.
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

		$message  = sprintf(__('New confirmed app subscriber on your site %s: %s', 'push-notifications-for-wp'), $blogname, $user->user_email) . "\r\n\r\n";

		wp_mail(get_option('admin_email'), sprintf(__('[%s] New Confirmed App Subscriber', 'push-notifications-for-wp'), $blogname), $message);
	}
}
