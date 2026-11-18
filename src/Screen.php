<?php
/**
 * An Add-ons screen in the admin.
 *
 * @package   SugarCommerce\Addons
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Addons;

use Closure;
use InvalidArgumentException;

/**
 * A plugin's Add-ons screen: the menu item, the stylesheet and script, and
 * the page, drawing the plugin's list (Addons) with Renderer.
 *
 *     Screen::register( 'myplugin', [
 *         'parent'     => 'myplugin',
 *         'page_title' => __( 'My Plugin Add-ons', 'my-plugin' ),
 *         'labels'     => [ 'intro' => __( 'Extras for My Plugin.', 'my-plugin' ) ],
 *     ] );
 *
 * The id is the list's: the screen shows Addons::all( $id ), and its words
 * pass through the `{id}_addons_labels` filter.
 *
 * **Everything on this page comes from this site.** No catalogue is
 * fetched, no picture is loaded from another server and nothing is sent
 * about who opened it: wordpress.org does not allow a plugin to contact a
 * server its user has not agreed to, and a list of things to buy is no
 * reason to ask. Searching and filtering are done by the page's own
 * script, over the cards already on it. The only way off the site is a
 * link somebody presses.
 *
 * @since 1.0.0
 */
final class Screen {

	/**
	 * The screens registered, by id.
	 *
	 * @var array<string, self>
	 */
	private static array $screens = [];

	/**
	 * The screen's settings, defaults filled in.
	 *
	 * @var array<string, mixed>
	 */
	private array $args;

	/**
	 * What WordPress calls the page once it is in the menu.
	 *
	 * @since 1.0.0
	 */
	private string $hook_suffix = '';

	/**
	 * Register a plugin's Add-ons screen.
	 *
	 * @param string               $id   The plugin's add-on list, which is also the
	 *                                   prefix of its hooks, such as `myplugin`.
	 * @param array<string, mixed> $args {
	 *     The screen. Any of the drawing settings may be a closure, called
	 *     when the screen is drawn, for a value that is not known yet when
	 *     the screen is registered.
	 *
	 *     @type string               $parent     The parent menu's slug; '' for a menu
	 *                                            item of its own. Default ''.
	 *     @type string               $slug       The page's slug. Default `{id}-addons`.
	 *     @type string               $page_title The browser tab's title. Default "Add-ons".
	 *     @type string               $menu_title The menu item. Default "Add-ons".
	 *     @type string               $capability Who may see it. Default `manage_options`.
	 *     @type int                  $priority   The `admin_menu` priority it is added at,
	 *                                            to place it after other items. Default 10.
	 *     @type string               $icon       A top-level item's icon, as WordPress's
	 *                                            menu takes one: a Dashicons class, a
	 *                                            `data:image/svg+xml` URI or a URL.
	 *                                            Default `dashicons-admin-plugins`.
	 *     @type int|null             $position   A top-level item's position. Default null.
	 *     @type string               $handle     The stylesheet's and script's handle.
	 *                                            Default the slug.
	 *     @type string               $class      A class for the page's wrapper, beside `wrap`.
	 *     @type array|Closure        $labels     Words, by Renderer::labels()' keys.
	 *     @type array|Closure        $palette    Icon tile colours: `tint` and `shade` pairs.
	 *     @type string|Closure       $brand      The band's first colour and the focus ring's.
	 *     @type array|Closure        $band       The band's two further colours.
	 *     @type callable|null        $badge      Draws a status: given the status and its
	 *                                            label, returns markup.
	 *     @type array|Closure        $notes      A further sentence for an add-on, by id.
	 *     @type callable|null        $icons      Draws an add-on's `set:name` icon: given the
	 *                                            name and `[ 'class' => … ]`, returns markup.
	 *                                            An icon library's own render function fits.
	 *                                            Without one, such an icon is the default.
	 * }
	 *
	 * @return self
	 * @throws InvalidArgumentException When the id is empty or not a key.
	 * @since 1.0.0
	 */
	public static function register( string $id, array $args = [] ): self {
		$screen = new self( $id, $args );

		self::$screens[ $id ] = $screen;

		add_action( 'admin_menu', [ $screen, 'menu' ], (int) $screen->args['priority'] );
		add_action( 'admin_enqueue_scripts', [ $screen, 'enqueue' ] );

		return $screen;
	}

