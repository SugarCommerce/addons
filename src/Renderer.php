<?php
/**
 * A catalogue of add-ons, drawn.
 *
 * @package   SugarCommerce\Addons
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Addons;


/**
 * Draws a list of add-ons as a shop window: a band across the top for the
 * one that is featured, a row to filter and search by, and a grid of cards.
 *
 * **Nothing here knows whose add-ons these are.** Every word on the page
 * is handed in as a label, every colour as a palette, and the list is
 * whatever array it is given -- usually Addons::all()'s. What is a
 * plugin's own (its words, its colours, its badge) is passed in by the
 * screen that calls it: Screen, or a plugin's own.
 *
 * What it draws, and why it is drawn so:
 *
 * - **The grid is fluid.** As many columns as fit at 300 pixels or more
 *   each, across the whole width: one on a phone, two on a laptop, five
 *   on a wide monitor. Cards in a row are the same height and what a card
 *   offers to do is pinned to its foot, so the buttons line up.
 * - **A card has one thing to press at most**: "Get It" for something
 *   that can be had, "Activate" for something installed, "Learn More" for
 *   something still coming that has a page -- and nothing at all when
 *   there is nowhere to send anybody. A dead button is a broken promise.
 * - **Filtering is the page's own script** and asks no server anything:
 *   each card carries its category and the words it is found by, and the
 *   script (assets/js/addons.js) hides the ones that do not match.
 *   With scripts off, every card is simply shown.
 * - **Accessible.** Each card is an article named by its heading. The
 *   categories are a radio group -- one is always chosen, the arrow keys
 *   move between them -- and how many cards are showing is announced in
 *   a live region when it changes.
 *
 * No picture, script, font or stylesheet is loaded from anywhere but the
 * site itself: the registry has already refused any image that is not.
 *
 * @since 1.0.0
 */
final class Renderer {

	/**
	 * The class every icon here has, SVG or font, which the stylesheet sizes.
	 *
	 * @since 1.0.0
	 */
	public const ICON_CLASS = 'addons-icon';

	/**
	 * The library's own icons, drawn here so that it needs no icon set:
	 * Heroicons outline paths, MIT licence, https://heroicons.com.
	 *
	 * @var array<string, string>
	 */
	private const OWN_ICONS = [
		'heroicons-outline:magnifying-glass' => 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z',
		'heroicons-outline:check-circle'     => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
		'heroicons-outline:check'            => 'm4.5 12.75 6 6 9-13.5',
		'heroicons-outline:puzzle-piece'     => 'M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.39 48.39 0 0 1-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 0 1-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 0 0-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 0 1-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 0 0 .657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 0 1-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 0 0 5.427-.63 48.05 48.05 0 0 0 .582-4.717.532.532 0 0 0-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 0 0 .658-.663 48.422 48.422 0 0 0-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 0 1-.61-.58v0Z',
	];

	/**
	 * What draws an add-on's `set:name` icon while a catalogue is drawn.
	 *
	 * @var callable|null
	 */
	private static $icons = null;

