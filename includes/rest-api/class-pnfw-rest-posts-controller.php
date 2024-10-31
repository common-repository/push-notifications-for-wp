<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

require_once dirname(__FILE__ ) . '/class-pnfw-rest-activated-controller.php';

class PNFW_REST_Posts_Controller extends PNFW_REST_Activated_Controller {
	private $post_id;
	private $post;

	public function __construct() {
		parent::__construct('posts');
	}

	public function register_routes() {
		$is_creatable = false;
		register_rest_route(
			$this->namespace,
			$this->route,
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'get_posts'),
				'permission_callback' => function($request) {
					if ($request->has_param('id')) {
						$id = $request->get_param('id');

						if (!$this->current_user_can_view_post($id)) {
							return false;
						}
					}

					return true;
				},
				'args' => $this->get_posts_args($is_creatable)
			)
		);

		$is_creatable = true;
		register_rest_route(
			$this->namespace,
			$this->route,
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'post_viewed'),
				'permission_callback' => '__return_true',
				'args' => $this->get_posts_args($is_creatable)
			)
		);
	}

	public function get_posts($request) {
		parent::rest_route_callback($request);

		// Optional
		$this->post_id = $request->get_param('id');

		$timestamp = $request->get_param('timestamp');
		if ($timestamp == $this->get_last_modification_timestamp())
			$this->header_error('304');

		if (isset($this->post_id)) {
			global $wp_embed;

			$post_date = new DateTime($this->post->post_date);

			$content = $wp_embed->run_shortcode($this->post->post_content); // process the [embed] shortcode (which needs to be run earlier than other shortcodes)
			$content = (bool)get_option('pnfw_use_wpautop') ? wpautop(do_shortcode($content)) : do_shortcode($content);

			$response = array(
				'id' => $this->post->ID,
				'title' => $this->post->post_title,
				'content' => $content,
				'categories' => $this->get_categories(),
				'date' => $post_date->getTimestamp(),
				'author' => $this->get_author(),
			);

			// Optional fields
			$image = $this->get_image();
			if (!is_null($image))
				$response['image'] = $image;

			if (!$this->is_viewed())
				$this->set_viewed();
			return new WP_REST_Response($response, 200);
		}
		else {
			$raw_posts = array();
			if (get_option('pnfw_enabled_post_types')) {
				$raw_posts = get_posts(
					array(
						'posts_per_page' => get_option('pnfw_posts_per_page'),
						'post_type' => get_option('pnfw_enabled_post_types'),
						'suppress_filters' => pnfw_suppress_filters()
					)
				);
			}
			$posts = array();

			foreach ($raw_posts as $raw_post) {
				if ($this->current_user_can_view_post($raw_post->ID)) {
					$post_date = new DateTime($raw_post->post_date);

					// Mandatory fields
					$post = array(
						'id' => $raw_post->ID,
						'title' => $raw_post->post_title,
						'date' => $post_date->getTimestamp(),
					);

					// Optional fields
					$thumbnail = $this->get_thumbnail($raw_post->ID);
					if (!is_null($thumbnail))
						$post['thumbnail'] = $thumbnail;

					if (!$this->is_read($raw_post->ID))
						$post['read'] = false;
					$posts[] = $post;
				}
			}

			return new WP_REST_Response(array('posts' => $posts, 'timestamp' => $this->get_last_modification_timestamp()), 200);
		}
	}

	public function post_viewed($request) {
		parent::rest_route_callback($request);

		$this->post_id = $request->get_param('id');
		$viewed = $request->get_param('viewed');

		$this->set_viewed($viewed);

		return new WP_REST_Response(null, 200);
	}

	protected function get_posts_args($is_creatable) {
		$args = $this->get_args();

		$args['id'] = array(
			'required' => $is_creatable, // Only if is the creatable API is required.
			'validate_callback' => function($param, $request, $key) {
				if (!is_numeric($param))
					return false;

				$this->post = get_post($param);
				if ($this->post == null || $this->post->post_status != 'publish') {
					return false;
				}

				return true;
			},
			'sanitize_callback' => function($value, $request, $param) {
				return abs(intval($value));
			}
		);

		if ($is_creatable) {
			$args['viewed'] = array(
				'required' => true,
				'sanitize_callback' => function($value, $request, $param) {
					return rest_sanitize_boolean($value);
				}
			);
		}

		return $args;
	}

	public function is_viewed($post_id = null) {
		if (is_null($post_id))
			$post_id = $this->post_id;

		return PNFW_DB()->is_viewed($post_id, $this->current_user_id());
	}

	public function set_viewed($viewed = true) {
		PNFW_DB()->set_viewed($this->post_id, $this->current_user_id(), $viewed);
	}

	public function is_read($post_id = null) {
		if (is_null($post_id))
			$post_id = $this->post_id;

		if (PNFW_DB()->is_sent($post_id, $this->current_user_id())) {
			return $this->is_viewed($post_id);
		}
		else {
			return true;
		}
	}

	private function get_categories($post = null) {
		if (is_null($post)) {
			$post = $this->post;
		}

		$taxonomies = array_intersect(get_object_taxonomies($post), get_option('pnfw_enabled_object_taxonomies', array()));
		$terms = empty($taxonomies) ? false : get_the_terms($post->ID, reset($taxonomies));

		$categories = array();

		if ($terms) {
			foreach ($terms as $term) {
				// Mandatory fields
				$category = array(
					'id' => $term->term_id,
					'name' => $term->name
				);

				$categories[] = $category;
			}
		}

		return $categories;
	}

	private function get_author($post = null) {
		if (is_null($post)) {
			$post = $this->post;
		}

		$user = get_userdata($post->post_author);
		return $user ? $user->display_name : __('Anonymous', 'push-notifications-for-wp');
	}

	private function get_image($post_id = null) {
		if (is_null($post_id)) {
			$post_id = $this->post_id;
		}

		if (has_post_thumbnail($post_id)) {
			$thumbnail_id = get_post_thumbnail_id($post_id);

			$array = wp_get_attachment_image_src($thumbnail_id, 'single-post-thumbnail');
			$url_thumbnail = $array[0];

			return $url_thumbnail;
		}
		else {
			return null;
		}
	}

	private function get_thumbnail($post_id = null) {
		if (is_null($post_id)) {
			$post_id = $this->post_id;
		}

		if (has_post_thumbnail($post_id)) {
			$thumbnail_id = get_post_thumbnail_id($post_id);

			$array = wp_get_attachment_image_src($thumbnail_id);
			$url_thumbnail = $array[0];

			return $url_thumbnail;
		}
		else {
			return null;
		}
	}
}
