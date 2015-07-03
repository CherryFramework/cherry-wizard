<?php
/**
 * @package   cherry_wizard
 * @author    Cherry Team
 * @license   GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name:       Cherry Wizard
 * Plugin URI:        http://www.cherryframework.com/
 * Description:       Installation wizard for CherryFramework-based templates
 * Version:           1.0.0
 * Author:            Cherry Team
 * Author URI:        http://www.cherryframework.com/
 * Text Domain:       cherry-wizard
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 *
 * Installation wizard for CherryFramework-based templates
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// If class 'cherry_wizard' not exists.
if ( !class_exists('cherry_wizard') ) {

	/**
	 * Sets up and initializes the Cherry Wizard plugin.
	 *
	 * @since 1.0.0
	 */
	class cherry_wizard {

		/**
		 * Wizard plugin slug (for text domains and options pages)
		 *
		 * @since 1.0.0
		 * @var string
		 */
		public $slug = 'cherry-wizard';

		/**
		 * Cherry license key
		 *
		 * @since 1.0.0
		 * @var string
		 */
		public $cherry_key = '';

		/**
		 * Cherry theme name
		 *
		 * @since 1.0.0
		 * @var string
		 */
		public $cherry_theme_name = '';

		/**
		 * Cherry cloud url
		 *
		 * @since 1.0.0
		 * @var string
		 */
		public $cherry_cloud_url = 'https://cloud.cherryframework.com/';

		/**
		 * Sets up needed actions/filters for the plugin to initialize.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {

			// Register activation and deactivation hooks.
			register_activation_hook( __FILE__, array( $this, 'activation' ) );
			register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

			// this plugin nothing to do on frontend
			if ( !is_admin() ) {
				return;
			}

			// setup theme name and keys from transient (for later steps)
			$this->cherry_key        = get_transient( 'cherry_key' );
			$this->cherry_theme_name = get_transient( 'cherry_theme_name' );

			// Set the constants needed by the plugin.
			add_action( 'plugins_loaded', array( $this, 'constants' ), 1 );
			// Internationalize the text strings used.
			add_action( 'plugins_loaded', array( $this, 'lang' ), 2 );
			// Load the functions files.
			add_action( 'plugins_loaded', array( $this, 'includes' ), 3 );
			// Load public-facing style sheet and JavaScript.
			add_action( 'admin_enqueue_scripts', array( $this, 'assets' ), 30 );
			// Start session
			add_action( 'init', array( $this, 'session_start' ) );
			// Allow files downloadin from TM hosts
			add_filter( 'http_request_host_is_external', array( $this, 'allow_tm_hosts' ), 10, 3 );

		}

		/**
		 * Start session
		 *
		 * @since 1.0.0
		 */
		function session_start() {

			if ( !session_id() ) {
				session_start();
			}

		}

		/**
		 * Check if current WP version is compatible with Cherry
		 *
		 * @since  1.0.0
		 * @return boolean
		 */
		function is_compatible_wp_version() {
			global $wp_version;
			return version_compare( $wp_version, '4.2.2' ) >= 0;
		}

		/**
		 * Enqueue CSS and JS
		 *
		 * @since 1.0.0
		 */
		function assets() {

			// Include admin interface styles
			wp_enqueue_style( 'cherry-ui-elements', CHERRY_WIZARD_URI . 'assets/css/cherry-ui.css', array(), '1.0.0' );
			wp_enqueue_style( $this->slug . '-style', CHERRY_WIZARD_URI . 'assets/css/style.css', array(), CHERRY_WIZARD_VERSION );

			// include next assets only for wizard-related pages
			if ( !$this->is_wizard_page() ) {
				return;
			}

			if ( isset($_GET['step']) && 1 == $_GET['step'] ) {

				// Theme installer scripts
				wp_enqueue_script( $this->slug . '-installer', CHERRY_WIZARD_URI . 'assets/js/installer.js', array( 'jquery' ), CHERRY_WIZARD_VERSION, true );
				global $cherry_wizard_dir_permissions, $cherry_wizard_server_settings;
				wp_localize_script( $this->slug . '-installer', 'cherry_wizard_install_data', array( 'dir_permissions' => $cherry_wizard_dir_permissions, 'server_settings' => $cherry_wizard_server_settings, 'redirect_message' => __( 'Theme installation complete. Redirecting to the next step...' ) ) );

			} elseif ( isset($_GET['step']) && 2 == $_GET['step'] ) {

				$type = isset( $_GET['type'] ) ? $_GET['type'] : 'demo';

				// Content importer scripts
				wp_enqueue_script( $this->slug . '-importer', CHERRY_WIZARD_URI . 'assets/js/importer.js', array( 'jquery', 'cherry-data-manager-importer' ), CHERRY_WIZARD_VERSION, true );
				wp_localize_script( $this->slug . '-importer', 'cherry_wizard_install_data', array( 'last_step' => add_query_arg( array( 'step' => 3, 'type' => $type ), menu_page_url( $this->slug, false ) ) ) );

			} elseif ( !isset($_GET['step']) ) {
				wp_enqueue_script( $this->slug . '-validator', CHERRY_WIZARD_URI . 'assets/js/validator.js', array( 'jquery' ), CHERRY_WIZARD_VERSION, true );
			}

		}

		/**
		 * Allow files downloading from TM hosts
		 *
		 * @since  1.0.0
		 */
		function allow_tm_hosts( $res, $host, $url ) {

			$allowed_hosts = array( 'tm-head.sasha.php.dev' );

			if ( in_array( $host, $allowed_hosts ) ) {
				return true;
			}

			return $res;

		}

		/**
		 * Defines constants for the plugin.
		 *
		 * @since 1.0.0
		 */
		function constants() {

			/**
			 * Set the version number of the plugin.
			 *
			 * @since 1.0.0
			 */
			define( 'CHERRY_WIZARD_VERSION', '1.0.0' );

			/**
			 * Set constant path to the plugin directory.
			 *
			 * @since 1.0.0
			 */
			define( 'CHERRY_WIZARD_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );

			/**
			 * Set constant path to the plugin URI.
			 *
			 * @since 1.0.0
			 */
			define( 'CHERRY_WIZARD_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );

		}

		/**
		 * Loads files from the '/includes' folder.
		 *
		 * @since 1.0.0
		 */
		function includes() {

			require_once( CHERRY_WIZARD_DIR . 'includes/class-cherry-wizard-notices.php' );
			require_once( CHERRY_WIZARD_DIR . 'includes/cherry-wizard-service-hooks.php' );
			require_once( CHERRY_WIZARD_DIR . 'includes/class-cherry-wizard-interface.php' );

			// include next handlers only for wizard pages and AJAX handlers
			if ( $this->is_wizard_page() ) {
				require_once( CHERRY_WIZARD_DIR . 'includes/class-cherry-wizard-helper.php' );
				require_once( CHERRY_WIZARD_DIR . 'includes/class-cherry-wizard-install-handlers.php' );
				require_once( CHERRY_WIZARD_DIR . 'includes/class-cherry-wizard-importer.php' );
			}

		}

		/**
		 * Loads the translation files.
		 *
		 * @since 1.0.0
		 */
		function lang() {
			load_plugin_textdomain( $this->slug, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		/**
		 * On plugin activation.
		 *
		 * @since 1.0.0
		 */
		function activation() {
			flush_rewrite_rules();
			add_option( 'cherry_wizard_need_install', 'yes' );
		}

		/**
		 * On plugin deactivation.
		 *
		 * @since 1.0.0
		 */
		function deactivation() {
			global $cherry_wizard;
			flush_rewrite_rules();
			delete_option( 'cherry_wizard_need_install' );
			delete_option( 'cherry_wizard_install_log_' . $cherry_wizard->cherry_theme_name );
			delete_transient( 'cherry_key' );
			delete_transient( 'cherry_theme_name' );
		}

		/**
		 * Check if is Cherry Wizard related page
		 *
		 * @since 1.0.0
		 */
		public function is_wizard_page() {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				return true;
			}

			if ( isset( $_GET['page'] ) && $this->slug === $_GET['page'] ) {
				return true;
			}

			return false;
		}

		/**
		 * Clear imorter sessions after import complete
		 *
		 * @since  1.0.0
		 */
		public function clear_import_data() {
			$session_vars = array(
				'processed_terms',
				'processed_menus',
				'url_remap',
				'featured_images',
				'attachment_posts',
				'processed_posts',
				'menu_items',
				'post_orphans',
				'meta_to_rewrite',
				'missing_menu_items',
				'posts'
			);

			foreach ( $session_vars as $var ) {
				if ( isset( $_SESSION[$var] ) ) {
					unset( $_SESSION[$var] );
				}
			}

		}

		/**
		 * Check if content importer plugin are avaliable
		 *
		 * @since 1.0.0
		 */
		public function has_importer() {
			return ( is_plugin_active( 'cherry-data-manager/cherry-data-manager.php' ) );
		}

		public function check_auth_data() {

			return true;

			$cherry_license_key = $this->cherry_key;
			$cherry_theme_name  = $this->cherry_theme_name;
			$cherry_demo_mail   = get_transient( 'cherry_wizard_demo_mail' );

			if ( !isset( $_GET['type'] ) ) {
				echo '<div class="wrap"><div class="' . $this->ui_wrapper_class() . '">' . __( 'Please select installation type', $this->slug ) . '<br><a href="' . menu_page_url( $this->slug, false ) . '" class="button-primary_">' . __( 'Retry', $this->slug ) . '</a>' . '</div>';
				return false;
			}

			if ( 'demo' == $_GET['type'] && !$cherry_demo_mail ) {
				echo '<div class="wrap"><div class="' . $this->ui_wrapper_class() . '">' . __( 'Please provide your email before installation', $this->slug ) . '<br><a href="' . menu_page_url( $this->slug, false ) . '" class="button-primary_">' . __( 'Retry', $this->slug ) . '</a>' . '</div>';
				return false;
			}

			if ( !$cherry_license_key || !$cherry_theme_name ) {
				echo '<div class="wrap"><div class="' . $this->ui_wrapper_class() . '">' . __( 'Activate your license before installation', $this->slug ) . '<br><a href="' . menu_page_url( $this->slug, false ) . '" class="button-primary_">' . __( 'Retry', $this->slug ) . '</a>' . '</div></div>';
				return false;
			}

			if ( 'premium' == $_GET['type'] && 'demo' == $cherry_license_key ) {
				echo '<div class="wrap"><div class="' . $this->ui_wrapper_class() . '">' . __( 'Activate your license before installation', $this->slug ) . '<br><a href="' . menu_page_url( $this->slug, false ) . '" class="button-primary_">' . __( 'Retry', $this->slug ) . '</a>' . '</div></div>';
				return false;
			}

			return true;
		}

		/**
		 * Get UI wrapper CSS class
		 *
		 * @since  1.0.0
		 */
		public function ui_wrapper_class( $classes = array(), $delimiter = ' ' ) {

			// prevent PHP errors
			if ( ! $classes || ! is_array( $classes ) ) {
				$classes = array();
			}
			if ( ! $delimiter || ! is_string( $delimiter ) ) {
				$delimiter = ' ';
			}

			$classes = array_merge( array( 'cherry-ui-core' ), $classes );

			/**
			 * Filter UI wrapper CSS classes
			 *
			 * @since 1.0.0
			 *
			 * @param array $classes - default CSS classes array
			 */
			$classes = apply_filters( 'cherry_ui_wrapper_class', $classes );

			$classes = array_unique( $classes );

			return join( $delimiter, $classes );

		}

	}

	// create class instance
	$GLOBALS['cherry_wizard'] = new cherry_wizard();

	//add_action( 'init', 'ch_request' );
	function ch_request() {

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$res = download_url( 'http://tm-head.sasha.php.dev/get.php?order_id=0M68lrIeK8iEQ9629YdM&templ_id=53391&pack=cherry-theme&sign=A4D9AF60D0DCDB07226F8B51EBA0F5CB' );
		die();
	}

}