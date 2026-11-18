<?php
/**
 * The registry: what is listed, how it is tidied, and its status on this site.
 *
 * @package SugarCommerce\Addons
 */

declare( strict_types=1 );

namespace SugarCommerce\Addons\Tests;

use SugarCommerce\Addons\Addons;
use PHPUnit\Framework\TestCase;

/**
 * Class AddonsTest
 */
final class AddonsTest extends TestCase {

	/**
	 * A fresh site and empty lists.
	 */
	protected function setUp(): void {
		parent::setUp();

		addons_test_reset();
		Addons::reset();
	}

	/**
	 * An add-on is listed with what it was given, tidied.
	 */
	public function test_an_addon_is_registered(): void {
		$this->assertTrue(
			Addons::register(
				'shop',
				'acme-reviews',
				[
					'name'        => ' Reviews <b>',
					'description' => "Let buyers rate\nwhat they bought.",
					'icon'        => 'dashicons-star-filled',
					'category'    => 'Marketing',
					'url'         => 'https://example.com/reviews',
					'price_label' => 'Included in Pro',
					'badge'       => 'New',
					'features'    => [ 'Stars', '', 'Photos', [ 'not text' ] ],
					'priority'    => 3,
				]
			)
		);

		$addon = Addons::all( 'shop' )['acme-reviews'];

		$this->assertSame( 'acme-reviews', $addon['id'] );
		$this->assertSame( 'Reviews', $addon['name'] );
		$this->assertSame( "Let buyers rate\nwhat they bought.", $addon['description'] );
		$this->assertSame( 'dashicons-star-filled', $addon['icon'] );
		$this->assertSame( '', $addon['image'] );
		$this->assertSame( 'available', $addon['status'] );
		$this->assertSame( 'Marketing', $addon['category'] );
		$this->assertSame( 'https://example.com/reviews', $addon['url'] );
		$this->assertSame( 'Included in Pro', $addon['price_label'] );
		$this->assertSame( 'New', $addon['badge'] );
		$this->assertSame( [ 'Stars', 'Photos' ], $addon['features'] );
		$this->assertSame( 3, $addon['priority'] );
		$this->assertFalse( $addon['featured'] );
		$this->assertSame( '', $addon['plugin'] );

		// Nothing that can be called is handed on.
		$this->assertArrayNotHasKey( 'is_active', $addon );
	}

	/**
	 * What cannot be listed is refused, and says so.
	 */
	public function test_bad_input_is_refused(): void {
		$good = [ 'name' => 'Reviews' ];

		$this->assertFalse( Addons::register( 'shop', '', $good ), 'no id' );
		$this->assertFalse( Addons::register( '', 'reviews', $good ), 'no list' );
		$this->assertFalse( Addons::register( 'shop', 'Has Spaces', $good ), 'an id that would have to be changed' );
		$this->assertFalse( Addons::register( 'shop', 'no-name', [] ), 'no name' );
		$this->assertFalse( Addons::register( 'shop', 'blank-name', [ 'name' => '  ' ] ), 'a blank name' );
		$this->assertFalse( Addons::register( 'shop', 'array-name', [ 'name' => [ 'x' ] ] ), 'a name that is not text' );
		$this->assertFalse( Addons::register( 'shop', 'bad-status', $good + [ 'status' => 'beta' ] ), 'a status that is not one' );

		$this->assertTrue( Addons::register( 'shop', 'once', $good ) );
		$this->assertFalse( Addons::register( 'shop', 'once', [ 'name' => 'Again' ] ), 'an id already taken' );

		$addons = Addons::all( 'shop' );

		$this->assertSame( [ 'once' ], array_keys( $addons ) );
		$this->assertSame( 'Reviews', $addons['once']['name'] );
	}

	/**
	 * Every status that can be given is accepted.
	 */
	public function test_the_four_statuses(): void {
		foreach ( [ 'available', 'coming_soon', 'installed', 'active' ] as $status ) {
			$this->assertTrue( Addons::register( 'shop', $status, [ 'name' => $status, 'status' => $status ] ), $status );
		}

		$this->assertSame( [ 'active', 'installed', 'available', 'coming_soon' ], array_keys( Addons::statuses() ) );
		$this->assertSame( 'Coming Soon', Addons::statuses()['coming_soon'] );
	}

