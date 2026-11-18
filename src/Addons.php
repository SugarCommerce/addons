<?php
/**
 * The add-ons a plugin can show.
 *
 * @package   SugarCommerce\Addons
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Addons;

/**
 * Lists of add-ons, registered while the request runs and kept nowhere.
 *
 * An add-on here is a card on an Add-ons screen: what something is
 * called, a sentence about it, and whether this site has it. It is not
 * the add-on -- nothing is installed, loaded or switched on by being
 * listed -- and the list is not stored: whoever wants to be on it says so
 * on every request, the way a post type does.
 *
 * Each plugin has a list of its own, named by an id that is also the
 * prefix of its two hooks, so two plugins bundling this library never see
 * each other's add-ons:
 *
 *     add_action( 'myplugin_register_addons', function () {
 *         Addons::register( 'myplugin', 'acme-reviews', [
 *             'name'        => 'Reviews',
 *             'description' => 'Let buyers rate what they bought.',
 *             'icon'        => 'heroicons-outline:star',
 *             'category'    => 'Marketing',
 *             'plugin'      => 'acme-reviews/acme-reviews.php',
 *             'url'         => 'https://example.com/reviews',
 *         ] );
 *     } );
 *
 * **Nothing here asks another server for anything.** There is no
 * catalogue fetched from the plugin's own site, no picture loaded from
 * somewhere else, and nothing reported about who looked. wordpress.org's guidelines
 * do not allow a plugin to contact a server its user has not agreed to,
 * and a list of things to buy is not worth anybody's privacy. So an icon
 * is one drawn on the page -- a Heroicon or any `set:name` icon, or a
 * Dashicon -- or a file on this site, and one that points anywhere else
 * is replaced with the default.
 *
 * @since 1.0.0
 */
final class Addons {

	/**
	 * Can be had now.
	 *
	 * @since 1.0.0
	 */
	public const AVAILABLE = 'available';

	/**
	 * Not made yet.
	 *
	 * @since 1.0.0
	 */
	public const COMING_SOON = 'coming_soon';

	/**
	 * On this site, and switched off.
	 *
	 * @since 1.0.0
	 */
	public const INSTALLED = 'installed';

	/**
	 * On this site and running.
	 *
	 * @since 1.0.0
	 */
	public const ACTIVE = 'active';

	/**
	 * The icon of an add-on that named none, or one that cannot be used.
	 *
	 * @since 1.0.0
	 */
	public const DEFAULT_ICON = 'heroicons-outline:puzzle-piece';

	/**
	 * The four things an add-on can be registered as.
	 *
	 * @since 1.0.0
	 */
	private const STATUSES = [ self::AVAILABLE, self::COMING_SOON, self::INSTALLED, self::ACTIVE ];

	/**
	 * What has been registered, by list and then by id.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	private static array $addons = [];

	/**
	 * Which lists have asked everybody for theirs.
	 *
	 * @var array<string, bool>
	 */
	private static array $asked = [];

	/**
	 * Add an add-on to a list.
	 *
	 * @param string               $catalogue The list: the plugin's id, which is
	 *                                        also its hooks' prefix.
	 * @param string               $id        A key of its own: lower-case
	 *                                        letters, digits, dashes and
	 *                                        underscores.
	 * @param array<string, mixed> $args      {
	 *     What the add-on is.
	 *
	 *     @type string   $name        What it is called. Required.
	 *     @type string   $description One or two plain sentences.
	 *     @type string   $icon        An icon as `set:name`
	 *                                 ("heroicons-outline:user-group"), a
	 *                                 Dashicon ("groups" or
	 *                                 "dashicons-groups"), or the address
	 *                                 of a picture on this site. One on
	 *                                 another server is not loaded.
	 *                                 Default a puzzle piece.
	 *     @type string   $image       The address of a picture on this site
	 *                                 to draw across the top of the card in
	 *                                 place of the icon, 16 by 9. One on
	 *                                 another server is not loaded.
	 *     @type string   $status      `available` (the default) or
	 *                                 `coming_soon`. `installed` and `active`
	 *                                 are worked out from `plugin` or
	 *                                 `is_active`, and can be given outright.
	 *     @type string   $plugin      Its plugin file, as WordPress names one:
	 *                                 "folder/file.php".
	 *     @type callable $is_active   Returns whether it is running here, for
	 *                                 an add-on that is not a plugin.
	 *     @type string   $category    The heading it is filtered under, such
	 *                                 as "Marketing".
	 *     @type string   $url         Where to learn more or buy it.
	 *     @type string   $price_label A few words on what it costs, such as
	 *                                 "Included in Pro".
	 *     @type string   $badge       One or two words to flag it: "New".
	 *     @type string[] $features    A short list of what it does.
	 *     @type int      $priority    Lower is shown first. Default 10.
	 *     @type bool     $featured    Drawn large, above the rest.
	 * }
	 *
	 * @return bool False, and nothing listed, when the list or the id is
	 *              empty, the id is malformed or taken, there is no name,
	 *              or the status is not one of the four.
	 * @since 1.0.0
	 */
	public static function register( string $catalogue, string $id, array $args ): bool {
		$key    = sanitize_key( $id );
		$name   = self::text( $args['name'] ?? '' );
		$status = (string) ( $args['status'] ?? self::AVAILABLE );

		// The id as it was given, or not at all: one that had to be
		// changed to be used is one its owner could never ask for again.
		$usable = '' !== $catalogue && '' !== $key && $key === $id && ! isset( self::$addons[ $catalogue ][ $key ] );

		if ( ! $usable || '' === $name ) {
			return false;
		}

		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}

