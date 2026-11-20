<?php
/**
 * A card's icon: a picture by URL, one of the screen's own, a dashicon, or one a consumer draws by `set:name`.
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
 * Trait Icons
 *
 * A card's icon: a picture by URL, one of the screen's own, a dashicon, or one a consumer draws by `set:name`.
 *
 * @since 1.0.0
 */
trait Icons {

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
