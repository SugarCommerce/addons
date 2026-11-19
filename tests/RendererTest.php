<?php
/**
 * The catalogue as it is drawn.
 *
 * @package SugarCommerce\Addons
 */

declare( strict_types=1 );

namespace SugarCommerce\Addons\Tests;

use SugarCommerce\Addons\Addons;
use SugarCommerce\Addons\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * Class RendererTest
 */
final class RendererTest extends TestCase {

	/**
	 * Twelve colours, as a plugin's palette might be.
	 */
	private const PALETTE = [
		[ 'tint' => '#fde8ef', 'shade' => '#a3246c' ],
		[ 'tint' => '#ece6fb', 'shade' => '#5b3ea8' ],
		[ 'tint' => '#e3f1fb', 'shade' => '#1f6aa5' ],
		[ 'tint' => '#e4f6ec', 'shade' => '#1f7a4a' ],
		[ 'tint' => '#fff4dc', 'shade' => '#8a5a00' ],
		[ 'tint' => '#fde9e2', 'shade' => '#a8401f' ],
		[ 'tint' => '#e6f6f6', 'shade' => '#16706f' ],
		[ 'tint' => '#f3e8fb', 'shade' => '#7a2fa8' ],
		[ 'tint' => '#eef4e0', 'shade' => '#4f6b12' ],
		[ 'tint' => '#e8ecf8', 'shade' => '#33449a' ],
		[ 'tint' => '#fbe9f6', 'shade' => '#9a2a83' ],
		[ 'tint' => '#f1efe9', 'shade' => '#5e5646' ],
	];

	/**
	 * A fresh site and empty lists.
	 */
	protected function setUp(): void {
		parent::setUp();

		addons_test_reset();
		Addons::reset();
	}

	/**
	 * A list like a plugin's placeholders: one featured, eight still coming, in four categories.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function catalogue(): array {
		Addons::register(
			'shop',
			'pro',
			[
				'name'        => 'Shop Pro',
				'description' => 'Everything, in one.',
				'icon'        => 'dashicons-star-filled',
				'status'      => 'coming_soon',
				'featured'    => true,
				'priority'    => 1,
				'features'    => [ 'One', 'Two', 'Three' ],
				'category'    => 'Bundles',
			]
		);

		$cards = [
			'gateways'   => [ 'Gateways', 'Payments' ],
			'licensing'  => [ 'Licensing', 'Selling' ],
			'affiliates' => [ 'Affiliates', 'Partners' ],
			'payouts'    => [ 'Payouts', 'Partners' ],
			'mobile'     => [ 'Mobile App', 'Selling' ],
			'firewall'   => [ 'Firewall Sync', 'Security' ],
			'reviews'    => [ 'Reviews', 'Selling' ],
			'themes'     => [ 'Themes', 'Payments' ],
		];

		foreach ( $cards as $id => [ $name, $category ] ) {
			Addons::register(
				'shop',
				$id,
				[
					'name'        => $name,
					'description' => 'Give ' . strtolower( $category ) . ' more.',
					'icon'        => 'dashicons-admin-generic',
					'status'      => 'coming_soon',
					'category'    => $category,
				]
			);
		}

		return Addons::all( 'shop' );
	}

	/**
	 * A title, the featured one as a band, the categories as radios, a search box, and a card for each.
	 */
	public function test_the_catalogue_is_drawn(): void {
		$html = Renderer::render( $this->catalogue(), [ 'palette' => self::PALETTE ] );

		$this->assertStringStartsWith( '<div class="addons-catalogue" data-addons data-category=""', $html );
		$this->assertStringContainsString( '<h1>Add-ons</h1>', $html );
		$this->assertSame( 1, substr_count( $html, '<article class="addons-hero' ) );
		$this->assertSame( 8, substr_count( $html, 'data-addons-card' ) );
		$this->assertSame( 9, substr_count( $html, 'Coming Soon' ) );

		// The band: its name, its sentence and its ticks, and its icon as a faint mark.
		$this->assertStringContainsString( 'Shop Pro</h2>', $html );
		$this->assertSame( 3, substr_count( $html, '<li>' . self::own( 'check-circle' ) ) );
		$this->assertStringContainsString( '<span class="dashicons dashicons-star-filled addons-icon addons-hero-mark" aria-hidden="true"></span>', $html );

		// Every card and the band is an article named by its own heading.
		$this->assertSame( 9, preg_match_all( '/<article[^>]+aria-labelledby="(addon-[a-z-]+)"/', $html, $labelled ) );

		foreach ( $labelled[1] as $id ) {
			$this->assertMatchesRegularExpression( '/<h[23] class="[a-z-]+" id="' . $id . '">/', $html );
		}

		// Four categories and All, as radios, with All chosen and the only
		// one the Tab key stops at. The featured one's category is not one.
		$this->assertStringContainsString( 'role="radiogroup" aria-label="Categories"', $html );
		$this->assertSame( 5, substr_count( $html, 'role="radio"' ) );
		$this->assertSame( 1, substr_count( $html, 'aria-checked="true"' ) );
		$this->assertSame( 1, substr_count( $html, 'tabindex="0"' ) );
		$this->assertStringContainsString( 'aria-checked="true" tabindex="0" data-addons-category="">All</button>', $html );
		$this->assertStringNotContainsString( 'data-addons-category="bundles"', $html );

		// Somewhere to search, somewhere to say how many are showing, and
		// what to say when none is -- hidden until then.
		$this->assertStringContainsString( 'type="search"', $html );
		$this->assertStringContainsString( 'role="status" aria-live="polite"', $html );
		$this->assertStringContainsString( 'data-shown-many="%d add-ons shown"', $html );
		$this->assertStringContainsString( 'data-addons-empty hidden', $html );

		// Each card says what it is filtered and found by.
		$this->assertStringContainsString( 'data-category="partners" data-search="affiliates give partners more. partners"', $html );

		// Nothing to press on something still coming without a page.
		$this->assertStringNotContainsString( '<a class="button', $html );

		// One section, with no heading over it: nothing is installed.
		$this->assertSame( 1, substr_count( $html, 'data-addons-section' ) );
		$this->assertStringNotContainsString( 'More Add-ons', $html );
	}

