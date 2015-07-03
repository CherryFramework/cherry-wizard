<?php
/**
 * Add cherry theme upgrader class
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
 * Theme Upgrader for the Cherry Wizard Theme Installer.
 *
 * @package cherry_wizard
 * @since 1.0.0
 */
class Cherry_Wizard_Theme_Upgrader extends Theme_Upgrader {

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

	/**
	 * Check, is theme or framework installation
	 *
	 * @since 1.0.0
	 * @var boolean
	 */
	public $is_theme = false;

	/**
	 * Performs the actual installation of each item.
	 *
	 * @since 1.0.0
	 *
	 * @param array $options The installation cofig options
	 * @return null/array Return early if error, array of installation data on success
	 */
	public function run( $options ) {

		$defaults = array(
			'package' => '', // Please always pass this.
			'destination' => '', // And this
			'clear_destination' => false,
			'abort_if_destination_exists' => true, // Abort if the Destination directory exists, Pass clear_destination as false please
			'clear_working' => true,
			'is_multi' => false,
			'hook_extra' => array() // Pass any extra $hook_extra args here, this will be passed to any hooked filters.
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
			'clear_destination'           => $options['clear_destination'],
			'abort_if_destination_exists' => $options['abort_if_destination_exists'],
			'clear_working'               => $options['clear_working'],
			'hook_extra'                  => $options['hook_extra']
		) );

		$this->skin->set_result($result);
		if ( is_wp_error($result) ) {
			if ( array_key_exists('folder_exists', $result->errors) ) {
				$this->result_type = 'warning';
				$this->set_theme_name_on_error( $result );
			} else {
				$this->result_type = 'error';
			}
			$this->output_result .= $this->skin->error($result);
			$this->output_result .= $this->skin->feedback('process_failed');
		} else {
			//Install Succeeded
			$this->output_result .= $this->skin->feedback('process_success');
			$_SESSION['cherry_data']['theme_name'] = $result['destination_name'];
		}

		$this->output_result .= $this->skin->after();

		if ( ! $options['is_multi'] ) {

			/** This action is documented in wp-admin/includes/class-wp-upgrader.php */
			do_action( 'upgrader_process_complete', $this, $options['hook_extra'] );
			$this->output_result .= $this->skin->footer();
		}

		return $result;
	}

	/**
	 * Set correct theme name for activation, if theme already installed
	 */
	public function set_theme_name_on_error( $error ) {
		$string = $error->error_data['folder_exists'];
		preg_match('/themes\/(.[^\/]*)\/$/', $string, $matches);
		if ( is_array( $matches ) && isset( $matches[1] ) ) {
			$_SESSION['cherry_data']['theme_name'] = $matches[1];
		}
	}

	/**
	 * Install a theme package.
	 *
	 * @param string  $package  The full local path or URI of the package.
	 * @param bool    $is_theme define - is framework or child theme installed
	 * @param array   $args
	 *
	 * @return bool|WP_Error True if the install was successful, false or a {@see WP_Error} object otherwise.
	 */
	public function install( $package, $is_theme = false, $args = array() ) {

		$this->is_theme = $is_theme;

		$defaults = array(
			'clear_update_cache' => true,
		);
		$parsed_args = wp_parse_args( $args, $defaults );

		$this->init();
		$this->install_strings();

		add_filter('upgrader_source_selection', array($this, 'check_package') );
		add_filter('upgrader_post_install', array($this, 'check_parent_theme_filter'), 10, 3);

		$this->run( array(
			'package'           => $package,
			'destination'       => get_theme_root(),
			'clear_destination' => false, //Do not overwrite files.
			'clear_working'     => true,
			'hook_extra'        => array(
				'type'   => 'theme',
				'action' => 'install',
			),
		) );

		remove_filter('upgrader_source_selection', array($this, 'check_package') );
		remove_filter('upgrader_post_install', array($this, 'check_parent_theme_filter'));

		if ( ! $this->result || is_wp_error($this->result) )
			return $this->result;

		// Refresh the Theme Update information
		wp_clean_themes_cache( $parsed_args['clear_update_cache'] );

		return true;
	}

}