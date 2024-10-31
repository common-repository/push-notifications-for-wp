<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

require_once dirname(__FILE__ ) . '/class-pnfw-notifications.php';

use DeliteStudio\Pushok\AuthProvider;
use DeliteStudio\Pushok\Client;
use DeliteStudio\Pushok\Notification;
use DeliteStudio\Pushok\Payload;
use DeliteStudio\Pushok\Payload\Alert;

class PNFW_Notifications_iOS extends PNFW_Notifications {

	public function __construct() {
		parent::__construct('iOS');
	}

	protected function raw_send($tokens, $title, $user_info) {
		// No devices, do nothing
		if (empty($tokens)) {
			return 0;
		}

		$production = true;

		if (get_option('pnfw_ios_use_sandbox')) {
			$production = false;
		}

		$key_id = get_option('pnfw_ios_key_id');

		if (empty($key_id)) {
			pnfw_log(PNFW_IOS_LOG, __('iOS Key ID is not correctly set.', 'push-notifications-for-wp'));
			return 0;
		}

		$team_id = get_option('pnfw_ios_team_id');

		if (empty($team_id)) {
			pnfw_log(PNFW_IOS_LOG, __('iOS Team ID is not correctly set.', 'push-notifications-for-wp'));
			return 0;
		}

		$private_key_content = get_option('pnfw_ios_private_key_content');

		if (empty($private_key_content)) {
			pnfw_log(PNFW_IOS_LOG, __('iOS private key is not correctly set.', 'push-notifications-for-wp'));
			return 0;
		}

		$app_bundle_id = get_option('pnfw_ios_bundle_id');

		if (empty($app_bundle_id)) {
			pnfw_log(PNFW_IOS_LOG, __('iOS bundle ID is not correctly set.', 'push-notifications-for-wp'));
			return 0;
		}

		$options = [
			'key_id' => $key_id,
			'team_id' => $team_id,
			'app_bundle_id' => $app_bundle_id,
			'private_key_content' => $private_key_content,
			'private_key_secret' => null
		];

		try {
			// get the auth token and if expired create a new one
			$auth_token = get_transient('pnfw_ios_auth_token');
			if (false === $auth_token) {
				$auth_token = AuthProvider\Token::create($options)->get();
				set_transient('pnfw_ios_auth_token', $auth_token, 3000);
			}
			$authProvider = AuthProvider\Token::useExisting($auth_token, $options);

			$client = new Client($authProvider, $production, [CURLOPT_CAPATH => dirname(__FILE__ ) . '/../../assets/certs']);

			foreach ($tokens as $deviceToken) {
				$payload = Payload::create()
					->setAlert($title)
					->setSound(get_option('pnfw_ios_payload_sound', 'default'))
					->setBadge($this->get_badge_count($deviceToken));

				// add custom value to your notification, needs to be customized
				foreach (array_keys($user_info) as $key) {
					$payload->setCustomValue($key, strval($user_info[$key]));
				}

				$client->addNotification(new Notification($payload, $deviceToken));
			}

			$responses = $client->push(); // returns an array of ApnsResponseInterface (one Response per Notification)

			$sent = 0;

			foreach ($responses as $response) {
				switch ($response->getStatusCode()) {
					// if success increment sent counter
					case 200:
						$this->notification_sent($response->getDeviceToken());
						$sent += 1;
						break;

					// if device is not active remove it from table
					case 410:
						$this->delete_token($response->getDeviceToken());
						break;

					// else not recoverable errors, ignore
					default:
						$this->log_apns2_error($response->getErrorReason(), $response->getErrorDescription());
						break;
				}
			}
		}
		catch (Exception $e) {
			pnfw_log(PNFW_IOS_LOG, strip_tags($e->getMessage()));
		}

		return $sent;
	}

	protected function get_badge_count($token) {
		return 1;
	}

	private function log_apns2_error($error_code, $error_message) {
		pnfw_log(PNFW_IOS_LOG, sprintf(__('Could not send message (%s): %s', 'push-notifications-for-wp'), $error_code, $error_message));
	}

}