		$url = esc_url_raw( (string) ( $args['url'] ?? '' ), [ 'http', 'https' ] );

		self::$addons[ $catalogue ][ $key ] = [
			'id'          => $key,
			'name'        => $name,
			'description' => self::textarea( $args['description'] ?? '' ),
			'icon'        => self::icon( (string) ( $args['icon'] ?? '' ) ),

			// A picture across the top of its card, in place of the icon.
			// On this site or not at all.
			'image'       => self::local( (string) ( $args['image'] ?? $args['banner'] ?? '' ) ),
			'status'      => $status,
			'category'    => self::text( $args['category'] ?? '' ),
			'url'         => $url,
			'price_label' => self::text( $args['price_label'] ?? '' ),
			'badge'       => self::text( $args['badge'] ?? '' ),
			'features'    => array_values( array_filter( array_map( [ self::class, 'text' ], (array) ( $args['features'] ?? [] ) ) ) ),
			'priority'    => (int) ( $args['priority'] ?? 10 ),
			'featured'    => ! empty( $args['featured'] ),

			// How to tell whether this site has it: its plugin file, as
			// WordPress names a plugin ("folder/file.php"), or anything
			// that can be called for a yes or a no.
			'plugin'      => ltrim( (string) ( $args['plugin'] ?? '' ), '/' ),
			'is_active'   => is_callable( $args['is_active'] ?? null ) ? $args['is_active'] : null,
		];

