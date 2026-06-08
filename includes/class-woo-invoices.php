<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocRenders_Woo_Invoices {

	private DocRenders_API_Client $client;

	public function __construct( DocRenders_API_Client $client ) {
		$this->client = $client;
	}

	public function init(): void {
		add_filter( 'woocommerce_email_attachments', [ $this, 'attach_to_email' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'maybe_show_limit_notice' ] );
		add_action( 'docrenders_retry_invoice', [ $this, 'retry_invoice' ] );
	}

	// -------------------------------------------------------------------------
	// Email attachment
	// -------------------------------------------------------------------------

	public function attach_to_email( array $attachments, string $email_id, $order ): array {
		if ( 'customer_completed_order' !== $email_id ) {
			return $attachments;
		}
		if ( ! $order instanceof WC_Order ) {
			return $attachments;
		}
		if ( 'on_complete' !== get_option( 'docrenders_woo_trigger', 'on_complete' ) ) {
			return $attachments;
		}

		$pdf = $this->generate_pdf( $order );
		if ( is_wp_error( $pdf ) ) {
			return $attachments;
		}

		$base = wp_tempnam( 'docrenders-invoice' );
		if ( ! $base ) {
			return $attachments;
		}
		// wp_tempnam() creates the base file to reserve the name; rename it to .pdf
		// so only one file exists on disk and cleanup targets the right path.
		$tmp = $base . '.pdf';
		if ( ! rename( $base, $tmp ) ) {
			unlink( $base );
			return $attachments;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		if ( false === file_put_contents( $tmp, $pdf ) ) {
			unlink( $tmp );
			return $attachments;
		}
		$attachments[] = $tmp;

		// Delete the temp file after the email is sent.
		add_action( 'woocommerce_email_sent', static function () use ( $tmp ) {
			if ( file_exists( $tmp ) ) {
				unlink( $tmp );
			}
		}, 10, 0 );

		return $attachments;
	}

	// -------------------------------------------------------------------------
	// Admin notice
	// -------------------------------------------------------------------------

	public function maybe_show_limit_notice(): void {
		if ( ! get_option( 'docrenders_limit_reached' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$count = count( (array) get_option( 'docrenders_failed_invoices', [] ) );

		printf(
			'<div class="notice notice-error"><p>%s <a href="%s" target="_blank">%s</a></p></div>',
			sprintf(
				esc_html(
					/* translators: %d: number of failed invoices */
					_n(
						'DocRenders: %d invoice could not be generated — plan limit reached.',
						'DocRenders: %d invoices could not be generated — plan limit reached.',
						$count,
						'docrenders'
					)
				),
				$count
			),
			esc_url( 'https://docrenders.com/pricing' ),
			esc_html__( 'Upgrade plan', 'docrenders' )
		);
	}

	// -------------------------------------------------------------------------
	// PDF generation with retry
	// -------------------------------------------------------------------------

	public function generate_pdf( WC_Order $order ): string|WP_Error {
		$data                   = $this->build_template_data( $order );
		$data['invoice_number'] = $this->next_invoice_number();

		$result = $this->client->render_template( 'woo-invoice', $data );

		if ( ! is_wp_error( $result ) ) {
			$this->clear_failed_order( $order->get_id() );
			update_option( 'docrenders_limit_reached', false );
			return $result;
		}

		$code = $result->get_error_code();

		if ( 'quota_exceeded' === $code ) {
			update_option( 'docrenders_limit_reached', true );
			$this->store_failed_order( $order->get_id() );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[DocRenders] Invoice for order #%s failed: plan limit reached.', $order->get_order_number() ) );
			return $result;
		}

		if ( 'rate_limited' === $code ) {
			// Schedule a retry via WP-Cron rather than blocking with sleep().
			wp_schedule_single_event( time() + 60, 'docrenders_retry_invoice', [ $order->get_id() ] );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[DocRenders] Invoice for order #%s rate-limited; retry scheduled.', $order->get_order_number() ) );
			return $result;
		}

		$this->store_failed_order( $order->get_id() );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf(
			'[DocRenders] Invoice for order #%s failed: %s',
			$order->get_order_number(),
			$result->get_error_message()
		) );
		return $result;
	}

	// -------------------------------------------------------------------------
	// Cron retry (rate-limited invoices)
	// -------------------------------------------------------------------------

	public function retry_invoice( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$pdf = $this->generate_pdf( $order );
		if ( is_wp_error( $pdf ) ) {
			return;
		}

		// Re-send the completed order email with the PDF attached.
		WC()->mailer()->emails['WC_Email_Customer_Completed_Order']->trigger( $order_id );
	}

	// -------------------------------------------------------------------------
	// Template data builder
	// -------------------------------------------------------------------------

	public function build_template_data( WC_Order $order ): array {
		$data = [
			'shop_name'      => get_option( 'docrenders_woo_shop_name', get_bloginfo( 'name' ) ),
			'shop_address'   => get_option( 'docrenders_woo_shop_address', '' ),
			'shop_email'     => get_option( 'docrenders_woo_shop_email', get_option( 'admin_email' ) ),
			'shop_vat_id'    => get_option( 'docrenders_woo_shop_vat_id', '' ),
			'order_number'   => $order->get_order_number(),
			'invoice_date'   => date_i18n( get_option( 'date_format' ) ),
			'order_date'     => $order->get_date_created()?->date_i18n( get_option( 'date_format' ) ) ?? '',
			'payment_method' => $order->get_payment_method_title(),
			'shipping_method'=> $this->get_shipping_method_label( $order ),
			'billing_name'   => $order->get_formatted_billing_full_name(),
			'billing_address'=> $order->get_formatted_billing_address(),
			'billing_email'  => $order->get_billing_email(),
			'billing_phone'  => $order->get_billing_phone(),
			'billing_vat_id' => $order->get_meta( '_billing_vat_id' ),
			'subtotal'       => (float) $order->get_subtotal(),
			'shipping_cost'  => (float) $order->get_shipping_total(),
			'discount'       => (float) $order->get_discount_total(),
			'total'          => (float) $order->get_total(),
			'notes'          => $order->get_customer_note(),
			'footer_text'    => get_option( 'docrenders_woo_footer_text', '' ),
		];

		if ( $order->has_shipping_address() ) {
			$shipping_addr = $order->get_formatted_shipping_address();
			if ( $shipping_addr && $shipping_addr !== $order->get_formatted_billing_address() ) {
				$data['shipping_address'] = $shipping_addr;
			}
		}

		$data['tax_lines'] = $this->build_tax_lines( $order );

		$items_data              = $this->build_items( $order );
		$data['items']           = $items_data['items'];
		$data['show_sku']        = $items_data['has_sku'];
		$data['show_tax_columns']= $items_data['has_tax'];

		return $data;
	}

	public function build_items( WC_Order $order ): array {
		$items   = [];
		$has_sku = false;
		$has_tax = (float) $order->get_total_tax() > 0;

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$sku     = $product ? $product->get_sku() : '';

			if ( $sku ) {
				$has_sku = true;
			}

			$qty        = (int) $item->get_quantity();
			$total      = (float) $item->get_total();
			$unit_price = $qty > 0 ? round( $total / $qty, 2 ) : 0.0;

			$items[] = [
				'name'       => $item->get_name(),
				'sku'        => $sku,
				'qty'        => $qty,
				'unit_price' => $unit_price,
				'tax_rate'   => '',
				'tax'        => (float) $item->get_total_tax(),
				'total'      => $total,
			];
		}

		return [
			'items'   => $items,
			'has_sku' => $has_sku,
			'has_tax' => $has_tax,
		];
	}

	public function build_tax_lines( WC_Order $order ): array {
		$lines = [];
		foreach ( $order->get_tax_totals() as $tax ) {
			$lines[] = [
				'label'  => $tax->label,
				'amount' => (float) $tax->amount,
			];
		}
		return $lines;
	}

	// -------------------------------------------------------------------------
	// Sequential invoice counter
	// -------------------------------------------------------------------------

	public function next_invoice_number(): string {
		global $wpdb;

		// Atomic SQL increment prevents duplicate numbers under concurrent order completions.
		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s",
			'docrenders_invoice_counter'
		) );

		if ( ! $updated ) {
			add_option( 'docrenders_invoice_counter', 1, '', 'no' );
		}

		$counter = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
			'docrenders_invoice_counter'
		) );

		$prefix = get_option( 'docrenders_woo_invoice_prefix', 'INV-' );
		return $prefix . str_pad( (string) $counter, 4, '0', STR_PAD_LEFT );
	}

	// -------------------------------------------------------------------------
	// Failed order queue
	// -------------------------------------------------------------------------

	public function store_failed_order( int $order_id ): void {
		$failed   = (array) get_option( 'docrenders_failed_invoices', [] );
		$failed[] = $order_id;
		update_option( 'docrenders_failed_invoices', array_unique( $failed ) );
	}

	public function clear_failed_order( int $order_id ): void {
		$failed = (array) get_option( 'docrenders_failed_invoices', [] );
		update_option( 'docrenders_failed_invoices', array_values( array_diff( $failed, [ $order_id ] ) ) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function get_shipping_method_label( WC_Order $order ): string {
		$methods = $order->get_shipping_methods();
		if ( empty( $methods ) ) {
			return '';
		}
		$labels = [];
		foreach ( $methods as $method ) {
			$labels[] = $method->get_method_title();
		}
		return implode( ', ', $labels );
	}
}
