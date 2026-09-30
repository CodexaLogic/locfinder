/**
 * Parses Google place data into Locfinder address and coordinate data.
 *
 * Accepts both result shapes the plugin receives:
 *  - google.maps.places.Place (Places API New): camelCase fields.
 *  - google.maps.GeocoderResult / legacy PlaceResult: snake_case fields,
 *    from the geocoding fallback and the legacy autocomplete widget.
 * Normalizing here keeps one parser for every source.
 */

/**
 * Parsed place data returned by parsePlaceResult().
 *
 * @typedef {Object} ParsedPlace
 * @property {string} formattedAddress - Full formatted address from Google.
 * @property {string} placeId          - Google Place ID.
 * @property {string} address2         - Suite, unit, or floor.
 * @property {string} city             - Locality or available fallback.
 * @property {string} state            - Administrative area level 1.
 * @property {string} postalCode       - Postal code.
 * @property {string} countryCode      - Two-letter uppercase country code.
 * @property {string} lat              - Latitude as a string.
 * @property {string} lng              - Longitude as a string.
 */

/**
 * Reads a LatLng or LatLngLiteral into plain numbers.
 *
 * Google returns LatLng objects (lat() methods) in some places and plain
 * { lat, lng } literals in others; this accepts either.
 *
 * @param   {google.maps.LatLng|google.maps.LatLngLiteral|null|undefined} loc
 * @returns {{lat: number, lng: number}|null} Coordinates, or null if invalid.
 */
function readLatLng(loc) {
	if (!loc) {
		return null;
	}

	const lat = typeof loc.lat === "function" ? loc.lat() : loc.lat;
	const lng = typeof loc.lng === "function" ? loc.lng() : loc.lng;

	return Number.isFinite(lat) && Number.isFinite(lng) ? { lat, lng } : null;
}

/**
 * Normalizes address components from either API into one shape.
 *
 * @param   {Object} place - Place (new) or GeocoderResult / PlaceResult (legacy).
 * @returns {{types: string[], long: string, short: string}[]} Normalized components.
 */
function readComponents(place) {
	// Places API (New): addressComponents[] with longText/shortText.
	if (Array.isArray(place.addressComponents)) {
		return place.addressComponents.map((component) => ({
			types: component.types ?? [],
			long: component.longText ?? "",
			short: component.shortText ?? "",
		}));
	}

	// Geocoder and legacy widget: address_components[] with long_name/short_name.
	return (place.address_components ?? []).map((component) => ({
		types: component.types ?? [],
		long: component.long_name ?? "",
		short: component.short_name ?? "",
	}));
}

/**
 * Gets the center coordinates of a place.
 *
 * Uses the exact location when available, then falls back to the center
 * of the viewport.
 *
 * @param   {Object|null} place - Place (new) or GeocoderResult / PlaceResult (legacy).
 * @returns {{lat: number, lng: number}|null} Place center, or null if unavailable.
 */
export function getPlaceCenter(place) {
	if (!place) {
		return null;
	}

	// New API exposes place.location; legacy nests it under geometry.
	const exact = readLatLng(place.location ?? place.geometry?.location);

	if (exact) {
		return exact;
	}

	const viewport = place.viewport ?? place.geometry?.viewport;

	if (viewport) {
		const sw = viewport.getSouthWest();
		const ne = viewport.getNorthEast();

		return {
			lat: (sw.lat() + ne.lat()) / 2,
			lng: (sw.lng() + ne.lng()) / 2,
		};
	}

	return null;
}

/**
 * Parses a place into Locfinder address data.
 *
 * Returns an empty ParsedPlace shape when no place is provided.
 *
 * @param   {Object|null} place - Place (new) or GeocoderResult / PlaceResult (legacy).
 * @returns {ParsedPlace} Parsed place data.
 */
export function parsePlaceResult(place) {
	const out = {
		formattedAddress: "",
		placeId: "",
		city: "",
		state: "",
		address2: "",
		postalCode: "",
		countryCode: "",
		lat: "",
		lng: "",
	};

	if (!place) {
		return out;
	}

	out.formattedAddress =
		place.formattedAddress || place.formatted_address || "";
	out.placeId = place.id || place.place_id || "";

	// Use the same resolver as the map pin so saved coordinates and the
	// preview pin can never disagree.
	const center = getPlaceCenter(place);

	if (center) {
		out.lat = String(center.lat);
		out.lng = String(center.lng);
	}

	for (const { types, long, short } of readComponents(place)) {
		if (types.includes("subpremise")) {
			out.address2 = long;
		} else if (types.includes("locality")) {
			out.city = long;
		} else if (
			!out.city &&
			(types.includes("postal_town") || types.includes("sublocality"))
		) {
			out.city = long;
		} else if (types.includes("administrative_area_level_1")) {
			out.state = short || long;
		} else if (types.includes("postal_code")) {
			out.postalCode = long;
		} else if (types.includes("country")) {
			out.countryCode = short.toUpperCase();
		}
	}

	return out;
}
