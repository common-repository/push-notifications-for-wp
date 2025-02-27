<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

global $sender_manager; // we have to be explicit and declare that variable as global (see "A Note on Variable Scope" http://codex.wordpress.org/Function_Reference/register_activation_hook)
$sender_manager = new PNFW_Sender_Manager();

class PNFW_Sender_Manager {

	protected $process_all;

	public function __construct() {
		add_action('plugins_loaded', array($this, 'init'));
		add_action('transition_post_status', array($this, 'notify_new_custom_post'), 10, 3);
	}

	public function init() {
		require_once dirname(__FILE__) . '/class-pnfw-sender-process.php';
		$this->process_all = new PNFW_Sender_Process();
	}

	function notify_new_custom_post($new_status, $old_status, $post) {
		if( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$enable_push_notifications = get_option('pnfw_enable_push_notifications');

		if (!$enable_push_notifications) {
			return; // nothing to do
		}

		$custom_post_types = get_option('pnfw_enabled_post_types', array());

		if (!$custom_post_types || !in_array($post->post_type, $custom_post_types)) {
			return; // nothing to do
		}

		if ($old_status === 'trash' && $new_status === 'publish') {
			return;
		}

		if (empty($post) || $new_status !== 'publish') {
			return;
		}

		$posted = !empty($_POST);

		$post_meta_value = get_post_meta($post->ID, 'pnfw_do_send_push_notifications_for_this_post', true);
		$post_request_value = isset($_POST['pnfw_do_send_push_notifications_for_this_post']) && $_POST['pnfw_do_send_push_notifications_for_this_post'] === '1';

		$send = ($posted && $post_request_value) || (!$posted && $post_meta_value);


		if (!$send) {
			pnfw_log(PNFW_SYSTEM_LOG, sprintf(__('Notifications for the %s %s deliberately not sent', 'push-notifications-for-wp'), $post->post_type, $post->post_title));
			return; // nothing to do
		}
		else {
			delete_post_meta($post->ID, 'pnfw_do_send_push_notifications_for_this_post');

			if ($posted) {
				if (array_key_exists('pnfw_do_send_push_notifications_for_this_post', $_POST)) {
					unset($_POST['pnfw_do_send_push_notifications_for_this_post']);
				}
			}
		}

		$this->process_all->data(array(array('post' => $post->ID, 'os' => 'iOS', 'offset' => 0, 'is_first' => true)))->save()->dispatch();
	}


}
