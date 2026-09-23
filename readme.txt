=== Location Finder – Store Locator & Resource Directory ===
Contributors: codexalogic
Donate link: https://buy.stripe.com/00wbJ3eAG6ePbaLbGQbEA00
Tags: store locator, location finder, resource directory, business directory, google maps

Requires at least: 6.5
Requires PHP:      8.0
Tested up to:      7.1
Stable tag:        1.1.1
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html

Build searchable store locators and resource directories with optional Google Maps, keyword search, category filters, and list or grid layouts.

== Description ==

Location Finder lets you build searchable store locators, business directories, and resource directories—with or without Google Maps. List stores, services, programs, or online resources. Listings do not need a physical address when used as a directory.

Visitors can search by keyword, filter by category, and browse results in a list or grid layout. Enable Google Maps to add an interactive map, address search, and radius-based "near me" filtering.

Choose which details appear for each listing, including images, descriptions, addresses, contact information, and hours. Add your locator or directory to a page using the Location Finder block or the [locfinder] shortcode.

The free version includes keyword and category search, list and grid layouts, and optional Google Maps features. Location Finder Pro adds custom taxonomy filtering, custom radius choices, distance display, result templates, and advanced map styling.

Full documentation and setup guide: [https://codexalogic.com/documentation/getting-started-with-location-finder/](https://codexalogic.com/documentation/getting-started-with-location-finder/)

= Highlights =
* Create store locators, business directories, service listings, and resource directories.
* Run a searchable directory with or without Google Maps.
* Search by keyword and filter by category.
* Display results in a list or grid layout.
* Enable Google Maps for interactive map pins, address search, radius filtering, and autocomplete when adding addresses.
* Show or hide listing details, including images, descriptions, addresses, phone numbers, email addresses, websites, and hours.
* Give each listing its own detail page.
* Embed your directory with the Location Finder block or [locfinder] shortcode.
* Use the automatically created Locations page or choose your own.
* Built with accessibility in mind: semantic markup, screen reader announcements for search results, and support for reduced motion.
* Translation-ready (i18n).

Pro features (optional upgrade):
* Set your own distance choices for radius search, in place of the built-in 10, 25, 50, and 75.
* Filter listings by any registered taxonomy, beyond Location Categories, so visitors can narrow results by service area, amenity, or other custom terms.
* Distance display on each result for geolocated or radius searches.
* A custom HTML template for result cards, with dynamic tokens for every location field.
* Custom map styling (JSON), custom pin icons, and per-category pin colors and icons.
* Show an "Open Now" or "Closed Now" badge based on each listing's hours and your site's timezone.

Use cases:
* Store/branch locator
* Program and service directories
* Office/campus/venue maps
* Resource finders for nonprofits and municipalities

== Installation ==

1. Upload the `locfinder` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins menu in WordPress.
3. For a directory without maps, turn off "Enable Google Maps" under Location Finder > General. To use Google Maps features, enable this setting and enter your Google Maps API key.
4. Add your listings under Locations in the WordPress admin.
5. Visit the automatically created Locations page, or display your locator or directory on another page using the Location Finder block or [locfinder] shortcode.

== Shortcodes ==

Display the search form and results, with an optional map:

[locfinder]

Every shortcode attribute is optional and falls back to your Location Finder settings when left out, so `[locfinder]` alone works right out of the box. Attributes let you override those settings for a single instance, including which search fields appear, sort order, results layout, and which listing details are shown, for example:

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

= Can I create a directory without Google Maps? =
Yes. Turn off "Enable Google Maps" under Location Finder > General to display a searchable directory with keyword search, category filtering, and list or grid results. No Google Maps API key is needed.

= Will this work for any kind of business or organization? =
Yes. The Location post type works for shops, restaurants, offices, programs, and resources.

= Do listings need a physical address? =
No. Listings can represent businesses, services, programs, or online resources without a physical address. You manage all listings under "Locations" in the WordPress admin, whether or not they use an address or map.

= What information can I display for each listing? =
An image, description, address, phone number, email address, website, hours, directions link, and categories. Choose which details appear using the global settings or customize them for each block or shortcode.

= Does the plugin support international phone numbers? =
Yes. Enter a US number as usual, or enter an international number with a leading + and country code (e.g. +44 20 7123 4567).

= Is my visitors' privacy protected when I show an email address? =
Email addresses are encoded in the page's HTML source so they aren't easily collected by automated spam scrapers, while still displaying and working normally as a clickable link for visitors. Email display is hidden by default until you turn it on.

= Does each listing get its own page? =
Yes. Each listing has its own detail page in addition to appearing in search results. Display the details you need, such as a description, contact information, and hours, with an optional map and address.

= Can I use a block instead of a shortcode? =
Yes. The Location Finder block offers the same options as the shortcode, directly in the block editor's settings panel.

= Can I customize the map style or pin icons? =
You can change the pin color in the plugin settings. Location Finder Pro adds custom pin icons, custom map styling, and per-category pin colors and icons.

= Does the plugin create anything when I activate it? =
Yes. Location Finder creates a page titled "Locations" containing the [locfinder] shortcode to display your locator or directory. You can rename it, change its slug, or delete it. To use another page, select it under Location Finder > General > Locations Page. The plugin does not recreate the page after you have set it or modify your other pages.

= Can I filter by a custom taxonomy? =
Yes. Location Finder Pro lets you choose a custom taxonomy for each block or shortcode instance, with per-category pin colors and icons. The free version supports filtering by the built-in Categories taxonomy.

= Does it support categories or filtering? =
Yes. The free version includes keyword search and category filtering. With Google Maps enabled, it also supports address search and radius filtering with options of 10, 25, 50, and 75 miles or kilometers. Location Finder Pro adds custom taxonomy filtering and custom radius choices.

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

= 1.1.1 =
* Improved: Search form no longer shows a drop shadow and spans the full width of its container when Google Maps is disabled, with the keyword field growing to fill the row.
* Improved: Placeholder text and select field text now use the same color for visual consistency.
* Improved: Location editor's address field and map preview now show different guidance and hide the map preview when Google Maps is disabled.
* Changed: Adjusted the default neutral gray color token to better complement custom color changes.
* Changed: Result card excerpt now appears higher in the card, below the distance badge.
* Fixed: Clicking a result card when Google Maps is disabled no longer highlights the card or scrolls the page, including for logged-in editors and administrators, who previously saw this happen because of how the admin-only "Google Maps is off" notice was detected.
* Fixed: Clicking or dragging inside the results area no longer selects surrounding text.
* Fixed: Removed a focus outline that appeared around the results panel when it had no scrollable content to receive it.
* Fixed: Category dropdown option labels now use a single, consistent method for escaping output.

= 1.1.0 =
* New: An "Enable Google Maps" setting under Location Finder → General. Turn it off to run Location Finder as a pure searchable directory with no map or Address and Radius Search fields.
* Improved: Redesigned the Pro upsell notices on locked settings fields with a clearer "Pro" badge and "Learn more" link, and rewrote the descriptions so each one explains what the feature actually does.
* Fixed: The address field no longer leaves stale location data behind (city, state, postal code, coordinates) after it's manually cleared without picking a new autocomplete suggestion.
* Changed: Lowered the minimum required PHP version to 8.0.

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