	/**
	 * A screen registered earlier, by its id.
	 *
	 * @param string $id The id.
	 *
	 * @return self|null
	 * @since 1.0.0
	 */
	public static function get( string $id ): ?self {
		return self::$screens[ $id ] ?? null;
	}

	/**
	 * Set a screen up, without hooking it anywhere.
	 *
	 * @param string               $id   The add-on list.
	 * @param array<string, mixed> $args See register().
	 *
	 * @throws InvalidArgumentException When the id is empty or not a key.
	 * @since 1.0.0
	 */
	public function __construct( private readonly string $id, array $args = [] ) {
		if ( '' === $id || sanitize_key( $id ) !== $id ) {
			throw new InvalidArgumentException( 'An Add-ons screen needs an id of lower-case letters, digits, dashes and underscores.' );
		}

		$args += [
			'parent'     => '',
			'slug'       => $id . '-addons',
			'page_title' => '',
			'menu_title' => '',
			'capability' => 'manage_options',
			'priority'   => 10,
			'icon'       => 'dashicons-admin-plugins',
			'position'   => null,
			'handle'     => '',
			'class'      => '',
			'labels'     => [],
			'palette'    => [],
			'brand'      => '',
			'band'       => [],
			'badge'      => null,
			'notes'      => [],
			'icons'      => null,
		];

		$args['handle'] = '' !== (string) $args['handle'] ? (string) $args['handle'] : (string) $args['slug'];

		$this->args = $args;
	}

	/**
	 * The add-on list this screen draws.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * The page's slug.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function slug(): string {
		return (string) $this->args['slug'];
	}

	/**
	 * Add the screen to the menu.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function menu(): void {
		$page_title = '' !== (string) $this->args['page_title'] ? (string) $this->args['page_title'] : __( 'Add-ons', 'sugarcommerce' );
		$menu_title = '' !== (string) $this->args['menu_title'] ? (string) $this->args['menu_title'] : __( 'Add-ons', 'sugarcommerce' );

		$hook = '' !== (string) $this->args['parent']
			? add_submenu_page( (string) $this->args['parent'], $page_title, $menu_title, (string) $this->args['capability'], $this->slug(), [ $this, 'render' ] )
			: add_menu_page( $page_title, $menu_title, (string) $this->args['capability'], $this->slug(), [ $this, 'render' ], (string) $this->args['icon'], $this->args['position'] );

		$this->hook_suffix = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Load the screen's stylesheet and script, on the screen and nowhere else.
	 *
	 * @param string $hook The current admin page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function enqueue( string $hook ): void {
		$ours = '' !== $this->hook_suffix
			? $hook === $this->hook_suffix
			: str_ends_with( $hook, '_page_' . $this->slug() );

		if ( ! $ours ) {
			return;
		}

		$handle = (string) $this->args['handle'];

		// Dashicons, for an add-on whose icon is one; the admin has them
		// loaded already.
		wp_register_style( $handle, self::asset_url( 'css/addons.css' ), [ 'dashicons' ], self::asset_version( 'css/addons.css' ) );
		wp_enqueue_style( $handle );

		// Filters the cards as a category is chosen or a word is typed.
		// Asks no server anything.
		wp_register_script( $handle, self::asset_url( 'js/addons.js' ), [], self::asset_version( 'js/addons.js' ), [ 'in_footer' => true ] );
		wp_enqueue_script( $handle );
	}

	/**
	 * The screen's address.
	 *
	 * @param string $category A category to open it on, by its key.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function url( string $category = '' ): string {
		$url = admin_url( 'admin.php?page=' . $this->slug() );

		return '' === $category ? $url : add_query_arg( 'category', rawurlencode( $category ), $url );
	}

	/**
	 * Draw the screen.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render(): void {
		if ( ! current_user_can( (string) $this->args['capability'] ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to see this page.', 'sugarcommerce' ), 403 );
		}

		// Which category the address asks to open on. Nothing is changed
		// by it, so there is no nonce to check: it chooses what is shown,
		// as a list table's own filters do.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$category = isset( $_GET['category'] ) ? sanitize_title( wp_unslash( (string) $_GET['category'] ) ) : '';

		$class = trim( 'wrap ' . sanitize_html_class( (string) $this->args['class'] ) );

		echo '<div class="' . esc_attr( $class ) . '">';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The renderer escapes everything it prints.
		echo $this->html( $category );

		echo '</div>';
	}

	/**
	 * The catalogue, as the screen draws it, without the page around it.
	 *
	 * @param string $category The category to open on, by key.
	 *
	 * @return string Markup.
	 * @since 1.0.0
	 */
	public function html( string $category = '' ): string {
		return Renderer::render(
			Addons::all( $this->id ),
			[
				'labels'   => $this->labels(),
				'palette'  => array_values( (array) $this->resolve( 'palette' ) ),
				'brand'    => (string) $this->resolve( 'brand' ),
				'band'     => array_values( (array) $this->resolve( 'band' ) ),
				'category' => $category,
				'badge'    => is_callable( $this->args['badge'] ) ? $this->args['badge'] : null,
				'notes'    => array_filter( (array) $this->resolve( 'notes' ) ),
				'icons'    => is_callable( $this->args['icons'] ) ? $this->args['icons'] : null,
			]
		);
	}

