<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Third-party proxy API engine (single-token mode).
 */
class TRP_Transhome_Machine_Translator extends TRP_Machine_Translator {

	/**
	 * Translate strings using the configured proxy batch endpoint.
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

		if ( get_transient( 'trp_transhome_translation_throttle' ) ) {
			return array();
		}

		$source_language = $this->map_language_code( $source_language_code, true );
		$target_language = $this->map_language_code( $target_language_code, false );
		$translated      = array();
		$chunks          = array_chunk( $new_strings, $this->get_chunk_size(), true );

		foreach ( $chunks as $chunk ) {
			$response = $this->send_batch_request( $source_language, $target_language, array_values( $chunk ) );

			$this->machine_translator_logger->log(
				array(
					'strings'     => serialize( $chunk ),
					'response'    => serialize( $response ),
					'lang_source' => $source_language,
					'lang_target' => $target_language,
				)
			);

			$parsed_response = $this->parse_batch_response( $response, count( $chunk ) );
			if ( empty( $parsed_response['request_success'] ) ) {
				$throttle_duration = (int) apply_filters( 'trp_transhome_throttle_duration', 10 );
				set_transient( 'trp_transhome_translation_throttle', true, max( 1, $throttle_duration ) );
				break;
			}

			$translations = $parsed_response['translated_texts'];
			$index        = 0;

			foreach ( $chunk as $key => $old_string ) {
				$translated_value = isset( $translations[ $index ] ) ? (string) $translations[ $index ] : '';
				if ( '' === $translated_value ) {
					$translated_value = $old_string;
				} else {
					$this->machine_translator_logger->count_towards_quota( array( $old_string ) );
				}

				$translated[ $key ] = $translated_value;
				$index++;
			}

			if ( $this->machine_translator_logger->quota_exceeded() ) {
				break;
			}
		}

		return $translated;
	}

	/**
	 * Send a batch request.
	 *
	 * @param string $source_language
	 * @param string $target_language
	 * @param array  $strings
	 *
	 * @return array|WP_Error
	 */
	public function send_batch_request( $source_language, $target_language, $strings ) {
		$body = array(
			'keywords'       => array_values( $strings ),
			'targetLanguage' => $target_language,
			'mimeType'       => $this->get_mime_type(),
		);

		if ( ! empty( $source_language ) && $source_language !== $target_language ) {
			$body['sourceLanguage'] = $source_language;
		}

		return wp_remote_post(
			$this->get_batch_endpoint(),
			array(
				'method'  => 'POST',
				'timeout' => 45,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
	}

	/**
	 * Lightweight request used by "Test API credentials".
	 *
	 * @return array|WP_Error
	 */
	public function test_request() {
		return $this->send_batch_request( 'en', 'zh-cn', array( 'about' ) );
	}

	/**
	 * Return token from settings.
	 *
	 * @return string|false
	 */
	public function get_api_key() {
		if ( isset( $this->settings['trp_machine_translation_settings']['transhome-token'] ) ) {
			$key = trim( (string) $this->settings['trp_machine_translation_settings']['transhome-token'] );
			return '' !== $key ? $key : false;
		}

		return false;
	}

	/**
	 * Expose supported language list for TP availability checks.
	 *
	 * @return array
	 */
	public function get_supported_languages() {
		$languages = isset( $this->settings['translation-languages'] ) && is_array( $this->settings['translation-languages'] )
			? $this->settings['translation-languages']
			: array();

		if ( ! empty( $this->settings['default-language'] ) && ! in_array( $this->settings['default-language'], $languages, true ) ) {
			$languages[] = $this->settings['default-language'];
		}

		$codes = $this->get_engine_specific_language_codes( $languages );

		return apply_filters( 'trp_transhome_supported_languages', $codes, $this->settings );
	}

	/**
	 * Convert TP language list into provider language list.
	 *
	 * @param array $languages
	 *
	 * @return array
	 */
	public function get_engine_specific_language_codes( $languages ) {
		$engine_codes = array();

		foreach ( $languages as $language ) {
			$engine_codes[] = $this->map_language_code( $language, false );
		}

		return array_values( array_unique( $engine_codes ) );
	}

	/**
	 * Validate token and endpoint settings.
	 *
	 * @return array
	 */
	public function check_api_key_validity() {
		$translation_engine = isset( $this->settings['trp_machine_translation_settings']['translation-engine'] )
			? $this->settings['trp_machine_translation_settings']['translation-engine']
			: '';

		$enabled = isset( $this->settings['trp_machine_translation_settings']['machine-translation'] )
			? $this->settings['trp_machine_translation_settings']['machine-translation']
			: 'no';

		$is_error       = false;
		$return_message = '';

		if ( 'transhome_translate' === $translation_engine && 'yes' === $enabled ) {
			if ( isset( $this->correct_api_key ) && null !== $this->correct_api_key ) {
				return $this->correct_api_key;
			}

			if ( empty( $this->get_api_key() ) ) {
				$is_error       = true;
				$return_message = __( 'Please enter proxy API token.', 'translatepress-openrouter-engine' );
			} else {
				$response = $this->test_request();
				$code     = wp_remote_retrieve_response_code( $response );

				if ( 200 !== (int) $code ) {
					$is_error       = true;
					$return_message = __( 'Proxy API request failed. Please verify token and endpoint.', 'translatepress-openrouter-engine' );
				} else {
					$body = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( ! is_array( $body ) ) {
						$is_error       = true;
						$return_message = __( 'Proxy API returned invalid JSON.', 'translatepress-openrouter-engine' );
					} elseif ( isset( $body['code'] ) && 1 !== (int) $body['code'] ) {
						$is_error       = true;
						$return_message = ! empty( $body['msg'] )
							? (string) $body['msg']
							: __( 'Proxy API returned an error.', 'translatepress-openrouter-engine' );
					}
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
	 * Parse batch response body.
	 *
	 * @param mixed $response
	 * @param int   $expected_count
	 *
	 * @return array
	 */
	private function parse_batch_response( $response, $expected_count ) {
		$result = array(
			'request_success' => false,
			'translated_texts'=> array(),
		);

		if ( ! is_array( $response ) || is_wp_error( $response ) ) {
			return $result;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $result;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return $result;
		}

		if ( isset( $body['code'] ) && 1 !== (int) $body['code'] ) {
			return $result;
		}

		$data = isset( $body['data'] ) ? $body['data'] : array();
		if ( is_string( $data ) ) {
			$decoded_data = json_decode( $data, true );
			if ( is_array( $decoded_data ) ) {
				$data = $decoded_data;
			}
		}

		$texts = array();
		if ( is_array( $data ) && isset( $data['text'] ) ) {
			if ( is_array( $data['text'] ) ) {
				foreach ( $data['text'] as $text ) {
					if ( is_scalar( $text ) ) {
						$texts[] = (string) $text;
					}
				}
			} elseif ( is_scalar( $data['text'] ) ) {
				$texts[] = (string) $data['text'];
			}
		} elseif ( is_array( $data ) && $this->is_list_array( $data ) ) {
			foreach ( $data as $text ) {
				if ( is_scalar( $text ) ) {
					$texts[] = (string) $text;
				}
			}
		}

		if ( empty( $texts ) ) {
			return $result;
		}

		if ( $expected_count > 1 ) {
			if ( 1 === count( $texts ) && false !== strpos( $texts[0], "\n" ) ) {
				$split = preg_split( "/\r\n|\n|\r/", $texts[0] );
				if ( is_array( $split ) ) {
					$split = array_map( 'trim', $split );
					$split = array_values( array_filter( $split, 'strlen' ) );
					if ( count( $split ) === $expected_count ) {
						$texts = $split;
					}
				}
			}

			if ( count( $texts ) !== $expected_count ) {
				return $result;
			}
		} else {
			$texts = array( (string) $texts[0] );
		}

		$result['request_success']  = true;
		$result['translated_texts'] = array_values( $texts );

		return $result;
	}

	/**
	 * Build full batch endpoint URL with token query.
	 *
	 * @return string
	 */
	private function get_batch_endpoint() {
		$base_url = untrailingslashit( $this->get_base_url() );
		$path     = '/' . ltrim( $this->get_api_path(), '/' );
		$url      = $base_url . $path;

		return add_query_arg( 'token', $this->get_api_key(), $url );
	}

	/**
	 * Base URL from settings.
	 *
	 * @return string
	 */
	private function get_base_url() {
		$value = isset( $this->settings['trp_machine_translation_settings']['transhome-base-url'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['transhome-base-url'] )
			: '';

		return '' !== $value ? $value : 'https://tb.trans-home.com';
	}

	/**
	 * Batch API path from settings.
	 *
	 * @return string
	 */
	private function get_api_path() {
		$value = isset( $this->settings['trp_machine_translation_settings']['transhome-api-path'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['transhome-api-path'] )
			: '';

		return '' !== $value ? $value : '/api/index/translateBatch';
	}

	/**
	 * MIME type mode.
	 *
	 * @return int
	 */
	private function get_mime_type() {
		$value = isset( $this->settings['trp_machine_translation_settings']['transhome-mime-type'] )
			? (int) $this->settings['trp_machine_translation_settings']['transhome-mime-type']
			: 0;

		return ( 1 === $value ) ? 1 : 0;
	}

	/**
	 * Request batch size.
	 *
	 * @return int
	 */
	private function get_chunk_size() {
		$size = (int) apply_filters( 'trp_transhome_chunk_size', 20 );

		if ( $size < 1 ) {
			$size = 1;
		}
		if ( $size > 100 ) {
			$size = 100;
		}

		return $size;
	}

	/**
	 * Map TP locale to proxy provider language code.
	 *
	 * @param string $language_code
	 * @param bool   $is_source
	 *
	 * @return string
	 */
	private function map_language_code( $language_code, $is_source ) {
		$map = array(
			'zh_CN'       => 'zh-cn',
			'zh_TW'       => 'zh-tw',
			'zh_HK'       => 'zh-tw',
			'de_DE_formal'=> 'de',
			'nb_NO'       => 'no',
			'en_US'       => 'en',
			'en_GB'       => 'en',
			'en_AU'       => 'en',
			'en_CA'       => 'en',
			'en_NZ'       => 'en',
			'en_ZA'       => 'en',
			'pt_PT'       => 'pt',
			'pt_AO'       => 'pt',
			'pt_PT_ao90'  => 'pt',
			'es_AR'       => 'es',
			'es_CL'       => 'es',
			'es_CO'       => 'es',
			'es_CR'       => 'es',
			'es_DO'       => 'es',
			'es_EC'       => 'es',
			'es_GT'       => 'es',
			'es_MX'       => 'es',
			'es_PE'       => 'es',
			'es_PR'       => 'es',
			'es_UY'       => 'es',
			'es_VE'       => 'es',
			'fr_CA'       => 'fr',
			'fr_BE'       => 'fr',
		);

		if ( isset( $map[ $language_code ] ) ) {
			return $map[ $language_code ];
		}

		$iso_codes = $this->trp_languages->get_iso_codes( array( $language_code ), false );
		$fallback  = isset( $iso_codes[ $language_code ] ) ? $iso_codes[ $language_code ] : $language_code;
		$fallback  = strtolower( str_replace( '_', '-', $fallback ) );

		if ( $is_source && '' === $fallback ) {
			return 'auto';
		}

		return $fallback;
	}

	/**
	 * Backport for list-array checks on older PHP.
	 *
	 * @param array $array
	 *
	 * @return bool
	 */
	private function is_list_array( $array ) {
		$expected = 0;
		foreach ( array_keys( $array ) as $key ) {
			if ( $key !== $expected ) {
				return false;
			}
			$expected++;
		}

		return true;
	}
}
