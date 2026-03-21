<?php
declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Loupe library stubs — needed so the admin indexer class can be loaded without the real library.
if ( ! class_exists( 'Loupe\\Loupe\\Configuration' ) ) {
	require_once __DIR__ . '/stubs/loupe-stubs.php';
}

require_once dirname( __DIR__ ) . '/includes/class-wp-loupe-admin-rest.php';
require_once dirname( __DIR__ ) . '/includes/class-wp-loupe-admin-search.php';
require_once dirname( __DIR__ ) . '/includes/class-wp-loupe-admin-schema.php';
require_once dirname( __DIR__ ) . '/includes/class-wp-loupe-admin-indexer.php';
require_once dirname( __DIR__ ) . '/includes/class-wp-loupe-admin-query-integration.php';

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

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		/** @var array<string,mixed> */
		private $params = [];

		/** @var int */
		public $found_posts = 0;

		/** @var int */
		public $max_num_pages = 0;

		/** @var bool */
		private $main_query = true;

		/** @var bool */
		private $search = true;

		/** @param array<string,mixed> $params */
		public function __construct( array $params = [] ) {
			$this->params = $params;
		}

		public function is_main_query(): bool {
			return $this->main_query;
		}

		public function is_search(): bool {
			return $this->search;
		}

		public function get( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function set( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}

		public function set_main_query( bool $main_query ): void {
			$this->main_query = $main_query;
		}

		public function set_is_search( bool $search ): void {
			$this->search = $search;
		}
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		/** @var int */
		public $ID;

		/** @var string */
		public $post_type;

		/** @var string */
		public $post_status;

		public function __construct( int $id = 0, string $post_type = 'post', string $post_status = 'publish' ) {
			$this->ID          = $id;
			$this->post_type   = $post_type;
			$this->post_status = $post_status;
		}
	}
}

if ( ! class_exists( 'WP_Loupe_Search_Engine_Test_Double' ) ) {
	class WP_Loupe_Search_Engine_Test_Double {
		/** @var array<int,array<string,mixed>> */
		public static $hits = [];

		/** @param array<int,string> $post_types */
		public function __construct( array $post_types ) {}

		/**
		 * @param string $query Search query.
		 * @return array<int,array<string,mixed>>
		 */
		public function search( string $query ): array {
			return self::$hits;
		}
	}
}

if ( ! class_exists( 'Soderlind\\Plugin\\WPLoupe\\WP_Loupe_Search_Engine' ) ) {
	class_alias( 'WP_Loupe_Search_Engine_Test_Double', 'Soderlind\\Plugin\\WPLoupe\\WP_Loupe_Search_Engine' );
}