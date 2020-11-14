<?php
/**
 * Plugin Name: DLUSER
 * Plugin URI:
 * Description: A simple plugin for users management via WP REST API
 * Version: 1.0.0
 * Author: D. Lev. http://e-cv.dograshvili.com/
 * Author URI:
 * Text Domain:
 *
 *
 */


 /**
  * Base hook action for plugin
  */
add_action('init','dluser');


/*
 * Base hook action for api
 */
add_action('rest_api_init', function() {
	register_rest_route('dluser/', 'vlogin/', [
		'methods'  => 'POST',
        'callback' => 'handle_vlogin'
	]);
});


/**
 * @function dluser
 * 		base plugin function init
 */
function dluser() {
	// TODO: Store this info to db in a new version
	define('DL_TOKEN', '63457f23b072ad4c5b768b38273e668708f4b87a9cf76577167a8b0a00aa33ad');
	define('DL_MAX_VALID_TIME', 120);
}


/*
 * @function handle_vlogin
 * 		function to handle the vlogin action
 * @param $req MIXED the request to handle
 */
function handle_vlogin($req) {
	global $wpdb;
	$params = $req->get_params();
	$ret = ['success' => false, 'msg' => 'GEN_ERR', 'data' => []];
	try {
		if ($params['token'] === DL_TOKEN) {
			if (isset($params['id'])) {
				$UserID = intval($params['id']);
				$User = new WP_User($UserID);
				if ($User->exists()) {
					$results = $wpdb->get_results(sprintf(
						"SELECT * FROM %sfa_user_logins WHERE user_id = %s ORDER BY time_login DESC",
						$wpdb->prefix,
						$UserID
					), ARRAY_A);
					if (!empty($results[0])) {
						$record = $results[0];
						$blLoggedIn = null;
						if (in_array($record['login_status'], ['login'], true) && ($record['time_logout'] == '' || $record['time_logout'] == null)) {
							$lTime = new DateTime($record['time_login']);
							$nowTime = new DateTime();
							$lTime->setTimezone(new DateTimeZone('Europe/Athens'));
							$nowTime->setTimezone(new DateTimeZone('Europe/Athens'));
							$diffTime = $lTime->diff($nowTime);
							$minutes = ($diffTime->days * 24 * 60);
							$minutes += ($diffTime->h * 60);
							$minutes += $diffTime->i;
							$blLoggedIn = ($minutes <= DL_MAX_VALID_TIME);
						} else {
							$blLoggedIn = false;
						}
						$ret = [
							'success' => true,
							'msg' => '',
							'data' => [
								'loggedIn' => $blLoggedIn
							]
						];
						if ($blLoggedIn) {
							$user = new WP_User($UserID);
							$ret['data']['username'] = $user->data->user_login;
						}
					}
				} else {
					$ret['msg'] = 'USER_NOT_EXISTS';
				}
			} else {
				$ret['msg'] = 'INVALID_ID';
			}
		} else {
			$ret['msg'] = 'INVALID_TOKEN';
		}
	} catch (\Exception $e) {
		$ret = [
			'success' => false,
			'msg' => 'FATAL',
			'data' => [
				'fatal_msg' => $e->getMessage()
			]
		];
	}
	$response = new WP_REST_Response($ret);
	$response->set_status(200);
	return $response;
}
