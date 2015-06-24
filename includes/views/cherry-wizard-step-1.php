<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * @package   cherry_wizard
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

global $cherry_wizard;

// validate auth data (theme name, key and installation type) before show anything to user
$cherry_auth_data = $cherry_wizard->check_auth_data();
if ( !$cherry_auth_data ) {
	return;
}

if ( isset( $cherry_wizard->install_handler->install_type ) ) {
	$install_type = $cherry_wizard->install_handler->install_type;
} else {
	$install_type = 'demo';
}

switch ( $install_type ) {
	case 'premium':
		$theme_type = __( 'Premium', $cherry_wizard->slug );
		break;

	case 'demo':
		$theme_type = __( 'Demo', $cherry_wizard->slug );
		break;

	default:
		$theme_type = __( 'Undefined', $cherry_wizard->slug );
		break;
}

?>
<div class="wrap">
	<div class="<?php echo $cherry_wizard->ui_wrapper_class( array( 'cherry-wizard_' ) ); ?>">
		<h2 class="main-title_"><?php echo sprintf( __( '<b>%s</b> theme installation', $cherry_wizard->slug ), $theme_type ); ?></h2>

		<?php
			if ( isset( $cherry_wizard->install_handler ) ) :
		?>

		<?php
			global $cherry_wizard_dir_permissions, $cherry_wizard_server_settings;

			$install_class = 'allowed';

			if ( 'warning' == $cherry_wizard_server_settings ) {
				$install_class = 'manually-allow';
			}

			if ( 'error' == $cherry_wizard_server_settings ) {
				$install_class = 'denied';
			}

			if ( 'error' == $cherry_wizard_dir_permissions ) {
				$install_class = 'denied';
			}

			/**
			 * Hook cherry_wizard_install_notices
			 * Fires before installation start
			 *
			 * hooked:
			 * @cherry_wizard_notices::show_install_notice - 10
			 */
			do_action( 'cherry_wizard_install_notices' );
		?>
		<div class="box-default_ content-wrap_">
			<?php
				/**
				 * Hook cherry_wizard_progress_bar
				 * Fires insed install box
				 *
				 * hooked:
				 * @cherry_wizard_install_progress - 10
				 */
				do_action( 'cherry_wizard_progress_bar' );
			?>
			<div class="wizard-installation box-inner_ <?php echo $install_class; ?>" data-install-type="<?php echo esc_attr( $install_type ); ?>" data-next-step="<?php echo add_query_arg( array( 'step' => 2, 'type' => $install_type ), menu_page_url( $cherry_wizard->slug, false ) ); ?>">
				<div id="cherry-wizard-retry-trigger" class="hidden_ wizard-retry">
					<a href="<?php echo $_SERVER['REQUEST_URI']; ?>"><?php _e( 'Retry', $cherry_wizard->slug ); ?></a>
				</div>
				<?php
				// show first step
				echo $cherry_wizard->install_handler->step( 'install-framework', 'no', __( 'Installing Cherry framework', $cherry_wizard->slug ) );
				?>
			</div>
		</div>
		<?php
			// do anything if installation type not provided
			else :
		?>
		<div class="box-default_ content-wrap_">
			<p>Select installation type - premium theme or demo</p>
		</div>
		<?php endif; ?>
	</div>
</div>
