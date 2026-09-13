=== Location Finder ===
Contributors: codexalogic
Donate link: https://buy.stripe.com/00wbJ3eAG6ePbaLbGQbEA00
Tags: store locator, google maps, locations, directory, map

Requires at least: 6.5
Requires PHP:      8.1
Tested up to:      7.1
Stable tag:        1.0.1
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html

Display locations on an interactive Google Map, with keyword, category, and radius "near me" search and results in a list or grid layout.

== Description ==

Location Finder makes it easy to create and manage locations for any type of business or organization (stores, restaurants, offices, programs, nonprofits, etc.) and display them as pins on a dynamic Google Map. A Google Maps API key is optional; without one, it still works as a fully searchable directory using keyword and category filtering. Visitors get a fast, intuitive search with built-in keyword, category, and radius-based "near me" filtering. The Pro version adds filtering by any custom taxonomy, your own distance choices, and deeper control over map styling and pins.

Full documentation and setup guide: [https://codexalogic.com/documentation/getting-started-with-location-finder/](https://codexalogic.com/documentation/getting-started-with-location-finder/)

= Highlights =
* Add locations via a friendly admin interface (Custom Post Type), with Google Places autocomplete for fast, accurate address entry.
* Display locations as pins on a Google Map, plus a dedicated page for each location with its own map.
* Works with or without a Google Maps API key — use it as a full interactive map locator, or skip the key and run it as a keyword/category-searchable directory.
* Show address, phone (US and international formats), email, website, weekly hours, a "Get Directions" link, and categories for each location, each one individually shown or hidden.
* Built-in keyword search for quick filtering.
* Category filtering and radius-based "near me" search, with distance choices of 10, 25, 50, and 75.
* Choose a list or grid layout for search results.
* Use the Location Finder block or `[locfinder]` shortcode, both offer the same functionality and the same set of options.
* A ready-to-use Locations page is created automatically on activation, so the map works right away without building a page yourself. You can rename it, change its URL, or point the plugin at a different page in General Settings.
* Built with accessibility in mind: semantic markup, screen reader announcements for search results, and support for reduced motion.
* Email addresses are obfuscated in the page source to help deter automated spam harvesting, and are hidden by default until you choose to show them.
* Translation-ready (i18n).

Pro features (optional upgrade):
* Set your own distance choices for radius search, in place of the built-in 10, 25, 50, and 75.
* Filter by any custom taxonomy you have registered for locations, beyond the built-in Categories.
* Distance display on each result for geolocated or radius searches.
* A custom HTML template for result cards, with dynamic tokens for every location field.
* Custom map styling (JSON), custom pin icons, and per-category pin colors and icons.

Use cases:
* Store/branch locator
* Program and service directories
* Office/campus/venue maps
* Resource finders for nonprofits and municipalities

== Installation ==

1. Upload the `locfinder` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins menu in WordPress.
3. Add your Google Maps API key in Location Finder > General Settings, if you want to display an interactive map. This step is optional — the plugin works as a searchable directory without one.
4. Add locations via Locations in the WordPress admin.
5. Visit the Locations page created automatically on activation, or embed the map anywhere using the `[locfinder]` shortcode or the Location Finder block.

== Shortcodes ==

Display the search form, map, and results:

[locfinder]

Every shortcode attribute is optional and falls back to your Location Finder settings when left out, so `[locfinder]` alone works right out of the box. Attributes let you override those settings for a single instance, including which search fields appear, sort order, results layout, and which location details are shown, for example:

[locfinder show_hours="0" grid_cols="2"]

The Location Finder block offers the same options directly in the block editor sidebar.

== External Services ==

This plugin uses the Google Maps JavaScript API to display an interactive map, geocode addresses, and power address autocomplete in the location editor. A Google Maps API key is required only for those features; without one, Location Finder still works as a searchable directory, using keyword and category search against your saved location data, with no connection to Google at all. Add your own Google Maps API key in the plugin settings whenever you want the map or address-lookup features.

When a Google Maps API key is configured and a page containing a map is loaded, the plugin loads a script from https://maps.googleapis.com and sends the data needed to render that map and resolve address-based searches. This includes the location addresses and coordinates you have saved, any address a visitor types into the search field, and, only when a visitor uses the "Use my location" button and grants their browser's permission prompt, that visitor's coordinates (used to look up a readable address to show in the search field).

No data is sent to Google unless a Google Maps API key is configured and either a map is displayed or an address-based search or lookup is performed. The plugin does not send data to codexalogic.com or to any service other than Google Maps.

Google Maps Platform Terms of Service: https://cloud.google.com/maps-platform/terms
Google Privacy Policy: https://policies.google.com/privacy

== Source Code ==

The full source code, including the unminified JavaScript and the build tooling used to generate the compiled assets in `build/`, is publicly available at:
https://github.com/CodexaLogic/locfinder

To build the assets yourself: run `npm install` followed by `npm run build` (see `package.json` for the full script list).

Third-party libraries bundled in `build/`:

* @googlemaps/markerclusterer, version 2.6.2, Apache-2.0 licensed. Source: https://github.com/googlemaps/js-markerclusterer

== Frequently Asked Questions ==

= Do I need a Google Maps API key? =
Only if you want to display the interactive map. Without one, Location Finder still works as a full-featured directory, with keyword and category search and results in a list or grid layout, just without the map itself. You can get a free API key from the Google Cloud Console any time you want to add it.

= Will this work for any kind of business or organization? =
Yes. The Location post type works for shops, restaurants, offices, programs, and other businesses or organizations with an address or coordinates.

= What information can I display for each location? =
Address, phone number, email, website, weekly hours, a "Get Directions" link, and categories. Each field can be shown or hidden independently, globally in settings or per shortcode/block instance.

= Does the plugin support international phone numbers? =
Yes. Enter a US number as usual, or enter an international number with a leading + and country code (e.g. +44 20 7123 4567).

= Is my visitors' privacy protected when I show an email address? =
Email addresses are encoded in the page's HTML source so they aren't easily collected by automated spam scrapers, while still displaying and working normally as a clickable link for visitors. Email display is also hidden by default until you turn it on.

= Does each location get its own page? =
Yes. Every location has its own page with its own map, address, contact details, and hours, in addition to appearing in search results.

= Can I use a block instead of a shortcode? =
Yes. The Location Finder block is available in the block editor and offers the same configuration options as the shortcode, through the block's own settings panel.

= Can I customize the map style or pin icons? =
Pin color can be customized in the plugin settings. The Pro version adds custom pin icons, custom map styling, and per-category pin colors and icons.

= Does the plugin create anything when I activate it? =
Yes. Location Finder creates a single page titled "Locations" containing the `[locfinder]` shortcode, so you have a working locator immediately. You are free to rename it, change its slug, or delete it. To use a different page instead, choose one under Location Finder > General Settings > Locations Page. The plugin never recreates the page once you have set it, and it does not modify any of your other pages.

= Can I use different categories on different pages? =
Yes. Each shortcode or block instance can target its own taxonomy, and pin colors are matched per instance to whichever taxonomy that instance is filtering on, so two maps on different pages can use different category sets and different pin colors.

= Does it support categories or filtering? =
The free version includes keyword search, filtering by the built-in Categories taxonomy, and radius-based "near me" search with distance choices of 10, 25, 50, and 75. The Pro version adds filtering by any custom taxonomy you register for locations, and lets you set your own distance choices.

= What happens on uninstall? =
By default, nothing is removed. Your plugin settings and all location data are kept. You don't lose anything if you deactivate and later reinstall. (The plugin does always remove the Location-editing permissions it granted to Administrators and Editors, since those have no purpose without the plugin.) If you check "Delete Data on Uninstall" on the Tools settings page, uninstalling the plugin will permanently remove all locations, plugin settings, and cached data. This cannot be undone.

= Is it translation ready? =
Yes. Use the `locfinder` text domain and place translation files in the `/languages` folder.

== Screenshots ==

1. Search results with no image, to the left of the map.
2. Search results including the image, to the left of the map.
3. Search results in list view, to the left of the map.
4. Search results below the map.
5. Search results including hours, to the right of the map.
6. The single location page, displaying whichever fields are enabled: phone, email, description, hours, categories, and map.
7. The Location Finder block in the WordPress editor, with every display option easily configurable.

== Changelog ==

= 1.0.1 =
* Fix: Location Details block is properly recognized by the block editor, preventing it from being flagged as invalid content and accidentally removed from the Single Location template.
* Fix: Results Columns setting disables when Layout is set to List, matching the existing Grid Columns behavior.
* Fix: Map width is no longer applied when Results Position is set to below the map.
* Improvement: Category dropdown shows parent and child categories as a nested, indented list.
* Improvement: Keyword and Address search fields use placeholder text inside the input instead of a separate visible label, with the label kept for screen reader accessibility.
* Improvement: Result card thumbnails are larger and card spacing is slightly increased.
* Docs: Clarified that a Google Maps API key is optional. Search and directory features work fully without one.

= 1.0.0 =
* Initial release.