	/**
	 * Every word is the caller's to give, and statuses can be given one at a time.
	 */
	public function test_the_words_are_the_callers(): void {
		$html = Renderer::render(
			$this->catalogue(),
			[
				'labels' => [
					'title'    => 'Extras',
					'intro'    => 'More for your <shop>.',
					'filter'   => 'Add-on categories',
					'statuses' => [ 'coming_soon' => 'Soon' ],
				],
			]
		);

		$this->assertStringContainsString( '<h1>Extras</h1><p class="addons-intro">More for your &lt;shop&gt;.</p>', $html );
		$this->assertStringContainsString( 'aria-label="Add-on categories"', $html );
		$this->assertSame( 9, substr_count( $html, '>Soon</span>' ) );

		$labels = Renderer::labels( [ 'statuses' => [ 'active' => 'Running' ] ] );

		$this->assertSame( 'Running', $labels['statuses']['active'] );
		$this->assertSame( 'Installed', $labels['statuses']['installed'] );
		$this->assertSame( 'Get It', $labels['get'] );
	}

	/**
	 * A badge drawer and a further sentence are the caller's too.
	 */
	public function test_badges_and_notes(): void {
		$html = Renderer::render(
			$this->catalogue(),
			[
				'badge' => static fn( string $status, string $label ): string => '<b class="pill-' . $status . '">' . esc_html( $label ) . '</b>',
				'notes' => [
					'pro'     => 'And a note for the band.',
					'reviews' => 'And one for a card.',
				],
			]
		);

		$this->assertSame( 9, substr_count( $html, '<b class="pill-coming_soon">Coming Soon</b>' ) );
		$this->assertStringContainsString( '<p class="addons-hero-about">And a note for the band.</p>', $html );
		$this->assertStringContainsString( 'Give selling more. And one for a card.</p>', $html );
	}

	/**
	 * The caller's `extra` is drawn in the card it is for, under the
	 * features and above the foot, and in the band for the featured one;
	 * an add-on it has nothing for gets no box at all.
	 */
	public function test_extra_is_drawn_in_its_own_card(): void {
		$given = [];
		$html  = Renderer::render(
			$this->catalogue(),
			[
				'extra' => static function ( array $addon ) use ( &$given ): string {
					$given[] = $addon['id'];

					return match ( $addon['id'] ) {
						'reviews' => '<form class="licence">Key for ' . esc_html( $addon['name'] ) . '</form>',
						'pro'     => '<p>Band extra</p>',
						default   => '  ',
					};
				},
			]
		);

		$this->assertCount( 9, $given, 'Asked once for every add-on.' );
		$this->assertSame( 1, substr_count( $html, 'addons-card-extra' ) );
		$this->assertMatchesRegularExpression( '#Reviews</h3>.*?<div class="addons-card-extra"><form class="licence">Key for Reviews</form></div><footer class="addons-card-foot">#s', $html );
		$this->assertStringContainsString( '<div class="addons-hero-extra"><p>Band extra</p></div>', $html );

		$this->assertStringNotContainsString( 'addons-card-extra', Renderer::render( $this->catalogue() ) );
	}

