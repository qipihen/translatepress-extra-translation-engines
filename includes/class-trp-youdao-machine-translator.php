<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Youdao Translation API v3 engine.
 */
class TRP_Youdao_Machine_Translator extends TRP_Machine_Translator {

	/**
	 * Translate strings with Youdao API.
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

		if ( get_transient( 'trp_youdao_translation_throttle' ) ) {
			return array();
		}

		$source_language = $this->map_language_code( $source_language_code, true );
		$target_language = $this->map_language_code( $target_language_code, false );
		$translated      = array();

		foreach ( $new_strings as $key => $old_string ) {
			$response = $this->send_request( $source_language, $target_language, $old_string );

			$this->machine_translator_logger->log(
				array(
					'strings'     => serialize( array( $old_string ) ),
					'response'    => serialize( $response ),
					'lang_source' => $source_language,
					'lang_target' => $target_language,
				)
			);

			$parsed_response = $this->parse_response_translation( $response );
			if ( empty( $parsed_response['request_success'] ) ) {
				$throttle_duration = (int) apply_filters( 'trp_youdao_throttle_duration', 10 );
				set_transient( 'trp_youdao_translation_throttle', true, max( 1, $throttle_duration ) );
				break;
			}

			$translated_value = $parsed_response['translated_text'];
			if ( '' === $translated_value ) {
				$translated_value = $old_string;
			} else {
				$this->machine_translator_logger->count_towards_quota( array( $old_string ) );
			}

			$translated[ $key ] = $translated_value;

			if ( $this->machine_translator_logger->quota_exceeded() ) {
				break;
			}
		}

		return $translated;
	}

	/**
	 * Send one text translation request to Youdao.
	 *
	 * @param string $source_language
	 * @param string $target_language
	 * @param string $text
	 *
	 * @return array|WP_Error
	 */
	public function send_request( $source_language, $target_language, $text ) {
		$text       = html_entity_decode( (string) $text, ENT_QUOTES );
		$app_key    = (string) $this->get_api_key();
		$app_secret = (string) $this->get_app_secret();
		$salt       = wp_generate_password( 16, false, false );
		$curtime    = (string) time();
		$input      = $this->truncate_input( $text );
		$sign       = hash( 'sha256', $app_key . $input . $salt . $curtime . $app_secret );

		$body = array(
			'q'        => $text,
			'from'     => $source_language,
			'to'       => $target_language,
			'appKey'   => $app_key,
			'salt'     => $salt,
			'sign'     => $sign,
			'signType' => 'v3',
			'curtime'  => $curtime,
		);

		return wp_remote_post(
			'https://openapi.youdao.com/api',
			array(
				'method'  => 'POST',
				'timeout' => 45,
				'body'    => $body,
			)
		);
	}

	/**
	 * Test credentials with one lightweight translation.
	 *
	 * @return array|WP_Error
	 */
	public function test_request() {
		return $this->send_request( 'en', 'zh-CHS', 'about' );
	}

	/**
	 * Return AppKey.
	 *
	 * @return string|false
	 */
	public function get_api_key() {
		if ( isset( $this->settings['trp_machine_translation_settings']['youdao-app-key'] ) ) {
			$key = trim( (string) $this->settings['trp_machine_translation_settings']['youdao-app-key'] );
			return '' !== $key ? $key : false;
		}
		return false;
	}

