<?php
/**
 * Each card's colour from the palette, steady from one visit to the next, and the brand's colours as CSS properties.
 *
 * @package   SugarCommerce\Addons
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Addons\Traits\Renderer;

/**
 * Trait Colours
 *
 * Each card's colour from the palette, steady from one visit to the next, and the brand's colours as CSS properties.
 *
 * Every colour is a hex colour by the time it reaches the page:
 * sanitize_hex_color() on each, and anything else dropped.
 *
 * @since 1.0.0
 */
trait Colours {

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
}