	/**
	 * Draw the catalogue.
	 *
	 * @param array<string, array<string, mixed>> $addons The add-ons, by id, in the order
	 *                                                    to show them, each as the registry
	 *                                                    gives it.
	 * @param array<string, mixed>                $args   {
	 *     How to draw them.
	 *
	 *     @type array<string, mixed>                              $labels   Every word on the page: see
	 *                                                                       labels() for the keys.
	 *     @type array<int, array{tint: string, shade: string}>    $palette  Colours for the icon tiles,
	 *                                                                       each a pale `tint` and a deep
	 *                                                                       `shade`, as hex.
	 *     @type string                                            $brand    The colour the band starts in,
	 *                                                                       and of focus rings, as hex.
	 *     @type array<int, string>                                $band     Two more colours, as hex, that
	 *                                                                       the band shades into.
	 *     @type string                                            $category The category to open on, by key.
	 *     @type callable                                          $badge    Draws a status: given the status
	 *                                                                       and its label, returns markup.
	 *     @type array<string, string>                             $notes    A further sentence for an add-on,
	 *                                                                       by id.
	 *     @type callable                                          $icons    Draws a `set:name` icon the library
	 *                                                                       does not have itself: given the name
	 *                                                                       and `[ 'class' => … ]`, returns markup,
	 *                                                                       or '' for one it does not have.
	 *     @type callable                                          $extra    Draws more inside an add-on's card or
	 *                                                                       band, under its features -- a licence
	 *                                                                       key box, a link to its settings: given
	 *                                                                       the add-on, returns markup, or '' for
	 *                                                                       nothing. Printed as it is returned, so
	 *                                                                       it escapes what it prints.
	 * }
	 *
	 * @return string Markup.
	 * @since 1.0.0
	 */
	public static function render( array $addons, array $args = [] ): string {
		self::$icons = is_callable( $args['icons'] ?? null ) ? $args['icons'] : null;

		try {
			return self::catalogue( $addons, $args );
		} finally {
			self::$icons = null;
		}
	}

	/**
	 * Draw the catalogue, the icon callable set.
	 *
	 * @param array<string, array<string, mixed>> $addons The add-ons.
	 * @param array<string, mixed>                $args   As render().
	 *
	 * @return string Markup.
	 */
	private static function catalogue( array $addons, array $args ): string {
		$labels   = self::labels( (array) ( $args['labels'] ?? [] ) );
		$palette  = self::palette( (array) ( $args['palette'] ?? [] ) );
		$badge    = is_callable( $args['badge'] ?? null ) ? $args['badge'] : null;
		$notes    = array_map( 'strval', (array) ( $args['notes'] ?? [] ) );
		$extra    = is_callable( $args['extra'] ?? null ) ? $args['extra'] : null;
		$current  = sanitize_title( (string) ( $args['category'] ?? '' ) );
		$style    = self::style( (string) ( $args['brand'] ?? '' ), (array) ( $args['band'] ?? [] ) );
		$featured = [];
		$owned    = [];
		$others   = [];

		foreach ( $addons as $id => $addon ) {
			$addon['id'] = (string) ( $addon['id'] ?? $id );

			if ( ! empty( $addon['featured'] ) ) {
				$featured[] = $addon;
			} elseif ( in_array( (string) ( $addon['status'] ?? '' ), [ 'installed', 'active' ], true ) ) {
				$owned[] = $addon;
			} else {
				$others[] = $addon;
			}
		}

		$cards      = array_merge( $owned, $others );
		$categories = self::categories( $cards );
		$colours    = self::colours( array_column( $cards, 'id' ), count( $palette ) );
		$current    = isset( $categories[ $current ] ) ? $current : '';

		$draw = static function ( array $items ) use ( $labels, $palette, $colours, $badge, $notes, $extra ): string {
			$html = '';

			foreach ( $items as $addon ) {
				$html .= self::card( $addon, $labels, $palette[ $colours[ $addon['id'] ] ?? 0 ], $badge, $notes[ $addon['id'] ] ?? '', self::extra( $extra, $addon ) );
			}

			return $html;
		};

		$html = sprintf(
			'<div class="addons-catalogue" data-addons data-category="%1$s" data-shown-one="%2$s" data-shown-many="%3$s"%4$s>',
			esc_attr( $current ),
			esc_attr( $labels['shown_one'] ),
			esc_attr( $labels['shown_many'] ),
			'' !== $style ? ' style="' . esc_attr( $style ) . '"' : ''
		);

		$html .= self::header( $labels, $categories, $current, [] !== $cards );

		foreach ( $featured as $addon ) {
			$html .= self::hero( $addon, $labels, $badge, $notes[ $addon['id'] ] ?? '', self::extra( $extra, $addon ) );
		}

		// The ones this site has, first and under a heading of their own
		// -- but only when there are some: a heading over the whole list
		// that says "More" has nothing to be more than.
		if ( [] !== $owned ) {
			$html .= sprintf(
				'<section class="addons-section" data-addons-section><h2 class="addons-section-title">%1$s</h2><div class="addons-grid">%2$s</div></section>',
				esc_html( $labels['installed'] ),
				$draw( $owned )
			);
		}

		if ( [] !== $others ) {
			$html .= sprintf(
				'<section class="addons-section" data-addons-section>%1$s<div class="addons-grid">%2$s</div></section>',
				[] !== $owned ? '<h2 class="addons-section-title">' . esc_html( $labels['more'] ) . '</h2>' : '',
				$draw( $others )
			);
		}

		// Nothing matches: hidden until the script finds that to be so,
		// and shown from the start when there is nothing at all.
		$html .= sprintf(
			'<div class="addons-empty" data-addons-empty%1$s>%5$s<p class="addons-empty-title">%2$s</p><p>%3$s</p>%4$s</div>',
			[] === $cards ? '' : ' hidden',
			esc_html( [] === $cards ? $labels['none_title'] : $labels['empty_title'] ),
			esc_html( [] === $cards ? $labels['none_text'] : $labels['empty_text'] ),
			[] === $cards ? '' : '<button type="button" class="button" data-addons-clear>' . esc_html( $labels['clear'] ) . '</button>',
			self::icon( 'heroicons-outline:magnifying-glass' )
		);

		// How many are showing, said aloud when it changes.
		$html .= '<p class="screen-reader-text" role="status" aria-live="polite" data-addons-status></p>';

		return $html . '</div>';
	}

