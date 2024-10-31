<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

abstract class PNFW_REST_Controller extends WP_REST_Controller {
	protected $namespace = 'pnfw/v1';
	protected $route = '';

	protected $token;
	protected $os;
	protected $url;
	protected $lang;

	public function __construct($route) {
		$this->route = $route;
		$this->url = home_url(rest_get_url_prefix() . '/' . $this->namespace . '/' . $this->route . '/');
	}

	protected function get_args() {
		$args = array(
			'token' => array(
				'required' => true,
				'sanitize_callback' => function($value, $request, $param) {
					return sanitize_text_field($value);
				}
			),
			'os' => array(
				'required' => true,
				'validate_callback' => function($param, $request, $key) {
					return in_array($param, array('iOS', 'Android', 'Safari', 'Web', 'Telegram'));
				},
				'sanitize_callback' => function($value, $request, $param) {
					return sanitize_text_field($value);
				}
			),
			'lang' => array(
				'required' => false,
				'sanitize_callback' => function($value, $request, $param) {
					return sanitize_text_field($value);
				}
			)
		);

		$oauth_enabled = get_option('pnfw_api_consumer_secret');
		if (isset($oauth_enabled) && strlen($oauth_enabled) > 0) {
			$args['oauth_consumer_key'] = array(
				'required' => true,
				'sanitize_callback' => function($value, $request, $param) {
					return sanitize_text_field($value);
				}
			);

			$args['oauth_timestamp'] = array(
				'required' => true,
				'sanitize_callback' => function($value, $request, $param) {
					return sanitize_text_field($value);
				}
			);

			$args['oauth_nonce'] = array(
				'required' => true,
				'sanitize_callback' => function($value, $request, $param) {
					return sanitize_text_field($value);
				}
			);

			$args['oauth_signature'] = array(
				'required' => true,
				'sanitize_callback' => function($value, $request, $param) {
					return sanitize_text_field($value);
				}
			);

			$args['oauth_signature_method'] = array(
				'required' => true,
				'validate_callback' => function($param, $request, $key) {
					return in_array($param, array('HMAC-SHA1', 'HMAC-SHA256'));
				},
				'sanitize_callback' => function($value, $request, $param) {
					return sanitize_text_field($value);
				}
			);
		}

		return $args;
	}

	protected function rest_route_callback($request) {
		$oauth_enabled = get_option('pnfw_api_consumer_secret');
		if (isset($oauth_enabled) && strlen($oauth_enabled) > 0) {
			$this->check_oauth_signature($request);
		}

		$this->os = $request->get_param('os');
		$this->token = $request->get_param('token');
		$this->lang = $request->get_param('lang');

		if (isset($this->lang)) {
			$this->lang = substr($this->lang, 0, 2); // FIXME deprecated, will be removed soon

			if (strlen($this->lang) != 2)
				$this->json_error('500', __('lang parameter invalid', 'push-notifications-for-wp'));

			pnfw_switch_lang($this->lang);
		}
	}

	function check_oauth_signature($request) {
		$http_method = $request->get_method();

		switch ($http_method) {
			case 'POST': $params = $request->get_body_params(); break;
			case 'GET': $params = $request->get_query_params(); break;
			default:
				$this->json_error('401', __('Invalid HTTP method', 'push-notifications-for-wp'));
		}

		$base_request_uri = rawurlencode($this->relaxed_url($this->url));

		// get the signature provided by the consumer and remove it from the parameters prior to checking the signature
		$consumer_signature = rawurldecode($params['oauth_signature']);
		unset($params['oauth_signature']);

		// normalize parameter key/values
		$params = $this->normalize_parameters($params);

		// sort parameters
		if (!uksort($params, 'strcmp')) {
			$this->json_error('401', __('Failed to sort parameters', 'push-notifications-for-wp'));
		}

		// form query string
		$query_params = array();
		foreach ($params as $param_key => $param_value) {
			$query_params[] = $param_key . '=' . $param_value; // join with equals sign
		}

		$query_string = rawurlencode(implode('&', $query_params)); // join with ampersand

		$string_to_sign = $http_method . '&' . $base_request_uri . '&' . $query_string;

		$hash_algorithm = strtolower(str_replace('HMAC-', '', $params['oauth_signature_method']));

		$api_consumer_secret = get_option('pnfw_api_consumer_secret');

		$key_parts = array($api_consumer_secret, '');
		$key = implode('&', $key_parts);

		$signature = base64_encode(hash_hmac($hash_algorithm, $string_to_sign, $key, true));

		if ($signature !== $consumer_signature) {
			$this->json_error('401', __('Provided signature does not match', 'push-notifications-for-wp'));
		}
	}

	function relaxed_url($url) {
		if (get_option('pnfw_api_oauth_relax')) {
			if (is_ssl()) {
				return str_replace('http://', 'https://', $url);
			}
			else {
				return str_replace('https://', 'http://', $url);
			}
		}
		else {
			return apply_filters('pnfw_relaxed_url', $url);
		}
	}

	function normalize_parameters($parameters) {
		$normalized_parameters = array();

		foreach ($parameters as $key => $value) {
			$key = rawurlencode(rawurldecode($key));
			$value = rawurlencode(rawurldecode($value));

			$normalized_parameters[$key] = $value;
		}

		return $normalized_parameters;
	}

	protected function json_error($error, $detail) {
		switch ($error) {
			case '401':
				header('HTTP/1.1 401 Unauthorized');
				$reason = __('Unauthorized', 'push-notifications-for-wp');
				break;

			case '404':
				header('HTTP/1.1 404 Not Found');
				$reason = __('Not Found', 'push-notifications-for-wp');
				break;

			default:
				header('HTTP/1.1 500 Internal Server Error');
				$reason = __('Internal Server Error', 'push-notifications-for-wp');
		}

		$response = array(
			'error' => $error,
			'reason' => $reason,
			'detail' => $detail
		);

		pnfw_log(PNFW_ALERT_LOG, sprintf(__('%s API Error (%s): %s, %s.', 'push-notifications-for-wp'), self::get_request_uri(), $error, $reason, $detail));

		echo json_encode($response);
		exit;
	}

	protected function current_user_id() {
		return self::get_user_id($this->token, $this->os);
	}

	protected function current_user_can_view_post($post_id) {
		$post_user_category = get_post_meta($post_id, 'pnfw_user_cat', true);

		if (empty($post_user_category)) {
			// All
			return true;
		}

		$user_id = $this->current_user_id();
		$user_categories = array();
		if ($this->is_current_user_anonymous()) {
			array_push($user_categories, 'anonymous-users');
		}
		else {
			array_push($user_categories, 'registered-users');
		}

		return in_array($post_user_category, $user_categories);
	}

	public static function get_user_id($token, $os) {
		return PNFW_DB()->get_user_id($token, $os);
	}

	protected function is_token_missing($token = null) {
		global $wpdb;
		if (is_null($token))
			$token = $this->token;

		$push_tokens = $wpdb->get_blog_prefix() . 'push_tokens';
		$res = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $push_tokens WHERE token=%s AND os=%s", $token, $this->os));

		return empty($res);
	}

	protected static function get_request_uri() {
		$res = '';

		if (isset($_SERVER['REQUEST_URI'])) {
			$res = $_SERVER['REQUEST_URI'];
		}

		return $res;
	}

	protected function get_last_modification_timestamp() {
		//return (int)get_option('pnfw_last_save_timestamp', time());
		return time(); // FIXME
	}
}
