<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocRenders_Settings {

	const MENU_SLUG = 'docrenders';

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	public function add_menu(): void {
		add_options_page(
			'DocRenders',
			'DocRenders',
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		register_setting( 'docrenders', 'docrenders_api_key', [
			'type'              => 'string',
			'sanitize_callback' => [ $this, 'sanitize_api_key' ],
			'default'           => '',
		] );

		register_setting( 'docrenders', 'docrenders_button_label', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'Download PDF',
		] );

		register_setting( 'docrenders', 'docrenders_button_placement', [
			'type'              => 'string',
			'sanitize_callback' => [ $this, 'sanitize_placement' ],
			'default'           => 'shortcode',
		] );

		register_setting( 'docrenders', 'docrenders_post_types', [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_post_types' ],
			'default'           => [ 'post', 'page' ],
		] );

		register_setting( 'docrenders', 'docrenders_custom_css', [
			'type'              => 'string',
			'sanitize_callback' => 'wp_strip_all_tags',
			'default'           => '',
		] );

		add_settings_section( 'docrenders_main', '', '__return_false', 'docrenders' );

		add_settings_field( 'docrenders_api_key', 'API Key', [ $this, 'field_api_key' ], 'docrenders', 'docrenders_main' );
		add_settings_field( 'docrenders_button_label', 'Button Label', [ $this, 'field_button_label' ], 'docrenders', 'docrenders_main' );
		add_settings_field( 'docrenders_button_placement', 'Button Placement', [ $this, 'field_button_placement' ], 'docrenders', 'docrenders_main' );
		add_settings_field( 'docrenders_post_types', 'Enabled Post Types', [ $this, 'field_post_types' ], 'docrenders', 'docrenders_main' );
		add_settings_field( 'docrenders_custom_css', 'Custom CSS', [ $this, 'field_custom_css' ], 'docrenders', 'docrenders_main' );

		if ( class_exists( 'WooCommerce' ) ) {
			$this->register_woo_settings();
		}
	}

	private function register_woo_settings(): void {
		$woo_options = [
			'docrenders_woo_enabled'        => [ 'type' => 'string',  'sanitize' => [ $this, 'sanitize_checkbox' ] ],
			'docrenders_woo_trigger'         => [ 'type' => 'string',  'sanitize' => [ $this, 'sanitize_woo_trigger' ] ],
			'docrenders_woo_shop_name'       => [ 'type' => 'string',  'sanitize' => 'sanitize_text_field' ],
			'docrenders_woo_shop_address'    => [ 'type' => 'string',  'sanitize' => 'sanitize_textarea_field' ],
			'docrenders_woo_shop_email'      => [ 'type' => 'string',  'sanitize' => 'sanitize_email' ],
			'docrenders_woo_shop_vat_id'     => [ 'type' => 'string',  'sanitize' => 'sanitize_text_field' ],
			'docrenders_woo_invoice_prefix'  => [ 'type' => 'string',  'sanitize' => 'sanitize_text_field' ],
			'docrenders_woo_footer_text'     => [ 'type' => 'string',  'sanitize' => 'sanitize_textarea_field' ],
		];

		foreach ( $woo_options as $name => $args ) {
			register_setting( 'docrenders', $name, [
				'type'              => $args['type'],
				'sanitize_callback' => $args['sanitize'],
				'default'           => '',
			] );
		}

		add_settings_section(
			'docrenders_woo',
			'WooCommerce Invoices',
			[ $this, 'section_woo_description' ],
			'docrenders'
		);

		add_settings_field( 'docrenders_woo_enabled', 'Enable WooCommerce', [ $this, 'field_woo_enabled' ], 'docrenders', 'docrenders_woo' );
		add_settings_field( 'docrenders_woo_trigger', 'Trigger', [ $this, 'field_woo_trigger' ], 'docrenders', 'docrenders_woo' );
		add_settings_field( 'docrenders_woo_shop_name', 'Shop Name', [ $this, 'field_woo_shop_name' ], 'docrenders', 'docrenders_woo' );
		add_settings_field( 'docrenders_woo_shop_address', 'Shop Address', [ $this, 'field_woo_shop_address' ], 'docrenders', 'docrenders_woo' );
		add_settings_field( 'docrenders_woo_shop_email', 'Shop Email', [ $this, 'field_woo_shop_email' ], 'docrenders', 'docrenders_woo' );
		add_settings_field( 'docrenders_woo_shop_vat_id', 'VAT / Tax ID', [ $this, 'field_woo_shop_vat_id' ], 'docrenders', 'docrenders_woo' );
		add_settings_field( 'docrenders_woo_invoice_prefix', 'Invoice Prefix', [ $this, 'field_woo_invoice_prefix' ], 'docrenders', 'docrenders_woo' );
		add_settings_field( 'docrenders_woo_footer_text', 'Invoice Footer', [ $this, 'field_woo_footer_text' ], 'docrenders', 'docrenders_woo' );
	}

	public function sanitize_api_key( string $value ): string {
		$value = sanitize_text_field( trim( $value ) );

		if ( ! $value ) {
			return $value;
		}

		// Validate the key against the API; bust the usage cache so the panel refreshes.
		$client = new DocRenders_API_Client( $value );
		$usage  = $client->get_usage( true );

		if ( is_wp_error( $usage ) ) {
			add_settings_error(
				'docrenders_api_key',
				'invalid_key',
				'Could not validate API key: ' . esc_html( $usage->get_error_message() )
			);
			// Return the previously saved value so a bad key doesn't overwrite a working one.
			return get_option( 'docrenders_api_key', '' );
		}

		return $value;
	}

	public function sanitize_placement( string $value ): string {
		$allowed = [ 'after_content', 'before_content', 'shortcode' ];
		return in_array( $value, $allowed, true ) ? $value : 'shortcode';
	}

	public function sanitize_post_types( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		$public_types = array_keys( get_post_types( [ 'public' => true ] ) );
		return array_values( array_intersect( array_map( 'sanitize_key', $value ), $public_types ) );
	}

	// -------------------------------------------------------------------------
	// Field renderers
	// -------------------------------------------------------------------------

	public function field_api_key(): void {
		$value = get_option( 'docrenders_api_key', '' );
		printf(
			'<input type="password" id="docrenders_api_key" name="docrenders_api_key" value="%s" class="regular-text" autocomplete="off">
			<p class="description">Your DocRenders API key. Find it at <a href="https://docrenders.com/dashboard" target="_blank">docrenders.com/dashboard</a>.</p>',
			esc_attr( $value )
		);
	}

	public function field_button_label(): void {
		$value = get_option( 'docrenders_button_label', 'Download PDF' );
		printf(
			'<input type="text" id="docrenders_button_label" name="docrenders_button_label" value="%s" class="regular-text">',
			esc_attr( $value )
		);
	}

	public function field_button_placement(): void {
		$value   = get_option( 'docrenders_button_placement', 'shortcode' );
		$options = [
			'after_content'  => 'After content',
			'before_content' => 'Before content',
			'shortcode'      => 'Shortcode only — use [docrenders_pdf] to place manually',
		];
		foreach ( $options as $key => $label ) {
			printf(
				'<label style="display:block;margin-bottom:4px"><input type="radio" name="docrenders_button_placement" value="%s"%s> %s</label>',
				esc_attr( $key ),
				checked( $value, $key, false ),
				esc_html( $label )
			);
		}
	}

	public function field_post_types(): void {
		$enabled = (array) get_option( 'docrenders_post_types', [ 'post', 'page' ] );
		$types   = get_post_types( [ 'public' => true ], 'objects' );
		foreach ( $types as $type ) {
			printf(
				'<label style="display:block;margin-bottom:4px"><input type="checkbox" name="docrenders_post_types[]" value="%s"%s> %s</label>',
				esc_attr( $type->name ),
				checked( in_array( $type->name, $enabled, true ), true, false ),
				esc_html( $type->labels->name )
			);
		}
	}

	public function field_custom_css(): void {
		$value = get_option( 'docrenders_custom_css', '' );
		printf(
			'<textarea id="docrenders_custom_css" name="docrenders_custom_css" rows="8" class="large-text code">%s</textarea>
			<p class="description">CSS appended to every post/page PDF. Useful for custom fonts, colours, or spacing.</p>',
			esc_textarea( $value )
		);
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>DocRenders</h1>

			<?php $this->render_usage_panel(); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'docrenders' );
				do_settings_sections( 'docrenders' );
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>
		<?php
	}

	private function render_usage_panel(): void {
		$api_key = get_option( 'docrenders_api_key', '' );

		if ( ! $api_key ) {
			echo '<div class="notice notice-info inline"><p>Enter your API key below to get started.</p></div>';
			return;
		}

		$client = new DocRenders_API_Client( $api_key );
		$usage  = $client->get_usage();

		if ( is_wp_error( $usage ) ) {
			printf(
				'<div class="notice notice-error inline"><p>Could not load usage — %s</p></div>',
				esc_html( $usage->get_error_message() )
			);
			return;
		}

		$used      = (int) ( $usage['renders']['used'] ?? 0 );
		$limit     = (int) ( $usage['renders']['limit'] ?? 0 );
		$plan      = ucfirst( $usage['plan'] ?? 'free' );
		$period    = $usage['renders']['period'] ?? ''; // "2026-06"
		$resets    = $period ? date_i18n( get_option( 'date_format' ), strtotime( $period . '-01 +1 month -1 day' ) ) : '—';
		$pct       = $limit > 0 ? min( 100, round( $used / $limit * 100 ) ) : 0;
		$bar_class = $pct >= 95 ? 'docrenders-bar--red' : ( $pct >= 80 ? 'docrenders-bar--amber' : '' );
		?>
		<div class="docrenders-usage-panel">
			<h2 class="docrenders-usage-title">Usage — <?php echo esc_html( date_i18n( 'F Y' ) ); ?></h2>
			<div class="docrenders-usage-row">
				<span class="docrenders-usage-label">Renders used</span>
				<div class="docrenders-bar-wrap">
					<div class="docrenders-bar <?php echo esc_attr( $bar_class ); ?>" style="width:<?php echo (int) $pct; ?>%"></div>
				</div>
				<span class="docrenders-usage-count"><?php echo esc_html( number_format( $used ) . ' / ' . number_format( $limit ) ); ?></span>
			</div>
			<div class="docrenders-usage-meta">
				<span>Plan: <strong><?php echo esc_html( $plan ); ?></strong></span>
				<span>Resets: <strong><?php echo esc_html( $resets ); ?></strong></span>
				<a href="https://docrenders.com/pricing" target="_blank">Upgrade plan ↗</a>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// WooCommerce sanitize callbacks
	// -------------------------------------------------------------------------

	public function sanitize_checkbox( $value ): string {
		return $value ? '1' : '';
	}

	public function sanitize_woo_trigger( string $value ): string {
		return in_array( $value, [ 'on_complete', 'manually' ], true ) ? $value : 'on_complete';
	}

	// -------------------------------------------------------------------------
	// WooCommerce field renderers
	// -------------------------------------------------------------------------

	public function section_woo_description(): void {
		echo '<p>Automatically attach a branded PDF invoice to WooCommerce order completion emails.</p>';
	}

	public function field_woo_enabled(): void {
		$value = get_option( 'docrenders_woo_enabled', '' );
		printf(
			'<label><input type="checkbox" name="docrenders_woo_enabled" value="1"%s> Generate PDF invoices for WooCommerce orders</label>',
			checked( $value, '1', false )
		);
	}

	public function field_woo_trigger(): void {
		$value   = get_option( 'docrenders_woo_trigger', 'on_complete' );
		$options = [
			'on_complete' => 'Automatically on order completion (attached to confirmation email)',
			'manually'    => 'Manually only (generate from the order admin screen)',
		];
		foreach ( $options as $key => $label ) {
			printf(
				'<label style="display:block;margin-bottom:4px"><input type="radio" name="docrenders_woo_trigger" value="%s"%s> %s</label>',
				esc_attr( $key ),
				checked( $value, $key, false ),
				esc_html( $label )
			);
		}
	}

	public function field_woo_shop_name(): void {
		$value = get_option( 'docrenders_woo_shop_name', get_bloginfo( 'name' ) );
		printf(
			'<input type="text" name="docrenders_woo_shop_name" value="%s" class="regular-text">',
			esc_attr( $value )
		);
	}

	public function field_woo_shop_address(): void {
		$value = get_option( 'docrenders_woo_shop_address', '' );
		printf(
			'<textarea name="docrenders_woo_shop_address" rows="4" class="regular-text">%s</textarea>
			<p class="description">Street, city, postcode, country — one line each.</p>',
			esc_textarea( $value )
		);
	}

	public function field_woo_shop_email(): void {
		$value = get_option( 'docrenders_woo_shop_email', get_option( 'admin_email' ) );
		printf(
			'<input type="email" name="docrenders_woo_shop_email" value="%s" class="regular-text">',
			esc_attr( $value )
		);
	}

	public function field_woo_shop_vat_id(): void {
		$value = get_option( 'docrenders_woo_shop_vat_id', '' );
		printf(
			'<input type="text" name="docrenders_woo_shop_vat_id" value="%s" class="regular-text">
			<p class="description">Optional. Shown in the FROM column and invoice footer.</p>',
			esc_attr( $value )
		);
	}

	public function field_woo_invoice_prefix(): void {
		$value = get_option( 'docrenders_woo_invoice_prefix', 'INV-' );
		printf(
			'<input type="text" name="docrenders_woo_invoice_prefix" value="%s" class="small-text">
			<p class="description">Prepended to the sequential counter, e.g. <code>INV-0001</code>.</p>',
			esc_attr( $value )
		);
	}

	public function field_woo_footer_text(): void {
		$value = get_option( 'docrenders_woo_footer_text', '' );
		printf(
			'<textarea name="docrenders_woo_footer_text" rows="3" class="large-text">%s</textarea>
			<p class="description">Appears at the bottom of every invoice — tax IDs, return policy, terms.</p>',
			esc_textarea( $value )
		);
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( 'settings_page_docrenders' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'docrenders-admin', DOCRENDERS_URL . 'assets/admin.css', [], DOCRENDERS_VERSION );
	}
}