	/**
	 * Every word the catalogue can say, with plain defaults.
	 *
	 * @param array<string, mixed> $labels The caller's.
	 *
	 * @return array<string, mixed>
	 * @since 1.0.0
	 */
	public static function labels( array $labels = [] ): array {
		$defaults = [
			'title'       => __( 'Add-ons', 'sugarcommerce' ),
			'intro'       => '',

			// The filter and the search.
			'filter'      => __( 'Categories', 'sugarcommerce' ),
			'all'         => __( 'All', 'sugarcommerce' ),
			'search'      => __( 'Search add-ons', 'sugarcommerce' ),
			'placeholder' => __( 'Search…', 'sugarcommerce' ),

			// The two sections, when the site has some of them.
			'installed'   => __( 'Installed', 'sugarcommerce' ),
			'more'        => __( 'More Add-ons', 'sugarcommerce' ),

			// A search or a category with nothing in it, and a list with
			// nothing on it at all.
			'empty_title' => __( 'No add-ons match', 'sugarcommerce' ),
			'empty_text'  => __( 'Try another word, or another category.', 'sugarcommerce' ),
			'clear'       => __( 'Show All Add-ons', 'sugarcommerce' ),
			'none_title'  => __( 'No add-ons yet', 'sugarcommerce' ),
			'none_text'   => '',

			// Announced: how many cards are showing. %d is the number.
			/* translators: %d: a number of add-ons, which is one. */
			'shown_one'   => __( '%d add-on shown', 'sugarcommerce' ),
			/* translators: %d: a number of add-ons. */
			'shown_many'  => __( '%d add-ons shown', 'sugarcommerce' ),

			// What a card offers to do.
			'get'         => __( 'Get It', 'sugarcommerce' ),
			'learn'       => __( 'Learn More', 'sugarcommerce' ),
			'activate'    => __( 'Activate', 'sugarcommerce' ),

			// Hidden text for a link that leaves the site. %s is its name.
			/* translators: Hidden accessibility text. %s: the add-on's name. */
			'new_tab'     => __( '%s (opens in a new tab)', 'sugarcommerce' ),

			'statuses'    => [
				'active'      => __( 'Active', 'sugarcommerce' ),
				'installed'   => __( 'Installed', 'sugarcommerce' ),
				'available'   => __( 'Available', 'sugarcommerce' ),
				'coming_soon' => __( 'Coming Soon', 'sugarcommerce' ),
			],
		];

		$labels['statuses'] = (array) ( $labels['statuses'] ?? [] ) + $defaults['statuses'];

		return $labels + $defaults;
	}

