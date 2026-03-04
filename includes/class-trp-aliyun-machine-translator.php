<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aliyun Machine Translation RPC engine.
 */
class TRP_Aliyun_Machine_Translator extends TRP_Machine_Translator {
	/**
	 * Translate strings using Aliyun MT API.
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

		if ( get_transient( 'trp_aliyun_translation_throttle' ) ) {
			return array();
		}

		$source_language = $this->map_language_code( $source_language_code );
		$target_language = $this->map_language_code( $target_language_code );
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
				$throttle_duration = (int) apply_filters( 'trp_aliyun_throttle_duration', 10 );
				set_transient( 'trp_aliyun_translation_throttle', true, max( 1, $throttle_duration ) );
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
	 * Send one request to Aliyun TranslateGeneral API.
	 *
	 * @param string $source_language
	 * @param string $target_language
	 * @param string $text
	 *
	 * @return array|WP_Error
	 */
	public function send_request( $source_language, $target_language, $text ) {
		$text   = html_entity_decode( (string) $text, ENT_QUOTES );
		$params = array(
			'AccessKeyId'      => (string) $this->get_api_key(),
			'Action'           => 'TranslateGeneral',
			'Format'           => 'JSON',
			'Version'          => '2018-10-12',
			'SignatureMethod'  => 'HMAC-SHA1',
			'SignatureVersion' => '1.0',
			'SignatureNonce'   => wp_generate_uuid4(),
			'Timestamp'        => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'RegionId'         => $this->get_region(),
			'FormatType'       => 'text',
			'SourceLanguage'   => $source_language,
			'TargetLanguage'   => $target_language,
			'SourceText'       => $text,
			'Scene'            => $this->get_scene(),
		);

		ksort( $params );
		$canonicalized_query_string = $this->build_canonicalized_query_string( $params );
		$string_to_sign             = 'POST&%2F&' . $this->percent_encode( $canonicalized_query_string );
		$signature                  = base64_encode(
			hash_hmac( 'sha1', $string_to_sign, $this->get_access_key_secret() . '&', true )
		);

		$params['Signature'] = $signature;

		return wp_remote_post(
			$this->get_endpoint(),
			array(
				'method'  => 'POST',
				'timeout' => 45,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => $params,
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
	 * Return AccessKeyId.
	 *
	 * @return string|false
	 */
	public function get_api_key() {
		if ( isset( $this->settings['trp_machine_translation_settings']['aliyun-access-key-id'] ) ) {
			$key = trim( (string) $this->settings['trp_machine_translation_settings']['aliyun-access-key-id'] );
			return '' !== $key ? $key : false;
		}
		return false;
	}

	/**
	 * Return AccessKeySecret.
	 *
	 * @return string
	 */
	public function get_access_key_secret() {
		return isset( $this->settings['trp_machine_translation_settings']['aliyun-access-key-secret'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['aliyun-access-key-secret'] )
			: '';
	}

	/**
	 * Return region.
	 *
	 * @return string
	 */
	public function get_region() {
		$region = isset( $this->settings['trp_machine_translation_settings']['aliyun-region-id'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['aliyun-region-id'] )
			: 'cn-hangzhou';
		return '' !== $region ? $region : 'cn-hangzhou';
	}

	/**
	 * Return scene.
	 *
	 * @return string
	 */
	public function get_scene() {
		$scene = isset( $this->settings['trp_machine_translation_settings']['aliyun-scene'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['aliyun-scene'] )
			: 'general';
		return '' !== $scene ? $scene : 'general';
	}

	/**
	 * Return API endpoint.
	 *
	 * @return string
	 */
	public function get_endpoint() {
		return 'https://mt.' . $this->get_region() . '.aliyuncs.com/';
	}

	/**
	 * Ensure AccessKeySecret exists.
	 *
	 * @param string $to_language
	 *
	 * @return bool
	 */
	public function extra_request_validations( $to_language ) {
		return '' !== $this->get_access_key_secret();
	}

	/**
	 * Return provider language whitelist for availability checks.
	 *
	 * @return array
	 */
	public function get_supported_languages() {
		return apply_filters( 'trp_aliyun_supported_languages', $this->get_provider_supported_language_codes() );
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
			$engine_codes[] = $this->map_language_code( $language );
		}
		return array_values( array_unique( $engine_codes ) );
	}

	/**
	 * Validate credentials.
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

		if ( 'aliyun_translate' === $translation_engine && 'yes' === $enabled ) {
			if ( isset( $this->correct_api_key ) && null !== $this->correct_api_key ) {
				return $this->correct_api_key;
			}

			if ( empty( $this->get_api_key() ) || '' === $this->get_access_key_secret() ) {
				$is_error       = true;
				$return_message = __( 'Please enter Aliyun AccessKeyId and AccessKeySecret.', 'translatepress-openrouter-engine' );
			} else {
				$response = $this->test_request();
				$code     = wp_remote_retrieve_response_code( $response );

				if ( 200 !== (int) $code ) {
					$is_error       = true;
					$return_message = __( 'Aliyun request failed. Please verify credentials and region.', 'translatepress-openrouter-engine' );
				} else {
					$body = is_array( $response ) && isset( $response['body'] ) ? json_decode( $response['body'], true ) : array();
					if ( isset( $body['Code'] ) && (string) $body['Code'] !== '200' ) {
						$is_error       = true;
						$return_message = isset( $body['Message'] ) ? (string) $body['Message'] : __( 'Aliyun API returned an error.', 'translatepress-openrouter-engine' );
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
	 * Parse Aliyun response.
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

		if ( isset( $body['Code'] ) && (string) $body['Code'] !== '200' ) {
			return $result;
		}

		$result['request_success'] = true;

		if ( isset( $body['Data']['Translated'] ) ) {
			$result['translated_text'] = (string) $body['Data']['Translated'];
			return $result;
		}

		if ( isset( $body['Translated'] ) ) {
			$result['translated_text'] = (string) $body['Translated'];
		}

		return $result;
	}

	/**
	 * Provider-supported Aliyun language codes.
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
			'zh',
			'zh-tw',
		);
	}

	/**
	 * Build canonicalized query string for RPC signing.
	 *
	 * @param array $params
	 *
	 * @return string
	 */
	private function build_canonicalized_query_string( $params ) {
		$pairs = array();
		foreach ( $params as $key => $value ) {
			$pairs[] = $this->percent_encode( $key ) . '=' . $this->percent_encode( (string) $value );
		}
		return implode( '&', $pairs );
	}

	/**
	 * Aliyun RFC3986 percent-encode variant.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	private function percent_encode( $value ) {
		return str_replace(
			array( '+', '*', '%7E' ),
			array( '%20', '%2A', '~' ),
			rawurlencode( (string) $value )
		);
	}

	/**
	 * Map TP language code to Aliyun language code.
	 *
	 * @param string $language_code
	 *
	 * @return string
	 */
	private function map_language_code( $language_code ) {
		$map = array(
			'zh_CN'       => 'zh',
			'zh_TW'       => 'zh-tw',
			'zh_HK'       => 'zh-tw',
			'de_DE_formal'=> 'de',
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
			'nb_NO'       => 'no',
		);

		if ( isset( $map[ $language_code ] ) ) {
			return $map[ $language_code ];
		}

		$iso_codes = $this->trp_languages->get_iso_codes( array( $language_code ), false );
		$fallback  = isset( $iso_codes[ $language_code ] ) ? $iso_codes[ $language_code ] : $language_code;
		return strtolower( str_replace( '_', '-', $fallback ) );
	}
}
