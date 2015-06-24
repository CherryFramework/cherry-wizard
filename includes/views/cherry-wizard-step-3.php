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

/**
 * Hook fires on installation last step
 *
 * @hooked cherry_wizard_set_install_status - 10
 */
do_action( 'cherry_wizard_all_done' );

?>
<div class="wrap">
	<div class="<?php echo $cherry_wizard->ui_wrapper_class(); ?>">
		<div class="content-box install-finished_">
			<h3><?php _e( 'Congratulations', $cherry_wizard->slug ); ?></h3>
			<div class="install-finished-text_">
				<?php _e( 'Theme and content has been successfully installed', $cherry_wizard->slug ); ?>
			</div>
			<div class="install-finished-actions_">
				<a class="button-default_" href="<?php echo home_url(); ?>"><?php _e( 'View your site', $cherry_wizard->slug ); ?></a>
				<a class="button-primary_" href="<?php echo menu_page_url( 'options', false ); ?>" target="_parent"><?php _e( 'Go to Cherry Options', $cherry_wizard->slug ); ?></a>
			</div>
		</div>
	</div>
</div>