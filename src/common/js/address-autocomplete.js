/**
 * Initializes address autocomplete, the typed-address fallback, and the
 * shared map preview.
 *
 * Used by both the admin location editor and the public locator. Address
 * suggestions come from places-autocomplete.js (Places API New, with an
 * automatic legacy fallback); typed text that was never matched to a
 * suggestion is resolved by geocoder.js. Every resolution path writes its
 * result through one function, applyPlace(), so the saved data is identical
 * regardless of which Google API answered.
 *
 * @module address-autocomplete
 */

import { ensureMaps } from "./google-loader.js";
import { geocodeAddress } from "./geocoder.js";
import { initMap } from "./map-utilities.js";
import { getPlaceCenter, parsePlaceResult } from "./place-parser.js";
import { attachPlacesAutocomplete } from "./places-autocomplete.js";

/**
 * Initializes the address input, its autocomplete, and the map preview.
 *
 * Loads Google Maps and renders the map first so the map always appears,
 * even when no Places API is available. Autocomplete is attached afterward;
 * if it cannot be attached, typed addresses are still geocoded.
 *
 * Returns the mapApi object from initMap() so callers can use setPins(),
 * drawRadius(), and clearRadius() after receiving search results.
 *
 * @param   {Object}      config                        - Configuration.
 * @param   {string}      config.apiKey                 - Google Maps API key.
 * @param   {string}      [config.mapId]                - Map ID for AdvancedMarkerElement support.
 * @param   {string}      [config.language]             - Language code, e.g. "en".
 * @param   {string}      [config.region]               - Region code, e.g. "US".
 * @param   {HTMLElement} [config.root]                 - Element that scopes input and map queries.
 * @param   {string}      [config.addressSelector]      - CSS selector for the address input.
 * @param   {string}      [config.address2Selector]     - CSS selector for the suite/unit input.
 * @param   {string}      [config.mapSelector]          - CSS selector for the map container.
 * @param   {string}      [config.latSelector]          - CSS selector for the hidden latitude input.
 * @param   {string}      [config.lngSelector]          - CSS selector for the hidden longitude input.
 * @param   {string}      [config.placeIdSelector]      - CSS selector for the hidden Google place ID input.
 * @param   {Object}      [config.defaultCenter]        - Default map center { lat, lng }.
 * @param   {number}      [config.defaultZoom]          - Default map zoom level.
 * @param   {number}      [config.pinZoom]              - Zoom level applied after an address resolves.
 * @param   {boolean}     [config.showPreviewPin]       - Drop a pin for the resolved address. On for the
 *                                                        admin editor; off for the public search form,
 *                                                        where a pin would look like a search result.
 * @param   {boolean}     [config.panOnSelect]          - Recenter the map when an address resolves. On for
 *                                                        the admin editor; off for the public search form,
 *                                                        where the map moves only after Search is clicked.
 * @param   {boolean}     [config.geocodeOnChange]      - Geocode typed text when the field loses focus. On
 *                                                        for the admin editor; off for the public search
 *                                                        form, which geocodes on submit instead.
 * @param   {boolean}     [config.replaceWithFormattedAddress] - Replace the field text with Google's formatted
 *                                                        address after a selection. On for the admin editor,
 *                                                        which saves it; off for the public search form,
 *                                                        where it would drop business and landmark names.
 * @param   {string}      [config.suggestionsLabel]     - Accessible name for the suggestion list.
 * @param   {string}      [config.pinColor]             - Default pin color.
 * @param   {string}      [config.pinIconUrl]           - Default custom pin icon URL.
 * @param   {Object}      [config.termPinStyles]        - Per-term pin overrides: { [termId]: { color, iconUrl } }.
 * @param   {string}      [config.unit]                 - Distance unit, "mi" or "km".
 * @param   {string}      [config.mapHeight]            - CSS height for the map element.
 * @param   {string}      [config.minMapHeight]         - CSS min-height for the map element.
 * @param   {boolean}     [config.autoFit]              - Fit the map to all markers after setPins().
 * @param   {boolean}     [config.clustering]           - Enable marker clustering.
 * @param   {number}      [config.clusterMaxZoom]       - Maximum zoom level for clustering.
 * @param   {boolean}     [config.zoomControl]          - Show the zoom control.
 * @param   {boolean}     [config.fullscreenControl]    - Show the fullscreen control.
 * @param   {boolean}     [config.streetViewControl]    - Show the Street View control.
 * @param   {boolean}     [config.mapTypeControl]       - Show the map type control.
 * @param   {boolean}     [config.highContrast]         - Use high-contrast markers.
 * @param   {Array}       [config.mapStyles]            - Google Maps style array.
 * @param   {string}      [config.viewDetailsLabel]     - Label for the info window's "View details" link.
 * @param   {string}      [config.loadingLabel]         - Label shown while info window details load.
 * @param   {string}      [config.resultLinkTarget]     - "same" or "new"; passed through to initMap().
 * @param   {Function}    [config.onMarkerClick]        - Called with the post ID when a marker is clicked.
 * @param   {Function}    [config.fetchLocationDetails] - Resolves a location's info window detail fields.
 * @returns {Promise<Object|null>} mapApi from initMap(), or null when the map cannot be created.
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
		geocodeOnChange = true,
		replaceWithFormattedAddress = true,
		suggestionsLabel = "Address suggestions",
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

	// One object reused by every Google call so key, language, and region
	// can never differ between the map, suggestions, and geocoding.
	const loaderArgs = { apiKey, language, region };

	await ensureMaps(loaderArgs);

	// Hidden fields written by every resolution path. Fields that don't exist
	// in the current context (e.g. city/state on the public form) stay null,
	// and setInputValue() skips them.
	const fields = {
		city: root.querySelector("#locfinder_city"),
		state: root.querySelector("#locfinder_state"),
		postalCode: root.querySelector("#locfinder_postal_code"),
		countryCode: root.querySelector("#locfinder_country_code"),
		lat: root.querySelector(latSelector),
		lng: root.querySelector(lngSelector),
		placeId: root.querySelector(placeIdSelector),
	};

	const savedLat = fields.lat?.value ? parseFloat(fields.lat.value) : null;
	const savedLng = fields.lng?.value ? parseFloat(fields.lng.value) : null;
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

	/**
	 * Drops the single preview pin used by the admin editor.
	 *
	 * Does nothing when showPreviewPin is off (public search form).
	 *
	 * @param   {{lat: number, lng: number}} center - Pin position.
	 * @param   {string}                     title  - Pin title (the address).
	 * @returns {void}
	 */
	function showPreview(center, title) {
		if (!showPreviewPin) {
			return;
		}

		mapApi.setPins([
			{
				id: 0,
				lat: center.lat,
				lng: center.lng,
				pinColor,
				postTitle: title,
			},
		]);
	}

	if (hasSavedCoords) {
		showPreview(
			{ lat: savedLat, lng: savedLng },
			addressInput?.value || ""
		);
		mapApi.map.setCenter({ lat: savedLat, lng: savedLng });
		mapApi.map.setZoom(pinZoom);
	}

	// Always expose the mode so callers can log it; "none" until attached.
	let autocomplete = null;

	Object.defineProperty(mapApi, "autocompleteMode", {
		get: () => autocomplete?.mode ?? "none",
		enumerable: true,
	});

	if (!addressInput) {
		return mapApi;
	}

	// The address text the current coordinates belong to. Lets the change
	// handler skip geocoding when the text hasn't actually changed.
	let lastResolvedAddress = hasSavedCoords ? addressInput.value.trim() : "";

	/**
	 * Writes a resolved place into every hidden field and updates the map.
	 *
	 * The single path shared by suggestion selection (new and legacy APIs)
	 * and the geocoding fallback, so all of them save identical data.
	 *
	 * @param   {Object} place - google.maps.places.Place, legacy PlaceResult, or GeocoderResult.
	 * @returns {void}
	 */
	function applyPlace(place) {
		const center = getPlaceCenter(place);

		if (!center) {
			console.warn("Locfinder: the selected address has no coordinates.");
			return;
		}

		const parsed = parsePlaceResult(place);

		setInputValue(fields.city, parsed.city);
		setInputValue(fields.state, parsed.state);
		setInputValue(fields.postalCode, parsed.postalCode);
		setInputValue(fields.countryCode, parsed.countryCode);
		setInputValue(fields.lat, parsed.lat);
		setInputValue(fields.lng, parsed.lng);
		setInputValue(fields.placeId, parsed.placeId);

		if (replaceWithFormattedAddress && parsed.formattedAddress) {
			addressInput.value = parsed.formattedAddress;
		}

		if (
			address2Input &&
			parsed.address2 &&
			address2Input.value.trim() === ""
		) {
			address2Input.value = parsed.address2;
		}

		lastResolvedAddress = addressInput.value.trim();

		showPreview(center, parsed.formattedAddress);

		if (panOnSelect) {
			mapApi.map.setCenter(center);
			mapApi.map.setZoom(pinZoom);
		}
	}

	/**
	 * Clears every derived field when the address field is emptied.
	 *
	 * Partial edits intentionally keep the previous coordinates until new
	 * ones resolve: the block editor saves meta boxes the instant Update is
	 * clicked, and keeping slightly stale coordinates is safer than saving
	 * an address with none.
	 *
	 * @returns {void}
	 */
	function onAddressInput() {
		if (addressInput.value.trim() !== "") {
			return;
		}

		Object.values(fields).forEach((el) => setInputValue(el, ""));
		lastResolvedAddress = "";
	}

	/**
	 * Geocodes typed text when the field loses focus without a selection.
	 *
	 * Waits for any in-flight suggestion selection first so the two paths
	 * never both write, skips unchanged text to avoid billable repeats, and
	 * discards the result if the user kept typing while it was pending.
	 *
	 * @returns {Promise<void>}
	 */
	async function onAddressChange() {
		await autocomplete?.pending;

		const text = addressInput.value.trim();

		if (text === "" || text === lastResolvedAddress) {
			return;
		}

		try {
			const result = await geocodeAddress(text, loaderArgs);

			if (result && addressInput.value.trim() === text) {
				applyPlace(result);
			}
		} catch (err) {
			console.warn("Locfinder: could not geocode the address.", err);
		}
	}

	addressInput.addEventListener("input", onAddressInput);

	try {
		autocomplete = await attachPlacesAutocomplete(addressInput, {
			loaderArgs,
			labels: { listbox: suggestionsLabel },
			onSelect: applyPlace,
		});
	} catch (err) {
		// Neither Places API is available for this key. Typed addresses are
		// still geocoded on change (editor) or on submit (public form).
		console.warn(
			"Locfinder: address suggestions unavailable; typed addresses will be geocoded instead.",
			err
		);
	}

	if (geocodeOnChange) {
		addressInput.addEventListener("change", onAddressChange);
	}

	return mapApi;
}

/**
 * Sets an input element's value safely.
 *
 * No-ops when the element is missing, so callers don't need to guard
 * against fields that only exist in one context (admin vs public).
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
