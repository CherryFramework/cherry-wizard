<?php
/**
 * Add cherry plugin upgrader classes (single and bulk)
 *
 * @package   cherry_wizard
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin Upgrader class for WordPress Plugins, It is designed to upgrade/install plugins from a local zip, remote zip URL, or uploaded zip file.
 *
 * @package WordPress
 * @subpackage Upgrader
 * @since 2.8.0
 */
class Cherry_Wizard_Plugin_Upgrader extends Plugin_Upgrader {

	public $result;
	public $bulk = false;

	/**
	 * Holds result of bulk plugin installation.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $output_result = '';

	/**
	 * Result type
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $result_type = 'success';

	public function run( $options ) {

		$defaults = array(
			'package'                     => '', // Please always pass this.
			'destination'                 => '', // And this
			'clear_destination'           => false,
			'abort_if_destination_exists' => true, // Abort if the Destination directory exists, Pass clear_destination as false please
			'clear_working'               => true,
			'is_multi'                    => false,
			'hook_extra'                  => array() // Pass any extra $hook_extra args here, this will be passed to any hooked filters.
		);

		$options = wp_parse_args( $options, $defaults );

		if ( ! $options['is_multi'] ) { // call $this->header separately if running multiple times
			$this->output_result .= $this->skin->header();
		}

		// Connect to the Filesystem first.
		$res = $this->fs_connect( array( WP_CONTENT_DIR, $options['destination'] ) );
		// Mainly for non-connected filesystem.
		if ( ! $res ) {
			if ( ! $options['is_multi'] ) {
				$this->output_result .= $this->skin->footer();
			}
			return false;
		}

		$this->output_result .= $this->skin->before();

		if ( is_wp_error($res) ) {
			$this->result_type = 'error';
			$this->output_result .= $this->skin->error($res);
			$this->output_result .= $this->skin->after();
			if ( ! $options['is_multi'] ) {
				$this->output_result .= $this->skin->footer();
			}
			return $res;
		}

		//Download the package (Note, This just returns the filename of the file if the package is a local file)
		$download = $this->download_package( $options['package'] );
		if ( is_wp_error($download) ) {
			$this->result_type = 'error';
			$this->output_result .= $this->skin->error($download);
			$this->output_result .= $this->skin->after();
			if ( ! $options['is_multi'] ) {
				$this->output_result .= $this->skin->footer();
			}
			return $download;
		}

		$delete_package = ( $download != $options['package'] ); // Do not delete a "local" file

		//Unzips the file into a temporary directory
		$working_dir = $this->unpack_package( $download, $delete_package );

		if ( is_wp_error($working_dir) ) {
			$this->result_type = 'error';
			$this->output_result .= $this->skin->error($working_dir);
			$this->output_result .= $this->skin->after();
			if ( ! $options['is_multi'] ) {
				$this->output_result .= $this->skin->footer();
			}
			return $working_dir;
		}

		//With the given options, this installs it to the destination directory.
		$result = $this->install_package( array(
			'source'                      => $working_dir,
			'destination'                 => $options['destination'],
			'clear_destination'           => false,
			'abort_if_destination_exists' => false,
			'clear_working'               => $options['clear_working'],
			'hook_extra'                  => $options['hook_extra']
		) );

		$this->skin->set_result($result);
		if ( is_wp_error($result) ) {
			if ( array_key_exists('folder_exists', $result->errors) ) {
				$this->result_type = 'warning';
			} else {
				$this->result_type = 'error';
			}
			$this->output_result .= $this->skin->error($result);
			$this->output_result .= $this->skin->feedback('process_failed');
		} else {
			//Install Succeeded
			$this->output_result .= $this->skin->feedback('process_success');
		}

		$this->output_result .= $this->skin->after();

		if ( ! $options['is_multi'] ) {

			/** This action is documented in wp-admin/includes/class-wp-upgrader.php */
			do_action( 'upgrader_process_complete', $this, $options['hook_extra'] );
			$this->output_result .= $this->skin->footer();
		}

		return $result;
	}

}

/**
 * Bulk Plugin Upgrader for the Cherry Wizard plugins Installer.
 *
 * @package cherry_wizard
 * @since 1.0.0
 */
class Cherry_Wizard_Plugin_Bulk_Installer extends WP_Upgrader {

	/**
	 * Holds result of bulk plugin installation.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $result;

	/**
	 * Flag to check if bulk installation is occurring or not.
	 *
	 * @since 1.0.0
	 * @var boolean
	 */
	public $bulk = false;

