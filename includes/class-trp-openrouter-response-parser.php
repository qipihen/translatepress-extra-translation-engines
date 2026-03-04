<?php

/**
 * Parses LLM output and extracts translation arrays in deterministic format.
 */
class TRP_OpenRouter_Response_Parser {

	/**
	 * Parse translations from raw model output.
	 *
	 * Supported outputs:
	 * - ["translated1", "translated2"]
	 * - {"translations":["translated1","translated2"]}
	 * - {"items":[{"id":0,"translation":"..."}, ...]}
	 *
	 * @param string $payload
	 * @param int    $expected_count
	 *
	 * @return array
	 */
	public static function parse_translations( $payload, $expected_count ) {
		if ( ! is_string( $payload ) ) {
			return array();
		}

		$payload = trim( $payload );
		if ( '' === $payload || $expected_count <= 0 ) {
			return array();
		}

		$candidates = array(
			$payload,
			self::strip_markdown_fence( $payload ),
		);

		$json_fragment = self::extract_json_fragment( $payload );
		if ( '' !== $json_fragment ) {
			$candidates[] = $json_fragment;
		}

		foreach ( $candidates as $candidate ) {
			$parsed = self::parse_candidate( $candidate, (int) $expected_count );
			if ( ! empty( $parsed ) ) {
				return $parsed;
			}
		}

		return array();
	}

	/**
	 * Parse one candidate JSON string into translation array.
	 *
	 * @param string $candidate
	 * @param int    $expected_count
	 *
	 * @return array
	 */
	private static function parse_candidate( $candidate, $expected_count ) {
		if ( '' === $candidate ) {
			return array();
		}

		$decoded = json_decode( $candidate, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$translations = self::extract_translations( $decoded );
		if ( count( $translations ) !== $expected_count ) {
			return array();
		}

		return $translations;
	}

	/**
	 * Extract translations from different JSON payload styles.
	 *
	 * @param array $decoded
	 *
	 * @return array
	 */
	private static function extract_translations( $decoded ) {
		// Direct array payload.
		if ( self::is_list_array( $decoded ) ) {
			return self::normalize_string_array( $decoded );
		}

		// Object payload with "translations".
		if ( isset( $decoded['translations'] ) && is_array( $decoded['translations'] ) ) {
			return self::normalize_string_array( $decoded['translations'] );
		}

		// Object payload with "items": [{"id":0,"translation":"..."}, ...]
		if ( isset( $decoded['items'] ) && is_array( $decoded['items'] ) ) {
			$items = array();
			foreach ( $decoded['items'] as $index => $row ) {
				if ( ! is_array( $row ) || ! array_key_exists( 'translation', $row ) ) {
					return array();
				}
				$id = isset( $row['id'] ) ? (int) $row['id'] : (int) $index;
				$items[] = array(
					'id'          => $id,
					'translation' => (string) $row['translation'],
				);
			}

			usort(
				$items,
				function ( $left, $right ) {
					if ( $left['id'] === $right['id'] ) {
						return 0;
					}
					return ( $left['id'] < $right['id'] ) ? -1 : 1;
				}
			);

			return array_values(
				array_map(
					function ( $row ) {
						return $row['translation'];
					},
					$items
				)
			);
		}

		return array();
	}

	/**
	 * Normalize scalar arrays to string arrays.
	 *
	 * @param array $values
	 *
	 * @return array
	 */
	private static function normalize_string_array( $values ) {
		$normalized = array();
		foreach ( $values as $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				return array();
			}
			$normalized[] = (string) $value;
		}
		return $normalized;
	}

	/**
	 * Strip markdown fenced code block wrappers.
	 *
	 * @param string $payload
	 *
	 * @return string
	 */
	private static function strip_markdown_fence( $payload ) {
		$payload = trim( $payload );
		if ( 0 === strpos( $payload, '```' ) ) {
			$payload = preg_replace( '/^```[a-zA-Z0-9_-]*\s*/', '', $payload );
			$payload = preg_replace( '/\s*```$/', '', $payload );
			return trim( $payload );
		}
		return $payload;
	}

	/**
	 * Extract probable JSON fragment from free-form text.
	 *
	 * @param string $payload
	 *
	 * @return string
	 */
	private static function extract_json_fragment( $payload ) {
		$payload = trim( $payload );
		$start   = strcspn( $payload, '{[' );
		if ( $start >= strlen( $payload ) ) {
			return '';
		}

		$fragment = substr( $payload, $start );
		$last_obj = strrpos( $fragment, '}' );
		$last_arr = strrpos( $fragment, ']' );
		$end_pos  = max( false === $last_obj ? -1 : $last_obj, false === $last_arr ? -1 : $last_arr );

		if ( $end_pos < 0 ) {
			return '';
		}

		return trim( substr( $fragment, 0, $end_pos + 1 ) );
	}

	/**
	 * Backport for array_is_list compatibility with older PHP.
	 *
	 * @param array $array
	 *
	 * @return bool
	 */
	private static function is_list_array( $array ) {
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
