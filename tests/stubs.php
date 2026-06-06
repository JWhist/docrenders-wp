<?php

/**
 * Minimal WordPress class stubs for unit tests.
 * These replicate only the surface area the plugin uses.
 */

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		private mixed $data;

		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message( string $code = '' ): string {
			return $this->message;
		}

		public function get_error_data( string $code = '' ): mixed {
			return $this->data;
		}
	}
}

// ── WooCommerce stubs ─────────────────────────────────────────────────────────

if ( ! class_exists( 'WC_DateTime' ) ) {
	class WC_DateTime {
		private string $formatted = '';
		public function __construct( string $formatted = '' ) { $this->formatted = $formatted; }
		public function date_i18n( string $format ): string { return $this->formatted; }
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		public function get_sku(): string { return ''; }
	}
}

if ( ! class_exists( 'WC_Order_Item_Shipping' ) ) {
	class WC_Order_Item_Shipping {
		public function get_method_title(): string { return ''; }
	}
}

if ( ! class_exists( 'WC_Order_Item_Product' ) ) {
	class WC_Order_Item_Product {
		public function get_name(): string { return ''; }
		public function get_product(): ?WC_Product { return null; }
		public function get_quantity(): int { return 1; }
		public function get_total(): string { return '0'; }
		public function get_total_tax(): string { return '0'; }
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {
		public function get_id(): int { return 0; }
		public function get_order_number(): string { return '0'; }
		public function get_date_created(): ?WC_DateTime { return null; }
		public function get_payment_method_title(): string { return ''; }
		public function get_shipping_methods(): array { return []; }
		public function get_formatted_billing_full_name(): string { return ''; }
		public function get_formatted_billing_address(): string { return ''; }
		public function get_formatted_shipping_address(): string { return ''; }
		public function has_shipping_address(): bool { return false; }
		public function get_billing_email(): string { return ''; }
		public function get_billing_phone(): string { return ''; }
		public function get_meta( string $key, bool $single = true ): mixed { return ''; }
		public function get_items( string $type = 'line_item' ): array { return []; }
		public function get_tax_totals(): array { return []; }
		public function get_subtotal(): string { return '0'; }
		public function get_shipping_total(): string { return '0'; }
		public function get_discount_total(): string { return '0'; }
		public function get_total(): string { return '0'; }
		public function get_total_tax(): string { return '0'; }
		public function get_customer_note(): string { return ''; }
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int    $ID           = 0;
		public string $post_content = '';
		public string $post_title   = '';
		public string $post_status  = 'publish';
		public string $post_name    = '';
		public string $post_type    = 'post';
		public int    $post_author  = 0;

		public function __construct( array $props = [] ) {
			foreach ( $props as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->$key = $value;
				}
			}
		}
	}
}