		return true;
	}

	/**
	 * Every add-on on a list, in the order they are shown.
	 *
	 * By priority, lowest first, then by name. Each with its `status` as
	 * it stands on this site, which is not always what it was registered
	 * with: one that is installed here says so.
	 *
	 * @param string $catalogue The list.
	 *
	 * @return array<string, array<string, mixed>> By id.
	 * @since 1.0.0
	 */
	public static function all( string $catalogue ): array {
		self::ask( $catalogue );

		$addons = [];

		foreach ( self::$addons[ $catalogue ] ?? [] as $id => $addon ) {
			$addon['status'] = self::status( $addon );

			// A callable is no use to a screen, a REST answer or an
			// assistant, and has done its job.
			unset( $addon['is_active'] );

			$addons[ $id ] = $addon;
		}

		uasort(
			$addons,
			static fn( array $a, array $b ): int => [ $a['priority'], $a['name'] ] <=> [ $b['priority'], $b['name'] ]
		);

		/**
		 * Filter the add-ons on a list, `{list}_addons`.
		 *
		 * The whole list, after everybody has registered and each has
		 * been given its status. For taking one out or changing what one
		 * says; to add one, call Addons::register() on the
		 * `{list}_register_addons` action, which checks it.
		 *
		 * @param array<string, array<string, mixed>> $addons By id, in order.
		 *
		 * @since 1.0.0
		 */
		$addons = apply_filters( $catalogue . '_addons', $addons );

		return is_array( $addons ) ? array_filter( $addons, 'is_array' ) : [];
	}

	/**
	 * What the four statuses are called.
	 *
	 * @return array<string, string> Status to label, in Title Case: each is
	 *                               the whole of a small badge.
	 * @since 1.0.0
	 */
	public static function statuses(): array {
		return [
			self::ACTIVE      => __( 'Active', 'sugarcommerce' ),
			self::INSTALLED   => __( 'Installed', 'sugarcommerce' ),
			self::AVAILABLE   => __( 'Available', 'sugarcommerce' ),
			self::COMING_SOON => __( 'Coming Soon', 'sugarcommerce' ),
		];
	}

	/**
	 * Forget everything registered on a list, or on every list. For tests.
	 *
	 * @param string|null $catalogue The list, or null for all of them.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function reset( ?string $catalogue = null ): void {
		if ( null === $catalogue ) {
			self::$addons = [];
			self::$asked  = [];

			return;
		}

		unset( self::$addons[ $catalogue ], self::$asked[ $catalogue ] );
	}

	/**
	 * Ask everybody for their add-ons, once a list.
	 *
	 * Left until the list is first wanted, so that being on it costs a
	 * request nothing unless somebody opens the screen or asks.
	 *
	 * @param string $catalogue The list.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function ask( string $catalogue ): void {
		if ( isset( self::$asked[ $catalogue ] ) ) {
			return;
		}

		self::$asked[ $catalogue ] = true;

		/**
		 * Fires when a list of add-ons is being put together, `{list}_register_addons`.
		 *
		 * The moment to call Addons::register() for that list.
		 *
		 * @since 1.0.0
		 */
		do_action( $catalogue . '_register_addons' );
	}

	/**
	 * One line of plain text, or '' for anything that is not text.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private static function text( mixed $value ): string {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Plain text that keeps its line breaks, or '' for anything that is not text.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private static function textarea( mixed $value ): string {
		return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
	}

	/**
	 * An add-on's status on this site.
	 *
	 * What is true here wins over what was registered: an add-on listed
	 * as available whose plugin is running is active. One that is still
	 * coming stays coming -- there is nothing of it to find.
	 *
	 * @param array<string, mixed> $addon The add-on, as registered.
	 *
	 * @return string One of the four.
	 * @since 1.0.0
	 */
	private static function status( array $addon ): string {
		if ( self::COMING_SOON === $addon['status'] ) {
			return self::COMING_SOON;
		}

		if ( is_callable( $addon['is_active'] ) && call_user_func( $addon['is_active'] ) ) {
			return self::ACTIVE;
		}

		$plugin = (string) $addon['plugin'];

		if ( '' !== $plugin ) {
			// Among the active plugins of this site or of the network.
			// Read from the options, since is_plugin_active() is only
			// loaded in the admin.
			if (
				in_array( $plugin, (array) get_option( 'active_plugins', [] ), true )
				|| ( is_multisite() && isset( ( (array) get_site_option( 'active_sitewide_plugins', [] ) )[ $plugin ] ) )
			) {
				return self::ACTIVE;
			}

			if ( 0 === validate_file( $plugin ) && is_readable( WP_PLUGIN_DIR . '/' . $plugin ) ) {
				return self::INSTALLED;
			}
		}

		return (string) $addon['status'];
	}

	/**
	 * An icon that can be drawn without asking another server for it.
	 *
	 * An icon drawn on the page -- `set:name`, or a Dashicon by its name or
	 * its class -- or the address of a picture on this site. Any other
	 * address is somebody else's server, which every admin who opened the
	 * screen would be made to visit: it is swapped for the default. So is
	 * A `set:name` is kept as given: the screen draws it, or the default
	 * in its place when it cannot.
	 *
	 * @param string $icon What was given.
	 *
	 * @return string An icon's name, or a URL on this site.
	 * @since 1.0.0
	 */
	private static function icon( string $icon ): string {
		$icon = trim( $icon );

		if ( 1 === preg_match( '/^(dashicons-)?[a-z0-9-]+$/', $icon ) ) {
			return $icon;
		}

		if ( 1 === preg_match( '/^[a-z0-9-]+:[a-z0-9-]+$/', $icon ) ) {
			return $icon;
		}

		$url = self::local( $icon );

		return '' !== $url ? $url : self::DEFAULT_ICON;
	}

	/**
	 * The address of a picture, when it is on this site.
	 *
	 * @param string $url What was given.
	 *
	 * @return string The address, or empty for one on any other server
	 *                and for anything that is not an address.
	 * @since 1.0.0
	 */
	private static function local( string $url ): string {
		$url  = esc_url_raw( trim( $url ), [ 'http', 'https' ] );
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );

		$own_hosts = [
			wp_parse_url( home_url(), PHP_URL_HOST ),
			wp_parse_url( content_url(), PHP_URL_HOST ),
		];

		$is_local = '' !== $host && in_array( $host, $own_hosts, true );

		return $is_local ? $url : '';
	}
}
