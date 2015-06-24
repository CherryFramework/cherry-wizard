/**
 * validate start installation form
 */
jQuery(document).ready(function($) {

	$(document).on('click', '#install_demo_theme', function(event) {
		event.preventDefault();
		$('#cherry-wizard-premium-install').addClass('hidden_');
		$('#cherry-wizard-demo-install').removeClass('hidden_');
	});

	$(document).on('click', '#install_premium_theme', function(event) {
		event.preventDefault();
		$('#cherry-wizard-premium-install').removeClass('hidden_');
		$('#cherry-wizard-demo-install').addClass('hidden_');
	});

	var _cw_button     = $('#start_install_btn'),
		_cw_form       = $('#cherry-wizard-premium-install'),
		_cw_theme_name = _cw_form.find('input[name="theme_name"]'),
		_cw_theme_key  = _cw_form.find('input[name="theme_key"]'),
		_cw_nonce      = _cw_form.find('input[name="_wpnonce"]');


	_cw_theme_name.focus(function(event) {
		if ( $(this).hasClass('error') ) {
			$(this).removeClass('error').parent().find('.wizard-popup-message').fadeOut('300');
		}
	});

	_cw_theme_key.focus(function(event) {
		if ( $(this).hasClass('error') ) {
			$(this).removeClass('error').parent().find('.wizard-popup-message').fadeOut('300');
		}
	});

	function cw_js_validate() {

		_cw_theme_name.removeClass('error');
		_cw_theme_key.removeClass('error');

		$( '.wizard-popup-message.error', _cw_form ).remove();

		var result = true;

		if ( _cw_theme_name.val() == null || _cw_theme_name.val() == '' ) {
			_cw_theme_name.addClass('error');
			result = false;
		}

		if ( _cw_theme_key.val() == null || _cw_theme_key.val() == '' ) {
			_cw_theme_key.addClass('error');
			result = false;
		}

		return result;
	}

	function cw_ajax_validate() {

		var _btn = $('#start_install_btn');

		_btn.after('<span class="spinner spinner-abs-left" style="display:inline-block; visibility:visible;"></span>');

		$.ajax({
			url: ajaxurl,
			type: "post",
			dataType: "json",
			data: {
				action: 'cherry_wizard_validate_key',
				nonce:  _cw_nonce.val(),
				theme:  _cw_theme_name.val(),
				key:    _cw_theme_key.val()
			}
		}).done(function(responce) {

			if ( 'object' != typeof(responce) ) {
				cw_result_message( 'Unknown error', 'error', 'success', 'success' );
			}

			if ( responce.type == undefined ) {
				_btn.next('.spinner').remove();
				return false;
			}

			if ( responce.type == 'error' ) {
				_cw_theme_name.addClass(responce.theme_class);
				_cw_theme_key.addClass(responce.key_class);
				cw_result_message( responce.message, responce.type, responce.theme_class, responce.key_class );
				_btn.next('.spinner').remove();
				return false;
			}
			cw_result_message( responce.message, responce.type );
			// redirect to next step on success
			window.location.replace( _cw_form.attr('action') );
		});

	}

	function cw_result_message( message, type, name_result, key_result ) {

		if ( key_result == 'error' ) {
			if ( _cw_theme_key.next('.wizard-popup-message').length ) {
				_cw_theme_key.next('.wizard-popup-message').remove();
			}
			_cw_theme_key.after('<div class="wizard-popup-message ' + type + '">' + message + '</div>');
			return;
		}

		if ( name_result == 'error' ) {
			if ( _cw_theme_name.next('.wizard-popup-message').length ) {
				_cw_theme_name.next('.wizard-popup-message').remove();
			}
			_cw_theme_name.after('<div class="wizard-popup-message ' + type + '">' + message + '</div>');
			return;
		}

		_cw_form.append('<div class="wizard-message ' + type + '">' + message + '</div>');
	}

	/**
	 * Validate theme key
	 */
	$(document).on('click', '#start_install_btn', function(event) {

		event.preventDefault();

		_cw_form.find('.wizard-message').remove();

		var validate = cw_js_validate();

		if ( false == validate ) {
			return false;
		}

		cw_ajax_validate();

	});

	/**
	 * Process demo installation start
	 */
	$(document).on('click', '#start_demo_install_btn', function(event) {
		event.preventDefault();
		var _mail_input = $('.wizard-user-mail-input'),
			_message    = $('.wizard-message'),
			_btn        = $(this),
			_href       = _btn.attr('data-href'),
			_mail       = _mail_input.val();

		_message.addClass('hidden_');

		if ( _mail == undefined || _mail == '' || _mail == null ) {
			_mail_input.addClass('error');
			_message.removeClass('hidden_').addClass('error');
			return;
		}

		_btn.after('<span class="spinner spinner-abs-left" style="display:inline-block; visibility:visible;"></span>');

		$.ajax({
			url: ajaxurl,
			type: "post",
			dataType: "json",
			data: {
				action: 'cherry_wizard_start_demo_install',
				mail: _mail
			}
		}).done(function(response) {
			_message.removeClass().addClass('wizard-message ' + response.type).html(response.message);
			_mail_input.addClass( response.type );

			if ( response.type == 'success' ) {
				window.location.replace( _href );
			} else {
				_btn.next('.spinner').remove();
			}
		})
	});
})