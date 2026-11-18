# Add-ons

An Add-ons screen for a plugin: a list of the extras it has, whether this
site already has each one, and a page that shows them, without a single
request to another server.

## What it does

A plugin with extras wants a page that shows them, and most fetch the
catalogue from their own site on every visit, which wordpress.org does not
allow without consent and which tells a server who opened the page. This
keeps the list in code: each add-on (yours, or a third party's) registers
itself on a hook, and the screen works out from the site whether it is
installed or running.

The page is a shop window: the featured add-on as a band across the top,
category pills and a search box that filter the cards in the browser, and
a fluid grid of cards with one honest button each.

## Features

* List an add-on with a name, a sentence, an icon, a category and ticks
* Show "Active" or "Installed" by itself when the site has the add-on
* Offer "Get It", "Activate" or "Learn More", and no button when there is nowhere to go
* Feature one add-on as a band across the top of the page
* Filter by category and search as you type, all in the browser
* Give the screen your own words, colours and status badge
* Let other plugins add their add-ons to your list, or take one out

Icons are Dashicons, any icon set you can draw, or pictures on the site; a
picture on another server is dropped, never loaded.
Nothing is installed or switched on by being listed.

## Installation

```bash
composer require sugarcommerce/addons
```

## Quick start

```php
use SugarCommerce\Addons\Addons;
use SugarCommerce\Addons\Screen;

Screen::register( 'myshop', [
	'parent'     => 'myshop',
	'page_title' => __( 'My Shop Add-ons', 'my-shop' ),
	'labels'     => [ 'intro' => __( 'Extras for your shop.', 'my-shop' ) ],
] );

add_action( 'myshop_register_addons', function () {
	Addons::register( 'myshop', 'reviews', [
		'name'        => __( 'Reviews', 'my-shop' ),
		'description' => __( 'Let buyers rate what they bought.', 'my-shop' ),
		'icon'        => 'heroicons-outline:star',
		'category'    => __( 'Marketing', 'my-shop' ),
		'plugin'      => 'myshop-reviews/myshop-reviews.php',
		'url'         => 'https://example.com/reviews',
	] );
} );
```

`myshop` is the list's id: the screen draws `Addons::all( 'myshop' )`, and
every hook is named from it, so two plugins bundling this library keep
their lists apart.

## Registering an add-on

Call `Addons::register( $list, $id, $args )` on `{list}_register_addons`.
It returns false, and lists nothing, for an id that is malformed or taken,
no name, or an unknown status.

| Key | What it is |
|-----|------------|
| `name` | What it is called. Required. |
| `description` | One or two plain sentences. |
| `icon` | An icon as `set:name` (`heroicons-outline:star`), a Dashicon (`star-filled` or `dashicons-star-filled`), or a picture on this site. Default `heroicons-outline:puzzle-piece`. See [Icons](#icons). |
| `image` | A 16:9 picture on this site, across the top of the card instead of the icon. |
| `status` | `available` (default) or `coming_soon`; `installed` and `active` are worked out, or can be given. |
| `plugin` | Its plugin file, `folder/file.php`, to tell whether it is installed or active. |
| `is_active` | A callable that says whether it is running, for an add-on that is not a plugin. |
| `category` | The pill it is filtered under. |
| `url` | Where to learn more or buy it; opens in a new tab. |
| `price_label` | A few words on what it costs. |
| `badge` | One or two words to flag it: "New". |
| `features` | A short list of what it does, drawn as ticks. |
| `priority` | Lower comes first. Default 10, then by name. |
| `featured` | Drawn as the band across the top. |

`{list}_addons` filters the finished list, after every status is worked out.

## The screen

```php
Screen::register( 'myshop', [
	'parent'     => 'myshop',        // '' for a menu item of its own
	'slug'       => 'myshop-addons', // the default
	'capability' => 'manage_options',
	'priority'   => 1000,            // the admin_menu priority, to sit last
	'labels'     => [ /* any of Renderer::labels()' keys */ ],
	'palette'    => fn() => my_palette(), // icon tiles: [ [ 'tint' => '#…', 'shade' => '#…' ], … ]
	'brand'      => '#3858e9',       // the band's first colour and the focus ring
	'band'       => [ '#5b3ea8', '#a3246c' ],
	'badge'      => fn( $status, $label ) => my_badge( $status, $label ),
	'notes'      => fn() => [ 'myshop-pro' => my_pro_sentence() ],
	'icons'      => [ Icons::class, 'render' ], // draws set:name icons; see Icons
] );
```

Any drawing setting can be a closure, worked out when the page is drawn.
`{list}_addons_labels` filters the words. The stylesheet and script load on
that screen only, from the library. `Screen::get( 'myshop' )->url( 'marketing' )`
links to the page opened on a category.

To draw the catalogue in a page of your own, call
`Renderer::render( Addons::all( 'myshop' ), $args )` with the same settings
(`icons` included) and enqueue `assets/css/addons.css` and `assets/js/addons.js`.

## Icons

An add-on's `icon` takes either form:

```php
'icon' => 'heroicons-outline:chart-bar', // any set:name: inline SVG, in the tile's colour
'icon' => 'chart-bar',                   // a Dashicon, or 'dashicons-chart-bar', drawn as before
```

A Dashicon is drawn by the library. A `set:name` icon is drawn by the
screen's `icons` callable, which is given the name and `[ 'class' => … ]` and
returns markup -- an icon library's own render function usually fits as it
stands. Without one, or when it returns nothing, the add-on gets the default,
a puzzle piece. The library's own icons -- the search box, the empty state,
the ticks and the default tile -- are Heroicons outline it draws itself, so it
needs no icon set. Every icon has the class `addons-icon`, which the
stylesheet sizes.

A top-level menu item's `icon` is WordPress's own and takes what
`add_menu_page()` does: a Dashicons class (`dashicons-admin-plugins`, the
default), a `data:image/svg+xml` URI or a URL.

## Moving from arraypress/wp-*

From `arraypress/wp-register-addons` (`ArrayPress\RegisterAddons`). Every
class, method, hook and setting keeps its name; it no longer needs
`wp-composer-assets` or `wp-field-kit`.

| Old | New |
|---|---|
| `ArrayPress\RegisterAddons\Addons` | `SugarCommerce\Addons\Addons` |
| `ArrayPress\RegisterAddons\Screen` | `SugarCommerce\Addons\Screen` |
| `ArrayPress\RegisterAddons\Renderer` | `SugarCommerce\Addons\Renderer` |
| `set:name` icons drawn by wp-field-kit when installed | drawn by the `icons` setting: pass the kit's `[ Icons::class, 'render' ]` to keep them |
| an unknown `set:name` swapped for the default when registered | kept, and drawn as the default when the callable cannot draw it |
| library icons fell back to Dashicons without the kit | always its own inline SVG |
| icon class `arraypress-icon` | `addons-icon` (`Renderer::ICON_CLASS`) |
| stylesheet depends on `dashicons` only without the kit | always depends on `dashicons` |
| `{list}_register_addons`, `{list}_addons`, `{id}_addons_labels`, every `addons-*` class | unchanged |
| text domain `arraypress` | text domain `sugarcommerce` |

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