	/**
	 * Each card's icon is on a tile of its own colour from the palette, the
	 * same one every time, and no two alike while there are colours to go round.
	 */
	public function test_each_card_has_a_colour_of_its_own(): void {
		preg_match_all( '/class="addons-tile" style="background:(#[0-9a-f]{6});color:(#[0-9a-f]{6})"/', Renderer::render( $this->catalogue(), [ 'palette' => self::PALETTE ] ), $tiles );

		$this->assertCount( 8, $tiles[1] );
		$this->assertCount( 8, array_unique( $tiles[1] ) );

		$tints = array_column( self::PALETTE, 'shade', 'tint' );

		foreach ( $tiles[1] as $index => $tint ) {
			$this->assertSame( $tints[ $tint ] ?? null, $tiles[2][ $index ], 'A tint with its own shade.' );
		}

		$this->assertSame( Renderer::colours( [ 'a', 'b', 'c' ], 12 ), Renderer::colours( [ 'a', 'b', 'c' ], 12 ) );
		$this->assertSame( (int) ( abs( crc32( 'a' ) ) % 12 ), Renderer::colours( [ 'a' ], 12 )['a'] );

		// More add-ons than colours go round again, and none is without.
		$many = Renderer::colours( array_map( 'strval', range( 1, 30 ) ), 12 );

		$this->assertCount( 30, $many );
		$this->assertCount( 12, array_unique( array_slice( $many, 0, 12 ) ) );
		$this->assertCount( 12, array_unique( array_slice( $many, 12, 12 ) ) );
		$this->assertSame( [], Renderer::colours( [ 'a' ], 0 ) );

		// No usable palette: a quiet grey, never no colour.
		$grey = Renderer::render( $this->catalogue(), [ 'palette' => [ [ 'tint' => 'red', 'shade' => '#000' ], 'nonsense' ] ] );

		$this->assertSame( 8, substr_count( $grey, 'style="background:#f0f0f1;color:#50575e"' ) );
	}

	/**
	 * The brand and band colours become custom properties, and only real colours do.
	 */
	public function test_the_brand_colours(): void {
		$html = Renderer::render( [], [ 'brand' => '#3858e9', 'band' => [ '#5b3ea8', 'url(x)' ] ] );

		$this->assertStringContainsString( 'style="--addons-brand:#3858e9;--addons-band-mid:#5b3ea8"', $html );

		$this->assertStringNotContainsString( 'style=', Renderer::render( [], [ 'brand' => 'red' ] ) );
	}

	/**
	 * A category asked for is the one the page opens on; one that does not exist opens on all.
	 */
	public function test_a_category_to_open_on(): void {
		$html = Renderer::render( $this->catalogue(), [ 'category' => 'Partners' ] );

		$this->assertStringContainsString( 'data-addons data-category="partners"', $html );
		$this->assertStringContainsString( 'aria-checked="true" tabindex="0" data-addons-category="partners">Partners</button>', $html );

		// Every card is still on the page: hiding is the script's, so with
		// scripts off nothing is lost.
		$this->assertSame( 8, substr_count( $html, 'data-addons-card' ) );

		$this->assertStringContainsString( 'data-addons data-category=""', Renderer::render( $this->catalogue(), [ 'category' => 'no-such-category' ] ) );
	}

