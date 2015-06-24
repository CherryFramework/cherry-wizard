<?php
/**
 * Add cherry theme installer skin
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
 * Theme Installer Skin for the Cherry Wizard Theme Installer.
 *
 * @package cherry_wizard
 * @since 1.0.0
 */
class Cherry_Theme_Installer_Skin extends Theme_Installer_Skin {
	public $api;
	public $type;

	public function __construct($args = array()) {
		$defaults = array( 'type' => 'web', 'url' => '', 'theme' => '', 'nonce' => '', 'title' => '' );
		$args = wp_parse_args($args, $defaults);

		$this->type = $args['type'];
		$this->api = isset($args['api']) ? $args['api'] : array();
		parent::__construct($args);
	}

	public function header() {
		if ( $this->done_header ) {
			return;
		}
		$this->done_header = true;
		$result = '';
		if ( $this->options['title'] ) {
			$result = '<h4>' . $this->options['title'] . '</h4>';
		}

		return $result;
	}

	public function footer() {
		if ( $this->done_footer ) {
			return;
		}
		$this->done_footer = true;
		$result = '';

		return $result;
	}

	public function before() {
		if ( !empty($this->api) && isset($this->upgrader->strings['process_success_specific']) ) {
			$this->upgrader->strings['process_success'] = sprintf( $this->upgrader->strings['process_success_specific'], $this->api->name, $this->api->version);
		}
	}

	public function after() {
		return '';
	}

	public function error($errors) {
		if ( ! $this->done_header )
			$this->header();
		if ( is_string($errors) ) {
			$this->feedback($errors);
		} elseif ( is_wp_error($errors) && $errors->get_error_code() ) {
			$result = '';
			foreach ( $errors->get_error_messages() as $message ) {
				if ( $errors->get_error_data() && is_string( $errors->get_error_data() ) )
					$result .= $this->feedback($message . ' ' . esc_html( strip_tags( $errors->get_error_data() ) ) );
				else
					$result .= $this->feedback($message);
			}
			return $result;
		}
	}

	public function feedback($string) {
		if ( isset( $this->upgrader->strings[$string] ) )
			$string = $this->upgrader->strings[$string];

		if ( strpos($string, '%') !== false ) {
			$args = func_get_args();
			$args = array_splice($args, 1);
			if ( $args ) {
				$args = array_map( 'strip_tags', $args );
				$args = array_map( 'esc_html', $args );
				$string = vsprintf($string, $args);
			}
		}
		if ( empty($string) )
			return;
		
		return '<p>' . $string . '</p>';

	}
}