<?php
/**
 * Add admin notices, warnings and checks
 *
 * @package   cherry_wizard
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( !class_exists( 'cherry_wizard_notices' ) ) {

	/**
	 * Add admin notice
	 *
	 * @since 1.0.0
	 */
	class cherry_wizard_notices {

		/**
		 * Cherry wizard dismissed notice meta fiels name
		 *
		 * @since 1.0.0
		 * @var string
		 */
		public $dismissed_notice = 'cherry_wizard_dismissed_notice';
		
		function __construct() {
			add_action( 'admin_head', array( $this, 'dismiss' ) );
			add_action( 'admin_notices', array( $this, 'show_notice' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'thickbox' ) );
			add_action( 'switch_theme', array( $this, 'update_dismiss' ) );
			add_action( 'init', array( $this, 'check_permissions' ) );
			add_action( 'init', array( $this, 'check_server_config' ), 15 );
			add_action( 'cherry_wizard_install_notices', array( $this, 'show_install_notice' ) );
		}

		/**
		 * Check if current user alredy dissmissed current notice
		 * 
		 * @since 1.0.0
		 */
		public function notice_dismissed() {
			return get_user_meta( get_current_user_id(), $this->dismissed_notice, true );
		}

		/**
		 * Enqueues thickbox scripts/styles for plugin info.
		 *
		 * Thickbox is not automatically included on all admin pages, so we must
		 * manually enqueue it for those pages.
		 *
		 * Thickbox is only loaded if the user has not dismissed the admin
		 * notice
		 *
		 * @since 1.0.0
		 */
		public function thickbox() {

			if ( $this->notice_dismissed() ) {
				return;
			}
			add_thickbox();
		}

		/**
		 * dismiss installation notice
		 * 
		 * @since 1.0.0
		 */
		public function dismiss() {
			if ( isset( $_GET[sanitize_key( 'cherry_wizard_dismiss' )] ) ) {
				update_user_meta( get_current_user_id(), $this->dismissed_notice, 1 );
			}
		}

		/**
		 * delete dismiss user meta on theme switch 
		 * 
		 * @since 1.0.0
		 */
		public function update_dismiss() {

			delete_user_meta( get_current_user_id(), $this->dismissed_notice );

		}

		/**
		 * Show installation start notice
		 *
		 * @since 1.0.0
		 */
		public function show_notice() {

			// check if installation needed before do anything
			$need_install = get_option( 'cherry_wizard_need_install' );
			if ( ! $need_install ) {
				return;
			}

			global $current_screen, $cherry_wizard;

			// break function if already dismissed
			if ( $this->notice_dismissed() ) {
				return;
			}

			// show nothing on wizard page
			if ( $cherry_wizard->is_wizard_page() ) {
				return;
			}

			echo '<div class="' . $cherry_wizard->ui_wrapper_class( array( 'updated' ) ) . '">';
				echo '<div class="wizard-admin-notice_"><div class="wizard-admin-notice-content_">';
					echo '<strong>' . __( 'Wizard will help you to install your Cherry Framework theme.', $cherry_wizard->slug ) . '</strong>';
					echo '<div class="wizar-notice-actions_"><a class="button-primary_" href="' . menu_page_url( $cherry_wizard->slug, false ) . '">' . __( "Install theme", $cherry_wizard->slug ) . '</a><a class="dismiss-notice_ button-default_" href="' . add_query_arg( 'cherry_wizard_dismiss', 'dismiss_admin_notices' ) . '" target="_parent">' . __( "Dismiss", $cherry_wizard->slug ) . '</a></div>';
				echo '</div></div>';
			echo '</div>';

		}

		/**
		 * check require directory permissions
		 * 
		 * @since 1.0.0
		 */
		public function check_permissions() {

			global $cherry_wizard;

			// check settings only on wizard related pages (no need to do this at other admin pages)
			if ( !$cherry_wizard->is_wizard_page() ) {
				return;
			}

			$plugins_dir = WP_PLUGIN_DIR;
			$themes_dir  = get_theme_root();
			$uploads_dir = wp_upload_dir();
			$uploads_dir = $uploads_dir['basedir'];

			$check_perms = 'ok';
			$message = array();

			if ( !is_writable($plugins_dir) ) {
				$check_perms = 'error';
				$message['plugins_dir'] = array(
					'type' => 'error',
					'text' => __( 'Plugins directory not writable', $cherry_wizard->slug )
				);
			}

			if ( !is_writable($themes_dir) ) {
				$check_perms = 'error';
				$message['themes_dir'] = array(
					'type' => 'error',
					'text' => __( 'Themes directory not writable', $cherry_wizard->slug )
				);
			}

			if ( !is_writable($uploads_dir) ) {
				$check_perms = 'error';
				$message['uploads_dir'] = array(
					'type' => 'error',
					'text' => __( 'Uploads directory not writable', $cherry_wizard->slug )
				);
			}

			$_SESSION['cherry_wizard_messages'] = $message;

			$GLOBALS['cherry_wizard_dir_permissions'] = $check_perms;

		}

		/**
		 * check require server configuration 
		 * 
		 * @since 1.0.0
		 */
		public function check_server_config() {

			global $cherry_wizard;

			// check settings only on wizard related pages (no need to do this at other admin pages)
			if ( !$cherry_wizard->is_wizard_page() ) {
				return;
			}

			$server_settings = 'ok';

			$messages = isset( $_SESSION['cherry_wizard_messages'] ) ? $_SESSION['cherry_wizard_messages'] : array();

			if ( !$messages ) {
				$messages = array();
			}

			$must_settings = array(
				'safe_mode'           => 'off',
				'file_uploads'        => 'on',
				'memory_limit'        => 128,
				'post_max_size'       => 8,
				'upload_max_filesize' => 8,
				'max_input_time'      => 45,
				'max_execution_time'  => 30
			);

			$units = array(
				'safe_mode'           => '',
				'file_uploads'        => '',
				'memory_limit'        => 'Mb',
				'post_max_size'       => 'Mb',
				'upload_max_filesize' => 'Mb',
				'max_input_time'      => 's',
				'max_execution_time'  => 's'
			);

			// curret server settings
			$current_settings = array();

			//result array
			$result = array();

			$current_settings['safe_mode'] = 'off';
			if ( ini_get('safe_mode') ) {
				$current_settings['safe_mode'] = 'on';
			}

			$current_settings['file_uploads'] = 'off';
			if ( ini_get('file_uploads') ) {
				$current_settings['file_uploads'] = 'on';
			}

			$current_settings['memory_limit']        = (int)ini_get('memory_limit');
			$current_settings['post_max_size']       = (int)ini_get('post_max_size');
			$current_settings['upload_max_filesize'] = (int)ini_get('upload_max_filesize');
			$current_settings['max_input_time']      = (int)ini_get('max_input_time');
			$current_settings['max_execution_time']  = (int)ini_get('max_execution_time');

			$diff = array_diff_assoc($must_settings, $current_settings);

			if ( strcmp($must_settings['safe_mode'], $current_settings['safe_mode']) ) {
				$result['safe_mode'] = $must_settings['safe_mode'];
				$messages['safe_mode'] = array(
					'type' => 'warning',
					'text' => 'Safe mode - ' . $result['safe_mode'] . '. Current - ' . $current_settings['safe_mode']
				);
				$server_settings = 'warning';
			}

			if ( strcmp($must_settings['file_uploads'], $current_settings['file_uploads']) ) {
				$result['file_uploads'] = $must_settings['file_uploads'];
				$messages['file_uploads'] = array(
					'type' => 'error',
					'text' => 'File uploads - ' . $result['file_uploads'] . 'Current - ' . $current_settings['file_uploads']
				);
				$server_settings = 'error';
			}

			foreach ( $diff as $key => $value ) {
				if ( $current_settings[$key] < $value ) {
					$result[$key] = $value;
					$messages[$key] = array(
						'type' => 'warning',
						'text' => $key . ' - ' . $value . $units[$key] . '. Current - ' . $current_settings[$key]. $units[$key]
					);
					$server_settings = 'error' != $server_settings ? 'warning' : 'error';
				}
			}

			$_SESSION['cherry_wizard_messages'] = $messages;

			$GLOBALS['cherry_wizard_server_settings'] = $server_settings;

		}

		/**
		 * Show installation notices
		 * 
		 * @since 1.0.0
		 */
		public function show_install_notice() {
			global $cherry_wizard_dir_permissions, $cherry_wizard_server_settings, $cherry_wizard;

			if ( 'ok' == $cherry_wizard_dir_permissions && 'ok' == $cherry_wizard_server_settings ) {
				return;
			}

			$messages = isset( $_SESSION['cherry_wizard_messages'] ) ? $_SESSION['cherry_wizard_messages'] : array();

			if ( empty($messages) ) {
				return;
			}

			echo '<div class="wizard-install-notices box-default_ content-wrap_">';

			foreach ( $messages as $message ) {
				echo '<div class="wizard-notice-item ' . $message['type'] . '">' . $message['text'] . '</div>';
			}

			if ( 'warning' == $cherry_wizard_server_settings && 'error' != $cherry_wizard_dir_permissions ) {
				echo '<div class="submit-wrap_"><a href="#" class="wizard-run-install button-primary_">' . __( 'I understand, start installation', $cherry_wizard->slug ) . '</a></div>';
			}

			echo '</div>';

			if ( 'error' == $cherry_wizard_dir_permissions || 'error' == $cherry_wizard_server_settings ) {
				echo '<div class="wizard-install-todo box-default_ content-wrap_">';
					echo '<p>' . __( 'Change required settings and try again', $cherry_wizard->slug ) . '</p>';
					echo '<a href="' .  add_query_arg( array( 'step' => 1 ), menu_page_url( $cherry_wizard->slug, false ) ) . '">' . __( 'Try again', $cherry_wizard->slug ) . '</a>';
				echo '</div>';
				return;
			}

		}

	}

	new cherry_wizard_notices();

}