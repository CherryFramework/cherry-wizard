<?php
/**
 * Add cherry theme install helper class
 * Activate license if premium install. Save theme name and key for demo install
 *
 * @package   cherry_wizard
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( !class_exists( 'cherry_wizard_helper' ) ) {

	/**
	 * Define helper class
	 *
	 * @since 1.0.0
	 */
	class cherry_wizard_helper {

		/**
		 * Key server URL
		 *
		 * @since  1.0.0
		 * @var    string
		 */
		public $key_server = 'https://cloud.cherryframework.com/';

		/**
		 * include necessary files. Run actions
		 */
		function __construct() {

			// check and activate license key
			add_action( 'wp_ajax_cherry_wizard_validate_key', array( $this, 'check_license' ) );
			// Save user mail for demo installation
			add_action( 'wp_ajax_cherry_wizard_start_demo_install', array( $this, 'start_demo_install' ) );

		}

		/**
		 * Check and activate license key
		 *
		 * @since 1.0.0
		 */
		public function check_license() {

			//make sure request is comming from Ajax
			check_ajax_referer( 'cherry_wizard', 'nonce' );

			if ( !session_id() ) {
				session_start();
			}

			global $cherry_wizard;

			$cherry_wizard->cherry_theme_name = isset($_REQUEST['theme']) ? esc_attr( $_REQUEST['theme'] ) : false;
			$cherry_wizard->cherry_key        = isset($_REQUEST['key']) ? esc_attr( $_REQUEST['key'] ) : false;

			if ( !$cherry_wizard->cherry_theme_name || !$cherry_wizard->cherry_key ) {

				$key_class   = empty($cherry_wizard->cherry_key) ? 'error' : 'success';
				$theme_class = empty($cherry_wizard->cherry_theme_name) ? 'error' : 'success';

				$this->send_license_response( array(
					'type'        => 'error',
					'theme_class' => $theme_class,
					'key_class'   => $key_class,
					'message'     => __( 'Please, fill required fields', $cherry_wizard->slug )
				) );
			}

			$request_uri = add_query_arg( array( 'edd_action' => 'activate_license', 'item_name' => urlencode( $cherry_wizard->cherry_theme_name ), 'license' => $cherry_wizard->cherry_key ), $this->key_server );

			$key_request = wp_remote_get( $request_uri );

			// Can't send request
			if ( is_wp_error( $key_request ) || ! isset($key_request['response']) ) {
				$this->send_license_response( array(
					'type'        => 'error',
					'message'     => __( 'Can not send activation request. ' . $key_request->get_error_message(), $cherry_wizard->slug )
				) );
			}

			if ( 200 != $key_request['response']['code'] ) {
				$this->send_license_response( array(
					'type'        => 'error',
					'message'     => __( 'Activation request error. ' . $key_request['response']['code'] . ' - ' . $key_request['response']['message'] . '. Please, try again later', $cherry_wizard->slug )
				) );
			}

			$response = json_decode( $key_request['body'] );

			// Request generate unexpected result
			if ( ! is_object( $response ) || !isset( $response->success ) ) {
				$this->send_license_response( array(
					'type'        => 'error',
					'message'     => __( 'Bad request.', $cherry_wizard->slug )
				) );
			}

			// Requested license key is missing
			if ( ! $response->success && 'missing' == $response->error ) {
				$this->send_license_response( array(
					'type'        => 'error',
					'theme_class' => 'success',
					'key_class'   => 'error',
					'message'     => __( 'Wrong license key. Make sure activation key is correct.', $cherry_wizard->slug )
				) );
			}

			// Theme name incorrect
			if ( ! $response->success && 'item_name_mismatch' == $response->error ) {
				$this->send_license_response( array(
					'type'        => 'error',
					'theme_class' => 'error',
					'key_class'   => 'success',
					'message'     => __( 'Invalid theme name. Make sure theme name is correct.', $cherry_wizard->slug )
				) );
			}

			// Unknown error
			if ( ! $response->success && $response->error ) {
				$this->send_license_response( array(
					'type'        => 'error',
					'theme_class' => 'success',
					'key_class'   => 'error',
					'message'     => $response->error
				) );
			}

			// Can not get the,e information from TM
			if ( empty( $response->tm_data->status ) || 'request failed' == $response->tm_data->status ) {
				$this->send_license_response( array(
					'type'        => 'error',
					'theme_class' => 'success',
					'key_class'   => 'success',
					'message'     => __( 'Something is wrong. Please contact support team for help.', $cherry_wizard->slug )
				) );
			}

			// Theme currently in queue
			if ( 'queue' == $response->tm_data->status ) {
				$this->send_license_response( array(
					'type'        => 'error',
					'theme_class' => 'success',
					'key_class'   => 'success',
					'message'     => __( 'Theme is not available yet. Please try again in 10 minutes.', $cherry_wizard->slug )
				) );
			}

			// Theme currently removed from cloud
			if ( 'failed' == $response->tm_data->status ) {
				$this->send_license_response( array(
					'type'        => 'error',
					'theme_class' => 'success',
					'key_class'   => 'success',
					'message'     => __( 'Theme is not available. Please contact support team for help.', $cherry_wizard->slug )
				) );
			}

			$cherry_keys = get_option( 'cherry_keys' );

			$cherry_keys[$cherry_wizard->cherry_theme_name] = $cherry_wizard->cherry_key;

			update_option( 'cherry_keys', $cherry_keys );

			set_transient( 'cherry_theme_name', $cherry_wizard->cherry_theme_name, WEEK_IN_SECONDS );
			set_transient( 'cherry_key', $cherry_wizard->cherry_key, WEEK_IN_SECONDS );

			$_SESSION['cherry_data'] = array(
				'theme'  => $response->tm_data->theme,
				'sample' => $response->tm_data->sample_data,
			);

			$this->send_license_response( array(
					'message' => __( 'Everything is ready. Starting theme installation...', $cherry_wizard->slug )
				) );

		}

		/**
		 * Save user mail for demo installation
		 *
		 * @since 1.0.0
		 */
		function start_demo_install() {

			//make sure request is comming from AJAX
			$xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
			if (!$xhr) {
				header('HTTP/1.1 500 Error: Expecting AJAX request!');
				exit();
			}

			global $cherry_wizard;

			if ( !session_id() ) {
				session_start();
			}

			$result = array(
				'type'     => 'error',
				'message'  => __( 'Unknown error. Please try again or contact support for help.', $cherry_wizard->slug )
			);

			if ( !isset( $_REQUEST['mail'] ) || empty( $_REQUEST['mail'] ) ) {
				$result['message'] = __( 'Please enter your email address', $cherry_wizard->slug );
				wp_send_json( $result );
			}

			if ( !is_email( $_REQUEST['mail'] ) ) {
				$result['message'] = __( 'Please enter valid email address', $cherry_wizard->slug );
				wp_send_json( $result );
			}

			// save mail to database to check it later
			set_transient( 'cherry_wizard_demo_mail', $_REQUEST['mail'], WEEK_IN_SECONDS );
			// send mail to Cherry Mailchimp account
			$request_url = add_query_arg( array( 'demo-mail' => $_REQUEST['mail'] ), $this->key_server );
			$request     = wp_remote_request( $request_url );

			set_transient( 'cherry_theme_name', 'cherryone', WEEK_IN_SECONDS );
			set_transient( 'cherry_key', 'demo', WEEK_IN_SECONDS );

			$result['type']     = 'success';
			$result['message']  = __( 'Thank you. Starting installation...', $cherry_wizard->slug );

			$_SESSION['cherry_data'] = array(
				'theme'  => 'https://github.com/CherryFramework/cherryone/archive/master.zip',
				'sample' => 'http://cloud.cherryframework.com/demo/sample_data.zip',
			);

			wp_send_json( $result );

		}

		/**
		 * send AJAX license response with selected parameters via JSON
		 *
		 * @since  1.0.0
		 * @param  array  $result  array of arguments to send via JSON
		 */
		public function send_license_response( $result = array() ) {

			$default_result = array(
				'type'        => 'success',
				'theme_class' => 'success',
				'key_class'   => 'success',
				'message'     => ''
			);

			$result = wp_parse_args( $result, $default_result );

			wp_send_json( $result );
		}

	}

	global $cherry_wizard;
	$cherry_wizard->helper = new cherry_wizard_helper();

}