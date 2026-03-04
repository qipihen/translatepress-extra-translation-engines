<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tencent Cloud TMT engine.
 */
class TRP_Tencent_TMT_Machine_Translator extends TRP_Machine_Translator {
	const HOST    = 'tmt.tencentcloudapi.com';
	const SERVICE = 'tmt';
	const VERSION = '2018-03-21';
	const ACTION  = 'TextTranslate';

	/**
	 * Translate strings using Tencent TMT.
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

		if ( get_transient( 'trp_tencent_tmt_translation_throttle' ) ) {
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
				$throttle_duration = (int) apply_filters( 'trp_tencent_tmt_throttle_duration', 10 );
				set_transient( 'trp_tencent_tmt_translation_throttle', true, max( 1, $throttle_duration ) );
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
	 * Send one text translation request to Tencent TMT.
	 *
	 * @param string $source_language
	 * @param string $target_language
	 * @param string $text
	 *
	 * @return array|WP_Error
	 */
	public function send_request( $source_language, $target_language, $text ) {
		$text        = html_entity_decode( (string) $text, ENT_QUOTES );
		$timestamp   = time();
		$date        = gmdate( 'Y-m-d', $timestamp );
		$region      = $this->get_region();
		$payload     = array(
			'SourceText' => $text,
			'Source'     => $source_language,
			'Target'     => $target_language,
			'ProjectId'  => $this->get_project_id(),
		);
		$payload_json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		$canonical_headers = "content-type:application/json; charset=utf-8\n";
		$canonical_headers .= 'host:' . self::HOST . "\n";
		$canonical_headers .= 'x-tc-action:' . strtolower( self::ACTION ) . "\n";
		$signed_headers = 'content-type;host;x-tc-action';

		$hashed_request_payload = hash( 'sha256', $payload_json );
		$canonical_request      = "POST\n/\n\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $hashed_request_payload;
		$credential_scope       = $date . '/' . self::SERVICE . '/tc3_request';
		$string_to_sign         = "TC3-HMAC-SHA256\n" . $timestamp . "\n" . $credential_scope . "\n" . hash( 'sha256', $canonical_request );

		$secret_date    = hash_hmac( 'sha256', $date, 'TC3' . $this->get_secret_key(), true );
		$secret_service = hash_hmac( 'sha256', self::SERVICE, $secret_date, true );
		$secret_signing = hash_hmac( 'sha256', 'tc3_request', $secret_service, true );
		$signature      = hash_hmac( 'sha256', $string_to_sign, $secret_signing );

		$authorization = 'TC3-HMAC-SHA256 ';
		$authorization .= 'Credential=' . $this->get_api_key() . '/' . $credential_scope . ', ';
		$authorization .= 'SignedHeaders=' . $signed_headers . ', ';
		$authorization .= 'Signature=' . $signature;

		$headers = array(
			'Authorization' => $authorization,
			'Content-Type'  => 'application/json; charset=utf-8',
			'Host'          => self::HOST,
			'X-TC-Action'   => self::ACTION,
			'X-TC-Timestamp'=> (string) $timestamp,
			'X-TC-Version'  => self::VERSION,
			'X-TC-Region'   => $region,
		);

		$token = $this->get_token();
		if ( '' !== $token ) {
			$headers['X-TC-Token'] = $token;
		}

		return wp_remote_post(
			'https://' . self::HOST,
			array(
				'method'  => 'POST',
				'timeout' => 45,
				'headers' => $headers,
				'body'    => $payload_json,
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
	 * Return SecretId.
	 *
	 * @return string|false
	 */
	public function get_api_key() {
		if ( isset( $this->settings['trp_machine_translation_settings']['tencent-secret-id'] ) ) {
			$key = trim( (string) $this->settings['trp_machine_translation_settings']['tencent-secret-id'] );
			return '' !== $key ? $key : false;
		}
		return false;
	}

	/**
	 * Return SecretKey.
	 *
	 * @return string
	 */
	public function get_secret_key() {
		return isset( $this->settings['trp_machine_translation_settings']['tencent-secret-key'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['tencent-secret-key'] )
			: '';
	}

	/**
	 * Return optional token.
	 *
	 * @return string
	 */
	public function get_token() {
		return isset( $this->settings['trp_machine_translation_settings']['tencent-token'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['tencent-token'] )
			: '';
	}

	/**
	 * Return region.
	 *
	 * @return string
	 */
	public function get_region() {
		$region = isset( $this->settings['trp_machine_translation_settings']['tencent-region'] )
			? trim( (string) $this->settings['trp_machine_translation_settings']['tencent-region'] )
			: 'ap-guangzhou';

		return '' !== $region ? $region : 'ap-guangzhou';
	}

	/**
	 * Return project id.
	 *
	 * @return int
	 */
	public function get_project_id() {
		$project_id = isset( $this->settings['trp_machine_translation_settings']['tencent-project-id'] )
			? (int) $this->settings['trp_machine_translation_settings']['tencent-project-id']
			: 0;

		return max( 0, $project_id );
	}

	/**
	 * Ensure SecretKey exists.
	 *
	 * @param string $to_language
	 *
	 * @return bool
	 */
	public function extra_request_validations( $to_language ) {
		return '' !== $this->get_secret_key();
	}

	/**
	 * Return provider language whitelist for availability checks.
	 *
	 * @return array
	 */
	public function get_supported_languages() {
		return apply_filters( 'trp_tencent_tmt_supported_languages', $this->get_provider_supported_language_codes() );
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

		if ( 'tencent_tmt' === $translation_engine && 'yes' === $enabled ) {
			if ( isset( $this->correct_api_key ) && null !== $this->correct_api_key ) {
				return $this->correct_api_key;
			}

			if ( empty( $this->get_api_key() ) || '' === $this->get_secret_key() ) {
				$is_error       = true;
				$return_message = __( 'Please enter Tencent SecretId and SecretKey.', 'translatepress-openrouter-engine' );
			} else {
				$response = $this->test_request();
				$code     = wp_remote_retrieve_response_code( $response );

				if ( 200 !== (int) $code ) {
					$is_error       = true;
					$return_message = __( 'Tencent TMT request failed. Please verify credentials and region.', 'translatepress-openrouter-engine' );
				} else {
					$body = is_array( $response ) && isset( $response['body'] ) ? json_decode( $response['body'], true ) : array();
					if ( isset( $body['Response']['Error']['Message'] ) ) {
						$is_error       = true;
						$return_message = (string) $body['Response']['Error']['Message'];
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
	 * Parse Tencent response.
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

		if ( isset( $body['Response']['Error'] ) ) {
			return $result;
		}

		$result['request_success'] = true;

		if ( isset( $body['Response']['TargetText'] ) ) {
			$result['translated_text'] = (string) $body['Response']['TargetText'];
		}

		return $result;
	}

	/**
	 * Provider-supported Tencent TMT language codes.
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
			'zh-TW',
		);
	}

	/**
	 * Map TP language code to Tencent TMT language code.
	 *
	 * @param string $language_code
	 *
	 * @return string
	 */
	private function map_language_code( $language_code ) {
		$map = array(
			'zh_CN'       => 'zh',
			'zh_TW'       => 'zh-TW',
			'zh_HK'       => 'zh-TW',
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
