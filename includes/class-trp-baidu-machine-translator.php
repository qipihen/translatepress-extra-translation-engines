<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Baidu Fanyi API engine.
 */
class TRP_Baidu_Machine_Translator extends TRP_Machine_Translator {

	/**
	 * Translate strings using Baidu API.
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

		if ( get_transient( 'trp_baidu_translation_throttle' ) ) {
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
				$throttle_duration = (int) apply_filters( 'trp_baidu_throttle_duration', 10 );
				set_transient( 'trp_baidu_translation_throttle', true, max( 1, $throttle_duration ) );
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
	 * Send one text translation request to Baidu.
	 *
	 * @param string $source_language
	 * @param string $target_language
	 * @param string $text
	 *
	 * @return array|WP_Error
	 */
	public function send_request( $source_language, $target_language, $text ) {
		$text        = html_entity_decode( (string) $text, ENT_QUOTES );
		$app_id      = (string) $this->get_api_key();
		$app_secret  = (string) $this->get_app_secret();
		$salt        = (string) wp_rand( 100000, 999999 );
		$signature   = md5( $app_id . $text . $salt . $app_secret );

		$body = array(
			'q'     => $text,
			'from'  => $source_language,
			'to'    => $target_language,
			'appid' => $app_id,
			'salt'  => $salt,
			'sign'  => $signature,
		);

		return wp_remote_post(
			'https://fanyi-api.baidu.com/api/trans/vip/translate',
			array(
				'method'  => 'POST',
				'timeout' => 45,
				'body'    => $body,
			)
		);
	}

	/**
	 * Test credentials.
	 *
	 * @return array|WP_Error
	 */
	public function test_request() {
		return $this->send_request( 'en', 'zh', 'about' );
	}

	/**
	 * Return AppID.
	 *
	 * @return string|false
	 */
	public function get_api_key() {
		if ( isset( $this->settings['trp_machine_translation_settings']['baidu-app-id'] ) ) {
			$key = trim( (string) $this->settings['trp_machine_translation_settings']['baidu-app-id'] );
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
		return isset( $this->settings['trp_machine_translation_settings']['baidu-app-secret'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['baidu-app-secret'] )
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
		return apply_filters( 'trp_baidu_supported_languages', $this->get_provider_supported_language_codes() );
	}

	/**
	 * Convert TP language codes to provider codes for availability checks.
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

		if ( 'baidu_translate' === $translation_engine && 'yes' === $enabled ) {
			if ( isset( $this->correct_api_key ) && null !== $this->correct_api_key ) {
				return $this->correct_api_key;
			}

			if ( empty( $this->get_api_key() ) || '' === $this->get_app_secret() ) {
				$is_error       = true;
				$return_message = __( 'Please enter Baidu AppID and AppSecret.', 'translatepress-openrouter-engine' );
			} else {
				$response = $this->test_request();
				$code     = wp_remote_retrieve_response_code( $response );

				if ( 200 !== (int) $code ) {
					$is_error       = true;
					$return_message = __( 'Baidu request failed. Please verify credentials and network access.', 'translatepress-openrouter-engine' );
				} else {
					$response_body = is_array( $response ) && isset( $response['body'] ) ? json_decode( $response['body'], true ) : array();
					if ( isset( $response_body['error_code'] ) ) {
						$is_error       = true;
						$return_message = sprintf(
							/* translators: %s is provider error code. */
							__( 'Baidu API returned error code %s.', 'translatepress-openrouter-engine' ),
							(string) $response_body['error_code']
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
	 * Parse Baidu response.
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
		if ( ! is_array( $body ) || isset( $body['error_code'] ) ) {
			return $result;
		}

		$result['request_success'] = true;

		if ( isset( $body['trans_result'][0]['dst'] ) ) {
			$result['translated_text'] = (string) $body['trans_result'][0]['dst'];
		}

		return $result;
	}

	/**
	 * Provider-supported Baidu language codes.
	 *
	 * @return array
	 */
	private function get_provider_supported_language_codes() {
		return array(
			'ara',
			'bul',
			'cht',
			'cs',
			'dan',
			'de',
			'el',
			'en',
			'est',
			'fin',
			'fra',
			'hu',
			'it',
			'jp',
			'kor',
			'nl',
			'nor',
			'pl',
			'pt',
			'rom',
			'ru',
			'slo',
			'spa',
			'swe',
			'th',
			'vie',
			'zh',
		);
	}

	/**
	 * Map TP language code to Baidu language code.
	 *
	 * @param string $language_code
	 * @param bool   $is_source
	 *
	 * @return string
	 */
	private function map_language_code( $language_code, $is_source ) {
		$map = array(
			'zh_CN'       => 'zh',
			'zh_TW'       => 'cht',
			'zh_HK'       => 'cht',
			'en_US'       => 'en',
			'en_GB'       => 'en',
			'en_AU'       => 'en',
			'en_CA'       => 'en',
			'en_NZ'       => 'en',
			'en_ZA'       => 'en',
			'ja'          => 'jp',
			'ko_KR'       => 'kor',
			'fr_FR'       => 'fra',
			'fr_CA'       => 'fra',
			'fr_BE'       => 'fra',
			'es_ES'       => 'spa',
			'es_AR'       => 'spa',
			'es_CL'       => 'spa',
			'es_CO'       => 'spa',
			'es_CR'       => 'spa',
			'es_DO'       => 'spa',
			'es_EC'       => 'spa',
			'es_GT'       => 'spa',
			'es_MX'       => 'spa',
			'es_PE'       => 'spa',
			'es_PR'       => 'spa',
			'es_UY'       => 'spa',
			'es_VE'       => 'spa',
			'de_DE'       => 'de',
			'de_DE_formal'=> 'de',
			'it_IT'       => 'it',
			'ru_RU'       => 'ru',
			'pt_BR'       => 'pt',
			'pt_PT'       => 'pt',
			'pt_AO'       => 'pt',
			'pt_PT_ao90'  => 'pt',
			'ro_RO'       => 'rom',
			'sk_SK'       => 'slo',
			'nb_NO'       => 'nor',
			'vi'          => 'vie',
			'ar'          => 'ara',
			'th'          => 'th',
			'el'          => 'el',
			'nl_NL'       => 'nl',
			'nl_BE'       => 'nl',
			'pl_PL'       => 'pl',
			'hu_HU'       => 'hu',
			'bg_BG'       => 'bul',
			'et'          => 'est',
			'sv_SE'       => 'swe',
			'da_DK'       => 'dan',
			'fi'          => 'fin',
			'cs_CZ'       => 'cs',
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
