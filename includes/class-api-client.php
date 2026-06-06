<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocRenders_API_Client {

	const API_BASE        = 'https://www.docrenders.com';
	const USAGE_CACHE_TTL = 300; // 5 minutes

	private string $api_key;

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Render an HTML string to PDF.
	 *
	 * @param string $html    Full HTML document to render.
	 * @param array  $options 'format', 'margin_top/right/bottom/left', 'landscape'
	 * @return string|WP_Error Raw PDF bytes on success.
	 */
	public function render_html( string $html, array $options = [] ) {
		$body = [ 'html' => $html ];
		$this->apply_options( $body, $options );
		return $this->post_render( $body );
	}

	/**
	 * Render a named template with data to PDF.
	 *
	 * @param string $template Template name (e.g. 'woo-invoice').
	 * @param array  $data     Template field values.
	 * @param array  $options  Same render options as render_html.
	 * @return string|WP_Error Raw PDF bytes on success.
	 */
	public function render_template( string $template, array $data, array $options = [] ) {
		$body = [
			'template' => $template,
			'data'     => $data,
		];
		$this->apply_options( $body, $options );
		return $this->post_render( $body );
	}

	/**
	 * Fetch current-period usage for the authenticated key.
	 *
	 * Cached for 5 minutes. Pass $force = true to bypass the cache.
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
	 * Defaults to false (free behavior) on error.
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

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private function post_render( array $body ) {
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

	private function apply_options( array &$body, array $options ): void {
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
	}
}
