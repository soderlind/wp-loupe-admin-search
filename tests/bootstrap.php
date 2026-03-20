<?php
declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/includes/class-wp-loupe-admin-rest.php';
require_once dirname( __DIR__ ) . '/includes/class-wp-loupe-admin-search.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'WP_LOUPE_ADMIN_URL' ) ) {
	define( 'WP_LOUPE_ADMIN_URL', 'https://plugins.local/wp-content/plugins/wp-loupe-admin/' );
}

if ( ! defined( 'WP_LOUPE_ADMIN_VERSION' ) ) {
	define( 'WP_LOUPE_ADMIN_VERSION', '0.1.0-test' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		public $code;

		/** @var string */
		public $message;

		/** @var mixed */
		public $data;

		public function __construct( string $code = '', string $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		/** @var mixed */
		private $data;

		public function __construct( $data = null ) {
			$this->data = $data;
		}

		public function get_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		/** @var array<string,mixed> */
		private $params = [];

		public function set_param( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}

		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}
	}
}

if ( ! class_exists( 'WP_Admin_Bar' ) ) {
	class WP_Admin_Bar {
		/** @var array<int,array<string,mixed>> */
		public $nodes = [];

		public function add_node( array $node ): void {
			$this->nodes[] = $node;
		}
	}
}