	/**
	 * What a card offers follows what can be done: get it, switch it on, learn more, or nothing.
	 */
	public function test_a_card_offers_one_thing_to_do(): void {
		Addons::register( 'shop', 'buy', [ 'name' => 'Buy', 'url' => 'https://example.com/buy?a=1&b=2', 'price_label' => 'Included in Pro', 'features' => [ 'One', 'Two' ], 'badge' => 'New' ] );
		Addons::register( 'shop', 'soon', [ 'name' => 'Soon', 'status' => 'coming_soon', 'url' => 'https://example.com/soon' ] );
		Addons::register( 'shop', 'off', [ 'name' => 'Off', 'plugin' => 'off/off.php' ] );
		Addons::register( 'shop', 'on', [ 'name' => 'On', 'status' => 'active', 'url' => 'https://example.com/on' ] );

		$GLOBALS['ra_can']['activate_plugin'] = true;

		$html = Renderer::render( Addons::all( 'shop' ) );

		$this->assertStringContainsString( 'href="https://example.com/buy?a=1&#038;b=2" target="_blank" rel="noopener noreferrer">Get It<span class="screen-reader-text"> Buy (opens in a new tab)</span></a>', $html );
		$this->assertStringContainsString( 'Included in Pro', $html );
		$this->assertStringContainsString( '<a class="button" href="https://example.com/soon" target="_blank" rel="noopener noreferrer">Learn More', $html );
		$this->assertStringContainsString( '>Activate<span class="screen-reader-text"> Off</span>', $html );
		$this->assertStringContainsString( 'plugins.php?action=activate&#038;plugin=off%2Foff.php&#038;_wpnonce=', $html );
		$this->assertStringContainsString( 'href="https://example.com/on" target="_blank" rel="noopener noreferrer">Learn More', $html );
		$this->assertSame( 4, substr_count( $html, '<a class="button' ) );
		$this->assertSame( 2, substr_count( $html, 'button-primary' ) );
		$this->assertSame( 1, substr_count( $html, 'addons-card-features' ) );
		$this->assertSame( 2, substr_count( $html, '<li>' . self::own( 'check' ) ) );
		$this->assertStringContainsString( '<span class="addons-flag">New</span>', $html );

		// What this site has comes first, under its own heading, and the
		// rest under another.
		$this->assertSame( 2, substr_count( $html, 'data-addons-section' ) );
		$this->assertLessThan( strpos( $html, 'More Add-ons</h2>' ), strpos( $html, 'Installed</h2>' ) );
		$this->assertLessThan( strpos( $html, 'id="addon-buy"' ), strpos( $html, 'id="addon-off"' ) );
		$this->assertLessThan( strpos( $html, 'More Add-ons</h2>' ), strpos( $html, 'id="addon-on"' ) );

		// One category or none: no pills. And nothing featured: no band.
		$this->assertStringNotContainsString( 'role="radiogroup"', $html );
		$this->assertStringNotContainsString( 'addons-hero', $html );

		// Somebody who may not switch plugins on is not offered to.
		$GLOBALS['ra_can']['activate_plugin'] = false;

		$this->assertStringNotContainsString( 'Activate', Renderer::render( Addons::all( 'shop' ) ) );
	}

	/**
	 * A picture on the site goes across the top of its card, in place of the icon tile.
	 */
	public function test_a_picture_or_an_icon(): void {
		Addons::register( 'shop', 'pictured', [ 'name' => 'Pictured', 'image' => content_url( 'uploads/banner.png' ) ] );
		Addons::register( 'shop', 'iconed', [ 'name' => 'Iconed', 'icon' => content_url( 'uploads/icon.png' ) ] );

		$html = Renderer::render( Addons::all( 'shop' ) );

		$this->assertStringContainsString( '<div class="addons-card-image"><img src="https://example.test/wp-content/uploads/banner.png" alt="" loading="lazy" /></div>', $html );
		$this->assertStringContainsString( '<img src="https://example.test/wp-content/uploads/icon.png" alt="" width="32" height="32" loading="lazy" />', $html );
		$this->assertSame( 1, substr_count( $html, 'addons-tile' ) );
	}

	/**
	 * The library's own icon, as it draws it.
	 *
	 * @param string $name    Its Heroicons outline name.
	 * @param string $classes Classes beside addons-icon.
	 *
	 * @return string
	 */
	private static function own( string $name, string $classes = '' ): string {
		$paths = [
			'check'            => 'm4.5 12.75 6 6 9-13.5',
			'check-circle'     => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
			'magnifying-glass' => 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z',
		];

		return sprintf(
			'<svg class="%s" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="%s"/></svg>',
			trim( 'addons-icon ' . $classes ),
			$paths[ $name ]
		);
	}