	/**
	 * Return AppSecret.
	 *
	 * @return string
	 */
	public function get_app_secret() {
		return isset( $this->settings['trp_machine_translation_settings']['youdao-app-secret'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['youdao-app-secret'] )
			: '';
	}

	/**
	 * Ensure AppSecret exists.
	 *
	 * @param string $to_language
	 *
	 * @return bool
	 */
	public function extra_request_validations( $to_language ) {
		return '' !== $this->get_app_secret();
	}

	/**
	 * Return provider language whitelist for availability checks.
	 *
	 * @return array
	 */
	public function get_supported_languages() {
		return apply_filters( 'trp_youdao_supported_languages', $this->get_provider_supported_language_codes() );
	}

	/**
	 * Convert TP language codes to Youdao codes for availability checks.
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
	 * Validate API key and secret.
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

		if ( 'youdao_translate' === $translation_engine && 'yes' === $enabled ) {
			if ( isset( $this->correct_api_key ) && null !== $this->correct_api_key ) {
				return $this->correct_api_key;
			}

			if ( empty( $this->get_api_key() ) || '' === $this->get_app_secret() ) {
				$is_error       = true;
				$return_message = __( 'Please enter Youdao AppKey and AppSecret.', 'translatepress-openrouter-engine' );
			} else {
				$response = $this->test_request();
				$code     = wp_remote_retrieve_response_code( $response );

				if ( 200 !== (int) $code ) {
					$is_error       = true;
					$return_message = __( 'Youdao request failed. Please verify credentials and network access.', 'translatepress-openrouter-engine' );
				} else {
					$response_body = is_array( $response ) && isset( $response['body'] ) ? json_decode( $response['body'], true ) : array();
					$error_code    = isset( $response_body['errorCode'] ) ? (string) $response_body['errorCode'] : '';
					if ( '' !== $error_code && '0' !== $error_code ) {
						$is_error       = true;
						$return_message = sprintf(
							/* translators: %s is provider error code. */
							__( 'Youdao API returned error code %s.', 'translatepress-openrouter-engine' ),
							$error_code
						);
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
	 * Parse Youdao response.
	 *
	 * @param mixed $response
	 *
	 * @return array
	 */
	private function parse_response_translation( $response ) {
		$result = array(
			'request_success' => false,
			'translated_text' => '',
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

		$error_code = isset( $body['errorCode'] ) ? (string) $body['errorCode'] : '';
		if ( '' !== $error_code && '0' !== $error_code ) {
			return $result;
		}

		$result['request_success'] = true;

		if ( ! empty( $body['translation'] ) && is_array( $body['translation'] ) ) {
			$result['translated_text'] = (string) $body['translation'][0];
		}

		return $result;
	}

	/**
	 * Provider-supported Youdao language codes.
	 *
	 * @return array
	 */
	private function get_provider_supported_language_codes() {
		return array(
			'ar',
			'de',
			'en',
			'es',
			'fr',
			'hi',
			'id',
			'it',
			'ja',
			'ko',
			'no',
			'pt',
			'ru',
			'th',
			'tr',
			'vi',
			'zh-CHS',
			'zh-CHT',
		);
	}

	/**
	 * Youdao truncate algorithm for sign generation.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private function truncate_input( $text ) {
		$length = mb_strlen( $text, 'UTF-8' );
		if ( $length <= 20 ) {
			return $text;
		}
		return mb_substr( $text, 0, 10, 'UTF-8' ) . $length . mb_substr( $text, -10, 10, 'UTF-8' );
	}

	/**
	 * Map language code to Youdao code.
	 *
	 * @param string $language_code
	 * @param bool   $is_source
	 *
	 * @return string
	 */
	private function map_language_code( $language_code, $is_source ) {
		$map = array(
			'zh_CN'       => 'zh-CHS',
			'zh_TW'       => 'zh-CHT',
			'zh_HK'       => 'zh-CHT',
			'de_DE_formal'=> 'de',
			'nb_NO'       => 'no',
			'en_US'       => 'en',
			'en_GB'       => 'en',
			'en_AU'       => 'en',
			'en_CA'       => 'en',
			'en_NZ'       => 'en',
			'en_ZA'       => 'en',
			'pt_BR'       => 'pt',
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
			'fr_FR'       => 'fr',
			'fr_CA'       => 'fr',
			'fr_BE'       => 'fr',
			'it_IT'       => 'it',
			'ru_RU'       => 'ru',
			'ja'          => 'ja',
			'ko_KR'       => 'ko',
			'id_ID'       => 'id',
			'th'          => 'th',
			'ar'          => 'ar',
			'tr_TR'       => 'tr',
			'hi_IN'       => 'hi',
			'vi'          => 'vi',
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
}
