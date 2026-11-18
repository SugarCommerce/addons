<?php
/**
 * PHPUnit bootstrap.
 *
 * This is a library, not a plugin: there is no WordPress to load. The
 * functions the registry, the renderer and the screen call are stubbed
 * here, as close to core's behaviour as the tests depend on: hooks that
 * really run, options in a global, a site at https://example.test, a
 * capability check the test controls, and every outgoing request refused
 * and remembered.
 *
 * @package SugarCommerce\Addons
 */

declare( strict_types=1 );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WP_CONTENT_DIR', '/nowhere/wp-content' );
define( 'WP_PLUGIN_DIR', __DIR__ . '/fixtures/plugins' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Put every stubbed global back as a fresh site has it.
 */
function addons_test_reset(): void {
	$GLOBALS['ra_hooks']    = [];
	$GLOBALS['ra_options']  = [];
	$GLOBALS['ra_network']  = [];
	$GLOBALS['ra_multi']    = false;
	$GLOBALS['ra_can']      = [ 'manage_options' => true ];
	$GLOBALS['ra_menu']     = [];
	$GLOBALS['ra_enqueued'] = [];
	$GLOBALS['ra_requests'] = [];
}

addons_test_reset();

final class WPDieException extends RuntimeException {
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['ra_hooks'][ $hook ][ (int) $priority ][] = [ $callback, (int) $accepted_args ];
		ksort( $GLOBALS['ra_hooks'][ $hook ] );

		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'remove_all_actions' ) ) {
	function remove_all_actions( $hook ) {
		unset( $GLOBALS['ra_hooks'][ $hook ] );

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['ra_hooks'][ $hook ] ?? [] as $callbacks ) {
			foreach ( $callbacks as [ $callback, $accepted ] ) {
				$value = $callback( ...array_slice( array_merge( [ $value ], $args ), 0, $accepted ) );
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		foreach ( $GLOBALS['ra_hooks'][ $hook ] ?? [] as $callbacks ) {
			foreach ( $callbacks as [ $callback, $accepted ] ) {
				$callback( ...array_slice( $args, 0, $accepted ) );
			}
		}
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['ra_options'][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'get_site_option' ) ) {
	function get_site_option( $key, $default = false ) {
		return $GLOBALS['ra_network'][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite() {
		return $GLOBALS['ra_multi'];
	}
}

if ( ! function_exists( 'validate_file' ) ) {
	function validate_file( $file ) {
		if ( str_contains( (string) $file, '..' ) ) {
			return 1;
		}

		return str_starts_with( (string) $file, '/' ) || str_contains( (string) $file, ':' ) ? 2 : 0;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		return ! empty( $GLOBALS['ra_can'][ $capability ] );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = [] ) {
		throw new WPDieException( (string) $message );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'site_url' ) ) {
	function site_url( $path = '' ) {
		return home_url( $path );
	}
}

if ( ! function_exists( 'content_url' ) ) {
	function content_url( $path = '' ) {
		return 'https://example.test/wp-content' . ( '' !== $path ? '/' . ltrim( (string) $path, '/' ) : '' );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $value, $url ) {
		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . $key . '=' . $value;
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $url, $action = -1 ) {
		return add_query_arg( '_wpnonce', substr( md5( (string) $action ), 0, 10 ), $url );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = null ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

		return in_array( $scheme, $protocols ?? [ 'http', 'https' ], true ) ? $url : '';
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return str_replace( '&', '&#038;', esc_url_raw( $url ) );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $text ) {
		return trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( strip_tags( (string) $title ) ) ), '-' );
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $class ) {
		return (string) preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		return 1 === preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color ) ? (string) $color : null;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		return (string) preg_replace( '|(?<=.)/+|', '/', str_replace( '\\', '/', (string) $path ) );
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent, $page_title, $menu_title, $capability, $slug, $callback = null, $position = null ) {
		$GLOBALS['ra_menu'][] = compact( 'parent', 'page_title', 'menu_title', 'capability', 'slug' );

		return sanitize_title( $parent ) . '_page_' . $slug;
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( $page_title, $menu_title, $capability, $slug, $callback = '', $icon = '', $position = null ) {
		$parent               = '';
		$GLOBALS['ra_menu'][] = compact( 'parent', 'page_title', 'menu_title', 'capability', 'slug', 'icon', 'position' );

		return 'toplevel_page_' . $slug;
	}
}

if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( $handle, $src, $deps = [], $ver = false, $media = 'all' ) {
		$GLOBALS['ra_registered'][ $handle ]['style']      = $src;
		$GLOBALS['ra_registered'][ $handle ]['style_deps'] = $deps;

		return true;
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( $handle, $src, $deps = [], $ver = false, $args = [] ) {
		$GLOBALS['ra_registered'][ $handle ]['script'] = $src;

		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle ) {
		$GLOBALS['ra_enqueued'][] = [ 'style', $handle, $GLOBALS['ra_registered'][ $handle ]['style'] ?? '' ];
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle ) {
		$GLOBALS['ra_enqueued'][] = [ 'script', $handle, $GLOBALS['ra_registered'][ $handle ]['script'] ?? '' ];
	}
}

// Anything that would reach another server goes through these, and none
// of them should ever be called.

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( ...$args ) {
		$GLOBALS['ra_requests'][] = 'wp_remote_get';

		return null;
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( ...$args ) {
		$GLOBALS['ra_requests'][] = 'wp_remote_post';

		return null;
	}
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	function wp_remote_request( ...$args ) {
		$GLOBALS['ra_requests'][] = 'wp_remote_request';

		return null;
	}
}

if ( ! function_exists( 'wp_safe_remote_get' ) ) {
	function wp_safe_remote_get( ...$args ) {
		$GLOBALS['ra_requests'][] = 'wp_safe_remote_get';

		return null;
	}
}

if ( ! function_exists( 'wp_safe_remote_post' ) ) {
	function wp_safe_remote_post( ...$args ) {
		$GLOBALS['ra_requests'][] = 'wp_safe_remote_post';

		return null;
	}
}

if ( ! function_exists( 'download_url' ) ) {
	function download_url( ...$args ) {
		$GLOBALS['ra_requests'][] = 'download_url';

		return null;
	}
}