	/**
	 * A stand-in icon library: draws two icons, and '' for any other.
	 *
	 * @param string               $name Icon name.
	 * @param array<string, mixed> $args What the renderer asked for.
	 *
	 * @return string
	 */
	public static function draw( string $name, array $args ): string {
		return in_array( $name, [ 'heroicons-outline:rocket-launch', 'heroicons-outline:chart-bar' ], true )
			? sprintf( '<svg class="%s" data-icon="%s"></svg>', $args['class'], $name )
			: '';
	}

	/**
	 * An icon in either form: a `set:name` is drawn by the icons callable
	 * on the tile and as the band's mark, a Dashicon is the font's span,
	 * and no icon is the default. The library's own icons need no callable.
	 */
	public function test_icons_in_either_form(): void {
		Addons::register( 'shop', 'band', [ 'name' => 'Band', 'featured' => true, 'icon' => 'heroicons-outline:rocket-launch', 'features' => [ 'One' ] ] );
		Addons::register( 'shop', 'set', [ 'name' => 'Set', 'icon' => 'heroicons-outline:chart-bar', 'features' => [ 'One' ] ] );
		Addons::register( 'shop', 'class', [ 'name' => 'Class', 'icon' => 'dashicons-groups' ] );
		Addons::register( 'shop', 'bare', [ 'name' => 'Bare', 'icon' => 'groups' ] );
		Addons::register( 'shop', 'none', [ 'name' => 'None' ] );
		Addons::register( 'shop', 'odd', [ 'name' => 'Odd', 'icon' => 'heroicons-outline:no-such-icon' ] );

		$html = Renderer::render( Addons::all( 'shop' ), [ 'icons' => [ self::class, 'draw' ] ] );

		$this->assertMatchesRegularExpression( '/<div class="addons-tile"[^>]*><svg class="addons-icon" data-icon="heroicons-outline:chart-bar"><\/svg><\/div>/', $html );
		$this->assertSame( 2, substr_count( $html, '<span class="dashicons dashicons-groups addons-icon" aria-hidden="true"></span>' ) );
		$this->assertSame( 2, preg_match_all( '/<div class="addons-tile"[^>]*><svg class="addons-icon" [^>]*><path [^>]*d="M14\.25 6\.087/', $html ), 'No icon, and one the callable cannot draw, are the puzzle piece.' );
		$this->assertStringContainsString( '<svg class="addons-icon addons-hero-mark" data-icon="heroicons-outline:rocket-launch"></svg>', $html );
		$this->assertStringContainsString( '<ul class="addons-hero-features"><li>' . self::own( 'check-circle' ) . 'One</li></ul>', $html );
		$this->assertStringContainsString( '<ul class="addons-card-features"><li>' . self::own( 'check' ) . 'One</li></ul>', $html );
		$this->assertStringContainsString( '<label class="addons-search"><span class="screen-reader-text">Search add-ons</span>' . self::own( 'magnifying-glass' ) . '<input', $html );
		$this->assertStringContainsString( 'data-addons-empty hidden>' . self::own( 'magnifying-glass' ) . '<p', $html );
		$this->assertStringNotContainsString( 'dashicons-search', $html );
		$this->assertStringNotContainsString( 'dashicons-admin-plugins', $html );

		// Without a callable, a set:name icon is the default, and the band
		// has the default as its mark.
		$html = Renderer::render( Addons::all( 'shop' ) );

		$this->assertStringNotContainsString( 'data-icon', $html );
		$this->assertSame( 3, preg_match_all( '/<div class="addons-tile"[^>]*><svg class="addons-icon" [^>]*><path [^>]*d="M14\.25 6\.087/', $html ) );
		$this->assertMatchesRegularExpression( '/<svg class="addons-icon addons-hero-mark" [^>]*><path [^>]*d="M14\.25 6\.087/', $html );

		// A band with no icon draws the default mark; a picture has none.
		$html = Renderer::render( [ 'picture' => [ 'name' => 'Picture', 'featured' => true, 'icon' => content_url( 'uploads/icon.png' ) ] ] );

		$this->assertStringNotContainsString( 'addons-hero-mark', $html );
	}

	/**
	 * The callable is the screen's own: it does not outlast the drawing.
	 */
	public function test_the_icons_callable_is_forgotten_after_drawing(): void {
		Renderer::render( [], [ 'icons' => [ self::class, 'draw' ] ] );

		$this->assertSame( '', ( new \ReflectionMethod( Renderer::class, 'glyph' ) )->invoke( null, 'heroicons-outline:chart-bar', '' ) );
	}

