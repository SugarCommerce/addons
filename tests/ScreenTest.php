<?php
/**
 * The admin screen: menu, assets, permissions, and the page.
 *
 * @package SugarCommerce\Addons
 */

declare( strict_types=1 );

namespace SugarCommerce\Addons\Tests;

use SugarCommerce\Addons\Addons;
use SugarCommerce\Addons\Screen;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPDieException;

/**
 * Class ScreenTest
 */
final class ScreenTest extends TestCase {

	/**
	 * A fresh site and empty lists.
	 */
	protected function setUp(): void {
		parent::setUp();

		addons_test_reset();
		Addons::reset();
		unset( $_GET['category'] );
	}

	/**
	 * Leave the request as it was.
	 */
	protected function tearDown(): void {
		unset( $_GET['category'] );

		parent::tearDown();
	}

	/**
	 * What a screen prints.
	 *
	 * @param Screen $screen The screen.
	 *
	 * @return string
	 */
	private function draw( Screen $screen ): string {
		ob_start();

		try {
			$screen->render();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * A submenu item under the plugin's menu, at the priority asked for.
	 */
	public function test_it_is_in_the_menu(): void {
		$screen = Screen::register(
			'shop',
			[
				'parent'     => 'shop',
				'page_title' => 'Shop Add-ons',
				'priority'   => 1000,
			]
		);

		$this->assertSame( $screen, Screen::get( 'shop' ) );
		$this->assertNull( Screen::get( 'nobody' ) );
		$this->assertArrayHasKey( 1000, $GLOBALS['ra_hooks']['admin_menu'] );

		do_action( 'admin_menu' );

		$this->assertSame(
			[
				[
					'parent'     => 'shop',
					'page_title' => 'Shop Add-ons',
					'menu_title' => 'Add-ons',
					'capability' => 'manage_options',
					'slug'       => 'shop-addons',
				],
			],
			$GLOBALS['ra_menu']
		);
		$this->assertSame( 'https://example.test/wp-admin/admin.php?page=shop-addons', $screen->url() );
		$this->assertSame( 'https://example.test/wp-admin/admin.php?page=shop-addons&category=partners', $screen->url( 'partners' ) );
	}

	/**
	 * With no parent, a menu item of its own.
	 */
	public function test_a_menu_item_of_its_own(): void {
		$screen = new Screen(
			'members',
			[
				'slug'       => 'member-extras',
				'menu_title' => 'Extras',
				'capability' => 'list_users',
				'position'   => 58,
			]
		);

		$screen->menu();

		$this->assertSame( '', $GLOBALS['ra_menu'][0]['parent'] );
		$this->assertSame( 'Extras', $GLOBALS['ra_menu'][0]['menu_title'] );
		$this->assertSame( 'Add-ons', $GLOBALS['ra_menu'][0]['page_title'] );
		$this->assertSame( 'list_users', $GLOBALS['ra_menu'][0]['capability'] );
		$this->assertSame( 'member-extras', $GLOBALS['ra_menu'][0]['slug'] );
		$this->assertSame( 'dashicons-admin-plugins', $GLOBALS['ra_menu'][0]['icon'] );
		$this->assertSame( 58, $GLOBALS['ra_menu'][0]['position'] );
	}

	/**
	 * The stylesheet and the script load on the screen, from the library, and nowhere else.
	 */
	public function test_the_assets_load_on_the_screen_only(): void {
		$screen = Screen::register( 'shop', [ 'parent' => 'shop' ] );

		// Before the menu is built, the page is known by its slug.
		$screen->enqueue( 'shop_page_other' );

		$this->assertSame( [], $GLOBALS['ra_enqueued'] );

		$screen->enqueue( 'shop_page_shop-addons' );

		$this->assertSame( [ 'style', 'script' ], array_column( $GLOBALS['ra_enqueued'], 0 ) );
		$this->assertSame( [ 'shop-addons', 'shop-addons' ], array_column( $GLOBALS['ra_enqueued'], 1 ) );
		$this->assertStringEndsWith( '/assets/css/addons.css', $GLOBALS['ra_enqueued'][0][2] );
		$this->assertStringEndsWith( '/assets/js/addons.js', $GLOBALS['ra_enqueued'][1][2] );
		$this->assertStringStartsWith( 'https://example.test/', $GLOBALS['ra_enqueued'][0][2] );

		// Dashicons, for an add-on whose icon is one.
		$this->assertSame( [ 'dashicons' ], $GLOBALS['ra_registered']['shop-addons']['style_deps'] );

		// Once it is in the menu, by the name WordPress gave it.
		$GLOBALS['ra_enqueued'] = [];

		$other = Screen::register(
			'members',
			[
				'parent' => 'members',
				'handle' => 'members-admin-addons',
			]
		);

		$other->menu();
		$other->enqueue( 'shop_page_members-addons' );

		$this->assertSame( [], $GLOBALS['ra_enqueued'] );

		$other->enqueue( 'members_page_members-addons' );

		$this->assertSame( [ 'members-admin-addons', 'members-admin-addons' ], array_column( $GLOBALS['ra_enqueued'], 1 ) );
	}

	/**
	 * The screen hands its icons callable to the catalogue.
	 */
	public function test_the_screen_draws_set_icons_with_its_callable(): void {
		add_action(
			'shop_register_addons',
			static function (): void {
				Addons::register( 'shop', 'set', [ 'name' => 'Set', 'icon' => 'heroicons-outline:chart-bar' ] );
			}
		);

		$screen = Screen::register(
			'shop',
			[
				'parent' => 'shop',
				'icons'  => static fn( string $name, array $args ): string => '<svg class="' . $args['class'] . '" data-icon="' . $name . '"></svg>',
			]
		);

		$this->assertStringContainsString( '<svg class="addons-icon" data-icon="heroicons-outline:chart-bar"></svg>', $screen->html() );
	}

	/**
	 * In a symlinked plugin the assets are found by the plugin's path, not the real one.
	 */
	public function test_the_assets_follow_a_symlinked_plugin(): void {
		$GLOBALS['wp_plugin_paths'] = [ WP_CONTENT_DIR . '/plugins/shop/vendor/sugarcommerce/addons' => dirname( __DIR__ ) ];

		try {
			Screen::register( 'shop', [ 'parent' => 'shop' ] )->enqueue( 'shop_page_shop-addons' );
		} finally {
			$GLOBALS['wp_plugin_paths'] = [];
		}

		$this->assertStringEndsWith( '/wp-content/plugins/shop/vendor/sugarcommerce/addons/assets/css/addons.css', $GLOBALS['ra_enqueued'][0][2] );
	}

	/**
	 * The screen is for whoever has the capability.
	 */
	public function test_the_screen_is_for_those_allowed(): void {
		$screen = Screen::register( 'shop', [ 'capability' => 'manage_shop' ] );

		$this->expectException( WPDieException::class );

		$this->draw( $screen );
	}

	/**
	 * The page: the plugin's list, in a wrap, with its words, colours, badge and notes.
	 */
	public function test_the_page_draws_the_plugins_list(): void {
		$GLOBALS['ra_can']['manage_shop'] = true;

		$late = 'unset';

		add_action(
			'shop_register_addons',
			static function () use ( &$late ): void {
				$late = 'set';

				Addons::register( 'shop', 'pro', [ 'name' => 'Shop Pro', 'featured' => true, 'status' => 'coming_soon', 'icon' => 'dashicons-star-filled' ] );
				Addons::register( 'shop', 'reviews', [ 'name' => 'Reviews', 'category' => 'Selling' ] );
				Addons::register( 'shop', 'payouts', [ 'name' => 'Payouts', 'category' => 'Partners' ] );
			}
		);

		Addons::register( 'members', 'not-ours', [ 'name' => 'Not Ours' ] );

		$screen = Screen::register(
			'shop',
			[
				'capability' => 'manage_shop',
				'class'      => 'shop-addons',
				'labels'     => [
					'title' => 'Shop Add-ons',
					'intro' => 'Extras for the shop.',
				],
				'palette'    => static fn(): array => [
					'pink' => [
						'tint'  => '#fde8ef',
						'shade' => '#a3246c',
					],
				],
				'brand'      => static fn(): string => '#3858e9',
				'band'       => [ '#5b3ea8', '#a3246c' ],
				'badge'      => static fn( string $status, string $label ): string => '<i class="badge-' . $status . '">' . esc_html( $label ) . '</i>',
				'notes'      => static function () use ( &$late ): array {
					return [
						'pro'     => 'Worked out when drawn: ' . $late,
						'payouts' => '',
					];
				},
			]
		);

		add_filter(
			'shop_addons_labels',
			static function ( array $labels ): array {
				$labels['statuses']['available'] = 'Ready';

				return $labels;
			}
		);

		$html = $this->draw( $screen );

		$this->assertStringStartsWith( '<div class="wrap shop-addons"><div class="addons-catalogue" data-addons data-category=""', $html );
		$this->assertStringEndsWith( '</div></div>', $html );
		$this->assertStringContainsString( '<h1>Shop Add-ons</h1><p class="addons-intro">Extras for the shop.</p>', $html );
		$this->assertStringContainsString( 'style="--addons-brand:#3858e9;--addons-band-mid:#5b3ea8;--addons-band-end:#a3246c"', $html );
		$this->assertStringContainsString( 'style="background:#fde8ef;color:#a3246c"', $html );
		$this->assertStringContainsString( '<i class="badge-coming_soon">Coming Soon</i>', $html );
		$this->assertSame( 2, substr_count( $html, '<i class="badge-available">Ready</i>' ) );
		$this->assertStringContainsString( 'Worked out when drawn: set', $html );
		$this->assertStringContainsString( 'Shop Pro</h2>', $html );
		$this->assertStringNotContainsString( 'Not Ours', $html );
	}

	/**
	 * A category in the address is the one the page opens on.
	 */
	public function test_a_category_in_the_address_is_opened_on(): void {
		Addons::register( 'shop', 'reviews', [ 'name' => 'Reviews', 'category' => 'Selling' ] );
		Addons::register( 'shop', 'payouts', [ 'name' => 'Payouts', 'category' => 'Partners' ] );

		$screen = Screen::register( 'shop' );

		$_GET['category'] = 'Partners';

		$this->assertStringContainsString( 'data-addons data-category="partners"', $this->draw( $screen ) );

		$_GET['category'] = 'no-such-category';

		$this->assertStringContainsString( 'data-addons data-category=""', $this->draw( $screen ) );
	}

	/**
	 * Nothing on the screen is fetched from anywhere but this site, and drawing it makes no request at all.
	 */
	public function test_the_screen_asks_no_other_server_for_anything(): void {
		Addons::register(
			'shop',
			'remote',
			[
				'name'  => 'Remote',
				'icon'  => 'https://cdn.example.com/icon.png',
				'image' => 'https://cdn.example.com/banner.png',
				'url'   => 'https://example.com/buy',
			]
		);

		Addons::register(
			'shop',
			'pictured',
			[
				'name'  => 'Pictured',
				'image' => content_url( 'uploads/banner.png' ),
			]
		);

		$screen = Screen::register( 'shop', [ 'parent' => 'shop' ] );

		$screen->enqueue( 'shop_page_shop-addons' );

		$html = $this->draw( $screen );

		$this->assertSame( [], $GLOBALS['ra_requests'] );

		// Whatever the page would load by being opened -- a picture, a
		// script, a stylesheet, a frame -- is on this site.
		preg_match_all( '/<(?:img|script|link|iframe|source|video|audio)[^>]+(?:src|href)="([^"]+)"/i', $html, $loaded );

		$addresses = array_merge( $loaded[1], array_column( $GLOBALS['ra_enqueued'], 2 ) );

		$this->assertCount( 3, $addresses );

		foreach ( $addresses as $address ) {
			$this->assertSame( 'example.test', wp_parse_url( $address, PHP_URL_HOST ), $address );
		}

		$this->assertStringNotContainsString( 'cdn.example.com', $html );
		$this->assertStringNotContainsString( 'url(', $html );

		// Nor does the stylesheet or the script reach anywhere else.
		foreach ( [ 'assets/css/addons.css', 'assets/js/addons.js' ] as $asset ) {
			$source = (string) file_get_contents( dirname( __DIR__ ) . '/' . $asset );

			$this->assertDoesNotMatchRegularExpression( '#https?://|url\(|@import|fetch\(|XMLHttpRequest#', $source, $asset );
		}

		// The one way off the site is a link somebody presses, in a tab of its own.
		$this->assertStringContainsString( 'href="https://example.com/buy" target="_blank" rel="noopener noreferrer">Get It', $html );
	}

	/**
	 * An id that is not a key is refused at once.
	 */
	public function test_a_bad_id_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );

		new Screen( 'My Shop' );
	}
}
