<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class ApiClientTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Real-ish aliases for WP utility functions used by the client.
		Functions\when( 'is_wp_error' )->alias( fn( $v ) => $v instanceof WP_Error );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( fn( $r ) => $r['code'] ?? 0 );
		Functions\when( 'wp_remote_retrieve_body' )->alias( fn( $r ) => $r['body'] ?? '' );
		Functions\when( 'wp_remote_retrieve_response_message' )->alias( fn( $r ) => $r['message'] ?? '' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// render_html
	// -------------------------------------------------------------------------

	public function test_render_html_returns_pdf_bytes_on_success(): void {
		$fake_pdf = '%PDF-1.4 fake';
		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( [ 'code' => 200, 'body' => $fake_pdf ] );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$result = $client->render_html( '<h1>Test</h1>' );

		$this->assertSame( $fake_pdf, $result );
	}

	public function test_render_html_returns_wp_error_on_transport_failure(): void {
		$error = new WP_Error( 'http_request_failed', 'Could not connect.' );
		Functions\expect( 'wp_remote_post' )->once()->andReturn( $error );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$result = $client->render_html( '<h1>Test</h1>' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'http_request_failed', $result->get_error_code() );
	}

	public function test_render_html_returns_wp_error_on_quota_exceeded(): void {
		$body = json_encode( [ 'error' => [ 'code' => 'quota_exceeded', 'message' => 'Limit reached.' ] ] );
		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( [ 'code' => 429, 'body' => $body ] );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$result = $client->render_html( '<h1>Test</h1>' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'quota_exceeded', $result->get_error_code() );
		$this->assertSame( 'Limit reached.', $result->get_error_message() );
		$this->assertSame( 429, $result->get_error_data()['status'] );
	}

	public function test_render_html_returns_wp_error_on_server_error(): void {
		$body = json_encode( [ 'error' => [ 'code' => 'render_failed', 'message' => 'Chromium crashed.' ] ] );
		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( [ 'code' => 500, 'body' => $body ] );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$result = $client->render_html( '<h1>Test</h1>' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'render_failed', $result->get_error_code() );
	}

	public function test_render_html_handles_non_json_error_body_gracefully(): void {
		Functions\when( 'wp_remote_retrieve_response_message' )->alias( fn( $r ) => $r['message'] ?? '' );
		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( [ 'code' => 503, 'body' => 'Service Unavailable', 'message' => 'Service Unavailable' ] );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$result = $client->render_html( '<h1>Test</h1>' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'docrenders_error', $result->get_error_code() );
		$this->assertSame( 503, $result->get_error_data()['status'] );
	}

	public function test_render_html_sends_html_as_json_field(): void {
		$html = '<p>Hello world</p>';
		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				\Mockery::pattern( '#/render$#' ),
				\Mockery::on( function ( $args ) use ( $html ) {
					$body = json_decode( $args['body'], true );
					return $body['html'] === $html;
				} )
			)
			->andReturn( [ 'code' => 200, 'body' => '%PDF' ] );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$client->render_html( $html );
		$this->addToAssertionCount( 1 );
	}

	public function test_render_html_sends_authorization_header(): void {
		$key = 'dcr_live_abc123';
		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				\Mockery::any(),
				\Mockery::on( function ( $args ) use ( $key ) {
					return ( $args['headers']['Authorization'] ?? '' ) === 'Bearer ' . $key;
				} )
			)
			->andReturn( [ 'code' => 200, 'body' => '%PDF' ] );

		$client = new DocRenders_API_Client( $key );
		$client->render_html( '<p>Test</p>' );
		$this->addToAssertionCount( 1 );
	}

	public function test_render_html_passes_format_option(): void {
		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				\Mockery::any(),
				\Mockery::on( function ( $args ) {
					$body = json_decode( $args['body'], true );
					return ( $body['options']['format'] ?? '' ) === 'Letter';
				} )
			)
			->andReturn( [ 'code' => 200, 'body' => '%PDF' ] );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$client->render_html( '<p>Test</p>', [ 'format' => 'Letter' ] );
		$this->addToAssertionCount( 1 );
	}

	public function test_render_html_passes_landscape_option(): void {
		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				\Mockery::any(),
				\Mockery::on( function ( $args ) {
					$body = json_decode( $args['body'], true );
					return ( $body['options']['landscape'] ?? false ) === true;
				} )
			)
			->andReturn( [ 'code' => 200, 'body' => '%PDF' ] );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$client->render_html( '<p>Test</p>', [ 'landscape' => true ] );
		$this->addToAssertionCount( 1 );
	}

	// -------------------------------------------------------------------------
	// get_usage
	// -------------------------------------------------------------------------

	public function test_get_usage_returns_cached_data_without_http_call(): void {
		$cached = [ 'plan' => 'starter', 'renders_used' => 10, 'renders_limit' => 5000, 'renders_remaining' => 4990 ];
		Functions\when( 'get_transient' )->justReturn( $cached );
		Functions\expect( 'wp_remote_get' )->never();

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$result = $client->get_usage();

		$this->assertSame( $cached, $result );
	}

	public function test_get_usage_fetches_from_api_when_cache_empty(): void {
		$data = [ 'plan' => 'free', 'renders_used' => 5, 'renders_limit' => 100, 'renders_remaining' => 95 ];
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( [ 'code' => 200, 'body' => json_encode( $data ) ] );
		Functions\when( 'set_transient' )->justReturn( true );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$result = $client->get_usage();

		$this->assertSame( $data, $result );
	}

	public function test_get_usage_caches_successful_response(): void {
		$data = [ 'plan' => 'pro', 'renders_used' => 100, 'renders_limit' => 25000, 'renders_remaining' => 24900 ];
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_remote_get' )->justReturn( [ 'code' => 200, 'body' => json_encode( $data ) ] );
		Functions\expect( 'set_transient' )
			->once()
			->with( \Mockery::type( 'string' ), $data, DocRenders_API_Client::USAGE_CACHE_TTL );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$client->get_usage();
		$this->addToAssertionCount( 1 );
	}

	public function test_get_usage_bypasses_cache_when_forced(): void {
		$data = [ 'plan' => 'starter', 'renders_used' => 0, 'renders_limit' => 5000, 'renders_remaining' => 5000 ];
		Functions\expect( 'get_transient' )->never();
		Functions\when( 'wp_remote_get' )->justReturn( [ 'code' => 200, 'body' => json_encode( $data ) ] );
		Functions\when( 'set_transient' )->justReturn( true );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$result = $client->get_usage( true );

		$this->assertSame( $data, $result );
	}

	public function test_get_usage_returns_wp_error_on_transport_failure(): void {
		$error = new WP_Error( 'http_request_failed', 'Connection refused.' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_remote_get' )->justReturn( $error );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$result = $client->get_usage();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'http_request_failed', $result->get_error_code() );
	}

	public function test_get_usage_returns_wp_error_on_401(): void {
		$body = json_encode( [ 'error' => [ 'code' => 'unauthorized', 'message' => 'Invalid API key.' ] ] );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_remote_get' )->justReturn( [ 'code' => 401, 'body' => $body, 'message' => 'Unauthorized' ] );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$result = $client->get_usage();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'unauthorized', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	public function test_get_usage_sends_authorization_header(): void {
		$key  = 'dcr_live_xyz';
		$data = [ 'plan' => 'free', 'renders_used' => 0, 'renders_limit' => 100, 'renders_remaining' => 100 ];
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				\Mockery::any(),
				\Mockery::on( fn( $a ) => ( $a['headers']['Authorization'] ?? '' ) === 'Bearer ' . $key )
			)
			->andReturn( [ 'code' => 200, 'body' => json_encode( $data ) ] );
		Functions\when( 'set_transient' )->justReturn( true );

		$client = new DocRenders_API_Client( $key );
		$client->get_usage();
		$this->addToAssertionCount( 1 );
	}

	// -------------------------------------------------------------------------
	// is_paid_plan
	// -------------------------------------------------------------------------

	public function test_is_paid_plan_returns_false_for_free(): void {
		$data = [ 'plan' => 'free', 'renders_used' => 0, 'renders_limit' => 100, 'renders_remaining' => 100 ];
		Functions\when( 'get_transient' )->justReturn( $data );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$this->assertFalse( $client->is_paid_plan() );
	}

	public function test_is_paid_plan_returns_true_for_starter(): void {
		$data = [ 'plan' => 'starter', 'renders_used' => 10, 'renders_limit' => 5000, 'renders_remaining' => 4990 ];
		Functions\when( 'get_transient' )->justReturn( $data );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$this->assertTrue( $client->is_paid_plan() );
	}

	public function test_is_paid_plan_returns_true_for_pro(): void {
		$data = [ 'plan' => 'pro', 'renders_used' => 100, 'renders_limit' => 25000, 'renders_remaining' => 24900 ];
		Functions\when( 'get_transient' )->justReturn( $data );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$this->assertTrue( $client->is_paid_plan() );
	}

	public function test_is_paid_plan_returns_false_on_api_error(): void {
		$error = new WP_Error( 'http_request_failed', 'Timeout.' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_remote_get' )->justReturn( $error );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$this->assertFalse( $client->is_paid_plan() );
	}

	public function test_cache_key_differs_per_api_key(): void {
		$data = [ 'plan' => 'free', 'renders_used' => 0, 'renders_limit' => 100, 'renders_remaining' => 100 ];

		$seen_keys = [];
		Functions\when( 'get_transient' )->alias( function ( $key ) use ( &$seen_keys, $data ) {
			$seen_keys[] = $key;
			return $data;
		} );

		( new DocRenders_API_Client( 'key_a' ) )->get_usage();
		( new DocRenders_API_Client( 'key_b' ) )->get_usage();

		$this->assertCount( 2, array_unique( $seen_keys ), 'Different API keys must use different cache keys.' );
	}
}
