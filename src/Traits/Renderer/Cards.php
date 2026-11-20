<?php
/**
 * The parts of the screen: the header, the featured add-on, and each card with its status, action and features.
 *
 * @package   SugarCommerce\Addons
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Addons\Traits\Renderer;

use SugarCommerce\Addons\Addons;

/**
 * Trait Cards
 *
 * The parts of the screen: the header, the featured add-on, and each card with its status, action and features.
 *
 * @since 1.0.0
 */
trait Cards {

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
}