	/**
	 * A Dashicon is the font's span, and a name that is not one draws nothing.
	 */
	public function test_a_glyph(): void {
		$glyph = new \ReflectionMethod( Renderer::class, 'glyph' );

		$this->assertSame( '', $glyph->invoke( null, 'heroicons-outline:rocket-launch', 'x' ), 'No callable, nothing to draw it.' );
		$this->assertSame( '<span class="dashicons dashicons-yes addons-icon x" aria-hidden="true"></span>', $glyph->invoke( null, 'yes', 'x' ) );
		$this->assertSame( '<span class="dashicons dashicons-yes addons-icon" aria-hidden="true"></span>', $glyph->invoke( null, ' Dashicons-Yes ', '' ) );
		$this->assertSame( '', $glyph->invoke( null, 'dashicons-<script>', '' ) );
		$this->assertSame( '', $glyph->invoke( null, '', '' ) );
		$this->assertSame( self::own( 'check', 'x' ), $glyph->invoke( null, 'heroicons-outline:check', 'x' ) );

		// An icon that cannot be drawn gives way to the next.
		$icon = new \ReflectionMethod( Renderer::class, 'icon' );

		$this->assertSame( '<span class="dashicons dashicons-yes addons-icon" aria-hidden="true"></span>', $icon->invoke( null, 'heroicons-outline:no-such-icon', '', 'heroicons-outline:also-not', 'yes' ) );
		$this->assertSame( '', $icon->invoke( null, 'heroicons-outline:no-such-icon', '' ) );
	}

	/**
	 * A list with nothing on it says so, and offers nothing to search.
	 */
	public function test_an_empty_list_says_so(): void {
		$html = Renderer::render( [], [ 'labels' => [ 'none_text' => 'Extras will be here.' ] ] );

		$this->assertStringContainsString( '<p class="addons-empty-title">No add-ons yet</p><p>Extras will be here.</p>', $html );
		$this->assertStringContainsString( 'data-addons-empty>' . self::own( 'magnifying-glass' ) . '<p', $html );
		$this->assertStringNotContainsString( 'type="search"', $html );
		$this->assertStringNotContainsString( 'data-addons-clear', $html );
	}

	/**
	 * Whatever the add-ons say is escaped.
	 */
	public function test_everything_is_escaped(): void {
		$html = Renderer::render(
			[
				'x' => [
					'name'        => 'A <b>bold</b> "name"',
					'description' => '<script>alert(1)</script>',
					'category'    => 'Tools & <More>',
					'features'    => [ '<i>x</i>' ],
					'badge'       => '<em>New</em>',
					'price_label' => '<u>Free</u>',
					'status'      => '"><script>',
				],
			]
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<b>bold', $html );
		$this->assertStringNotContainsString( '<i>x', $html );
		$this->assertStringNotContainsString( '<em>', $html );
		$this->assertStringNotContainsString( '<u>', $html );

		// The band too.
		$band = Renderer::render(
			[
				'f' => [
					'name'        => 'A <b>bold</b> band',
					'description' => '<script>alert(1)</script>',
					'badge'       => '<em>New</em>',
					'featured'    => true,
				],
			]
		);

		$this->assertStringContainsString( 'A &lt;b&gt;bold&lt;/b&gt; band</h2>', $band );
		$this->assertStringNotContainsString( '<script>', $band );
		$this->assertStringNotContainsString( '<em>', $band );
	}

	/**
	 * The categories are the cards', in the order met, by slug; the featured one's is not one.
	 */
	public function test_the_categories(): void {
		$this->assertSame(
			[
				'selling'      => 'Selling',
				'partners-co' => 'Partners & Co',
			],
			Renderer::categories(
				[
					[ 'category' => 'Selling' ],
					[ 'category' => 'Bundles', 'featured' => true ],
					[ 'category' => ' Partners & Co ' ],
					[ 'category' => 'Selling' ],
					[ 'category' => '' ],
					[],
				]
			)
		);

		// One category divides nothing: no pills.
		Addons::register( 'shop', 'a', [ 'name' => 'A', 'category' => 'Selling' ] );
		Addons::register( 'shop', 'b', [ 'name' => 'B', 'category' => 'Selling' ] );

		$this->assertStringNotContainsString( 'role="radiogroup"', Renderer::render( Addons::all( 'shop' ) ) );
	}
}