	/**
	 * The categories the cards are in.
	 *
	 * In the order they are first met. Keyed by a slug of the name, which
	 * is what a card carries and an address can ask for.
	 *
	 * @param array<int|string, array<string, mixed>> $addons The add-ons.
	 *
	 * @return array<string, string> Key to name.
	 * @since 1.0.0
	 */
	public static function categories( array $addons ): array {
		$categories = [];

		foreach ( $addons as $addon ) {
			$name = trim( (string) ( $addon['category'] ?? '' ) );

			if ( '' !== $name && empty( $addon['featured'] ) ) {
				$categories[ sanitize_title( $name ) ] = $name;
			}
		}

		return $categories;
	}

	/**
	 * Which colour of the palette each add-on's icon is drawn in.
	 *
	 * Its own, from its id, so an add-on is the same colour every time the
	 * page is opened; and not a neighbour's, while there are colours left,
	 * so a row of cards is not three of one. An id whose colour is taken
	 * has the next free one.
	 *
	 * @param array<int, string> $ids   The add-ons, in order.
	 * @param int                $count How many colours there are.
	 *
	 * @return array<string, int> Id to an index of the palette.
	 * @since 1.0.0
	 */
	public static function colours( array $ids, int $count ): array {
		$colours = [];
		$taken   = [];

		if ( $count < 1 ) {
			return $colours;
		}

		foreach ( $ids as $id ) {
			$index = (int) ( abs( crc32( (string) $id ) ) % $count );

			// Round again once every colour has been used.
			if ( count( $taken ) >= $count ) {
				$taken = [];
			}

			while ( isset( $taken[ $index ] ) ) {
				$index = ( $index + 1 ) % $count;
			}

			$taken[ $index ]         = true;
			$colours[ (string) $id ] = $index;
		}

		return $colours;
	}

	/**
	 * The palette, as pairs that can be used: a tint and a shade, in hex.
	 *
	 * @param array<int|string, mixed> $palette What was given.
	 *
	 * @return array<int, array{tint: string, shade: string}> Never empty:
	 *         a quiet grey when nothing usable was given.
	 * @since 1.0.0
	 */
	private static function palette( array $palette ): array {
		$kept = [];

		foreach ( $palette as $colour ) {
			$tint  = is_array( $colour ) ? sanitize_hex_color( (string) ( $colour['tint'] ?? '' ) ) : '';
			$shade = is_array( $colour ) ? sanitize_hex_color( (string) ( $colour['shade'] ?? '' ) ) : '';

			if ( $tint && $shade ) {
				$kept[] = [
					'tint'  => $tint,
					'shade' => $shade,
				];
			}
		}

		return [] !== $kept ? $kept : [
			[
				'tint'  => '#f0f0f1',
				'shade' => '#50575e',
			],
		];
	}

	/**
	 * The caller's colours, as the custom properties the stylesheet reads.
	 *
	 * @param string             $brand The band's first colour.
	 * @param array<int, string> $band  Its second and third.
	 *
	 * @return string A `style` attribute's value; empty for no colours.
	 * @since 1.0.0
	 */
	private static function style( string $brand, array $band ): string {
		$style = [];

		foreach ( [
			'--addons-brand'    => $brand,
			'--addons-band-mid' => $band[0] ?? '',
			'--addons-band-end' => $band[1] ?? '',
		] as $property => $colour ) {
			$colour = sanitize_hex_color( (string) $colour );

			if ( $colour ) {
				$style[] = $property . ':' . $colour;
			}
		}

		return implode( ';', $style );
	}

