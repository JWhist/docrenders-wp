<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class ContentPdfTest extends TestCase {

	private DocRenders_API_Client $client;
	private DocRenders_Content_PDF $pdf;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'esc_attr' )->alias( 'htmlspecialchars' );

		$this->client = $this->createMock( DocRenders_API_Client::class );
		$this->pdf    = new DocRenders_Content_PDF( $this->client );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// inject_button
	// -------------------------------------------------------------------------

	public function test_inject_button_appends_button_after_content_by_default(): void {
		$post = new WP_Post( [ 'ID' => 1, 'post_type' => 'post' ] );

		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_queried_object' )->justReturn( $post );
		Functions\when( 'get_option' )
			->alias( fn( $key, $default = null ) => match ( $key ) {
				'docrenders_post_types'       => [ 'post', 'page' ],
				'docrenders_button_placement' => 'after_content',
				'docrenders_button_label'     => 'Download PDF',
				default                       => $default,
			} );
		Functions\when( 'wp_create_nonce' )->justReturn( 'fake_nonce' );
		Functions\when( 'esc_attr' )->alias( 'htmlspecialchars' );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );

		$result = $this->pdf->inject_button( '<p>Content</p>' );

		$this->assertStringStartsWith( '<p>Content</p>', $result );
		$this->assertStringContainsString( 'docrenders-pdf-btn', $result );
	}

	public function test_inject_button_prepends_button_before_content(): void {
		$post = new WP_Post( [ 'ID' => 2, 'post_type' => 'post' ] );

		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_queried_object' )->justReturn( $post );
		Functions\when( 'get_option' )
			->alias( fn( $key, $default = null ) => match ( $key ) {
				'docrenders_post_types'       => [ 'post', 'page' ],
				'docrenders_button_placement' => 'before_content',
				'docrenders_button_label'     => 'Download PDF',
				default                       => $default,
			} );
		Functions\when( 'wp_create_nonce' )->justReturn( 'fake_nonce' );
		Functions\when( 'esc_attr' )->alias( 'htmlspecialchars' );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );

		$result = $this->pdf->inject_button( '<p>Content</p>' );

		$this->assertStringContainsString( 'docrenders-pdf-btn', $result );
		$this->assertStringEndsWith( '<p>Content</p>', $result );
	}

	public function test_inject_button_skips_injection_for_shortcode_placement(): void {
		$post = new WP_Post( [ 'ID' => 3, 'post_type' => 'post' ] );

		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_queried_object' )->justReturn( $post );
		Functions\when( 'get_option' )
			->alias( fn( $key, $default = null ) => match ( $key ) {
				'docrenders_post_types'       => [ 'post', 'page' ],
				'docrenders_button_placement' => 'shortcode',
				default                       => $default,
			} );

		$result = $this->pdf->inject_button( '<p>Content</p>' );

		$this->assertSame( '<p>Content</p>', $result );
	}

	public function test_inject_button_returns_content_unchanged_on_non_singular(): void {
		Functions\when( 'is_singular' )->justReturn( false );

		$result = $this->pdf->inject_button( '<p>Archive content</p>' );

		$this->assertSame( '<p>Archive content</p>', $result );
	}

	public function test_inject_button_skips_disabled_post_type(): void {
		$post = new WP_Post( [ 'ID' => 4, 'post_type' => 'product' ] );

		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_queried_object' )->justReturn( $post );
		Functions\when( 'get_option' )
			->alias( fn( $key, $default = null ) => match ( $key ) {
				'docrenders_post_types' => [ 'post', 'page' ],
				default                 => $default,
			} );

		$result = $this->pdf->inject_button( '<p>Content</p>' );

		$this->assertSame( '<p>Content</p>', $result );
	}

	public function test_inject_button_returns_content_unchanged_when_queried_object_is_not_post(): void {
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_queried_object' )->justReturn( new stdClass() );

		$result = $this->pdf->inject_button( '<p>Content</p>' );

		$this->assertSame( '<p>Content</p>', $result );
	}

	// -------------------------------------------------------------------------
	// render_button
	// -------------------------------------------------------------------------

	public function test_render_button_contains_post_id(): void {
		Functions\when( 'get_option' )->justReturn( 'Download PDF' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'abc' );
		Functions\when( 'esc_attr' )->alias( 'htmlspecialchars' );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );

		$html = $this->pdf->render_button( 42 );

		$this->assertStringContainsString( 'data-post-id="42"', $html );
	}

	public function test_render_button_contains_nonce(): void {
		Functions\when( 'get_option' )->justReturn( 'Download PDF' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test_nonce_value' );
		Functions\when( 'esc_attr' )->alias( 'htmlspecialchars' );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );

		$html = $this->pdf->render_button( 1 );

		$this->assertStringContainsString( 'data-nonce="test_nonce_value"', $html );
	}

	public function test_render_button_uses_custom_label(): void {
		Functions\when( 'get_option' )->justReturn( 'Save as PDF' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'n' );
		Functions\when( 'esc_attr' )->alias( 'htmlspecialchars' );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );

		$html = $this->pdf->render_button( 1 );

		$this->assertStringContainsString( 'Save as PDF', $html );
	}

	public function test_render_button_escapes_nonce(): void {
		Functions\when( 'get_option' )->justReturn( 'Download PDF' );
		Functions\when( 'wp_create_nonce' )->justReturn( '"<bad>' );
		Functions\when( 'esc_attr' )->alias( 'htmlspecialchars' );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );

		$html = $this->pdf->render_button( 1 );

		$this->assertStringNotContainsString( '"<bad>', $html );
	}

	public function test_render_button_includes_aria_live_message_span(): void {
		Functions\when( 'get_option' )->justReturn( 'Download PDF' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'n' );
		Functions\when( 'esc_attr' )->alias( 'htmlspecialchars' );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );

		$html = $this->pdf->render_button( 1 );

		$this->assertStringContainsString( 'aria-live="polite"', $html );
	}

	// -------------------------------------------------------------------------
	// build_post_data
	// -------------------------------------------------------------------------

	private function call_build_post_data( WP_Post $post ): array {
		$method = new ReflectionMethod( DocRenders_Content_PDF::class, 'build_post_data' );
		$method->setAccessible( true );
		return $method->invoke( $this->pdf, $post );
	}

	public function test_build_post_data_includes_title_and_content(): void {
		$post = new WP_Post( [ 'ID' => 1, 'post_content' => 'Raw.', 'post_author' => 1 ] );

		Functions\when( 'get_the_title' )->justReturn( 'My Post' );
		Functions\when( 'apply_filters' )->justReturn( '<p>Filtered.</p>' );
		Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
		Functions\when( 'get_the_date' )->justReturn( 'June 6, 2026' );
		Functions\when( 'get_the_author_meta' )->justReturn( 'Jane Smith' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/my-post/' );
		Functions\when( 'get_the_post_thumbnail_url' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'get_theme_mod' )->justReturn( false );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );

		$data = $this->call_build_post_data( $post );

		$this->assertSame( 'My Post', $data['title'] );
		$this->assertSame( '<p>Filtered.</p>', $data['content'] );
	}

	public function test_build_post_data_includes_meta_fields(): void {
		$post = new WP_Post( [ 'ID' => 5, 'post_content' => '', 'post_author' => 2 ] );

		Functions\when( 'get_the_title' )->justReturn( 'T' );
		Functions\when( 'apply_filters' )->justReturn( '' );
		Functions\when( 'get_bloginfo' )->justReturn( 'Acme Blog' );
		Functions\when( 'get_the_date' )->justReturn( 'January 1, 2026' );
		Functions\when( 'get_the_author_meta' )->justReturn( 'John Doe' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/t/' );
		Functions\when( 'get_the_post_thumbnail_url' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'get_theme_mod' )->justReturn( false );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );

		$data = $this->call_build_post_data( $post );

		$this->assertSame( 'Acme Blog', $data['site_name'] );
		$this->assertSame( 'January 1, 2026', $data['post_date'] );
		$this->assertSame( 'John Doe', $data['author'] );
		$this->assertSame( 'https://example.com/t/', $data['url'] );
	}

	public function test_build_post_data_includes_featured_image_when_present(): void {
		$post = new WP_Post( [ 'ID' => 7, 'post_content' => '', 'post_author' => 1 ] );

		Functions\when( 'get_the_title' )->justReturn( 'T' );
		Functions\when( 'apply_filters' )->justReturn( '' );
		Functions\when( 'get_bloginfo' )->justReturn( '' );
		Functions\when( 'get_the_date' )->justReturn( '' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'get_permalink' )->justReturn( '' );
		Functions\when( 'get_the_post_thumbnail_url' )->justReturn( 'https://example.com/image.jpg' );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'get_theme_mod' )->justReturn( false );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );

		$data = $this->call_build_post_data( $post );

		$this->assertSame( 'https://example.com/image.jpg', $data['featured_image'] );
	}

	public function test_build_post_data_omits_featured_image_when_absent(): void {
		$post = new WP_Post( [ 'ID' => 8, 'post_content' => '', 'post_author' => 1 ] );

		Functions\when( 'get_the_title' )->justReturn( 'T' );
		Functions\when( 'apply_filters' )->justReturn( '' );
		Functions\when( 'get_bloginfo' )->justReturn( '' );
		Functions\when( 'get_the_date' )->justReturn( '' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'get_permalink' )->justReturn( '' );
		Functions\when( 'get_the_post_thumbnail_url' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'get_theme_mod' )->justReturn( false );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );

		$data = $this->call_build_post_data( $post );

		$this->assertArrayNotHasKey( 'featured_image', $data );
	}

	public function test_build_post_data_includes_custom_css_when_set(): void {
		$post = new WP_Post( [ 'ID' => 9, 'post_content' => '', 'post_author' => 1 ] );

		Functions\when( 'get_the_title' )->justReturn( 'T' );
		Functions\when( 'apply_filters' )->justReturn( '' );
		Functions\when( 'get_bloginfo' )->justReturn( '' );
		Functions\when( 'get_the_date' )->justReturn( '' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'get_permalink' )->justReturn( '' );
		Functions\when( 'get_the_post_thumbnail_url' )->justReturn( false );
		Functions\when( 'get_option' )->alias( fn( $key, $default = null ) => match ( $key ) {
			'docrenders_custom_css' => 'body { font-size: 14pt; }',
			default                 => $default ?? '',
		} );
		Functions\when( 'get_theme_mod' )->justReturn( false );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );

		$data = $this->call_build_post_data( $post );

		$this->assertSame( 'body { font-size: 14pt; }', $data['custom_css'] );
	}

	public function test_build_post_data_omits_custom_css_when_empty(): void {
		$post = new WP_Post( [ 'ID' => 10, 'post_content' => '', 'post_author' => 1 ] );

		Functions\when( 'get_the_title' )->justReturn( 'T' );
		Functions\when( 'apply_filters' )->justReturn( '' );
		Functions\when( 'get_bloginfo' )->justReturn( '' );
		Functions\when( 'get_the_date' )->justReturn( '' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'get_permalink' )->justReturn( '' );
		Functions\when( 'get_the_post_thumbnail_url' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'get_theme_mod' )->justReturn( false );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );

		$data = $this->call_build_post_data( $post );

		$this->assertArrayNotHasKey( 'custom_css', $data );
	}

	public function test_build_post_data_content_runs_through_wp_filters(): void {
		$post = new WP_Post( [ 'ID' => 11, 'post_content' => 'raw', 'post_author' => 1 ] );

		Functions\when( 'get_the_title' )->justReturn( 'T' );
		Functions\when( 'get_bloginfo' )->justReturn( '' );
		Functions\when( 'get_the_date' )->justReturn( '' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'get_permalink' )->justReturn( '' );
		Functions\when( 'get_the_post_thumbnail_url' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'get_theme_mod' )->justReturn( false );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'the_content', 'raw' )
			->andReturn( '<p>processed</p>' );

		$data = $this->call_build_post_data( $post );

		$this->assertSame( '<p>processed</p>', $data['content'] );
	}

	public function test_build_post_data_includes_logo_when_custom_logo_set(): void {
		$post = new WP_Post( [ 'ID' => 12, 'post_content' => '', 'post_author' => 1 ] );

		Functions\when( 'get_the_title' )->justReturn( 'T' );
		Functions\when( 'apply_filters' )->justReturn( '' );
		Functions\when( 'get_bloginfo' )->justReturn( '' );
		Functions\when( 'get_the_date' )->justReturn( '' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'get_permalink' )->justReturn( '' );
		Functions\when( 'get_the_post_thumbnail_url' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'get_theme_mod' )->justReturn( 42 );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.com/logo.png' );

		$data = $this->call_build_post_data( $post );

		$this->assertSame( 'https://example.com/logo.png', $data['logo'] );
	}

	public function test_build_post_data_omits_logo_when_no_custom_logo(): void {
		$post = new WP_Post( [ 'ID' => 13, 'post_content' => '', 'post_author' => 1 ] );

		Functions\when( 'get_the_title' )->justReturn( 'T' );
		Functions\when( 'apply_filters' )->justReturn( '' );
		Functions\when( 'get_bloginfo' )->justReturn( '' );
		Functions\when( 'get_the_date' )->justReturn( '' );
		Functions\when( 'get_the_author_meta' )->justReturn( '' );
		Functions\when( 'get_permalink' )->justReturn( '' );
		Functions\when( 'get_the_post_thumbnail_url' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'get_theme_mod' )->justReturn( false );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );

		$data = $this->call_build_post_data( $post );

		$this->assertArrayNotHasKey( 'logo', $data );
	}

	// -------------------------------------------------------------------------
	// user_facing_message (private — tested via ReflectionMethod)
	// -------------------------------------------------------------------------

	private function call_user_facing_message( string $code, string $fallback ): string {
		$method = new ReflectionMethod( DocRenders_Content_PDF::class, 'user_facing_message' );
		$method->setAccessible( true );
		return $method->invoke( $this->pdf, $code, $fallback );
	}

	public function test_user_facing_message_quota_exceeded(): void {
		$result = $this->call_user_facing_message( 'quota_exceeded', 'fallback' );
		$this->assertStringContainsString( 'limit', strtolower( $result ) );
	}

	public function test_user_facing_message_rate_limited(): void {
		$result = $this->call_user_facing_message( 'rate_limited', 'fallback' );
		$this->assertStringContainsString( 'wait', strtolower( $result ) );
	}

	public function test_user_facing_message_returns_fallback_for_unknown_code(): void {
		$result = $this->call_user_facing_message( 'render_failed', 'Something went wrong.' );
		$this->assertSame( 'Something went wrong.', $result );
	}
}
