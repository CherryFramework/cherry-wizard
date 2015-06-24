<?php
/**
 * Add some service actions and filters
 *
 * @package   cherry_wizard
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Assign necessary function to hooks
 */

// Remove WooCommerce notice
add_action( 'cherry_wizard_required_plugin_active', 'cherry_wizard_skip_install_woocommerce_pages' );
// Save theme installation progress
add_action( 'cherry_wizard_theme_install_complete', 'cherry_wizard_theme_install_complete' );
// Save content installation progress
add_action( 'cherry_data_manager_install_complete', 'cherry_wizard_content_import_complete' );
// Show instllation progress HTML markup
add_action( 'cherry_wizard_progress_bar', 'cherry_wizard_install_progress' );
// Set import status on installation step #3
add_action( 'cherry_wizard_all_done', 'cherry_wizard_set_install_status' );
// Set skip sample data import URL
add_filter( 'cherry_data_manager_cancel_import_url', 'cherry_wizard_skip_sample_data_url' );


/**
 * Prevent woocommerce install pages notice appearing. Remove it right away after woocommerce installation
 *
 * @since  1.0.0
 * @param  string  $plugin  installed plugin slug
 */
function cherry_wizard_skip_install_woocommerce_pages( $plugin = null ) {

	// Do nothing if is not WooCommerce
	if ( !$plugin || 'woocommerce' != $plugin ) {
		return;
	}

	delete_option( '_wc_needs_pages' );
	delete_transient( '_wc_activation_redirect' );

}

/**
 * Set wizard installation step. Save theme install progress
 *
 * @since 1.0.0
 */
function cherry_wizard_theme_install_complete() {

	global $cherry_wizard;

	if ( !$cherry_wizard->cherry_theme_name ) {
		return;
	}

	$current_progress = get_option( 'cherry_wizard_install_log_' . $cherry_wizard->cherry_theme_name );

	$current_progress['theme_installed'] = 'complete';

	update_option( 'cherry_wizard_install_log_' . $cherry_wizard->cherry_theme_name, $current_progress );

}

/**
 * Set wizard installation step. Save content install progress
 *
 * @since 1.0.0
 */
function cherry_wizard_content_import_complete() {

	global $cherry_wizard;

	if ( !$cherry_wizard->cherry_theme_name ) {
		return;
	}

	$current_progress = get_option( 'cherry_wizard_install_log_' . $cherry_wizard->cherry_theme_name );

	$current_progress['content_installed'] = 'complete';

	update_option( 'cherry_wizard_install_log_' . $cherry_wizard->cherry_theme_name, $current_progress );

}

/**
 * Set install stataus on wizard lats step
 *
 * @since 1.0.0
 */
function cherry_wizard_set_install_status() {

	global $cherry_wizard;
	$current_progress = get_option( 'cherry_wizard_install_log_' . $cherry_wizard->cherry_theme_name );
	$skip_sample_data = isset( $_GET['skip_sample_data_install'] ) ? $_GET['skip_sample_data_install'] : false;

	if ( !isset( $current_progress['theme_installed'] ) || 'complete' != $current_progress['theme_installed'] ) {
		return;
	}

	if ( ( !isset( $current_progress['content_installed'] ) || 'complete' != $current_progress['theme_installed'] ) && !$skip_sample_data ) {
		return;
	}

	delete_option( 'cherry_wizard_need_install' );

}

/**
 * Show installation progress HTML markup
 *
 * @since 1.0.0
 *
 * @param boolean $is_plugins        is markup only for plugins section or not
 * @param array   $required_plugins  requierd plugins array
 */
function cherry_wizard_install_progress( $is_plugins = false, $required_plugins = array() ) {

	global $cherry_wizard;

	if ( $is_plugins ) {
		$result = '';

		if ( is_array( $required_plugins ) && !empty( $required_plugins ) ) {
			foreach ( $required_plugins as $plugin_name => $plugin_data ) {
				$result .= '<div class="wizard-install-progress-step empty" data-step="' . $plugin_name . '"></div>';
			}
		}

		return $result;
	}

	if ( !isset( $cherry_wizard->install_handler ) ) {
		return;
	}

	if ( !isset( $cherry_wizard->install_handler->install_groups ) || !is_array( $cherry_wizard->install_handler->install_groups ) ) {
		return;
	}

	echo '<div class="wizard-install-progress progress-bar_">';

	foreach ( $cherry_wizard->install_handler->install_groups as $install_group_name => $install_group ) {

		if ( !is_array( $install_group ) ) {
			continue;
		}

		echo '<div class="wizard-install-progress-group" data-group="' . $install_group_name . '" data-group-count="' . count( $install_group ) . '">';
			foreach ( $install_group as $step ) {
				echo '<div class="wizard-install-progress-step empty" data-step="' . $step . '"></div>';
			}
		echo '</div>';
	}

	echo '</div>';
	echo '<div class="progress-bar-counter_" id="theme-install-progress" data-progress="0"><span>0</span>% ' . __( 'complete', $cherry_wizard->slug ) . '</div>';

}

/**
 * Set skip sample data button url for Data Manager plugin
 *
 * @since  1.0.0
 *
 * @param  string $url default URL
 * @return string      new URL
 */
function cherry_wizard_skip_sample_data_url( $url ) {
	global $cherry_wizard;
	$type = isset( $_GET['type'] ) ? $_GET['type'] : 'demo';
	return add_query_arg( array( 'step' => 3, 'type' => $type, 'skip_sample_data_install' => true ), menu_page_url( $cherry_wizard->slug, false ) );
}