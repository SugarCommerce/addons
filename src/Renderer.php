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

use SugarCommerce\Addons\Traits\Renderer\Cards;
use SugarCommerce\Addons\Traits\Renderer\Colours;
use SugarCommerce\Addons\Traits\Renderer\Icons;

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
 *
 * The colours, the cards and the icons are traits in Traits/Renderer;
 * assembling the screen is here.
 */
final class Renderer {

	use Colours;
	use Cards;
	use Icons;

	/**
	 * The class every icon here has, SVG or font, which the stylesheet sizes.
	 *
	 * @since 1.0.0
	 */
	public const string ICON_CLASS = 'addons-icon';

	/**
	 * The library's own icons, drawn here so that it needs no icon set:
	 * Heroicons outline paths, MIT licence, https://heroicons.com.
	 *
	 * @var array<string, string>
	 */
	private const array OWN_ICONS = [
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
}
