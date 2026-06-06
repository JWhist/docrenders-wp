<?php

define( 'ABSPATH', '/tmp/wordpress/' );
define( 'WP_CONTENT_DIR', '/tmp/wordpress/wp-content' );
define( 'DOCRENDERS_VERSION', '1.0.0' );
define( 'DOCRENDERS_DIR', dirname( __DIR__ ) . '/' );
define( 'DOCRENDERS_URL', 'http://example.com/wp-content/plugins/docrenders/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-api-client.php';
require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-content-pdf.php';
require_once dirname( __DIR__ ) . '/includes/class-woo-invoices.php';