	/**
	 * The screen's words.
	 *
	 * @return array<string, mixed>
	 * @since 1.0.0
	 */
	public function labels(): array {
		$labels = (array) $this->resolve( 'labels' );

		$labels['statuses'] = (array) ( $labels['statuses'] ?? [] ) + Addons::statuses();

		/**
		 * Filter the words on an Add-ons screen, `{id}_addons_labels`.
		 *
		 * @param array<string, mixed> $labels By key; `statuses` is the four
		 *                                     statuses' own labels.
		 *
		 * @since 1.0.0
		 */
		return (array) apply_filters( $this->id . '_addons_labels', $labels );
	}

	/**
	 * A drawing setting, worked out now when it was given as a closure.
	 *
	 * @param string $key Which.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	private function resolve( string $key ): mixed {
		$value = $this->args[ $key ];

		return $value instanceof Closure ? $value() : $value;
	}

	/**
	 * A file's URL in this copy of the package.
	 *
	 * Worked out from where the copy sits -- under the content directory,
	 * under the site root, or in a symlinked plugin -- since a Composer
	 * library has no plugin file to hand plugins_url().
	 *
	 * @param string $file From the assets folder.
	 *
	 * @return string
	 */
	private static function asset_url( string $file ): string {
		$path = wp_normalize_path( dirname( __DIR__ ) . '/assets/' . $file );

		// A symlinked plugin: __DIR__ is the real path. Core keeps a map
		// from each plugin's path under the content directory to its real one.
		foreach ( (array) ( $GLOBALS['wp_plugin_paths'] ?? [] ) as $plugin_path => $real_path ) {
			$real_path = rtrim( wp_normalize_path( (string) $real_path ), '/' );

			if ( '' !== $real_path && str_starts_with( $path, $real_path . '/' ) ) {
				$path = wp_normalize_path( (string) $plugin_path ) . substr( $path, strlen( $real_path ) );
			}
		}

		foreach ( [ [ WP_CONTENT_DIR, content_url() ], [ ABSPATH, site_url( '/' ) ] ] as [ $base, $base_url ] ) {
			$base = rtrim( wp_normalize_path( (string) $base ), '/' );

			if ( '' !== $base && str_starts_with( $path, $base . '/' ) ) {
				return rtrim( (string) $base_url, '/' ) . substr( $path, strlen( $base ) );
			}
		}

		return '';
	}

	/**
	 * A file's version: when it last changed, so a new copy is fetched.
	 *
	 * @param string $file From the assets folder.
	 *
	 * @return string
	 */
	private static function asset_version( string $file ): string {
		$time = @filemtime( dirname( __DIR__ ) . '/assets/' . $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file is a version, not a warning.

		return false === $time ? '1.0.0' : (string) $time;
	}
}
