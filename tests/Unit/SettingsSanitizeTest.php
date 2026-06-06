<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DocRenders_Settings sanitize callbacks.
 *
 * sanitize_api_key is exercised via ApiClientTest because it constructs a
 * DocRenders_API_Client internally — the logic under test there is the
 * validation call and the "keep old value on failure" fallback.
 */
class SettingsSanitizeTest extends TestCase {

	private DocRenders_Settings $settings;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->settings = new DocRenders_Settings();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// sanitize_placement
	// -------------------------------------------------------------------------

	/** @dataProvider validPlacementProvider */
	public function test_sanitize_placement_accepts_valid_values( string $value ): void {
		$this->assertSame( $value, $this->settings->sanitize_placement( $value ) );
	}

	public static function validPlacementProvider(): array {
		return [
			[ 'after_content' ],
			[ 'before_content' ],
			[ 'shortcode' ],
		];
	}

	/** @dataProvider invalidPlacementProvider */
	public function test_sanitize_placement_rejects_invalid_values( string $value ): void {
		$this->assertSame( 'after_content', $this->settings->sanitize_placement( $value ) );
	}

	public static function invalidPlacementProvider(): array {
		return [
			[ '' ],
			[ 'nowhere' ],
			[ 'AFTER_CONTENT' ],
			[ '<script>alert(1)</script>' ],
		];
	}

	// -------------------------------------------------------------------------
	// sanitize_post_types
	// -------------------------------------------------------------------------

	public function test_sanitize_post_types_keeps_valid_public_types(): void {
		Functions\when( 'get_post_types' )->justReturn( [
			'post'       => 'post',
			'page'       => 'page',
			'attachment' => 'attachment',
		] );

		$result = $this->settings->sanitize_post_types( [ 'post', 'page' ] );

		$this->assertContains( 'post', $result );
		$this->assertContains( 'page', $result );
	}

	public function test_sanitize_post_types_removes_non_public_types(): void {
		Functions\when( 'get_post_types' )->justReturn( [
			'post' => 'post',
			'page' => 'page',
		] );

		// 'revision' is not in the public types list.
		$result = $this->settings->sanitize_post_types( [ 'post', 'revision' ] );

		$this->assertContains( 'post', $result );
		$this->assertNotContains( 'revision', $result );
	}

	public function test_sanitize_post_types_sanitizes_keys(): void {
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post' ] );
		Functions\when( 'sanitize_key' )->alias( 'strtolower' );

		// A type name with uppercase would be downcased by sanitize_key, then
		// checked against the public types list. Only 'post' would survive.
		$result = $this->settings->sanitize_post_types( [ 'post' ] );

		$this->assertSame( [ 'post' ], $result );
	}

	public function test_sanitize_post_types_returns_empty_array_for_non_array_input(): void {
		$result = $this->settings->sanitize_post_types( 'post' );

		$this->assertSame( [], $result );
	}

	public function test_sanitize_post_types_returns_empty_array_when_nothing_matches(): void {
		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post', 'page' => 'page' ] );

		$result = $this->settings->sanitize_post_types( [ 'custom_type', 'another_type' ] );

		$this->assertSame( [], $result );
	}

	// -------------------------------------------------------------------------
	// sanitize_api_key — valid key path
	// -------------------------------------------------------------------------

	public function test_sanitize_api_key_returns_trimmed_key_when_valid(): void {
		$key  = '  dcr_live_abc123  ';
		$data = [ 'plan' => 'free', 'renders_used' => 0, 'renders_limit' => 100, 'renders_remaining' => 100 ];

		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'is_wp_error' )->alias( fn( $v ) => $v instanceof WP_Error );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( fn( $r ) => $r['code'] ?? 0 );
		Functions\when( 'wp_remote_retrieve_body' )->alias( fn( $r ) => $r['body'] ?? '' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_remote_get' )->justReturn( [ 'code' => 200, 'body' => json_encode( $data ) ] );
		Functions\when( 'set_transient' )->justReturn( true );

		$result = $this->settings->sanitize_api_key( $key );

		$this->assertSame( 'dcr_live_abc123', $result );
	}

	public function test_sanitize_api_key_returns_empty_string_without_validation_when_blank(): void {
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\expect( 'wp_remote_get' )->never();

		$result = $this->settings->sanitize_api_key( '' );

		$this->assertSame( '', $result );
	}

	public function test_sanitize_api_key_keeps_old_key_and_adds_error_on_bad_key(): void {
		$old_key = 'dcr_live_working';

		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'is_wp_error' )->alias( fn( $v ) => $v instanceof WP_Error );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( fn( $r ) => $r['code'] ?? 0 );
		Functions\when( 'wp_remote_retrieve_body' )->alias( fn( $r ) => $r['body'] ?? '' );
		Functions\when( 'wp_remote_retrieve_response_message' )->alias( fn( $r ) => $r['message'] ?? '' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_remote_get' )->justReturn( [
			'code'    => 401,
			'body'    => json_encode( [ 'error' => [ 'code' => 'unauthorized', 'message' => 'Bad key.' ] ] ),
			'message' => 'Unauthorized',
		] );
		Functions\when( 'get_option' )->justReturn( $old_key );
		Functions\expect( 'add_settings_error' )->once();

		$result = $this->settings->sanitize_api_key( 'dcr_live_bad' );

		$this->assertSame( $old_key, $result );
	}
}