	/**
	 * The top of the page: its title and a line, then the filter and the
	 * search on the same row.
	 *
	 * @param array<string, mixed>  $labels     The words.
	 * @param array<string, string> $categories Key to name.
	 * @param string                $current    The category chosen.
	 * @param bool                  $searchable Whether there are cards
	 *                                          to search.
	 *
	 * @return string Markup.
	 * @since 1.0.0
	 */
	private static function header( array $labels, array $categories, string $current, bool $searchable ): string {
		$tools = '';

		// Pills only when they would divide something.
		if ( count( $categories ) > 1 ) {
			$pills = self::pill( '', (string) $labels['all'], '' === $current );

			foreach ( $categories as $key => $name ) {
				$pills .= self::pill( (string) $key, $name, (string) $key === $current );
			}

			$tools .= sprintf(
				'<div class="addons-filter" role="radiogroup" aria-label="%1$s" data-addons-filter>%2$s</div>',
				esc_attr( (string) $labels['filter'] ),
				$pills
			);
		}

		if ( $searchable ) {
			$tools .= sprintf(
				'<label class="addons-search"><span class="screen-reader-text">%1$s</span>%3$s<input type="search" placeholder="%2$s" autocomplete="off" data-addons-search /></label>',
				esc_html( (string) $labels['search'] ),
				esc_attr( (string) $labels['placeholder'] ),
				self::icon( 'heroicons-outline:magnifying-glass' )
			);
		}

		return sprintf(
			'<header class="addons-header"><div class="addons-heading"><h1>%1$s</h1>%2$s</div>%3$s</header>',
			esc_html( (string) $labels['title'] ),
			'' !== (string) $labels['intro'] ? '<p class="addons-intro">' . esc_html( (string) $labels['intro'] ) . '</p>' : '',
			'' !== $tools ? '<div class="addons-tools">' . $tools . '</div>' : ''
		);
	}

	/**
	 * One category, as a pill in the radio group.
	 *
	 * @param string $key     Its key; empty for "all".
	 * @param string $name    What it says.
	 * @param bool   $current Whether it is the one chosen.
	 *
	 * @return string Markup.
	 * @since 1.0.0
	 */
	private static function pill( string $key, string $name, bool $current ): string {
		// Only the chosen one is a tab stop; the arrow keys reach the
		// rest, as in any group of radios.
		return sprintf(
			'<button type="button" class="addons-pill" role="radio" aria-checked="%1$s" tabindex="%2$s" data-addons-category="%3$s">%4$s</button>',
			$current ? 'true' : 'false',
			$current ? '0' : '-1',
			esc_attr( $key ),
			esc_html( $name )
		);
	}