	/**
	 * Processes the bulk installation of plugins.
	 *
	 * @since 1.0.0
	 */
	public function bulk_install( $packages ) {

		/** Pass installer skin object and set bulk property to true */
		$this->init();
		$this->bulk = true;

		/** Set install strings and automatic activation strings (if config option is set to true) */
		$this->install_strings();
		$this->activate_strings();

		/** Run the header string to notify user that the process has begun */
		$this->skin->header();

		/** Connect to the Filesystem */
		$res = $this->fs_connect( array( WP_CONTENT_DIR, WP_PLUGIN_DIR ) );
		if ( ! $res ) {
			$this->skin->footer();
			return false;
		}

		/** Set the bulk header and prepare results array */
		$this->skin->bulk_header();
		$results = array();

		/** Get the total number of packages being processed and iterate as each package is successfully installed */
		$this->update_count   = count( $packages );
		$this->update_current = 0;

		/** Loop through each plugin and process the installation */
		foreach ( $packages as $plugin ) {
			$this->update_current++; // Increment counter

			/** Do the plugin install */
			$result = $this->run(
				array(
					'package'           => $plugin, // The plugin source
					'destination'       => WP_PLUGIN_DIR, // The destination dir
					'clear_destination' => false, // Do we want to clear the destination or not?
					'clear_working'     => true, // Remove original install file
					'is_multi'          => true, // Are we processing multiple installs?
					'hook_extra'        => array( 'plugin' => $plugin, ), // Pass plugin source as extra data
				)
			);

			/** Store installation results in result property */
			$results[$plugin] = $this->result;

			/** Prevent credentials auth screen from displaying multiple times */
			if ( false === $result )
				break;
		}

		/** Pass footer skin strings */
		$this->skin->bulk_footer();
		$this->skin->footer();

		/** Return our results */
		return $results;

	}

	/**
	 * Performs the actual installation of each plugin.
	 *
	 * @since 1.0.0
	 */
	public function run( $options ) {

		/** Default config options */
		$defaults = array(
			'package'           => '',
			'destination'       => '',
			'clear_destination' => false,
			'clear_working'     => true,
			'is_multi'          => false,
			'hook_extra'        => array(),
		);

		/** Parse default options with config options from $this->bulk_upgrade and extract them */
		$options = wp_parse_args( $options, $defaults );
		extract( $options );

		/** Connect to the Filesystem */
		$res = $this->fs_connect( array( WP_CONTENT_DIR, $destination ) );
		if ( ! $res )
			return false;

		/** Return early if there is an error connecting to the Filesystem */
		if ( is_wp_error( $res ) ) {
			$this->skin->error( $res );
			return $res;
		}

		/** Call $this->header separately if running multiple times */
		if ( ! $is_multi )
			$this->skin->header();

		/** Set strings before the package is installed */
		$this->skin->before();

		/** Download the package (this just returns the filename of the file if the package is a local file) */
		$download = $this->download_package( $package );
		if ( is_wp_error( $download ) ) {
			$this->skin->error( $download );
			$this->skin->after();
			return $download;
		}

		/** Don't accidentally delete a local file */
		$delete_package = ( $download != $package );

		/** Unzip file into a temporary working directory */
		$working_dir = $this->unpack_package( $download, $delete_package );
		if ( is_wp_error( $working_dir ) ) {
			$this->skin->error( $working_dir );
			$this->skin->after();
			return $working_dir;
		}

		/** Install the package into the working directory with all passed config options */
		$result = $this->install_package(
			array(
				'source'            => $working_dir,
				'destination'       => $destination,
				'clear_destination' => $clear_destination,
				'clear_working'     => $clear_working,
				'hook_extra'        => $hook_extra,
			)
		);

		/** Pass the result of the installation */
		$this->skin->set_result( $result );

		/** Set correct strings based on results */
		if ( is_wp_error( $result ) ) {
			$this->skin->error( $result );
			$this->skin->feedback( 'process_failed' );
		}
		/** The plugin install is successful */
		else {
			$this->skin->feedback( 'process_success' );
		}

		/** Flush plugins cache so we can make sure that the installed plugins list is always up to date */
		wp_cache_flush();

		/** Get the installed plugin file and activate it */
		$plugin_info = $this->plugin_info( $package );
		$activate    = activate_plugin( $plugin_info );

		/** Set correct strings based on results */
		if ( is_wp_error( $activate ) ) {
			$this->skin->error( $activate );
			$this->skin->feedback( 'activation_failed' );
		}
		/** The plugin activation is successful */
		else {
			$this->skin->feedback( 'activation_success' );
		}

		/** Flush plugins cache so we can make sure that the installed plugins list is always up to date */
		wp_cache_flush();

		/** Set install footer strings */
		$this->skin->after();
		if ( ! $is_multi )
			$this->skin->footer();

		return $result;

	}

	/**
	 * Grabs the plugin file from an installed plugin.
	 *
	 * @since 1.0.0
	 */
	public function plugin_info() {

		/** Return false if installation result isn't an array or the destination name isn't set */
		if ( ! is_array( $this->result ) )
			return false;
		if ( empty( $this->result['destination_name'] ) )
			return false;

		/** Get the installed plugin file or return false if it isn't set */
		$plugin = get_plugins( '/' . $this->result['destination_name'] );
		if ( empty( $plugin ) )
			return false;

		/** Assume the requested plugin is the first in the list */
		$pluginfiles = array_keys( $plugin );

		return $this->result['destination_name'] . '/' . $pluginfiles[0];

	}

}