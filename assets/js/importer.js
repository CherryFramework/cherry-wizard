/**
 * Make redirect on import end
 */
jQuery(document).ready(function($) {
	$(document).on('cherry_data_manager_import_end', function(event) {
		event.preventDefault();
		window.location.replace( cherry_wizard_install_data.last_step );
	});
})