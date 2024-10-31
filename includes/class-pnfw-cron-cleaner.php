<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

global $cron_cleaner; // we have to be explicit and declare that variable as global (see "A Note on Variable Scope" http://codex.wordpress.org/Function_Reference/register_activation_hook)
$cron_cleaner = new PNFW_Cron_Cleaner();

class PNFW_Cron_Cleaner {

	function __construct() {
		add_action('pnfw_cleaner_event', array($this, 'clean'));
	}

	public function run() {
		if (!$this->is_active()) {
			wp_schedule_event(time(), 'daily', 'pnfw_cleaner_event');
		}
	}

	public function stop() {
		if ($this->is_active()) {
			wp_clear_scheduled_hook('pnfw_cleaner_event');
		}
	}

	public function is_active() {
		return (bool)wp_next_scheduled('pnfw_cleaner_event');
	}

	public function next_scheduled() {
		return wp_next_scheduled('pnfw_cleaner_event');
	}

	public function clean() {
		global $wpdb;

		// Clean users without tokens
		pnfw_log(PNFW_SYSTEM_LOG, 'Starting users without tokens cleaner...');

		$users = $wpdb->get_blog_prefix() . 'users';
		$push_tokens = $wpdb->get_blog_prefix() . 'push_tokens';

		$user_ids = $wpdb->get_col("SELECT $users.ID FROM $users LEFT JOIN $push_tokens ON $users.ID = $push_tokens.user_id WHERE $push_tokens.user_id IS NULL");

		foreach ($user_ids as $user_id) {
			$user = get_user_by('ID', $user_id);

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
		}

		pnfw_log(PNFW_SYSTEM_LOG, 'End users without tokens cleaner.');

	}
}
