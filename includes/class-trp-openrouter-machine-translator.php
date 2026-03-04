<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenRouter/OpenAI-compatible engine for TranslatePress automatic translation.
 */
class TRP_OpenRouter_Machine_Translator extends TRP_Machine_Translator {

	/**
	 * Send one translation request.
	 *
	 * @param string $source_language_label
	 * @param string $target_language_label
	 * @param array  $strings_array
	 *
	 * @return array|WP_Error
	 */
	public function send_request( $source_language_label, $target_language_label, $strings_array ) {
		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are a translation engine. Translate each input text preserving order. Return valid JSON only. Output schema: {"translations":["..."]}. No markdown. No extra keys.',
			),
			array(
				'role'    => 'user',
				'content' => wp_json_encode(
					array(
						'task'            => 'translate',
						'source_language' => $source_language_label,
						'target_language' => $target_language_label,
						'texts'           => array_values( $strings_array ),
					),
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				),
			),
		);

		$payload = array(
			'model'       => $this->get_model(),
			'messages'    => $messages,
			'temperature' => $this->get_temperature(),
		);

		$payload = apply_filters(
			'trp_openrouter_request_payload',
			$payload,
			$source_language_label,
			$target_language_label,
			$strings_array,
			$this->settings
		);

		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->get_api_key(),
		);

		$site_url = $this->get_site_url_header_value();
		if ( ! empty( $site_url ) ) {
			$headers['HTTP-Referer'] = $site_url;
		}

		$site_name = $this->get_site_name_header_value();
		if ( ! empty( $site_name ) ) {
			$headers['X-Title'] = $site_name;
		}

		return wp_remote_post(
			$this->get_completion_endpoint(),
			array(
				'method'  => 'POST',
				'timeout' => 45,
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
			)
		);
	}

	/**
	 * Translate an array of strings.
	 *
	 * @param array       $new_strings
	 * @param string      $target_language_code
	 * @param string|null $source_language_code
	 *
	 * @return array
	 */
	public function translate_array( $new_strings, $target_language_code, $source_language_code = null ) {
		if ( null === $source_language_code ) {
			$source_language_code = $this->settings['default-language'];
		}

		if ( empty( $new_strings ) || ! $this->verify_request_parameters( $target_language_code, $source_language_code ) ) {
			return array();
		}

		if ( get_transient( 'trp_openrouter_translation_throttle' ) ) {
			return array();
		}

		$source_label = $this->get_language_label( $source_language_code );
		$target_label = $this->get_language_label( $target_language_code );
		$chunk_size   = $this->get_chunk_size();

		$translated_strings = array();
		$new_strings_chunks = array_chunk( $new_strings, $chunk_size, true );

		foreach ( $new_strings_chunks as $new_strings_chunk ) {
			$response = $this->send_request( $source_label, $target_label, $new_strings_chunk );

			$this->machine_translator_logger->log(
				array(
					'strings'     => serialize( $new_strings_chunk ),
					'response'    => serialize( $response ),
					'lang_source' => $source_label,
					'lang_target' => $target_label,
				)
			);

			$translated_chunk = $this->parse_translation_response( $response, $new_strings_chunk );

			if ( ! empty( $translated_chunk ) ) {
				$this->machine_translator_logger->count_towards_quota( $new_strings_chunk );
				foreach ( $new_strings_chunk as $key => $old_string ) {
					$translated_strings[ $key ] = isset( $translated_chunk[ $key ] ) ? $translated_chunk[ $key ] : $old_string;
				}
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( 429 === (int) $code ) {
				$throttle_duration = (int) apply_filters( 'trp_openrouter_throttle_duration', 10 );
				set_transient( 'trp_openrouter_translation_throttle', true, max( 1, $throttle_duration ) );
				break;
			}

			if ( $this->machine_translator_logger->quota_exceeded() ) {
				break;
			}
		}

		return $translated_strings;
	}

	/**
	 * Extract translated strings keyed by original chunk keys.
	 *
	 * @param mixed $response
	 * @param array $new_strings_chunk
	 *
	 * @return array
	 */
	private function parse_translation_response( $response, $new_strings_chunk ) {
		if ( ! is_array( $response ) || is_wp_error( $response ) ) {
			return array();
		}

		if ( ! isset( $response['response']['code'] ) || 200 !== (int) $response['response']['code'] ) {
			return array();
		}

		$response_data = json_decode( (string) $response['body'], true );
		if ( ! is_array( $response_data ) ) {
			return array();
		}

		$content = $this->extract_content_from_response( $response_data );
		if ( '' === $content ) {
			return array();
		}

		$parsed = TRP_OpenRouter_Response_Parser::parse_translations( $content, count( $new_strings_chunk ) );
		if ( empty( $parsed ) ) {
			return array();
		}

		$mapped = array();
		$i      = 0;
		foreach ( $new_strings_chunk as $key => $old_string ) {
			$mapped[ $key ] = isset( $parsed[ $i ] ) && '' !== $parsed[ $i ] ? $parsed[ $i ] : $old_string;
			$i++;
		}

		return $mapped;
	}

	/**
	 * Extract content field from chat completion response.
	 *
	 * @param array $response_data
	 *
	 * @return string
	 */
	private function extract_content_from_response( $response_data ) {
		if ( empty( $response_data['choices'][0]['message']['content'] ) ) {
			return '';
		}

		$content = $response_data['choices'][0]['message']['content'];

		if ( is_string( $content ) ) {
			return trim( $content );
		}

		// Some providers return content as structured blocks.
		if ( is_array( $content ) ) {
			$parts = array();
			foreach ( $content as $part ) {
				if ( is_array( $part ) && ! empty( $part['text'] ) ) {
					$parts[] = (string) $part['text'];
				} elseif ( is_string( $part ) ) {
					$parts[] = $part;
				}
			}
			return trim( implode( "\n", $parts ) );
		}

		return '';
	}

	/**
	 * Send a test request.
	 *
	 * @return array|WP_Error
	 */
	public function test_request() {
		return $this->send_request( 'English [en]', 'Spanish [es]', array( 'about' ) );
	}

	/**
	 * Return API key from settings.
	 *
	 * @return string|false
	 */
	public function get_api_key() {
		if ( isset( $this->settings['trp_machine_translation_settings']['openrouter-api-key'] ) ) {
			$key = trim( (string) $this->settings['trp_machine_translation_settings']['openrouter-api-key'] );
			return '' !== $key ? $key : false;
		}

		return false;
	}

	/**
	 * Return supported languages as ISO-like lowercase codes.
	 *
	 * @return array
	 */
	public function get_supported_languages() {
		$configured_supported_languages = $this->get_configured_supported_languages();
		if ( ! empty( $configured_supported_languages ) ) {
			return apply_filters( 'trp_openrouter_supported_languages', $configured_supported_languages );
		}

		$wp_languages = $this->trp_languages->get_wp_languages();
		$supported    = array();

		foreach ( $wp_languages as $wp_language ) {
			if ( ! empty( $wp_language['iso'] ) && is_array( $wp_language['iso'] ) ) {
				$supported[] = $this->normalize_language_code( reset( $wp_language['iso'] ) );
			}
		}

		$supported = array_values( array_unique( $supported ) );

		return apply_filters( 'trp_openrouter_supported_languages', $supported );
	}

	/**
	 * Return engine-specific language codes for selected languages.
	 *
	 * @param array $languages
	 *
	 * @return array
	 */
	public function get_engine_specific_language_codes( $languages ) {
		$iso_translation_codes    = $this->trp_languages->get_iso_codes( $languages, false );
		$engine_specific_languages = array();

		foreach ( $languages as $language ) {
			$code = isset( $iso_translation_codes[ $language ] ) ? $iso_translation_codes[ $language ] : $language;
			$engine_specific_languages[] = $this->normalize_language_code( $code );
		}

		return $engine_specific_languages;
	}

	/**
	 * Validate API key and endpoint by issuing one lightweight request.
	 *
	 * @return array
	 */
	public function check_api_key_validity() {
		$translation_engine = isset( $this->settings['trp_machine_translation_settings']['translation-engine'] )
			? $this->settings['trp_machine_translation_settings']['translation-engine']
			: '';

		$machine_translation_enabled = isset( $this->settings['trp_machine_translation_settings']['machine-translation'] )
			? $this->settings['trp_machine_translation_settings']['machine-translation']
			: 'no';

		$is_error       = false;
		$return_message = '';

		if ( 'openrouter' === $translation_engine && 'yes' === $machine_translation_enabled ) {
			if ( isset( $this->correct_api_key ) && null !== $this->correct_api_key ) {
				return $this->correct_api_key;
			}

			if ( empty( $this->get_api_key() ) ) {
				$is_error       = true;
				$return_message = __( 'Please enter your OpenRouter/API key.', 'translatepress-openrouter-engine' );
			} else {
				$response = $this->test_request();
				$code     = wp_remote_retrieve_response_code( $response );
				if ( 200 !== (int) $code ) {
					$body            = is_array( $response ) && isset( $response['body'] ) ? $response['body'] : '';
					$response_status = trp_or_response_codes( $code, $body );
					$is_error        = (bool) $response_status['error'];
					$return_message  = (string) $response_status['message'];
				}
			}

			$this->correct_api_key = array(
				'message' => $return_message,
				'error'   => $is_error,
			);
		}

		return array(
			'message' => $return_message,
			'error'   => $is_error,
		);
	}

	/**
	 * Resolve language label for prompt context.
	 *
	 * @param string $language_code
	 *
	 * @return string
	 */
	private function get_language_label( $language_code ) {
		$names = $this->trp_languages->get_language_names( array( $language_code ), 'english_name' );
		$name  = isset( $names[ $language_code ] ) ? $names[ $language_code ] : $language_code;

		$iso_codes = $this->trp_languages->get_iso_codes( array( $language_code ), false );
		$iso       = isset( $iso_codes[ $language_code ] ) ? $iso_codes[ $language_code ] : $language_code;

		return sprintf( '%s [%s]', $name, $this->normalize_language_code( $iso ) );
	}

	/**
	 * Get configured model.
	 *
	 * @return string
	 */
	private function get_model() {
		$model = isset( $this->settings['trp_machine_translation_settings']['openrouter-model'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['openrouter-model'] )
			: '';

		return '' !== $model ? $model : 'openai/gpt-4o-mini';
	}

	/**
	 * Get configured temperature.
	 *
	 * @return float
	 */
	private function get_temperature() {
		$temp = isset( $this->settings['trp_machine_translation_settings']['openrouter-temperature'] )
			? (float) $this->settings['trp_machine_translation_settings']['openrouter-temperature']
			: 0.0;

		if ( $temp < 0 ) {
			$temp = 0;
		}
		if ( $temp > 2 ) {
			$temp = 2;
		}

		return $temp;
	}

	/**
	 * Get configured chunk size.
	 *
	 * @return int
	 */
	private function get_chunk_size() {
		$size = isset( $this->settings['trp_machine_translation_settings']['openrouter-chunk-size'] )
			? (int) $this->settings['trp_machine_translation_settings']['openrouter-chunk-size']
			: 20;

		$size = max( 1, min( 100, $size ) );

		return (int) apply_filters( 'trp_openrouter_chunk_size', $size );
	}

	/**
	 * Build completion endpoint from base url + path.
	 *
	 * @return string
	 */
	private function get_completion_endpoint() {
		$base_url = isset( $this->settings['trp_machine_translation_settings']['openrouter-base-url'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['openrouter-base-url'] )
			: '';
		$path     = isset( $this->settings['trp_machine_translation_settings']['openrouter-api-path'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['openrouter-api-path'] )
			: '';

		if ( '' === $base_url ) {
			$base_url = 'https://openrouter.ai/api/v1';
		}
		if ( '' === $path ) {
			$path = '/chat/completions';
		}

		$base_url = untrailingslashit( $base_url );
		$path     = '/' . ltrim( $path, '/' );

		return $base_url . $path;
	}

	/**
	 * Optional HTTP-Referer header.
	 *
	 * @return string
	 */
	private function get_site_url_header_value() {
		$site_url = isset( $this->settings['trp_machine_translation_settings']['openrouter-site-url'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['openrouter-site-url'] )
			: '';

		if ( '' !== $site_url ) {
			return $site_url;
		}

		return $this->get_referer();
	}

	/**
	 * Optional X-Title header.
	 *
	 * @return string
	 */
	private function get_site_name_header_value() {
		$site_name = isset( $this->settings['trp_machine_translation_settings']['openrouter-site-name'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['openrouter-site-name'] )
			: '';

		if ( '' !== $site_name ) {
			return $site_name;
		}

		return get_bloginfo( 'name' );
	}

	/**
	 * Normalize language code for comparisons.
	 *
	 * @param string $language_code
	 *
	 * @return string
	 */
	private function normalize_language_code( $language_code ) {
		return strtolower( str_replace( '_', '-', (string) $language_code ) );
	}

	/**
	 * Read and normalize configured supported language whitelist.
	 *
	 * @return array
	 */
	private function get_configured_supported_languages() {
		$raw_whitelist = isset( $this->settings['trp_machine_translation_settings']['openrouter-language-whitelist'] )
			? (string) $this->settings['trp_machine_translation_settings']['openrouter-language-whitelist']
			: '';

		if ( '' === trim( $raw_whitelist ) ) {
			return array();
		}

		$parts = preg_split( '/[\s,;|]+/', $raw_whitelist );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$supported_languages = array();
		foreach ( $parts as $part ) {
			$code = $this->normalize_language_code( trim( (string) $part ) );
			$code = preg_replace( '/[^a-z0-9-]/', '', $code );

			if ( '' !== $code ) {
				$supported_languages[] = $code;
			}
		}

		return array_values( array_unique( $supported_languages ) );
	}
}