	/**
	 * An icon is a `set:name` icon, kept for the screen to draw, or a
	 * Dashicon by its class or its bare name; anything else is the default.
	 */
	public function test_icons_in_either_form(): void {
		$expected = [
			'set'     => [ 'heroicons-outline:chart-bar', 'heroicons-outline:chart-bar' ],
			'solid'   => [ 'heroicons-solid:star', 'heroicons-solid:star' ],
			'class'   => [ 'dashicons-groups', 'dashicons-groups' ],
			'bare'    => [ 'groups', 'groups' ],
			'missing' => [ 'heroicons-outline:no-such-icon', 'heroicons-outline:no-such-icon' ],
			'odd'     => [ 'Not An:Icon!', 'heroicons-outline:puzzle-piece' ],
			'none'    => [ '', 'heroicons-outline:puzzle-piece' ],
		];

		foreach ( $expected as $id => [ $given ] ) {
			Addons::register( 'shop', $id, [ 'name' => $id, 'icon' => $given ] );
		}

		$addons = Addons::all( 'shop' );

		foreach ( $expected as $id => [ , $kept ] ) {
			$this->assertSame( $kept, $addons[ $id ]['icon'], $id );
		}
	}

	/**
	 * A link that is not a web address is dropped, and so is a picture on
	 * anybody else's server: the screen asks no other site for anything.
	 */
	public function test_unsafe_links_and_remote_pictures_are_dropped(): void {
		Addons::register(
			'shop',
			'remote',
			[
				'name'   => 'Remote',
				'url'    => 'javascript:alert(1)',
				'icon'   => 'https://cdn.example.com/icon.png',
				'banner' => 'https://cdn.example.com/banner.png',
			]
		);

		Addons::register(
			'shop',
			'local',
			[
				'name'  => 'Local',
				'icon'  => content_url( 'uploads/icon.png' ),
				'image' => home_url( 'banner.png' ),
			]
		);

		Addons::register(
			'shop',
			'banner',
			[
				'name'   => 'Banner',
				'banner' => content_url( 'uploads/banner.png' ),
			]
		);

		Addons::register(
			'shop',
			'odd',
			[
				'name' => 'Odd',
				'icon' => 'dashicons-<script>',
			]
		);

		$addons = Addons::all( 'shop' );

		$this->assertSame( '', $addons['remote']['url'] );
		$this->assertSame( 'heroicons-outline:puzzle-piece', $addons['remote']['icon'] );
		$this->assertSame( '', $addons['remote']['image'] );
		$this->assertSame( content_url( 'uploads/icon.png' ), $addons['local']['icon'] );
		$this->assertSame( home_url( 'banner.png' ), $addons['local']['image'] );
		$this->assertSame( 'heroicons-outline:puzzle-piece', $addons['odd']['icon'] );

		// `banner` is another name for `image`.
		$this->assertSame( content_url( 'uploads/banner.png' ), $addons['banner']['image'] );
	}

	/**
	 * What is true on this site wins over what was registered, except that
	 * something still coming stays coming.
	 */
	public function test_status_is_found_on_the_site(): void {
		$GLOBALS['ra_options']['active_plugins'] = [ 'on/on.php' ];

		Addons::register( 'shop', 'running', [ 'name' => 'Running', 'plugin' => 'on/on.php' ] );
		Addons::register( 'shop', 'installed', [ 'name' => 'Installed', 'plugin' => '/off/off.php' ] );
		Addons::register( 'shop', 'missing', [ 'name' => 'Missing', 'plugin' => 'not-here/not-here.php' ] );
		Addons::register( 'shop', 'escaping', [ 'name' => 'Escaping', 'plugin' => '../../../composer.json' ] );
		Addons::register( 'shop', 'called', [ 'name' => 'Called', 'is_active' => static fn(): bool => true ] );
		Addons::register( 'shop', 'called-no', [ 'name' => 'Called No', 'is_active' => static fn(): bool => false ] );
		Addons::register( 'shop', 'said', [ 'name' => 'Said', 'status' => 'installed' ] );
		Addons::register( 'shop', 'coming', [ 'name' => 'Coming', 'status' => 'coming_soon', 'is_active' => static fn(): bool => true, 'plugin' => 'on/on.php' ] );

		$status = array_column( Addons::all( 'shop' ), 'status', 'id' );

		$this->assertSame( 'active', $status['running'] );
		$this->assertSame( 'installed', $status['installed'] );
		$this->assertSame( 'available', $status['missing'] );
		$this->assertSame( 'available', $status['escaping'] );
		$this->assertSame( 'active', $status['called'] );
		$this->assertSame( 'available', $status['called-no'] );
		$this->assertSame( 'installed', $status['said'] );
		$this->assertSame( 'coming_soon', $status['coming'] );
	}

