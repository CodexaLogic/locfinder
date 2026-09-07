/**
 * Initializes Google Places autocomplete and the shared map preview.
 *
 * Works in both the admin location editor and public locator. Places loads
 * separately from the core Maps library so autocomplete failures do not
 * prevent the map from rendering.
 *
 * @module address-autocomplete
 */

import { ensureMaps, ensurePlaces } from "./google-loader.js";
import { getPlaceCenter, parsePlaceResult } from "./place-parser.js";
import { initMap } from "./map-utilities.js";

/**
 * Initializes the address autocomplete input and map preview.
 *
 * Loads the Google Maps library and initializes the map first so the map
 * always renders regardless of Places availability. Places Autocomplete is
 * then attached in a separate try/catch, if it fails the address field
 * falls back to plain text without affecting map functionality.
 *
 * Returns the mapApi object from initMap so the caller can call setPins,
 * drawRadius, and clearRadius after receiving AJAX search results.
 *
 * @param {Object}      config
 * @param {string}      config.apiKey              - Google Maps API key.
 * @param {string}      [config.mapId]             - Map ID for AdvancedMarkerElement support.
 * @param {string}      [config.language]          - Language code.
 * @param {string}      [config.region]            - Region code.
 * @param {HTMLElement} [config.root]              - Root element to scope input and map queries to.
 * @param {string}      [config.addressSelector]   - CSS selector for the address input.
 * @param {string}      [config.address2Selector]  - CSS selector for the suite/unit input.
 * @param {string}      [config.mapSelector]       - CSS selector for the map container.
 * @param {string}      [config.latSelector]       - CSS selector for the hidden latitude input.
 * @param {string}      [config.lngSelector]       - CSS selector for the hidden longitude input.
 * @param {string}      [config.placeIdSelector]   - CSS selector for the hidden Google place ID input.
 * @param {Object}      [config.defaultCenter]     - Default map center { lat, lng }.
 * @param {number}      [config.defaultZoom]       - Default map zoom level.
 * @param {number}      [config.pinZoom]           - Zoom level applied after a place is selected.
 * @param {string}      [config.pinColor]          - Default pin color.
 * @param {string}      [config.pinIconUrl]        - Default custom pin icon URL, used instead of the color pin when set.
 * @param {Object}      [config.termPinStyles]     - Per-category pin overrides, keyed by term ID: { [termId]: { color, iconUrl } }.
 * @param {string}      [config.unit]              - Distance unit, 'mi' or 'km'.
 * @param {string}      [config.mapHeight]         - CSS height for the map element e.g. '500px'.
 * @param {string}      [config.minMapHeight]      - CSS min-height for the map element e.g. '300px'.
 * @param {boolean}     [config.autoFit]           - Whether to fit the map bounds to all markers after setPins.
 * @param {boolean}     [config.clustering]        - Whether to enable marker clustering.
 * @param {number}      [config.clusterMaxZoom]    - Max zoom level for clustering.
 * @param {boolean}     [config.zoomControl]       - Whether to show the zoom control.
 * @param {boolean}     [config.fullscreenControl] - Whether to show the fullscreen control.
 * @param {boolean}     [config.streetViewControl] - Whether to show the Street View control.
 * @param {boolean}     [config.mapTypeControl]    - Whether to show the map type control.
 * @param {boolean}     [config.highContrast]      - Whether to use high contrast mode for markers.
 * @param {Array}       [config.mapStyles]         - Google Maps style array for custom styling.
 * @param {string}      [config.viewDetailsLabel]  - Label for the info window's "View details" button.
 * @param {string}      [config.loadingLabel]      - Label shown in the info window while details are being fetched.
 * @param {Function}    [config.onMarkerClick]     - Callback fired when a marker is clicked.
 * @param {Function}    [config.fetchLocationDetails] - Callback that resolves with a location's detail fields.
 * @param {string}      [config.resultLinkTarget]  - 'same' or 'new'. Passed straight through to initMap().
 * @param {boolean}     [config.showPreviewPin]    - Whether to drop a pin for the address currently in the
 *                                                    field. On for the admin meta box (pins the one location
 *                                                    being edited); off for the public search form, where a
 *                                                    pin before Search is clicked would look like a result
 *                                                    rather than the search location.
 * @param {boolean}     [config.panOnSelect]       - Whether the map recenters and zooms to the selected
 *                                                    address immediately. On for the admin meta box (shows
 *                                                    where the marker is being placed as it's picked); off
 *                                                    for the public search form, where the map shouldn't
 *                                                    move until Search is actually clicked.
 */
