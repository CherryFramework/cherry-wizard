<?php
/**
 * Add admin interface
 *
 * @package   cherry_wizard
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( !class_exists( 'cherry_wizard_interface' ) ) {

	/**
	 * Add admin interface
	 *
	 * @since 1.0.0
	 */
	class cherry_wizard_interface {
		
		function __construct() {
			// Add the withard page and menu item.
			add_action( 'admin_menu', array( $this, 'add_wizard_admin_menu' ) );
		}
		
		/**
		 * Register the administration menu for this plugin into the WordPress Dashboard menu.
		 *
		 * @since 1.0.0
		 */
		public function add_wizard_admin_menu() {
			global $cherry_wizard;
			add_management_page( 
				__( 'Cherry Wizard', $cherry_wizard->slug ),
				__( 'Cherry Wizard', $cherry_wizard->slug ),
				'manage_options',
				$cherry_wizard->slug,
				array( $this, 'display_plugin_admin_page' )
			);
		}

		/**
		 * show wizard management page
		 * 
		 * @since 1.0.0
		 */
		public function display_plugin_admin_page() {

			$step = isset($_GET['step']) ? $_GET['step'] : '';

			switch ($step) {
				case 1:
					require_once( CHERRY_WIZARD_DIR . 'includes/class-cherry-wizard-install-handlers.php' );
					include_once( 'views/cherry-wizard-step-1.php' );
					break;

				case 2:
					include_once( 'views/cherry-wizard-step-2.php' );
					break;

				case 3:
					include_once( 'views/cherry-wizard-step-3.php' );
					break;
				
				default:
					include_once( 'views/cherry-wizard.php' );
					break;
			}

		}

	}

	new cherry_wizard_interface();

}