	/**
	 * A plugin switched on for the whole network is active, on a network.
	 */
	public function test_a_network_active_plugin_is_active(): void {
		$GLOBALS['ra_network']['active_sitewide_plugins'] = [ 'off/off.php' => time() ];

		Addons::register( 'shop', 'network', [ 'name' => 'Network', 'plugin' => 'off/off.php' ] );

		$this->assertSame( 'installed', Addons::all( 'shop' )['network']['status'] );

		Addons::reset();
		$GLOBALS['ra_multi'] = true;

		Addons::register( 'shop', 'network', [ 'name' => 'Network', 'plugin' => 'off/off.php' ] );

		$this->assertSame( 'active', Addons::all( 'shop' )['network']['status'] );
	}

	/**
	 * In order of priority, then name; and the filter has the last word.
	 */
	public function test_the_list_is_ordered_and_filterable(): void {
		Addons::register( 'shop', 'b', [ 'name' => 'Banana' ] );
		Addons::register( 'shop', 'a', [ 'name' => 'Apple' ] );
		Addons::register( 'shop', 'c', [ 'name' => 'Cherry', 'priority' => 1 ] );

		$this->assertSame( [ 'c', 'a', 'b' ], array_keys( Addons::all( 'shop' ) ) );

		add_filter(
			'shop_addons',
			static function ( array $addons ): array {
				unset( $addons['a'] );

				$addons['b']['name'] = 'Blueberry';

				return $addons;
			}
		);

		$addons = Addons::all( 'shop' );

		$this->assertSame( [ 'c', 'b' ], array_keys( $addons ) );
		$this->assertSame( 'Blueberry', $addons['b']['name'] );

		// A filter that answers with nonsense empties the list and breaks
		// nothing; one that slips in something that is not an add-on loses it.
		add_filter( 'shop_addons', static fn( array $addons ): array => $addons + [ 'junk' => 'not an add-on' ], 15 );

		$this->assertSame( [ 'c', 'b' ], array_keys( Addons::all( 'shop' ) ) );

		add_filter( 'shop_addons', static fn() => 'nonsense', 20 );

		$this->assertSame( [], Addons::all( 'shop' ) );
	}

	/**
	 * Everybody is asked once a list, when the list is first wanted, and only for that list.
	 */
	public function test_the_action_fires_once_and_third_parties_can_join(): void {
		$asked = 0;

		add_action(
			'shop_register_addons',
			static function () use ( &$asked ): void {
				++$asked;

				Addons::register( 'shop', 'theirs', [ 'name' => 'Theirs', 'category' => 'Marketing' ] );
			}
		);

		$this->assertSame( 0, $asked );

		Addons::all( 'shop' );
		$addons = Addons::all( 'shop' );

		$this->assertSame( 1, $asked );
		$this->assertArrayHasKey( 'theirs', $addons );

		// Another plugin's list is asked for its own, and not this one's.
		$this->assertSame( [], Addons::all( 'members' ) );
		$this->assertSame( 1, $asked );
	}

	/**
	 * Two plugins' lists are apart: the same id on each, and neither's filter touches the other.
	 */
	public function test_lists_are_apart(): void {
		$this->assertTrue( Addons::register( 'shop', 'reviews', [ 'name' => 'Shop Reviews' ] ) );
		$this->assertTrue( Addons::register( 'members', 'reviews', [ 'name' => 'Member Reviews' ] ) );

		add_filter( 'members_addons', static fn(): array => [] );

		$this->assertSame( 'Shop Reviews', Addons::all( 'shop' )['reviews']['name'] );
		$this->assertSame( [], Addons::all( 'members' ) );

		Addons::reset( 'members' );

		$this->assertTrue( Addons::register( 'members', 'reviews', [ 'name' => 'Again' ] ) );
		$this->assertArrayHasKey( 'reviews', Addons::all( 'shop' ) );
	}

	/**
	 * Listing makes no request of any server.
	 */
	public function test_no_request_is_made(): void {
		Addons::register( 'shop', 'remote', [ 'name' => 'Remote', 'icon' => 'https://cdn.example.com/x.png', 'plugin' => 'x/x.php' ] );
		Addons::all( 'shop' );

		$this->assertSame( [], $GLOBALS['ra_requests'] );
	}
}
