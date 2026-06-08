<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocRenders_Content_PDF {

	private DocRenders_API_Client $client;

	public function __construct( DocRenders_API_Client $client ) {
		$this->client = $client;
	}

	public function init(): void {
		add_filter( 'the_content', [ $this, 'inject_button' ] );
		add_shortcode( 'docrenders_pdf', [ $this, 'shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_docrenders_render', [ $this, 'handle_ajax' ] );
		add_action( 'wp_ajax_nopriv_docrenders_render', [ $this, 'handle_ajax' ] );
		add_action( 'init', [ $this, 'register_block' ] );
	}

	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		register_block_type(
			DOCRENDERS_DIR . 'blocks/pdf-button',
			[
				'render_callback' => function () {
					$post = get_queried_object();
					if ( ! $post instanceof WP_Post ) {
						return '';
					}
					return $this->render_button( $post->ID );
				},
			]
		);
	}

	// -------------------------------------------------------------------------
	// Button injection
	// -------------------------------------------------------------------------

	public function inject_button( string $content ): string {
		if ( ! is_singular() ) {
			return $content;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return $content;
		}

		if ( ! $this->post_type_enabled( $post->post_type ) ) {
			return $content;
		}

		$placement = get_option( 'docrenders_button_placement', 'shortcode' );
		if ( 'shortcode' === $placement ) {
			return $content;
		}

		$button = $this->render_button( $post->ID );

		return 'before_content' === $placement ? $button . $content : $content . $button;
	}

	public function shortcode( array $atts ): string {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return '';
		}
		return $this->render_button( $post->ID );
	}

	public function render_button( int $post_id ): string {
		$label = get_option( 'docrenders_button_label', 'Download PDF' );
		$nonce = wp_create_nonce( 'docrenders_render_' . $post_id );

		return sprintf(
			'<div class="docrenders-pdf-wrap"><button class="docrenders-pdf-btn" data-post-id="%d" data-nonce="%s">%s</button><span class="docrenders-pdf-msg" aria-live="polite"></span></div>',
			$post_id,
			esc_attr( $nonce ),
			esc_html( $label )
		);
	}

	// -------------------------------------------------------------------------
	// AJAX handler
	// -------------------------------------------------------------------------

	public function handle_ajax(): void {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! $post_id || ! wp_verify_nonce( $nonce, 'docrenders_render_' . $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Invalid request.' ], 403 );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( [ 'message' => 'Post not found.' ], 404 );
		}

		if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Access denied.' ], 403 );
		}

		if ( post_password_required( $post ) ) {
			wp_send_json_error( [ 'message' => 'Access denied.' ], 403 );
		}

		$data   = $this->build_post_data( $post );
		$result = $this->client->render_template( 'post', $data );

		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[DocRenders] PDF generation failed for post %d: %s', $post_id, $result->get_error_message() ) );
			$status  = $result->get_error_data()['status'] ?? 500;
			$message = $this->user_facing_message( $result->get_error_code(), $result->get_error_message() );
			wp_send_json_error( [ 'message' => $message ], $status );
		}

		$filename = sanitize_file_name( $post->post_name ?: (string) $post->ID ) . '.pdf';

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $result ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $result;
		exit;
	}

	// -------------------------------------------------------------------------
	// Post data builder
	// -------------------------------------------------------------------------

	public function build_post_data( WP_Post $post ): array {
		$data = [
			'title'      => get_the_title( $post ),
			'content'    => apply_filters( 'the_content', $post->post_content ),
			'site_name'  => get_bloginfo( 'name' ),
			'post_date'  => get_the_date( get_option( 'date_format' ), $post ),
			'author'     => get_the_author_meta( 'display_name', $post->post_author ),
			'url'        => get_permalink( $post->ID ),
		];

		$featured_image = get_the_post_thumbnail_url( $post->ID, 'large' );
		if ( $featured_image ) {
			$data['featured_image'] = $featured_image;
		}

		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
			if ( $logo_url ) {
				$data['logo'] = $logo_url;
			}
		}

		$custom_css = get_option( 'docrenders_custom_css', '' );
		if ( $custom_css ) {
			$data['custom_css'] = $custom_css;
		}

		return $data;
	}

	// -------------------------------------------------------------------------
	// Script enqueue
	// -------------------------------------------------------------------------

	public function enqueue_scripts(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( ! $this->post_type_enabled( $post->post_type ) ) {
			return;
		}

		wp_enqueue_style(
			'docrenders-front',
			DOCRENDERS_URL . 'assets/front.css',
			[],
			DOCRENDERS_VERSION
		);

		wp_enqueue_script(
			'docrenders-front',
			DOCRENDERS_URL . 'assets/front.js',
			[],
			DOCRENDERS_VERSION,
			true
		);

		wp_localize_script( 'docrenders-front', 'docrendersData', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function post_type_enabled( string $post_type ): bool {
		$enabled = (array) get_option( 'docrenders_post_types', [ 'post', 'page' ] );
		return in_array( $post_type, $enabled, true );
	}

	private function user_facing_message( string $code, string $fallback ): string {
		$messages = [
			'quota_exceeded' => 'This site has reached its PDF generation limit for this period.',
			'rate_limited'   => 'Please wait a moment and try again.',
		];
		return $messages[ $code ] ?? $fallback;
	}
}
