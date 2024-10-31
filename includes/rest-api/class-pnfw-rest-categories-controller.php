<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

require_once dirname(__FILE__ ) . '/class-pnfw-rest-registered-controller.php';

class PNFW_REST_Categories_Controller extends PNFW_REST_Registered_Controller {
	private $category_id;

	public function __construct() {
		parent::__construct('categories');
	}

	public function register_routes() {
		$is_creatable = false;
		register_rest_route(
			$this->namespace,
			$this->route,
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'get_categories'),
				'permission_callback' => '__return_true',
				'args' => $this->get_categories_args($is_creatable)
			)
		);

		$is_creatable = true;
		register_rest_route(
			$this->namespace,
			$this->route,
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'exclude_category'),
				'permission_callback' => '__return_true',
				'args' => $this->get_categories_args($is_creatable)
			)
		);
	}

	public function get_categories($request) {
		parent::rest_route_callback($request); // Oauth auth (if enabled)

		$timestamp = $request->get_param('timestamp');
		if ($timestamp == $this->get_last_modification_timestamp())
			return new WP_REST_Response(null, 304);

		$object_taxonomies = get_option('pnfw_enabled_object_taxonomies', array());
		if (empty($object_taxonomies)) {
			$this->json_error('404', __('Categories Filterable by App Subscribers not set', 'push-notifications-for-wp'));
		}

		$raw_terms = get_terms($object_taxonomies, array('hide_empty' => false));
		$categories = array();

		foreach ($raw_terms as $raw_term) {
			$category = array(
				'id' => (int)$raw_term->term_id,
				'name' => $raw_term->name,
				'parent' => $raw_term->parent
			);

			$description = $raw_term->description;
			if (!empty($description))
				$category['description'] = $description;

			$category['exclude'] = $this->isCategoryExcluded(pnfw_get_normalized_term_id((int)$raw_term->term_id));
			$categories[] = $category;
		}

		return new WP_REST_Response(array('categories' => $categories, 'timestamp' => $this->get_last_modification_timestamp()), 200);
	}

	public function exclude_category($request) {
		parent::rest_route_callback($request); // Oauth auth (if enabled)

		$timestamp = $request->get_param('timestamp');
		if ($timestamp == $this->get_last_modification_timestamp())
			return new WP_REST_Response(null, 304);

		$this->category_id = pnfw_get_normalized_term_id($request->get_param('id'));

		$excluded = $request->get_param('exclude');

		$this->setCategoryExcluded($excluded);

		return new WP_REST_Response(null, 200);
	}

	protected function get_categories_args($is_creatable) {
		$args = $this->get_args();

		$args['timestamp'] = array(
			'required' => false,
			'validate_callback' => function($param, $request, $key) {
				return is_numeric($param);
			},
			'sanitize_callback' => function($value, $request, $param) {
				return abs(intval($value));
			}
		);

		if ($is_creatable) {
			$args['id'] = array(
				'required' => true,
				'validate_callback' => function($param, $request, $key) {
					return is_numeric($param);
				},
				'sanitize_callback' => function($value, $request, $param) {
					return abs(intval($value));
				}
			);

			$args['exclude'] = array(
				'required' => true,
				'sanitize_callback' => function($value, $request, $param) {
					return rest_sanitize_boolean($value);
				}
			);
		}

		return $args;
	}

	private function isCategoryExcluded($category_id = null) {
		return self::is_category_excluded($this->current_user_id(), $category_id === null ? $this->category_id : $category_id);
	}

	private function setCategoryExcluded($excluded) {
		self::set_category_excluded($this->current_user_id(), $this->category_id, $excluded);
	}

	private function is_category_excluded($user_id, $category_id) {
		global $wpdb;
		$push_excluded_categories = $wpdb->get_blog_prefix() . 'push_excluded_categories';
		return (boolean)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $push_excluded_categories WHERE category_id=%d AND user_id=%d", $category_id, $user_id));
	}

	public static function set_category_excluded($user_id, $category_id, $excluded) {
		global $wpdb;
		$push_excluded_categories = $wpdb->get_blog_prefix() . 'push_excluded_categories';
		if ($excluded) {
			$wpdb->replace($push_excluded_categories, array('category_id' => $category_id, 'user_id' => $user_id));
		}
		else {
			$wpdb->delete($push_excluded_categories, array('category_id' => $category_id, 'user_id' => $user_id));
		}
	}
}
