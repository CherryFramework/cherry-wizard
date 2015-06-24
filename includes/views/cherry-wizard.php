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
$nonce = wp_create_nonce( 'cherry_wizard' );
?>
<div class="wrap">
	<div class="<?php echo $cherry_wizard->ui_wrapper_class( array( 'cherry-wizard_' ) ); ?>">

		<h2 class="main-title_"><?php echo esc_html( get_admin_page_title() ); ?></h2>

		<?php
			if ( ! $cherry_wizard->is_compatible_wp_version() ) :
		?>
		<div class="box-default_ content-wrap_ warning-box_">
			<p>
				<?php _e( 'Please make sure your WordPress is upgraded to version <b>4.2.2</b> or later. This version contains vital security and stability updates required for the correct work of Cherry Framework.', $cherry_wizard->slug ); ?>
			</p>
		</div>
		<?php endif; ?>

		<div id="cherry-wizard-start-install-form" class="box-default_ content-wrap_">
			<!-- Premium installation form -->
			<form action="<?php echo add_query_arg( array( 'step' => 1, 'type' => 'premium' ), menu_page_url( $cherry_wizard->slug, false ) ); ?>" method="post" id="cherry-wizard-premium-install">
				<input type="hidden" name="_wpnonce" value="<?php echo $nonce; ?>">
				<div class="wizard-form-row_">
					<div class="wizard-form-col-label_">
						<label for="cherry-theme-name"><?php _e( 'Theme ID', $cherry_wizard->slug ); ?></label>
					</div>
					<div class="wizard-form-col-control_">
						<input type="text" name="theme_name" id="cherry-theme-name" value="" placeholder="<?php _e( 'Enter your theme ID', $cherry_wizard->slug ); ?>">
					</div>
				</div>
				<div class="wizard-form-row_">
					<div class="wizard-form-col-label_">
						<label for="cherry-theme-key"><?php _e( 'Activation Key', $cherry_wizard->slug ); ?></label>
					</div>
					<div class="wizard-form-col-control_">
						<input type="text" name="theme_key" id="cherry-theme-key" value="" placeholder="<?php _e( 'Enter theme activation key', $cherry_wizard->slug ); ?>">
					</div>
				</div>
				<div class="wizard-form-row_ submit-row_">
					<?php if ( $cherry_wizard->is_compatible_wp_version() ) : ?>
					<a href="#" class="button-primary_ fullwidth_" id="start_install_btn"><?php _e( 'Start', $cherry_wizard->slug ); ?></a>
					<?php endif; ?>
				</div>
				<div class="wizard-form-row_ info-row_">
					or <a id="install_demo_theme" href="#">try a demo theme</a>
				</div>
			</form>
			<!-- Premium installation form end -->

			<!-- Demo installation form -->
			<div id="cherry-wizard-demo-install" class="wizard-user-mail hidden_">
				<div class="wizard-form-row_">
					<div class="wizard-form-col-label_">
						<label for="cherry-theme-name"><?php _e( 'Email', $cherry_wizard->slug ); ?></label>
					</div>
					<div class="wizard-form-col-control_">
						<input type="email" value="" class="wizard-user-mail-input" placeholder="<?php _e( 'Enter your email address', $cherry_wizard->slug ); ?>">
					</div>
				</div>
				<div class="wizard-form-row_ submit-row_">
					<?php if ( $cherry_wizard->is_compatible_wp_version() ) : ?>
					<a href="#" data-href="<?php echo add_query_arg( array( 'step' => 1, 'type' => 'demo' ), menu_page_url( $cherry_wizard->slug, false ) ); ?>" class="button-primary_ fullwidth_" id="start_demo_install_btn"><?php _e( 'Start', $cherry_wizard->slug ); ?></a>
					<?php endif; ?>
					<div class="wizard-message hidden_">Please enter your email to proceed with demo theme installation</div>
				</div>

				<div class="wizard-form-row_ info-row_">
					<a id="install_premium_theme" href="#">Install premium theme</a>
				</div>
			</div>
			<!-- Demo installation form end -->

			<!-- Request sample data by Oreder ID -->
			<div class="wizard-restore-key hidden_" id="cherry-wizrd-restore-key">
				<?php _e( 'Get theme key', $cherry_wizard->slug ); ?>
				<input type="text" class="wizard-user-order" value="" placeholder="<?php _e( 'Order ID', $cherry_wizard->slug ); ?>">
				<a href="#" class="button-primary_" id="start_demo_install_btn"><?php _e( 'Request', $cherry_wizard->slug ); ?></a>
			</div>
			<!-- Request sample data by Oreder ID end -->
		</div>

	</div>
</div>