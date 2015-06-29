/**
 * theme and plugins installer scripts
 */
jQuery(document).ready(function($) {

	$(document).on('click', '.wizard-installation_item_details', function(event) {

		event.preventDefault();
		var response = $(this).parent().next();

		if (response.hasClass('active') ) {
			response.removeClass('active');
			$(this).removeClass('active');
		} else {
			response.addClass('active');
			$(this).addClass('active');
		}

	});

	var step_event = jQuery.Event('cherry_wizard_step_response');

	function cherry_wizard_ajax_request() {

		var _item         = $('.wizard-installation .wizard-installation_item').last(),
			_is_last_step = _item.attr('data-is-last-step'),
			_step         = _item.attr('data-step'),
			_type         = $('.wizard-installation').attr('data-install-type');
			_plugin       = _item.attr('data-plugin');

		$.ajax({
			url: ajaxurl,
			type: "get",
			dataType: "json",
			data: {
				action: 'cherry_wizard_install_step',
				step: _step,
				type: _type,
				plugin: _plugin
			},
			error:function(response) {
				$('#cherry-wizard-retry-trigger').removeClass('hidden_');
				_item.parent().addClass('break');
				_item.addClass('item-error');
				return;
			}
		}).done(function(response) {

			jQuery(document).trigger( step_event, [_step, _plugin, response] );

			if ( response.type == 'success' || response.type == 'warning'  ) {
				_item.addClass('item-' + response.type);
				_item.find('.wizard-installation_item_responce').html(response.content);
				_item.parent().append(response.next_step);
				if ( _is_last_step == 'no' ) {
					cherry_wizard_ajax_request();
				}
			} else {
				_item.addClass('item-error');
				_item.find('.wizard-installation_item_response').html(response.content);
			}

			_item.find('.wizard-installation_item_details').removeClass('hidden_');

			if ( response.emergency_break && response.emergency_break == 'break' ) {
				$('#cherry-wizard-retry-trigger').removeClass('hidden_');
				_item.parent().addClass('break');
				return;
			}

			// go to sample data installation if this was last step
			if ( _is_last_step == 'yes' ) {
				var _href = $('.wizard-installation').attr('data-next-step');
				_item.after( '<div class="submit-wrap_">' + cherry_wizard_install_data.redirect_message + '</div>' );
				window.location.replace( _href );
			}
		})

	}

	function cherry_wizard_progress_item_length() {
		var _progress_bar  = $('.wizard-install-progress'),
			_progress_item = _progress_bar.find('.wizard-install-progress-group'),
			_count         = parseInt(_progress_item.length);

		if ( _count != 0 ) {
			var _width = 100/_count;
		} else {
			var _width = 100;
		}

		_progress_item.css('width', _width + '%').attr('data-group-width', _width);
	}

	function toFixed(value, precision) {
		var precision = precision || 0,
			power = Math.pow(10, precision),
			absValue = Math.abs(Math.round(value * power)),
			result = (value < 0 ? '-' : '') + String(Math.floor(absValue / power));

		if (precision > 0) {
			var fraction = String(absValue % power),
				padding = new Array(Math.max(precision - fraction.length, 0) + 1).join('0');
			result += '.' + padding + fraction;
		}
		return result;
	}

	function cherry_wizard_update_plugins_progress( step, response ) {

		if ( response.plugins_progress == '' ) {
			return 0;
		}

		if ( response.plugins_count != 0 ) {
			$('.wizard-install-progress').find('.wizard-install-progress-group[data-group="frontend_plugins"]').attr('data-group-count', response.plugins_count);
		}

		return response.plugins_progress;

	}

	// Run first install step if configuration ok
	if ( 'ok' == cherry_wizard_install_data.dir_permissions && 'ok' == cherry_wizard_install_data.server_settings ) {
		cherry_wizard_ajax_request();
	} else if ( 'error' == cherry_wizard_install_data.dir_permissions && 'error' == cherry_wizard_install_data.server_settings ) {
		$('.wizard-installation').remove();
	}

	$(document).on('click', '.wizard-run-install', function(event) {
		event.preventDefault();
		$('.wizard-installation').removeClass('manually-allow');
		$('.wizard-install-notices').addClass('hidden_');
		$('.wizard-install-todo').addClass('hidden_');
		cherry_wizard_ajax_request();
	});

	cherry_wizard_progress_item_length();

	// installation progress bar
	var plugins_progress = 0;
	$(document).on( 'cherry_wizard_step_response', function( event, step, plugin, response ) {
		event.preventDefault();

		if ( plugins_progress == 0 ) {
			plugins_progress = cherry_wizard_update_plugins_progress( step, response );
			if ( plugins_progress != 0 ) {
				$('.wizard-install-progress').find('.wizard-install-progress-group[data-group="frontend_plugins"]').html( plugins_progress );
			}
		}

		if ( step == 'install-plugins' && plugin != '' ) {
			var _step = $('.wizard-install-progress').find('.wizard-install-progress-step[data-step="' + plugin + '"]');
		} else {
			var _step = $('.wizard-install-progress').find('.wizard-install-progress-step[data-step="' + step + '"]');
		}

		var _group       = _step.parent(),
			_group_count = parseInt(_group.attr('data-group-count'));

		if ( _group_count == null || _group_count == 0 || _group_count == undefined ) {
			_group_count = 1;
		}

		var _width = 100/_group_count;

		_step.css('width', _width + '%').removeClass('empty');

		var _progress_counter = $('#theme-install-progress'),
			_prev_progress    = parseFloat( _progress_counter.attr('data-progress') ),
			_group_percent    = parseFloat( _group.attr('data-group-width') ),
			_progress_percent = parseFloat( _prev_progress + ( ( _group_percent * _width ) / 100 ) );

		if ( _step.length ) {
			_progress_percent = toFixed( _progress_percent, 2 );
			_progress_counter.find('span').html( parseFloat( _progress_percent ) );
			_progress_counter.attr( 'data-progress', _progress_percent );
		}
	});
});