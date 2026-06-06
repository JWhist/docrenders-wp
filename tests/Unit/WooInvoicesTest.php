<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class WooInvoicesTest extends TestCase {

	private DocRenders_API_Client $client;
	private DocRenders_Woo_Invoices $woo;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'is_wp_error' )->alias( fn( $v ) => $v instanceof WP_Error );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Store' );
		Functions\when( 'date_i18n' )->justReturn( 'June 6, 2026' );
		Functions\when( 'error_log' )->justReturn( null );

		$this->client = $this->createMock( DocRenders_API_Client::class );
		$this->woo    = new DocRenders_Woo_Invoices( $this->client );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// build_template_data
	// -------------------------------------------------------------------------

	public function test_build_template_data_maps_billing_fields(): void {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_order_number' )->willReturn( '1042' );
		$order->method( 'get_date_created' )->willReturn( new WC_DateTime( 'June 4, 2026' ) );
		$order->method( 'get_payment_method_title' )->willReturn( 'Visa' );
		$order->method( 'get_formatted_billing_full_name' )->willReturn( 'Jane Smith' );
		$order->method( 'get_formatted_billing_address' )->willReturn( '123 Main St, Austin TX 78701' );
		$order->method( 'get_billing_email' )->willReturn( 'jane@example.com' );
		$order->method( 'get_billing_phone' )->willReturn( '+1 555 0100' );
		$order->method( 'get_meta' )->willReturn( '' );
		$order->method( 'has_shipping_address' )->willReturn( false );
		$order->method( 'get_items' )->willReturn( [] );
		$order->method( 'get_tax_totals' )->willReturn( [] );
		$order->method( 'get_subtotal' )->willReturn( '100.00' );
		$order->method( 'get_shipping_total' )->willReturn( '10.00' );
		$order->method( 'get_discount_total' )->willReturn( '0.00' );
		$order->method( 'get_total' )->willReturn( '110.00' );
		$order->method( 'get_total_tax' )->willReturn( '0.00' );
		$order->method( 'get_customer_note' )->willReturn( '' );
		$order->method( 'get_shipping_methods' )->willReturn( [] );

		$data = $this->woo->build_template_data( $order );

		$this->assertSame( '1042', $data['order_number'] );
		$this->assertSame( 'Jane Smith', $data['billing_name'] );
		$this->assertSame( '123 Main St, Austin TX 78701', $data['billing_address'] );
		$this->assertSame( 'jane@example.com', $data['billing_email'] );
		$this->assertSame( '+1 555 0100', $data['billing_phone'] );
		$this->assertSame( 'Visa', $data['payment_method'] );
		$this->assertSame( 'June 4, 2026', $data['order_date'] );
	}

	public function test_build_template_data_includes_shipping_address_when_different(): void {
		$order = $this->make_simple_order();
		$order->method( 'has_shipping_address' )->willReturn( true );
		$order->method( 'get_formatted_billing_address' )->willReturn( '1 Billing St' );
		$order->method( 'get_formatted_shipping_address' )->willReturn( '2 Shipping Ave' );

		$data = $this->woo->build_template_data( $order );

		$this->assertSame( '2 Shipping Ave', $data['shipping_address'] );
	}

	public function test_build_template_data_omits_shipping_address_when_same_as_billing(): void {
		$order = $this->make_simple_order();
		$order->method( 'has_shipping_address' )->willReturn( true );
		$order->method( 'get_formatted_billing_address' )->willReturn( '1 Same St' );
		$order->method( 'get_formatted_shipping_address' )->willReturn( '1 Same St' );

		$data = $this->woo->build_template_data( $order );

		$this->assertArrayNotHasKey( 'shipping_address', $data );
	}

	public function test_build_template_data_omits_shipping_address_when_no_shipping_address(): void {
		$order = $this->make_simple_order();
		$order->method( 'has_shipping_address' )->willReturn( false );

		$data = $this->woo->build_template_data( $order );

		$this->assertArrayNotHasKey( 'shipping_address', $data );
	}

	public function test_build_template_data_casts_monetary_values_to_float(): void {
		$order = $this->make_simple_order();
		$order->method( 'get_subtotal' )->willReturn( '99.99' );
		$order->method( 'get_shipping_total' )->willReturn( '5.00' );
		$order->method( 'get_discount_total' )->willReturn( '10.00' );
		$order->method( 'get_total' )->willReturn( '94.99' );

		$data = $this->woo->build_template_data( $order );

		$this->assertSame( 99.99, $data['subtotal'] );
		$this->assertSame( 5.0,   $data['shipping_cost'] );
		$this->assertSame( 10.0,  $data['discount'] );
		$this->assertSame( 94.99, $data['total'] );
	}

	// -------------------------------------------------------------------------
	// build_items
	// -------------------------------------------------------------------------

	public function test_build_items_maps_line_item_fields(): void {
		$product = $this->createMock( WC_Product::class );
		$product->method( 'get_sku' )->willReturn( 'WIDGET-001' );

		$item = $this->createMock( WC_Order_Item_Product::class );
		$item->method( 'get_name' )->willReturn( 'Widget Pro' );
		$item->method( 'get_product' )->willReturn( $product );
		$item->method( 'get_quantity' )->willReturn( 2 );
		$item->method( 'get_total' )->willReturn( '59.98' );
		$item->method( 'get_total_tax' )->willReturn( '4.95' );

		$order = $this->make_items_order( [ $item ], '4.95' );
		$result = $this->woo->build_items( $order );

		$this->assertCount( 1, $result['items'] );
		$this->assertSame( 'Widget Pro',  $result['items'][0]['name'] );
		$this->assertSame( 'WIDGET-001',  $result['items'][0]['sku'] );
		$this->assertSame( 2,             $result['items'][0]['qty'] );
		$this->assertSame( 29.99,         $result['items'][0]['unit_price'] );
		$this->assertSame( 59.98,         $result['items'][0]['total'] );
		$this->assertSame( 4.95,          $result['items'][0]['tax'] );
	}

	public function test_build_items_sets_has_sku_true_when_any_item_has_sku(): void {
		$product = $this->createMock( WC_Product::class );
		$product->method( 'get_sku' )->willReturn( 'SKU-123' );

		$item = $this->createMock( WC_Order_Item_Product::class );
		$item->method( 'get_product' )->willReturn( $product );
		$item->method( 'get_quantity' )->willReturn( 1 );
		$item->method( 'get_total' )->willReturn( '10.00' );
		$item->method( 'get_total_tax' )->willReturn( '0' );
		$item->method( 'get_name' )->willReturn( 'Item' );

		$result = $this->woo->build_items( $this->make_items_order( [ $item ] ) );

		$this->assertTrue( $result['has_sku'] );
	}

	public function test_build_items_sets_has_sku_false_when_no_items_have_sku(): void {
		$product = $this->createMock( WC_Product::class );
		$product->method( 'get_sku' )->willReturn( '' );

		$item = $this->createMock( WC_Order_Item_Product::class );
		$item->method( 'get_product' )->willReturn( $product );
		$item->method( 'get_quantity' )->willReturn( 1 );
		$item->method( 'get_total' )->willReturn( '10.00' );
		$item->method( 'get_total_tax' )->willReturn( '0' );
		$item->method( 'get_name' )->willReturn( 'Item' );

		$result = $this->woo->build_items( $this->make_items_order( [ $item ] ) );

		$this->assertFalse( $result['has_sku'] );
	}

	public function test_build_items_sets_has_tax_true_when_order_has_tax(): void {
		$result = $this->woo->build_items( $this->make_items_order( [], '8.50' ) );

		$this->assertTrue( $result['has_tax'] );
	}

	public function test_build_items_handles_item_without_product(): void {
		$item = $this->createMock( WC_Order_Item_Product::class );
		$item->method( 'get_product' )->willReturn( null );
		$item->method( 'get_quantity' )->willReturn( 1 );
		$item->method( 'get_total' )->willReturn( '20.00' );
		$item->method( 'get_total_tax' )->willReturn( '0' );
		$item->method( 'get_name' )->willReturn( 'Manual Item' );

		$result = $this->woo->build_items( $this->make_items_order( [ $item ] ) );

		$this->assertSame( '', $result['items'][0]['sku'] );
		$this->assertFalse( $result['has_sku'] );
	}

	// -------------------------------------------------------------------------
	// build_tax_lines
	// -------------------------------------------------------------------------

	public function test_build_tax_lines_maps_label_and_amount(): void {
		$tax         = new stdClass();
		$tax->label  = 'Sales Tax 8%';
		$tax->amount = '8.00';

		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_tax_totals' )->willReturn( [ $tax ] );

		$lines = $this->woo->build_tax_lines( $order );

		$this->assertCount( 1, $lines );
		$this->assertSame( 'Sales Tax 8%', $lines[0]['label'] );
		$this->assertSame( 8.0,            $lines[0]['amount'] );
	}

	public function test_build_tax_lines_returns_empty_array_when_no_tax(): void {
		$order = $this->make_simple_order();
		$order->method( 'get_tax_totals' )->willReturn( [] );

		$this->assertSame( [], $this->woo->build_tax_lines( $order ) );
	}

	// -------------------------------------------------------------------------
	// generate_pdf
	// -------------------------------------------------------------------------

	public function test_generate_pdf_returns_pdf_bytes_on_success(): void {
		Functions\when( 'add_option' )->justReturn( true );
		Functions\when( 'update_option' )->justReturn( true );

		$order = $this->make_order_with_invoice_number( '1001' );

		$this->client->method( 'render_template' )->willReturn( '%PDF-success' );

		global $wpdb;
		$wpdb = $this->make_wpdb_mock( 1, '1' );

		$result = $this->woo->generate_pdf( $order );

		$this->assertSame( '%PDF-success', $result );
	}

	public function test_generate_pdf_sets_limit_reached_and_stores_failed_order_on_quota_exceeded(): void {
		Functions\when( 'add_option' )->justReturn( true );
		Functions\expect( 'update_option' )
			->with( 'docrenders_limit_reached', true )
			->once();
		Functions\expect( 'update_option' )
			->with( 'docrenders_failed_invoices', \Mockery::any() )
			->once();

		$order = $this->make_order_with_invoice_number( '1002' );
		$order->method( 'get_id' )->willReturn( 1002 );

		Functions\when( 'get_option' )
			->alias( fn( $key, $default = null ) => match ( $key ) {
				'docrenders_failed_invoices' => [],
				default                      => $default ?? '',
			} );

		$error = new WP_Error( 'quota_exceeded', 'Limit reached.' );
		$this->client->method( 'render_template' )->willReturn( $error );

		global $wpdb;
		$wpdb = $this->make_wpdb_mock( 1, '1' );

		$result = $this->woo->generate_pdf( $order );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'quota_exceeded', $result->get_error_code() );
		$this->addToAssertionCount( 1 );
	}

	public function test_generate_pdf_clears_failed_order_and_limit_flag_on_success(): void {
		Functions\when( 'add_option' )->justReturn( true );
		Functions\expect( 'update_option' )
			->with( 'docrenders_failed_invoices', \Mockery::any() )
			->once();
		Functions\expect( 'update_option' )
			->with( 'docrenders_limit_reached', false )
			->once();

		$order = $this->make_order_with_invoice_number( '1003' );
		$order->method( 'get_id' )->willReturn( 1003 );

		Functions\when( 'get_option' )
			->alias( fn( $key, $default = null ) => match ( $key ) {
				'docrenders_failed_invoices' => [ 1003 ],
				default                      => $default ?? '',
			} );

		$this->client->method( 'render_template' )->willReturn( '%PDF-ok' );

		global $wpdb;
		$wpdb = $this->make_wpdb_mock( 1, '1' );

		$this->woo->generate_pdf( $order );
		$this->addToAssertionCount( 1 );
	}

	// -------------------------------------------------------------------------
	// store_failed_order / clear_failed_order
	// -------------------------------------------------------------------------

	public function test_store_failed_order_adds_to_list(): void {
		Functions\when( 'get_option' )
			->alias( fn( $key, $default = null ) => 'docrenders_failed_invoices' === $key ? [ 1, 2 ] : $default );

		Functions\expect( 'update_option' )
			->once()
			->with( 'docrenders_failed_invoices', \Mockery::on( fn( $v ) => in_array( 3, $v, true ) ) );

		$this->woo->store_failed_order( 3 );
		$this->addToAssertionCount( 1 );
	}

	public function test_store_failed_order_deduplicates(): void {
		Functions\when( 'get_option' )
			->alias( fn( $key, $default = null ) => 'docrenders_failed_invoices' === $key ? [ 5 ] : $default );

		Functions\expect( 'update_option' )
			->once()
			->with( 'docrenders_failed_invoices', [ 5 ] );

		$this->woo->store_failed_order( 5 );
		$this->addToAssertionCount( 1 );
	}

	public function test_clear_failed_order_removes_from_list(): void {
		Functions\when( 'get_option' )
			->alias( fn( $key, $default = null ) => 'docrenders_failed_invoices' === $key ? [ 1, 2, 3 ] : $default );

		Functions\expect( 'update_option' )
			->once()
			->with( 'docrenders_failed_invoices', \Mockery::on( fn( $v ) => ! in_array( 2, $v, true ) && in_array( 1, $v, true ) ) );

		$this->woo->clear_failed_order( 2 );
		$this->addToAssertionCount( 1 );
	}

	// -------------------------------------------------------------------------
	// attach_to_email
	// -------------------------------------------------------------------------

	public function test_attach_to_email_skips_non_completed_email(): void {
		$order = $this->make_simple_order();
		$this->client->expects( $this->never() )->method( 'render_template' );

		$result = $this->woo->attach_to_email( [], 'customer_processing_order', $order );

		$this->assertSame( [], $result );
	}

	public function test_attach_to_email_skips_when_trigger_is_manually(): void {
		Functions\when( 'get_option' )
			->alias( fn( $key, $default = null ) => 'docrenders_woo_trigger' === $key ? 'manually' : ( $default ?? '' ) );

		$order = $this->make_simple_order();
		$this->client->expects( $this->never() )->method( 'render_template' );

		$result = $this->woo->attach_to_email( [], 'customer_completed_order', $order );

		$this->assertSame( [], $result );
	}

	public function test_attach_to_email_returns_unchanged_attachments_when_wp_tempnam_fails(): void {
		Functions\when( 'add_option' )->justReturn( true );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'get_option' )
			->alias( fn( $key, $default = null ) => 'docrenders_woo_trigger' === $key ? 'on_complete' : ( $default ?? '' ) );
		Functions\when( 'wp_tempnam' )->justReturn( false );

		global $wpdb;
		$wpdb = $this->make_wpdb_mock( 1, '1' );

		$order = $this->make_order_with_invoice_number( '600' );
		$this->client->method( 'render_template' )->willReturn( '%PDF-ok' );

		$result = $this->woo->attach_to_email( [ 'prev.pdf' ], 'customer_completed_order', $order );

		$this->assertSame( [ 'prev.pdf' ], $result );
	}

	public function test_attach_to_email_returns_unchanged_attachments_on_pdf_error(): void {
		Functions\when( 'add_option' )->justReturn( true );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'get_option' )
			->alias( fn( $key, $default = null ) => match ( $key ) {
				'docrenders_woo_trigger'     => 'on_complete',
				'docrenders_failed_invoices' => [],
				default                      => $default ?? '',
			} );

		global $wpdb;
		$wpdb = $this->make_wpdb_mock( 1, '1' );

		$order = $this->make_order_with_invoice_number( '500' );
		$order->method( 'get_id' )->willReturn( 500 );

		$this->client->method( 'render_template' )->willReturn( new WP_Error( 'render_failed', 'Oops.' ) );

		$result = $this->woo->attach_to_email( [ 'existing.pdf' ], 'customer_completed_order', $order );

		$this->assertSame( [ 'existing.pdf' ], $result );
	}

	// -------------------------------------------------------------------------
	// ApiClient render_template
	// -------------------------------------------------------------------------

	public function test_api_client_render_template_sends_correct_body(): void {
		Functions\when( 'is_wp_error' )->alias( fn( $v ) => $v instanceof WP_Error );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( fn( $r ) => $r['code'] ?? 0 );
		Functions\when( 'wp_remote_retrieve_body' )->alias( fn( $r ) => $r['body'] ?? '' );

		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				\Mockery::any(),
				\Mockery::on( function ( $args ) {
					$body = json_decode( $args['body'], true );
					return $body['template'] === 'woo-invoice'
						&& isset( $body['data']['billing_name'] )
						&& $body['data']['billing_name'] === 'Jane';
				} )
			)
			->andReturn( [ 'code' => 200, 'body' => '%PDF' ] );

		$client = new DocRenders_API_Client( 'dcr_live_test' );
		$result = $client->render_template( 'woo-invoice', [ 'billing_name' => 'Jane' ] );

		$this->assertSame( '%PDF', $result );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function make_simple_order(): WC_Order {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_order_number' )->willReturn( '100' );
		$order->method( 'get_date_created' )->willReturn( null );
		$order->method( 'get_payment_method_title' )->willReturn( '' );
		$order->method( 'get_shipping_methods' )->willReturn( [] );
		$order->method( 'get_formatted_billing_full_name' )->willReturn( '' );
		$order->method( 'get_billing_email' )->willReturn( '' );
		$order->method( 'get_billing_phone' )->willReturn( '' );
		$order->method( 'get_meta' )->willReturn( '' );
		$order->method( 'get_items' )->willReturn( [] );
		$order->method( 'get_tax_totals' )->willReturn( [] );
		$order->method( 'get_customer_note' )->willReturn( '' );
		$order->method( 'get_id' )->willReturn( 0 );
		return $order;
	}

	private function make_items_order( array $items, string $total_tax = '0' ): WC_Order {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_items' )->willReturn( $items );
		$order->method( 'get_total_tax' )->willReturn( $total_tax );
		return $order;
	}

	private function make_order_with_invoice_number( string $number ): WC_Order {
		$order = $this->make_simple_order();
		$order->method( 'get_order_number' )->willReturn( $number );
		return $order;
	}

	private function make_wpdb_mock( int $rows_affected, string $counter_value ): object {
		return new class( $rows_affected, $counter_value ) {
			public string $options = 'wp_options';
			public int $rows_affected;
			private string $counter;

			public function __construct( int $r, string $c ) {
				$this->rows_affected = $r;
				$this->counter       = $c;
			}

			public function query( string $sql ): int { return $this->rows_affected; }
			public function prepare( string $sql, ...$args ): string {
				return vsprintf( str_replace( '%s', "'%s'", $sql ), $args );
			}
			public function get_var( string $sql ): string { return $this->counter; }
		};
	}
}
