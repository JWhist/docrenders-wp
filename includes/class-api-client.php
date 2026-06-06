<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocRenders_API_Client {

	const API_BASE        = 'https://api.docrenders.dev/v1';
	const USAGE_CACHE_TTL = 300; // 5 minutes

	private string $api_key;

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Render an HTML string to PDF.
	 *
	 * Returns raw PDF bytes on success, WP_Error on failure.
	 * HTTP status 429 → WP_Error code 'quota_exceeded' or 'rate_limited'.
	 * HTTP status 402 → WP_Error code 'payment_required'.
	 *
	 * @param string $html    Full HTML document to render.
	 * @param array  $options Optional render options:
	 *                        'format'        string  A4 | Letter | Legal (default A4)
	 *                        'margin_top'    string  CSS value, e.g. "1in"
	 *                        'margin_right'  string
	 *                        'margin_bottom' string
	 *                        'margin_left'   string
	 *                        'landscape'     bool
	 * @return string|WP_Error
	 */
	public function render_html( string $html, array $options = [] ) {
		$body = [ 'html' => $html ];

		if ( ! empty( $options['format'] ) ) {
			$body['options']['format'] = $options['format'];
		}
		foreach ( [ 'margin_top', 'margin_right', 'margin_bottom', 'margin_left' ] as $margin ) {
			if ( ! empty( $options[ $margin ] ) ) {
				$body['options'][ $margin ] = $options[ $margin ];
			}
		}
		if ( isset( $options['landscape'] ) ) {
			$body['options']['landscape'] = (bool) $options['landscape'];
		}

		$response = wp_remote_post(
			self::API_BASE . '/render',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status      = wp_remote_retrieve_response_code( $response );
		$body_string = wp_remote_retrieve_body( $response );

		if ( 200 === $status ) {
			return $body_string;
		}

		$data    = json_decode( $body_string, true );
		$code    = $data['error']['code'] ?? 'docrenders_error';
		$message = $data['error']['message'] ?? wp_remote_retrieve_response_message( $response );

		return new WP_Error( $code, $message, [ 'status' => $status ] );
	}

	/**
	 * Fetch current-period usage for the authenticated key.
	 *
	 * Cached for 5 minutes. Pass $force = true to bypass the cache (e.g. after
	 * the settings page saves a new key).
	 *
	 * Returns an array with keys: plan, renders_used, renders_limit,
	 * renders_remaining, period_start, period_end.
	 *
	 * @param bool $force Skip the transient cache.
	 * @return array|WP_Error
	 */
	public function get_usage( bool $force = false ) {
		$cache_key = 'docrenders_usage_' . substr( md5( $this->api_key ), 0, 8 );

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$response = wp_remote_get(
			self::API_BASE . '/usage',
			[
				'headers' => [ 'Authorization' => 'Bearer ' . $this->api_key ],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status      = wp_remote_retrieve_response_code( $response );
		$body_string = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body_string, true );

		if ( 200 !== $status ) {
			$code    = $data['error']['code'] ?? 'docrenders_error';
			$message = $data['error']['message'] ?? wp_remote_retrieve_response_message( $response );
			return new WP_Error( $code, $message, [ 'status' => $status ] );
		}

		set_transient( $cache_key, $data, self::USAGE_CACHE_TTL );
		return $data;
	}

	/**
	 * Returns true if the API key belongs to a paid plan.
	 *
	 * Used to gate branding footer injection — free plan gets the footer,
	 * everything else does not. Defaults to false (free behavior) on error.
	 *
	 * @return bool
	 */
	public function is_paid_plan(): bool {
		$usage = $this->get_usage();
		if ( is_wp_error( $usage ) ) {
			return false;
		}
		return ( $usage['plan'] ?? 'free' ) !== 'free';
	}
}