export async function initLocfinderAddressMap(config = {}) {
	const {
		apiKey,
		mapId = "",
		language = "en",
		region = "US",
		root = document,
		addressSelector = "#locfinder_address",
		address2Selector = "#locfinder_address_2",
		mapSelector = ".locfinder__map",
		latSelector = "#locfinder_latitude",
		lngSelector = "#locfinder_longitude",
		placeIdSelector = "#locfinder_place_id",
		defaultCenter = { lat: 39.8283, lng: -98.5795 },
		defaultZoom = 4,
		pinZoom = 14,
		showPreviewPin = true,
		panOnSelect = true,
		pinColor = "#00606b",
		pinIconUrl = "",
		termPinStyles = {},
		unit = "mi",
		mapHeight = "",
		minMapHeight = "300px",
		autoFit = true,
		clustering = true,
		clusterMaxZoom = 14,
		zoomControl = true,
		fullscreenControl = false,
		streetViewControl = false,
		mapTypeControl = false,
		highContrast = false,
		mapStyles = [],
		viewDetailsLabel = "View details",
		loadingLabel = "Loading…",
		resultLinkTarget = "same",
		onMarkerClick,
		fetchLocationDetails,
	} = config;

	if (!apiKey) {
		console.error("Locfinder: Google Maps API key is missing.");
		return null;
	}

	const addressInput = root.querySelector(addressSelector);
	const address2Input = root.querySelector(address2Selector);
	const mapElement = root.querySelector(mapSelector);

	if (!mapElement) {
		return null;
	}

	await ensureMaps({ apiKey, language, region });

	const latEl = root.querySelector(latSelector);
	const lngEl = root.querySelector(lngSelector);
	const placeIdEl = root.querySelector(placeIdSelector);
	const savedLat = latEl?.value ? parseFloat(latEl.value) : null;
	const savedLng = lngEl?.value ? parseFloat(lngEl.value) : null;
	const hasSavedCoords =
		Number.isFinite(savedLat) && Number.isFinite(savedLng);

	const mapApi = await initMap({
		apiKey,
		mapId,
		language,
		region,
		mapElement,
		pinColor,
		pinIconUrl,
		termPinStyles,
		unit,
		mapHeight,
		minMapHeight,
		autoFit,
		defaultLat: hasSavedCoords ? savedLat : defaultCenter.lat,
		defaultLng: hasSavedCoords ? savedLng : defaultCenter.lng,
		defaultZoom: hasSavedCoords ? pinZoom : defaultZoom,
		clustering,
		clusterMaxZoom,
		zoomControl,
		fullscreenControl,
		streetViewControl,
		mapTypeControl,
		highContrast,
		mapStyles,
		viewDetailsLabel,
		loadingLabel,
		resultLinkTarget,
		onMarkerClick,
		fetchLocationDetails,
	});

	if (!mapApi) {
		return null;
	}

	// Place an initial marker at the saved coordinates if they exist.
	if (hasSavedCoords) {
		if (showPreviewPin) {
			mapApi.setPins([
				{
					id: 0,
					lat: savedLat,
					lng: savedLng,
					pinColor,
					postTitle: addressInput.value || "",
				},
			]);
		}
		mapApi.map.setCenter({ lat: savedLat, lng: savedLng });
		mapApi.map.setZoom(pinZoom);
	}

	if (addressInput) {
		try {
			await ensurePlaces({ apiKey, language, region });

			const { Autocomplete } = await google.maps.importLibrary("places");

			const autocomplete = new Autocomplete(addressInput, {
				fields: [
					"address_components",
					"geometry",
					"formatted_address",
					"place_id",
				],
			});

			autocomplete.addListener("place_changed", () => {
				const place = autocomplete.getPlace();

				if (!place?.geometry) {
					console.warn(
						"Locfinder: No geometry returned for selected place."
					);
					return;
				}

				const parsed = parsePlaceResult(place);
				const center = getPlaceCenter(place);

				setInputValue(
					root.querySelector("#locfinder_city"),
					parsed.city
				);
				setInputValue(
					root.querySelector("#locfinder_state"),
					parsed.state
				);
				setInputValue(
					root.querySelector("#locfinder_postal_code"),
					parsed.postalCode
				);
				setInputValue(
					root.querySelector("#locfinder_country_code"),
					parsed.countryCode
				);

				setInputValue(latEl, parsed.lat);
				setInputValue(lngEl, parsed.lng);
				setInputValue(placeIdEl, parsed.placeId);

				if (parsed.formattedAddress) {
					addressInput.value = parsed.formattedAddress;
				}

				if (
					address2Input &&
					parsed.address2 &&
					address2Input.value.trim() === ""
				) {
					address2Input.value = parsed.address2;
				}

				if (center) {
					if (showPreviewPin) {
						mapApi.setPins([
							{
								id: 0,
								lat: center.lat,
								lng: center.lng,
								pinColor,
								postTitle: parsed.formattedAddress || "",
							},
						]);
					}
					if (panOnSelect) {
						mapApi.map.setCenter(center);
						mapApi.map.setZoom(pinZoom);
					}
				}
			});
		} catch (err) {
			console.warn(
				"Locfinder: Places autocomplete unavailable, using plain text fallback:",
				err
			);
		}
	}
	return mapApi;
}

/**
 * Sets an input element's value safely.
 *
 * No-ops when the element is null or undefined so the caller does not
 * need to guard against missing elements in different rendering contexts.
 *
 * @param   {HTMLElement|null} el    - The input element to update.
 * @param   {string}           value - The value to set.
 * @returns {void}
 */
function setInputValue(el, value) {
	if (el) {
		el.value = value ?? "";
	}
}
