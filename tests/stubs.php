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

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int    $ID           = 0;
		public string $post_content = '';
		public string $post_title   = '';
		public string $post_status  = 'publish';
		public string $post_name    = '';
		public string $post_type    = 'post';

		public function __construct( array $props = [] ) {
			foreach ( $props as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->$key = $value;
				}
			}
		}
	}
}
