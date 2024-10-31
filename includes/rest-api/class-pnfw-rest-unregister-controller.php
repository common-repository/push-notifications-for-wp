<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

require_once dirname(__FILE__ ) . '/class-pnfw-rest-registered-controller.php';

class PNFW_REST_Unregister_Controller extends PNFW_REST_Registered_Controller {
	public function __construct() {
		parent::__construct('unregister');
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->route,
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'unregister_token'),
				'permission_callback' => '__return_true',
				'args' => $this->get_args()
			)
		);
	}

	public function unregister_token($request) {
		parent::rest_route_callback($request); // Oauth auth (if enabled)

		$user_id = PNFW_DB()->get_user_id($this->token, $this->os);

		$res = PNFW_DB()->delete_token($this->token, $this->os);

		if ($res === false) {
			$this->json_error('500', __('Unable to delete token', 'push-notifications-for-wp'));
		}

		$user = new WP_User($user_id);

		if (in_array(PNFW_Push_Notifications_for_WordPress_Lite::USER_ROLE, $user->roles) && empty($user->user_email)) {
			pnfw_log(PNFW_SYSTEM_LOG, sprintf(__("Automatically deleted the anonymous user %s (%s) since left without tokens.", 'push-notifications-for-wp'), $user->user_login, $user_id));
			if (is_multisite()) {
				if (is_user_member_of_blog($user_id)) {
					require_once(ABSPATH . 'wp-admin/includes/ms.php');
					wpmu_delete_user($user_id);
				}
			}
			else {
				require_once(ABSPATH . 'wp-admin/includes/user.php');
				wp_delete_user($user_id);
			}
		}

		return new WP_REST_Response(null, 200);
	}
}