	/**
	 * The featured add-on, as a band across the page.
	 *
	 * @param array<string, mixed> $addon  The add-on.
	 * @param array<string, mixed> $labels The words.
	 * @param callable|null        $badge  Draws a status.
	 * @param string               $note   A further sentence.
	 * @param string               $extra  More to draw under its features.
	 *
	 * @return string Markup.
	 * @since 1.0.0
	 */
	private static function hero( array $addon, array $labels, ?callable $badge, string $note, string $extra = '' ): string {
		$id       = sanitize_html_class( 'addon-' . $addon['id'] );
		$status   = (string) ( $addon['status'] ?? 'available' );
		$features = '';

		foreach ( self::features( $addon ) as $feature ) {
			$features .= '<li>' . self::icon( 'heroicons-outline:check-circle' ) . esc_html( $feature ) . '</li>';
		}

		$action = self::action( $addon, $status, $labels );
		$icon   = (string) ( $addon['icon'] ?? '' );

		return sprintf(
			'<article class="addons-hero is-%1$s" aria-labelledby="%2$s">%10$s<div class="addons-hero-inner"><div class="addons-hero-text"><div class="addons-hero-head"><h2 class="addons-hero-name" id="%2$s">%3$s</h2>%4$s%5$s</div>%6$s%7$s%8$s%11$s</div>%9$s</div></article>',
			esc_attr( sanitize_html_class( $status ) ),
			esc_attr( $id ),
			esc_html( (string) ( $addon['name'] ?? '' ) ),
			'' !== (string) ( $addon['badge'] ?? '' ) ? '<span class="addons-flag">' . esc_html( (string) $addon['badge'] ) . '</span>' : '',
			self::status( $status, $labels, $badge ),
			'' !== (string) ( $addon['description'] ?? '' ) ? '<p class="addons-hero-about">' . esc_html( (string) $addon['description'] ) . '</p>' : '',
			'' !== $note ? '<p class="addons-hero-about">' . esc_html( $note ) . '</p>' : '',
			'' !== $features ? '<ul class="addons-hero-features">' . $features . '</ul>' : '',
			'' !== $action || '' !== (string) ( $addon['price_label'] ?? '' )
				? '<div class="addons-hero-action">' . $action . ( '' !== (string) ( $addon['price_label'] ?? '' ) ? '<span class="addons-price">' . esc_html( (string) $addon['price_label'] ) . '</span>' : '' ) . '</div>'
				: '',
			// Its icon again, very large and very faint, off the far
			// edge: something for the band to be a picture of. Only for
			// an icon: a picture, or no icon, has no mark.
			! self::is_picture( $icon )
				? self::icon( $icon, 'addons-hero-mark', Addons::DEFAULT_ICON )
				: '',
			'' !== $extra ? '<div class="addons-hero-extra">' . $extra . '</div>' : ''
		);
	}

	/**
	 * One add-on, as a card.
	 *
	 * An article with its name as its heading, so the page is a list of
	 * headings to a screen reader, and its one link last, so the keyboard
	 * goes card by card in the order they are read.
	 *
	 * @param array<string, mixed>               $addon  The add-on.
	 * @param array<string, mixed>               $labels The words.
	 * @param array{tint: string, shade: string} $colour Its icon's colours.
	 * @param callable|null                      $badge  Draws a status.
	 * @param string                             $note   A further sentence.
	 * @param string                             $extra  More to draw under its features.
	 *
	 * @return string Markup.
	 * @since 1.0.0
	 */
	private static function card( array $addon, array $labels, array $colour, ?callable $badge, string $note, string $extra = '' ): string {
		$id       = sanitize_html_class( 'addon-' . $addon['id'] );
		$status   = (string) ( $addon['status'] ?? 'available' );
		$name     = (string) ( $addon['name'] ?? '' );
		$about    = trim( (string) ( $addon['description'] ?? '' ) . ' ' . $note );
		$category = trim( (string) ( $addon['category'] ?? '' ) );
		$image    = (string) ( $addon['image'] ?? '' );
		$features = '';

		foreach ( self::features( $addon ) as $feature ) {
			$features .= '<li>' . self::icon( 'heroicons-outline:check' ) . esc_html( $feature ) . '</li>';
		}

		$action = self::action( $addon, $status, $labels );
		$price  = (string) ( $addon['price_label'] ?? '' );

		// A picture across the top when it has one; otherwise its icon on
		// a tile of its own colour.
		$top = '' !== $image
			? sprintf( '<div class="addons-card-image"><img src="%s" alt="" loading="lazy" /></div>', esc_url( $image ) )
			: '';

		$tile = '' !== $image
			? ''
			: sprintf(
				'<div class="addons-tile" style="%1$s" aria-hidden="true">%2$s</div>',
				esc_attr( 'background:' . $colour['tint'] . ';color:' . $colour['shade'] ),
				self::tile_icon( (string) ( $addon['icon'] ?? '' ) )
			);

		return sprintf(
			'<article class="addons-card is-%1$s" aria-labelledby="%2$s" data-addons-card data-category="%3$s" data-search="%4$s">%5$s<div class="addons-card-body"><div class="addons-card-top">%6$s%7$s</div><h3 class="addons-card-name" id="%2$s">%8$s%9$s</h3>%10$s%11$s%15$s<footer class="addons-card-foot"><span class="addons-card-category">%12$s</span><span class="addons-card-action">%13$s%14$s</span></footer></div></article>',
			esc_attr( sanitize_html_class( $status ) ),
			esc_attr( $id ),
			esc_attr( sanitize_title( $category ) ),
			// What it is found by, in lower case, so the script compares
			// like with like.
			esc_attr( strtolower( wp_strip_all_tags( $name . ' ' . $about . ' ' . $category . ' ' . implode( ' ', self::features( $addon ) ) ) ) ),
			$top,
			$tile,
			self::status( $status, $labels, $badge ),
			esc_html( $name ),
			'' !== (string) ( $addon['badge'] ?? '' ) ? ' <span class="addons-flag">' . esc_html( (string) $addon['badge'] ) . '</span>' : '',
			'' !== $about ? '<p class="addons-card-about">' . esc_html( $about ) . '</p>' : '',
			'' !== $features ? '<ul class="addons-card-features">' . $features . '</ul>' : '',
			esc_html( $category ),
			'' !== $price ? '<span class="addons-price">' . esc_html( $price ) . '</span>' : '',
			$action,
			'' !== $extra ? '<div class="addons-card-extra">' . $extra . '</div>' : ''
		);
	}

