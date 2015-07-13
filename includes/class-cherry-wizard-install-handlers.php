<?php
/**
 * Add cherry theme install handlers
 *
 * @package   cherry_wizard
 * @author    Cherry Team <support@cherryframework.com>
 * @copyright Copyright © 2012 - 2015, Cherry Team
 * @license   GNU General Public License version 3. See LICENSE.txt or http://www.gnu.org/licenses/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( !class_exists( 'cherry_wizard_install_handlers' ) ) {

	/**
	 * Add cherry theme install handlers
	 *
	 * @since 1.0.0
	 */
	class cherry_wizard_install_handler {

		/**
		 * Installation steps
		 *
		 * @since 1.0.0
		 * @var   array
		 */
		public $steps = array();

		/**
		 * Required plugins
		 *
		 * @since 1.0.0
		 * @var   array
		 */
		public $required_plugins = array();

		/**
		 * Last Installation step
		 *
		 * @since 1.0.0
		 * @var   string
		 */
		public $last_step = '';

		/**
		 * Theme installation type
		 *
		 * @since 1.0.0
		 * @var   string
		 */
		public $install_type = 'premium';

		/**
		 * Installation progress groups
		 *
		 * @since 1.0.0
		 * @var   array
		 */
		public $install_groups = array();

		/**
		 * Github API url
		 *
		 * @since 1.0.0
		 * @var   string
		 */
		public $api_url = 'https://cloud.cherryframework.com/cherry-update/';

		/**
		 * include necessary files. Run actions
		 */
		function __construct( $install_type ) {

			$this->install_type = $install_type;

			/**
			 * Get installation steps array
			 *
			 * @since 1.0.0
			 * @var   array with installation steps
			 */
			$this->steps = apply_filters(
				'cherry_wizard_installation_steps',
				array(
					'install-framework',
					'install-theme',
					'activate-theme',
					'install-live-chat',
					'install-data-manager',
					'install-plugins'
					)
				);

			/**
			 * Get last installation step
			 *
			 * @since 1.0.0
			 * @var   name of last installation step
			 */
			$this->last_step = apply_filters( 'cherry_wizard_installation_last_step', 'install-plugins' );

			/**
			 * Get installation groups
			 *
			 * @since 1.0.0
			 * @var   array of installation groups
			 */
			$this->install_groups = apply_filters( 'cherry_wizard_installation_groups', array(
				'framework'        => array( 'install-framework' ),
				'theme'            => array( 'install-theme', 'activate-theme' ),
				'service_plugins'  => array( 'install-live-chat', 'install-data-manager' ),
				'frontend_plugins' => array( 'install-plugins' )
			) );

			include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
			include_once( 'installers/class-cherry-theme-installer-skin.php' );
			include_once( 'installers/class-cherry-theme-upgrader.php' );
			include_once( 'installers/class-cherry-plugin-upgrader.php' );

			// AJAX handler for installation steps
			add_action( 'wp_ajax_cherry_wizard_install_step', array( $this, 'install_steps' ) );
			add_filter( 'upgrader_source_selection', array( $this, 'rename_github_zip' ), 1, 3 );
			add_filter( 'upgrader_source_selection', array( $this, 'relocate_child' ), 99, 3 );

		}

		/**
		 * Clear framework folder name from github data
		 *
		 * @since  1.0.0
		 */
		public function rename_github_zip( $upgrade_dir, $remote_dir, $skin_upgrader ) {

			if ( isset( $skin_upgrader->is_theme ) && true == $skin_upgrader->is_theme ) {
				return $upgrade_dir;
			}

			$plugin = isset( $skin_upgrader->skin->options['plugin'] ) ? $skin_upgrader->skin->options['plugin'] : '';
			$theme  = isset( $skin_upgrader->skin->options['theme'] ) ? $skin_upgrader->skin->options['theme'] : '';

			$rewrite = ( ! empty( $plugin ) && false !== strpos( $upgrade_dir, $plugin ) ) ? $plugin : false;

			if ( ! $rewrite ) {
				$rewrite = ( ! empty( $theme ) && strpos( $upgrade_dir, $theme ) ) ? $theme : false;
			}

			if ( ! $rewrite ) {
				return $upgrade_dir;
			}

			$upgrade_dir_path = pathinfo( $upgrade_dir );
			$new_upgrade_dir  = trailingslashit( $upgrade_dir_path['dirname'] ) . trailingslashit( $rewrite );

			rename( $upgrade_dir, $new_upgrade_dir );
			remove_all_filters( 'upgrader_source_selection' );

			return $new_upgrade_dir;

		}

		/**
		 * Relocate source path for child theme
		 *
		 * @since  1.0.0
		 */
		public function relocate_child( $upgrade_dir, $remote_dir, $theme_upgrader ) {

			if ( ! isset( $theme_upgrader->is_theme ) || true !== $theme_upgrader->is_theme ) {
				return $upgrade_dir;
			}

			global $wp_filesystem, $wp_theme_directories;
			$source_files = array_keys( $wp_filesystem->dirlist( $remote_dir ) );

			if ( 1 == count( $source_files ) ) {

				global $cherry_wizard;

				if ( isset( $cherry_wizard->install_handler->install_type )
					&& 'demo' == $cherry_wizard->install_handler->install_type
				) {
					$new_upgrade_dir = $this->rename_by_theme_name( $upgrade_dir );
					return $new_upgrade_dir;
				}

				return $upgrade_dir;
			}

			if ( 2 < count( $source_files ) ) {
				$new_upgrade_dir = $this->rename_by_theme_name( $upgrade_dir );
				return $new_upgrade_dir;
			}

			foreach ( $source_files as $file ) {

				if ( 'index.html' == $file ) {
					continue;
				}

				return trailingslashit( $upgrade_dir . $file );
			}

		}

		/**
		 * Rename folder by nested theme name
		 *
		 * @since  1.0.0
		 *
		 * @param  string $upgrade_dir full theme directory name
		 * @return string
		 */
		public function rename_by_theme_name( $upgrade_dir ) {

			$child_data = get_file_data(
				trailingslashit( $upgrade_dir ) . 'style.css',
				array( 'ThemeName' => 'Theme Name' )
			);

			if ( isset( $child_data['ThemeName'] ) ) {

				$new_upgrade_dir = preg_replace(
					"/\/(.[^\/]+)\/?$/",
					"/" . $child_data['ThemeName'] . "/",
					$upgrade_dir
				);

				rename( $upgrade_dir, $new_upgrade_dir );

				return $new_upgrade_dir;
			}

			return $upgrade_dir;

		}

		/**
		 * Get zip url from Git API to seleted product
		 *
		 * @since  1.0.0
		 * @param  string $product needed product slug
		 * @return theme URL
		 */
		public function get_git_zip( $product, $use_dev = true ) {

			// prepare params
			$product = urlencode( $product );
			$repo    = 'CherryFramework/' . $product;

			$args = array(
				'user-agent'        => 'WordPress',
				'github_repository' => home_url( '/' )
			);

			$query_arg = array(
				'github_repository' => $repo,
				'up_query_limit'    => 1
			);

			if ( $use_dev ) {
				$query_arg['get_alpha'] = 1;
			}

			$request_url = add_query_arg( $query_arg, trailingslashit( $this->api_url ) );

			// get latest release zip URL from GitHub API
			$git_request = wp_remote_get( $request_url, $args );

			if ( is_wp_error( $git_request ) ) {
				return false;
			}

			if ( isset( $git_request['response'] ) && 200 != $git_request['response']['code'] ) {
				return false;
			}

			if ( empty( $git_request['body'] ) ) {
				return false;
			}

			$respose_body = json_decode( $git_request['body'] );

			if ( empty( $respose_body ) || ! isset( $respose_body->package ) ) {
				return false;
			}

			return esc_url( $respose_body->package );

		}

		/**
		 * Cherry Wizard installation steps trigger
		 *
		 * @since 1.0.0
		 */
		public function install_steps() {

			//make sure request is comming from AJAX
			$xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
			if (!$xhr) {
				header('HTTP/1.1 500 Error: Request must come from AJAX!');
				exit();
			}

			if ( !isset($_REQUEST['step']) || !in_array($_REQUEST['step'], $this->steps) ) {
				return;
			}

			$this->install_type = $_REQUEST['type'];

			switch ($_REQUEST['step']) {
				case 'install-framework':
					$this->install_framework();
					break;

				case 'install-theme':
					$this->install_theme();
					break;

				case 'activate-theme':
					$this->activate_theme();
					break;

				case 'install-live-chat':
					$this->install_live_chat();
					break;

				case 'install-data-manager':
					$this->install_data_manager();
					break;

				case 'install-plugins':
					$this->install_required_plugins();
					break;

				default:
					/**
					 * Hook to add custom installation step handlers
					 *
					 * @since 1.0.0
					 */
					do_action( 'cherry_wizard_install_step_' . $_REQUEST['step'] );
					break;
			}

		}

		/**
		 * Instal Cherry Framework
		 *
		 * @since 1.0.0
		 */
		public function install_framework() {

			global $cherry_wizard;

			// Create a new instance of Cherry_Wizard_Theme_Upgrader

			$theme = 'cherryframework4';
			$url   = $this->get_git_zip( $theme );

			if ( ! $url ) {
				$url = $cherry_wizard->cherry_cloud_url . 'downloads/framework/cherryframework4.zip';
			}

			if ( ! $url ) {
				$result['type']      = 'error';
				$result['content']   = __( 'Can\'t download CherryFramework', $cherry_wizard->slug );
				$result['next_step'] = false;
				$this->send_install_response( $result );
			}

			$upgrader = new Cherry_Wizard_Theme_Upgrader(
				new Cherry_Theme_Installer_Skin(
					compact( 'url', 'theme' )
				)
			);

			// Perform theme insatallation from source url
			$inst = $upgrader->install( esc_url( $url ) );

			$result['type']      = $upgrader->result_type;
			$result['content']   = $upgrader->output_result;
			$result['next_step'] = $this->step( 'install-theme', 'no', __( 'Installing child theme', $cherry_wizard->slug ) );

			if ( 'error' == $result['type'] ) {
				$result['emergency_break'] = 'break';
			}

			/**
			 * Hook fires after framework installations
			 *
			 * @since  1.0.0
			 * @param  $result - result of framework installation
			 */
			do_action( 'cherry_wizard_install_framework', $result );

			$this->send_install_response( $result );
		}

		/**
		 * Instal child theme
		 *
		 * @since 1.0.0
		 */
		public function install_theme() {

			global $cherry_wizard;

			if ( !$cherry_wizard->cherry_theme_name && !$cherry_wizard->cherry_key ) {
				$result['type']      = 'error';
				$result['content']   = __( 'Theme license not activated', $cherry_wizard->slug );
				$result['next_step'] = false;
			}

			if ( ! isset( $_SESSION['cherry_data']['theme'] ) ) {
				$result['type']      = 'error';
				$result['content']   = __( 'Can\'t find theme link', $cherry_wizard->slug );
				$result['next_step'] = false;

				$this->send_install_response( $result );
			}

			$url = htmlspecialchars_decode( $_SESSION['cherry_data']['theme'] );

			// Create a new instance of Cherry_Wizard_Theme_Upgrader
			$upgrader = new Cherry_Wizard_Theme_Upgrader(
				new Cherry_Theme_Installer_Skin(
					compact( 'url' )
				)
			);

			// Perform theme insatallation from source url
			$installed = $upgrader->install( $url, true );

			$result = array();

			$result['type']      = $upgrader->result_type;
			$result['content']   = $upgrader->output_result;
			$result['next_step'] = $this->step( 'activate-theme', 'no', __( 'Activating child theme', $cherry_wizard->slug ) );

			if ( 'error' == $result['type'] ) {
				$result['emergency_break'] = 'break';
			}

			/**
			 * Hook fires after child theme installations
			 *
			 * @since  1.0.0
			 * @param  $result - result of child theme installation
			 */
			do_action( 'cherry_wizard_install_theme', $result );

			$this->send_install_response( $result );
		}

		/**
		 * Activate child theme
		 *
		 * @since 1.0.0
		 */
		public function activate_theme() {

			global $cherry_wizard;

			$theme_name = ! empty( $_SESSION['cherry_data']['theme_name'] )
							? $_SESSION['cherry_data']['theme_name']
							: $cherry_wizard->cherry_theme_name;

			if ( ! $theme_name ) {
				$result['type']      = 'error';
				$result['content']   = '<p>' . __( 'Theme name was not provided. Please activate your theme agian', $cherry_wizard->slug ) . '</p>';
				$result['next_step'] = false;

				$this->send_install_response( $result );
			}

			$current_theme = wp_get_theme();

			if ( $current_theme->stylesheet == $theme_name ) {
				$result['type']      = 'warning';
				$result['content']   = '<p>' . __( 'This theme already active', $cherry_wizard->slug ) . '</p>';
				$result['next_step'] = $this->step( 'install-live-chat', 'no', __( 'Installing Live Chat', $cherry_wizard->slug ) );

				$this->send_install_response( $result );
			}

			switch_theme( $theme_name );

			$result['type']      = 'success';
			$result['content']   = '<p>' . __( 'Theme successfully activated', $cherry_wizard->slug ) . '</p>';
			$result['next_step'] = $this->step( 'install-live-chat', 'no', __( 'Installing Live Chat', $cherry_wizard->slug ) );

			/**
			 * Hook fires after child theme activation
			 *
			 * @since  1.0.0
			 * @param  $result - result of child theme activation
			 */
			do_action( 'cherry_wizard_activate_theme', $result );

			$this->send_install_response( $result );
		}

		/**
		 * Install live chat
		 *
		 * @since 1.0.0
		 */
		public function install_live_chat() {

			global $cherry_wizard;

			$plugin = 'cherry-live-chat';

			$url = $cherry_wizard->cherry_cloud_url . 'downloads/free-plugins/cherry-live-chat.zip';

			if ( false == $url ) {
				$result['type']      = 'error';
				$result['content']   = __( 'Can not download Plugin Manager', $cherry_wizard->slug );
				$result['next_step'] = false;
				$this->send_install_response( $result );
			}

			// Create a new instance of Cherry_Wizard_Plugin_Upgrader
			$upgrader = new Cherry_Wizard_Plugin_Upgrader(
				$skin = new Cherry_Theme_Installer_Skin(
					compact( 'url', 'plugin' )
				)
			);

			// Perform plugin insatallation from source url
			$upgrader->install( $url );
			// Flush plugins cache so we can make sure that the installed plugins list is always up to date
			wp_cache_flush();

			// activate plugin if installation was successfull
			if ( 'success' == $upgrader->result_type || 'warning' == $upgrader->result_type ) {

				$plugin_activate = $upgrader->plugin_info();

				if ( !$plugin_activate ) {
					$plugin_activate = 'cherry-live-chat/cherry-live-chat.php';
				}

				$activate = activate_plugin( $plugin_activate ); // Activate the plugin
				//$this->populate_file_path(); // Re-populate the file path now that the plugin has been installed and activated

				if ( is_wp_error( $activate ) ) {
					$upgrader->result_type = 'warning';
					$upgrader->output_result .= '<p>' . $activate->get_error_message() . '</p>';
				} else {
					$upgrader->result_type = 'success';
					$upgrader->output_result .= '<p>' . __( 'Plugin activated', $cherry_wizard->slug ) . '</p>';
				}

			}

			$result['type']      = $upgrader->result_type;
			$result['content']   = $upgrader->output_result;
			$result['next_step'] = $this->step( 'install-data-manager', 'no', __( 'Installing Data Manager', $cherry_wizard->slug ) );

			/**
			 * Hook fires after plugin manager installation
			 *
			 * @since  1.0.0
			 * @param  $result - result of plugin manager installation
			 */
			do_action( 'cherry_wizard_install_plugins_manager', $result );

			if ( 'error' == $result['type'] ) {
				$result['emergency_break'] = 'break';
			}

			$this->send_install_response( $result );
		}

		/**
		 * Install data manager
		 *
		 * @since 1.0.0
		 */
		public function install_data_manager() {

			global $cherry_wizard;

			$plugin = 'cherry-data-manager';
			$url    = $this->get_git_zip( 'cherry-data-manager' );

			if ( false == $url ) {
				$url = $cherry_wizard->cherry_cloud_url . 'downloads/free-plugins/cherry-data-manager.zip';
			}

			if ( false == $url ) {
				$result['type']      = 'error';
				$result['content']   = __( 'Can not download Data Manager', $cherry_wizard->slug );
				$result['next_step'] = false;
				$this->send_install_response( $result );
			}

			// Create a new instance of Cherry_Wizard_Plugin_Upgrader
			$upgrader = new Cherry_Wizard_Plugin_Upgrader(
				$skin = new Cherry_Theme_Installer_Skin(
					compact( 'url', 'plugin' )
				)
			);

			// Perform plugin insatallation from source url
			$upgrader->install( $url );
			// Flush plugins cache so we can make sure that the installed plugins list is always up to date
			wp_cache_flush();

			// activate plugin if installation was successfull (or if warning - if plugin not active)
			if ( 'success' == $upgrader->result_type || 'warning' == $upgrader->result_type ) {

				$plugin_activate = $upgrader->plugin_info();

				if ( ! $plugin_activate ) {
					$plugin_activate = 'cherry-data-manager/cherry-data-manager.php';
				}

				$activate = activate_plugin( $plugin_activate ); // Activate the plugin
				//$this->populate_file_path(); // Re-populate the file path now that the plugin has been installed and activated

				if ( is_wp_error( $activate ) ) {
					$upgrader->result_type = 'warning';
					$upgrader->output_result .= '<p>' . $activate->get_error_message() . '</p>';
				} else {
					$upgrader->result_type = 'success';
					$upgrader->output_result .= '<p>' . __( 'Plugin activated', $cherry_wizard->slug ) . '</p>';
				}

			}

			$result['type']      = $upgrader->result_type;
			$result['content']   = $upgrader->output_result;

			/**
			 * Get required plugins array from child theme
			 *
			 * @since 1.0.0
			 * @var   array with required plugin inits
			 */
			$this->required_plugins = apply_filters( 'cherry_theme_required_plugins', $this->required_plugins );

			if ( empty($this->required_plugins) ) {
				$result['next_step'] = $this->step( 'install-plugins', 'yes', __( 'Installing required plugins', $cherry_wizard->slug ) );
			} else {
				$result['next_step'] = $this->step( 'install-plugins', 'no', __( 'Preparing required plugins', $cherry_wizard->slug ) );
			}

			// also send new plugins installation progressbar HTML markup
			$result['plugins_progress'] = cherry_wizard_install_progress( true, $this->required_plugins );
			$result['plugins_count']    = count( $this->required_plugins );

			/**
			 * Hook fires after data manager installation
			 *
			 * @since  1.0.0
			 * @param  $result - result of data manger installation
			 */
			do_action( 'cherry_wizard_install_data_manager', $result );

			if ( 'error' == $result['type'] ) {
				$result['emergency_break'] = 'break';
			}

			$this->send_install_response( $result );
		}

		/**
		 * Install required plugins
		 *
		 * @since 1.0.0
		 */
		public function install_required_plugins() {

			global $cherry_wizard;

			$result = array();

			/**
			 * Get required plugins array from child theme
			 *
			 * @since 1.0.0
			 * @var   array with required plugin inits
			 */
			$this->required_plugins = apply_filters( 'cherry_theme_required_plugins', $this->required_plugins );

			// return if no plugins need to install
			if ( empty( $this->required_plugins ) ) {
				$result['type']      = 'success';
				$result['content']   = '<p>' . __( 'All done', $cherry_wizard->slug ) . '</p>';
				$result['next_step'] = false;

				/**
				 * Hook fires when theme and plugins installation complete
				 *
				 * @since 1.0.0
				 */
				do_action( 'cherry_wizard_theme_install_complete' );

				$this->send_install_response( $result );
			}

			$required_plugins = $plugins_clone = $this->required_plugins;

			// Find first and last plugins in array
			$plugin_keys  = array_keys($required_plugins);
			$first_plugin = array_shift($plugin_keys);
			// array_shift remove element from array, so if we had 1 plugin to install - on this stage $plugin_keys are empty, so get it again just in case
			$plugin_keys = array_keys($required_plugins);
			$last_plugin = array_pop($plugin_keys);

			// If not passed parameter PLUGIN in request - get first plugin and send it to installation
			if ( !isset($_REQUEST['plugin']) || !$_REQUEST['plugin'] ) {

				$is_last   = 'no';
				if ( 1 == count($required_plugins) ) {
					$is_last = 'yes';
				}
				// next step - is our first plugin, current step - is preparing plugins
				$next_step = $this->step( 'install-plugins', $is_last, sprintf( __( 'Installing %s', $cherry_wizard->slug ), $required_plugins[$first_plugin]['name'] ), $required_plugins[$first_plugin]['slug'] );
				// send first plugin to install
				$result['type']      = 'success';
				$result['content']   = '<p>' . __( 'Plugins prepared', $cherry_wizard->slug ) . '</p>';
				$result['next_step'] = $next_step;

				$this->send_install_response( $result );
			}

			// if we here - PLUGIN parameter isset
			$current_plugin = $_REQUEST['plugin'];

			// if is last plugin - install it without next step
			if ( $current_plugin == $last_plugin ) {
				$result = $this->install_plugin( $current_plugin, $required_plugins );
				$result['next_step'] = false;

				/**
				 * Hook fires when theme and plugins installation complete
				 *
				 * @since 1.0.0
				 */
				do_action( 'cherry_wizard_theme_install_complete' );

				$this->send_install_response( $result );
			}

			$next_plugin = $this->get_next_key( $plugins_clone, $current_plugin );
			$is_last = 'no';

			if ( $next_plugin == $last_plugin ) {
				$is_last = 'yes';
			}

			// just in case check - have we next plugin for install and cancell next step if not
			if ( $next_plugin ) {
				$next_step = $this->step( 'install-plugins', $is_last, sprintf( __( 'Installing %s', $cherry_wizard->slug ), $required_plugins[$next_plugin]['name'] ), $required_plugins[$next_plugin]['slug'] );
			} else {
				$next_step = false;

				/**
				 * Hook fires when theme and plugins installation complete
				 *
				 * @since 1.0.0
				 */
				do_action( 'cherry_wizard_theme_install_complete' );
			}

			// run current plugin installation and send result for jumping to next step
			$result              = $this->install_plugin( $current_plugin, $required_plugins );
			$result['next_step'] = $next_step;

			$this->send_install_response( $result );

		}

		/**
		 * Install single plugin from required plugins set
		 *
		 * @since  1.0.0
		 * @param  $current_plugin   current plugin slug
		 * @param  $required_plugins array of required plugins
		 */
		function install_plugin( $current_plugin, $required_plugins ) {

			global $cherry_wizard;

			if ( !$current_plugin || !array_key_exists($current_plugin, $required_plugins) ) {
				$result['type']            = 'warning';
				$result['content']         = '<p>' . __( 'Plugin not exist', $cherry_wizard->slug ) . '</p>';
				$result['emergency_break'] = 'break';
				return $result;
			}

			// prepare plugin data to avoid undefined index in array
			$plugin_data = wp_parse_args( $required_plugins[$current_plugin], array(
				'name'    => '', // The plugin name
				'slug'    => '', // The plugin slug (typically the folder name)
				'source'  => 'cherry', // The plugin source type (cherry, wordpress or direct link to zip archive)
				'version' => ''
			) );

			$plugin = $plugin_data['slug'];

			$result = array();

			switch ($plugin_data['source']) {
				case 'cherry-premium':
					$source = esc_url(
						add_query_arg(
							array(
								'cherry_get_plugin' => $plugin,
								'license'           => $cherry_wizard->cherry_key
							),
							$cherry_wizard->cherry_cloud_url
						)
					);
					$source = htmlspecialchars_decode( $source );
					$source = str_replace( '&#038;', '&', $source );
					break;

				case 'cherry-free':
					$source = $this->get_git_zip( $plugin );

					if ( false == $source ) {
						$source = $cherry_wizard->cherry_cloud_url . 'downloads/free-plugins/' . $plugin . '.zip';
					}

					if ( false == $source ) {
						$result['type']      = 'error';
						$result['content']   = __( 'Can not download ', $cherry_wizard->slug ) . $plugin_data['name'];
						$result['next_step'] = false;
						$this->send_install_response( $result );
					}

					break;

				case 'wordpress':
					require_once ABSPATH . 'wp-admin/includes/plugin-install.php'; // Need for plugins_api

					$api = plugins_api(
						'plugin_information',
						array( 'slug' => $plugin, 'fields' => array( 'sections' => false ) )
					);

					if ( is_wp_error( $api ) ) {
						wp_die( $this->strings['oops'] . var_dump( $api ) );
					}

					if ( isset( $api->download_link ) ) {
						$source = $api->download_link;
					}
					break;

				default:
					$source = $plugin_data['source'];
					break;
			}

			// Create a new instance of Cherry_Wizard_Plugin_Upgrader
			$upgrader = new Cherry_Wizard_Plugin_Upgrader(
				$skin = new Cherry_Theme_Installer_Skin(
					compact( 'plugin', 'api' )
				)
			);

			// Perform plugin insatallation from source url
			$upgrader->install( $source );
			// Flush plugins cache so we can make sure that the installed plugins list is always up to date
			wp_cache_flush();

			// activate plugin if installation was successfull
			if ( 'success' == $upgrader->result_type || 'warning' == $upgrader->result_type ) {

				$plugin_activate = $upgrader->plugin_info();

				$activate = activate_plugin( $plugin_activate ); // Activate the plugin
				//$this->populate_file_path(); // Re-populate the file path now that the plugin has been installed and activated

				/**
				 * Hook fires after required plugin installation
				 *
				 * @since  1.0.0
				 * @param  $current_plugin - activated plugin slug
				 */
				do_action( 'cherry_wizard_required_plugin_active', $current_plugin );

				if ( is_wp_error( $activate ) ) {
					$upgrader->result_type = 'warning';
					$upgrader->output_result .= '<p>' . $activate->get_error_message() . '</p>';
				} else {
					$upgrader->result_type = 'success';
					$upgrader->output_result .= '<p>' . __( 'Plugin activated', $cherry_wizard->slug ) . '</p>';
				}

			}

			$result['type']    = $upgrader->result_type;
			$result['content'] = $upgrader->output_result;

			return $result;
		}

		/**
		 * Get next array key by current
		 *
		 * @since  1.0.0
		 * @param  $array the original array
		 * @param  $key   current key
		 */
		function get_next_key($array, $key) {
			reset($array);
			$currentKey = key($array);

			while ($currentKey !== null && $currentKey != $key) {
				next($array);
				$currentKey = key($array);
			}

			next($array);
			$next_key = key($array);
			return $next_key;
		}

		/**
		 * Get step item HTML markup with selected params
		 *
		 * @since  1.0.0
		 * @param  $step       step slug
		 * @param  $last_step  is last step or no (yes/no)
		 * @param  $label      step label text
		 * @param  $plugin     if this step contain plugin installation - plugin slug, if not- empty
		 */
		public function step( $step = null, $last_step = 'no', $label = '', $plugin = '' ) {

			if ( !$step ) {
				return;
			}

			global $cherry_wizard;

			if ( !$label ) {
				$label = __( 'Next step', $cherry_wizard->slug );
			}

			return '<div class="wizard-installation_item" data-step="' . $step . '" data-is-last-step="' . $last_step . '" data-plugin="' . esc_attr( $plugin ) . '">
						<div class="wizard-installation_item_title">' . $label . '<a href="" class="wizard-installation_item_details hidden_">' . __( 'Details', $cherry_wizard->slug ) . '</a></div>
						<div class="wizard-installation_item_responce"></div>
					</div>';

		}

		/**
		 * Send JSON response from installation step
		 *
		 * @since  1.0.0
		 * @param  array  $args array of respose raguments
		 */
		public function send_install_response( $args = array() ) {

			$args = wp_parse_args( $args, apply_filters( 'cherry_wizard_install_response_arguments', array(
				'type'             => 'success',
				'content'          => '',
				'next_step'        => false,
				'plugins_progress' => '',
				'plugins_count'    => 0
			) ) );

			wp_send_json( $args );
		}

	}

	// create new instance of default installer class if is premium theme installation
	if ( isset( $_REQUEST['type'] ) && 'premium' == $_REQUEST['type'] ) {
		global $cherry_wizard;
		$cherry_wizard->install_handler = new cherry_wizard_install_handler( 'premium' );
	}

	// create new instance of demo installer class if is demo theme installation
	if ( isset( $_REQUEST['type'] ) && 'demo' == $_REQUEST['type'] ) {
		global $cherry_wizard;
		$cherry_wizard->install_handler = new cherry_wizard_install_handler( 'demo' );
	}

}