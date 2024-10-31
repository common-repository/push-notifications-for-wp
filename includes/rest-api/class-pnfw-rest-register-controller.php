<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

require_once dirname(__FILE__ ) . '/class-pnfw-rest-controller.php';

class PNFW_REST_Register_Controller extends PNFW_REST_Controller {
	protected $email;
	protected $activation_code;

	public function __construct() {
		parent::__construct('register');
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->route,
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'register_token'),
				'permission_callback' => '__return_true',
				'args' => $this->get_args()
			)
		);
	}

	public function register_token($request) {
		parent::rest_route_callback($request); // Oauth auth (if enabled)

		add_filter('wp_mail_from_name', array($this, 'from_name'));

		// Optional
		$prevToken = $request->get_param('prevToken');
		$this->email = $request->get_param('email');


		$this->activation_code = $this->get_activation_code();

		global $wpdb;
		$push_tokens = $wpdb->get_blog_prefix().'push_tokens';
		$push_viewed = $wpdb->get_blog_prefix().'push_viewed';
		$push_sent = $wpdb->get_blog_prefix().'push_sent';
		$push_excluded_categories = $wpdb->get_blog_prefix().'push_excluded_categories';

		if (isset($prevToken)) {
			// Check if registered
			if ($this->is_token_missing($prevToken))
				$this->json_error('404', __('prevToken not found', 'push-notifications-for-wp'));

			if ($prevToken == $this->token) {
				return new WP_REST_Response(null, 200); // nothing to do
			}

			$data = array(
				'token' => $this->token,
				'os' => $this->os,
				'lang' => $this->lang
			);

			$where = array(
				'token' => $prevToken,
				'os' => $this->os
			);

			$count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $push_tokens WHERE token = %s AND os = %s", $this->token, $this->os));

			if ($count != 0) {
				pnfw_log(PNFW_SYSTEM_LOG, sprintf(__('Attempted an update of an %s token equal to a token already present: %s.', 'push-notifications-for-wp'), $this->os, $this->token));

				// Delete destination token to allow overwrite
				PNFW_DB()->delete_token($this->token, $this->os);
			}

			// Update prevToken with new token
			$wpdb->update($push_tokens, $data, $where);
		}
		else {
			$user_id = email_exists($this->email);

			// If the user does not exist it is created, otherwise the role app_subscriber is added
			if (empty($user_id) || is_null($user_id)) {
				$current_user = wp_get_current_user();

				if (0 == $current_user->ID) {
					// Not logged in.
					$user_id = $this->create_user($this->email);

					if (is_wp_error($user_id)) {
						$this->json_error('500', $user_id->get_error_message());
					}
				}
				else {
					// Logged in.
					$current_user->add_role(PNFW_Push_Notifications_for_WordPress_Lite::USER_ROLE);
					$user_id = $current_user->ID;
				}
			}
			else {
				$user = new WP_User($user_id);

				$user->add_role(PNFW_Push_Notifications_for_WordPress_Lite::USER_ROLE);
			}


			// Following code should not be accessed simultaneously by different threads
			$push_logs = $wpdb->get_blog_prefix().'push_logs';
			$wp_options = $wpdb->options;
			$wpdb->query("LOCK TABLES $push_tokens WRITE, $push_viewed WRITE, $push_sent WRITE, $push_excluded_categories WRITE, $push_logs WRITE, $wp_options READ;");

			$active = empty($this->email);

			if (get_option('pnfw_disable_email_verification') == true) {
				$active = true;
			}

			// If the device does not exist it is created, otherwise the user_id is updated
			if ($this->is_token_missing()) {
				$data = array(
					'token' => $this->token,
					'os' => $this->os,
					'lang' => $this->lang,
					'timestamp' => current_time('mysql'),
					'user_id' => $user_id,
					'active' => $active,
					'activation_code' => $this->activation_code
				);
				$wpdb->insert($push_tokens, $data);

				$wpdb->query("UNLOCK TABLES;");

				if (!$active) {
					$this->new_device_email($user_id, $activation_link);
				}
			}
			else {
				if ($this->reassign_token_to($user_id) && !$active) {
					$this->new_device_email($user_id, $activation_link);
				}
			}

		}

		return new WP_REST_Response(null, 200);
	}

	protected function get_args() {
		$args = parent::get_args();

		$args['prevToken'] = array(
			'required' => false,
			'sanitize_callback' => function($value, $request, $param) {
				return sanitize_text_field($value);
			}
		);

		$args['email'] = array(
			'required' => false,
			'validate_callback' => function($param, $request, $key) {
				return filter_var($param, FILTER_VALIDATE_EMAIL);
			},
			'sanitize_callback' => function($value, $request, $param) {
				return sanitize_text_field($value);
			}
		);

		$args['userCategory'] = array(
			'required' => false,
			'validate_callback' => function($param, $request, $key) {
				return is_numeric($param);
			},
			'sanitize_callback' => function($value, $request, $param) {
				return abs(intval($value));
			}
		);

		// Custom parameters
		$custom_parameters = apply_filters('pnfw_register_custom_parameters', array());

		foreach ($custom_parameters as $custom_parameter) {
			$name = $custom_parameter['name'];

			$args[$name] = array(
				'required' => $custom_parameter['required'],
				'validate_callback' => $custom_parameter['validate']
			);
		}

		return $args;
	}

	private function from_name() {
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

		return $blogname;
	}


	private function get_activation_code() {
		if (empty($this->email))
			return null;
		else
			return sha1($this->email.'&'.$this->token.'&'.time());
	}

	private function reassign_token_to($user_id) {
		global $wpdb;
		$push_tokens = $wpdb->get_blog_prefix().'push_tokens';
		$push_viewed = $wpdb->get_blog_prefix().'push_viewed';
		$push_sent = $wpdb->get_blog_prefix().'push_sent';
		$push_excluded_categories = $wpdb->get_blog_prefix().'push_excluded_categories';

		$old_user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $push_tokens WHERE token=%s AND os=%s", $this->token, $this->os));

		if ($old_user_id == $user_id) {
			$wpdb->query("UNLOCK TABLES;");

			return false; // reassign the tokens to the same user
		}

		pnfw_log(PNFW_SYSTEM_LOG, sprintf(__("Reassign %s token %s from user %s to user %s.", 'push-notifications-for-wp'), $this->os, $this->token, $old_user_id, $user_id));

		$active = empty($this->email);

		if (get_option('pnfw_disable_email_verification') == true) {
			$active = true;
		}

		$wpdb->update(
			$push_tokens,
			array(
				'lang' => $this->lang,
				'user_id' => $user_id,
				'active' => $active,
				'activation_code' => $this->activation_code
			),
			array(
				'token' => $this->token,
				'os' => $this->os
			)
		);

		if ($this->user_without_tokens($old_user_id)) {
			$wpdb->update($push_viewed,
				array('user_id' => $user_id),
				array('user_id' => $old_user_id)
			);

			$wpdb->update($push_sent,
				array('user_id' => $user_id),
				array('user_id' => $old_user_id)
			);

			$wpdb->update($push_excluded_categories,
				array('user_id' => $user_id),
				array('user_id' => $old_user_id)
			);

			$wpdb->query("UNLOCK TABLES;");
			$old_user = new WP_User($old_user_id);

			if (in_array(PNFW_Push_Notifications_for_WordPress_Lite::USER_ROLE, $old_user->roles) && empty($old_user->user_email)) {
				pnfw_log(PNFW_SYSTEM_LOG, sprintf(__("Automatically deleted the anonymous user %s (%s) since left without tokens.", 'push-notifications-for-wp'), $old_user->user_login, $old_user_id));

				if (is_multisite()) {
					if (is_user_member_of_blog($old_user_id)) {
						require_once(ABSPATH . 'wp-admin/includes/ms.php');
						wpmu_delete_user($old_user_id);
					}
				}
				else {
					require_once(ABSPATH . 'wp-admin/includes/user.php');
					wp_delete_user($old_user_id);
				}
			}
		}
		else {
			$wpdb->query("UNLOCK TABLES;");
		}

		return true;
	}

	private function user_without_tokens($user_id) {
		global $wpdb;
		$push_tokens = $wpdb->get_blog_prefix().'push_tokens';
		$count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $push_tokens WHERE user_id=%d", $user_id));

		return $count == 0;
	}

	private function create_user($email) {
		if (isset($email)) {
			$user_login = $email;
			$user_email = $email;
			$user_pass = wp_generate_password(10, false, false);
		}
		else {
			// Anonymous user
			$user_login = $this->create_unique_user_login();
			$user_email = NULL;
			$user_pass = NULL;
		}

		$user_id = wp_insert_user(array(
			'user_login' => $user_login,
			'user_email' => $user_email,
			'user_pass' => $user_pass,
			'role' => PNFW_Push_Notifications_for_WordPress_Lite::USER_ROLE
		));

		return $user_id;
	}

	private function create_unique_user_login() {
		$rand = wp_rand();

		$tmp_user_login = 'anonymous' . $rand;

		if (username_exists($tmp_user_login)) {
			return $this->create_unique_user_login();
		}
		else {
			return $tmp_user_login;
		}
	}

	private function new_device_email($user_id) {
		$user = get_userdata($user_id);
		if ($user === false)
			return;

		// The blogname option is escaped with esc_html on the way into the database in sanitize_option
		// we want to reverse this for the plain text arena of emails.
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

		$message  = sprintf(__('Thank you for joining %s!', 'push-notifications-for-wp'), $blogname) . "\r\n\r\n";

		$message .= __('To confirm your registration, please click on the following link:', 'push-notifications-for-wp') . "\r\n\r\n";
		$message .= esc_url(add_query_arg(array('activation_code' => $this->activation_code), get_permalink(PNFW_ACTIVATE_PAGE_ID)));

		wp_mail($user->user_email, sprintf(__('Welcome to %s', 'push-notifications-for-wp'), $blogname), $message);
	}

}
