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

		Functions\when( 'wp_print_font_faces' )->justReturn( null );
		Functions\when( 'wp_get_global_stylesheet' )->justReturn( '' );

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
				'docrenders_post_types' => [ 'post', 'page' ], // 'product' not in list
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
	// build_html (private — tested via ReflectionMethod)
	// -------------------------------------------------------------------------

	private function call_build_html( WP_Post $post ): string {
		$method = new ReflectionMethod( DocRenders_Content_PDF::class, 'build_html' );
		$method->setAccessible( true );
		return $method->invoke( $this->pdf, $post );
	}

	public function test_build_html_includes_post_title(): void {
		$post = new WP_Post( [ 'ID' => 1, 'post_title' => 'My Post', 'post_content' => 'Hello.' ] );

		Functions\when( 'get_the_title' )->justReturn( 'My Post' );
		Functions\when( 'apply_filters' )->justReturn( 'Hello.' );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );

		$this->client->method( 'is_paid_plan' )->willReturn( true );

		$html = $this->call_build_html( $post );

		$this->assertStringContainsString( '<h1>My Post</h1>', $html );
		$this->assertStringContainsString( '<title>My Post</title>', $html );
	}

	public function test_build_html_includes_post_content(): void {
		$post = new WP_Post( [ 'ID' => 1, 'post_content' => 'Raw content.' ] );

		Functions\when( 'get_the_title' )->justReturn( 'Title' );
		Functions\when( 'apply_filters' )->justReturn( '<p>Filtered content.</p>' );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );

		$this->client->method( 'is_paid_plan' )->willReturn( true );

		$html = $this->call_build_html( $post );

		$this->assertStringContainsString( '<p>Filtered content.</p>', $html );
	}

	public function test_build_html_content_runs_through_wp_filters(): void {
		$post = new WP_Post( [ 'ID' => 1, 'post_content' => 'raw' ] );

		Functions\when( 'get_the_title' )->justReturn( 'T' );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'the_content', 'raw' )
			->andReturn( '<p>processed</p>' );

		$this->client->method( 'is_paid_plan' )->willReturn( true );

		$html = $this->call_build_html( $post );

		$this->assertStringContainsString( '<p>processed</p>', $html );
	}

	public function test_build_html_injects_branding_footer_on_free_plan(): void {
		$post = new WP_Post( [ 'ID' => 1, 'post_content' => '' ] );

		Functions\when( 'get_the_title' )->justReturn( 'T' );
		Functions\when( 'apply_filters' )->justReturn( '' );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );

		$this->client->method( 'is_paid_plan' )->willReturn( false );

		$html = $this->call_build_html( $post );

		$this->assertStringContainsString( 'docrenders.com', $html );
		$this->assertStringContainsString( 'position:fixed', $html );
	}

	public function test_build_html_omits_branding_footer_on_paid_plan(): void {
		$post = new WP_Post( [ 'ID' => 1, 'post_content' => '' ] );

		Functions\when( 'get_the_title' )->justReturn( 'T' );
		Functions\when( 'apply_filters' )->justReturn( '' );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );

		$this->client->method( 'is_paid_plan' )->willReturn( true );

		$html = $this->call_build_html( $post );

		$this->assertStringNotContainsString( 'PDF generated by', $html );
	}

	public function test_build_html_includes_custom_css(): void {
		$post = new WP_Post( [ 'ID' => 1, 'post_content' => '' ] );

		Functions\when( 'get_the_title' )->justReturn( 'T' );
		Functions\when( 'apply_filters' )->justReturn( '' );
		Functions\when( 'get_option' )->alias( fn( $key, $default = null ) => match ( $key ) {
			'docrenders_custom_css' => 'body { font-size: 14pt; }',
			default                 => $default ?? '',
		} );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );

		$this->client->method( 'is_paid_plan' )->willReturn( true );

		$html = $this->call_build_html( $post );

		$this->assertStringContainsString( 'body { font-size: 14pt; }', $html );
	}

	public function test_build_html_is_valid_html_document(): void {
		$post = new WP_Post( [ 'ID' => 1, 'post_content' => 'Hello.' ] );

		Functions\when( 'get_the_title' )->justReturn( 'Test' );
		Functions\when( 'apply_filters' )->justReturn( '<p>Hello.</p>' );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );

		$this->client->method( 'is_paid_plan' )->willReturn( true );

		$html = $this->call_build_html( $post );

		$this->assertStringContainsString( '<!DOCTYPE html>', $html );
		$this->assertStringContainsString( '<html>', $html );
		$this->assertStringContainsString( '<head>', $html );
		$this->assertStringContainsString( '<body>', $html );
		$this->assertStringContainsString( '</body></html>', $html );
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