	/**
	 * What the caller draws inside one add-on's card, if anything.
	 *
	 * @param callable|null        $extra The caller's `extra`.
	 * @param array<string, mixed> $addon The add-on.
	 *
	 * @return string Markup; empty for nothing.
	 * @since 1.0.0
	 */
	private static function extra( ?callable $extra, array $addon ): string {
		return null === $extra ? '' : trim( (string) $extra( $addon ) );
	}

	/**
	 * An add-on's status, as a small badge.
	 *
	 * @param string               $status The status.
	 * @param array<string, mixed> $labels The words.
	 * @param callable|null        $badge  Draws it, when the caller has a
	 *                                     badge of its own.
	 *
	 * @return string Markup.
	 * @since 1.0.0
	 */
	private static function status( string $status, array $labels, ?callable $badge ): string {
		$label = (string) ( $labels['statuses'][ $status ] ?? $labels['statuses']['available'] );

		return '<span class="addons-status is-' . esc_attr( sanitize_html_class( $status ) ) . '">'
			. ( $badge ? (string) $badge( $status, $label ) : esc_html( $label ) )
			. '</span>';
	}

	/**
	 * The one thing a card offers to do.
	 *
	 * @param array<string, mixed> $addon  The add-on.
	 * @param string               $status Its status on this site.
	 * @param array<string, mixed> $labels The words.
	 *
	 * @return string Markup. Empty when there is nothing to do: something
	 *                still coming with no page, something already running.
	 * @since 1.0.0
	 */
	private static function action( array $addon, string $status, array $labels ): string {
		$url    = (string) ( $addon['url'] ?? '' );
		$plugin = (string) ( $addon['plugin'] ?? '' );
		$name   = (string) ( $addon['name'] ?? '' );

		if ( 'installed' === $status && '' !== $plugin && current_user_can( 'activate_plugin', $plugin ) ) {
			// WordPress's own way of switching a plugin on, with its own
			// nonce.
			return sprintf(
				'<a class="button button-primary" href="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></a>',
				esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $plugin ) ), 'activate-plugin_' . $plugin ) ),
				esc_html( (string) $labels['activate'] ),
				esc_html( $name )
			);
		}

		if ( '' === $url ) {
			return '';
		}

		$get   = 'available' === $status;
		$label = (string) ( $get ? $labels['get'] : $labels['learn'] );

		// Somewhere else, so in a tab of its own, and said so to whoever
		// cannot see that it is.
		return sprintf(
			'<a class="button%1$s" href="%2$s" target="_blank" rel="noopener noreferrer">%3$s<span class="screen-reader-text"> %4$s</span></a>',
			$get ? ' button-primary' : '',
			esc_url( $url ),
			esc_html( $label ),
			esc_html( sprintf( (string) $labels['new_tab'], $name ) )
		);
	}

	/**
	 * An add-on's features, as plain lines.
	 *
	 * @param array<string, mixed> $addon The add-on.
	 *
	 * @return array<int, string>
	 * @since 1.0.0
	 */
	private static function features( array $addon ): array {
		return array_values( array_filter( array_map( 'strval', (array) ( $addon['features'] ?? [] ) ) ) );
	}

	/**
	 * An add-on's icon: an icon, or a picture on this site.
	 *
	 * @param string $icon A `set:name` icon, a Dashicons name, or a URL.
	 *
	 * @return string Markup.
	 * @since 1.0.0
	 */
	private static function tile_icon( string $icon ): string {
		if ( '' === $icon || ! self::is_picture( $icon ) ) {
			return self::icon( '' !== $icon ? $icon : Addons::DEFAULT_ICON, '', Addons::DEFAULT_ICON );
		}

		return sprintf( '<img src="%s" alt="" width="32" height="32" loading="lazy" />', esc_url( $icon ) );
	}

	/**
	 * Whether an add-on's icon is the address of a picture rather than an
	 * icon's name.
	 *
	 * @param string $icon What the add-on has.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private static function is_picture( string $icon ): bool {
		return 1 !== preg_match( '/^[a-z0-9-]+(:[a-z0-9-]+)?$/i', trim( $icon ) );
	}

	/**
	 * An icon, in either form a caller may give one, ready to print.
	 *
	 * A Dashicons name (`yes`, or the class `dashicons-yes`) is the font's
	 * span. A `set:name` is inline SVG: the library's own four are drawn
	 * here, and any other by the `icons` callable. Either way the element
	 * has the ICON_CLASS, which the stylesheet sizes.
	 *
	 * @param string $value        The icon.
	 * @param string $classes      Classes for it, beside ICON_CLASS.
	 * @param string ...$fallbacks Icons to draw, in turn, when it cannot be.
	 *
	 * @return string Markup; empty when none of them can be drawn.
	 * @since 1.0.0
	 */
	private static function icon( string $value, string $classes = '', string ...$fallbacks ): string {
		foreach ( [ $value, ...$fallbacks ] as $candidate ) {
			$html = self::glyph( $candidate, $classes );

			if ( '' !== $html ) {
				return $html;
			}
		}

		return '';
	}

	/**
	 * One icon.
	 *
	 * @param string $value   The icon.
	 * @param string $classes Classes for it.
	 *
	 * @return string Markup; empty when it cannot be drawn.
	 * @since 1.0.0
	 */
	private static function glyph( string $value, string $classes ): string {
		$value   = strtolower( trim( $value ) );
		$classes = trim( self::ICON_CLASS . ' ' . $classes );

		if ( isset( self::OWN_ICONS[ $value ] ) ) {
			return sprintf(
				'<svg class="%1$s" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="%2$s"/></svg>',
				esc_attr( $classes ),
				esc_attr( self::OWN_ICONS[ $value ] )
			);
		}

		if ( str_contains( $value, ':' ) ) {
			return null === self::$icons ? '' : (string) call_user_func( self::$icons, $value, [ 'class' => $classes ] );
		}

		$name = str_starts_with( $value, 'dashicons-' ) ? substr( $value, strlen( 'dashicons-' ) ) : $value;

		if ( 1 !== preg_match( '/^[a-z0-9-]+$/', $name ) ) {
			return '';
		}

		return sprintf(
			'<span class="%s" aria-hidden="true"></span>',
			esc_attr( 'dashicons dashicons-' . $name . ' ' . $classes )
		);
	}
}
