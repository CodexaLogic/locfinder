/**
 * Parses Google Places results into Locfinder address and coordinate data.
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
 * Parses a Google PlaceResult into Locfinder address data.
 *
 * Returns an empty ParsedPlace shape when no place is provided.
 *
 * @param   {google.maps.places.PlaceResult} place - Google Place result.
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

	out.formattedAddress = place.formatted_address || "";
	out.placeId = place.place_id || "";

	const loc = place.geometry?.location;

	if (loc) {
		out.lat = String(typeof loc.lat === "function" ? loc.lat() : loc.lat);
		out.lng = String(typeof loc.lng === "function" ? loc.lng() : loc.lng);
	}

	const components = place.address_components || [];

	for (const component of components) {
		const types = component.types || [];
		const longName = component.long_name || "";
		const shortName = component.short_name || "";

		if (types.includes("subpremise")) {
			out.address2 = longName;
			continue;
		}

		if (types.includes("locality")) {
			out.city = longName;
			continue;
		}

		if (
			!out.city &&
			(types.includes("postal_town") || types.includes("sublocality"))
		) {
			out.city = longName;
			continue;
		}

		if (types.includes("administrative_area_level_1")) {
			out.state = shortName || longName;
			continue;
		}

		if (types.includes("postal_code")) {
			out.postalCode = longName;
			continue;
		}

		if (types.includes("country")) {
			out.countryCode = shortName.toUpperCase();
			continue;
		}
	}

	return out;
}

/**
 * Gets the center coordinates from a Google PlaceResult.
 *
 * Uses geometry.location when available, then falls back to the center of
 * geometry.viewport.
 *
 * @param   {google.maps.places.PlaceResult} place - Google Place result.
 * @returns {{lat: number, lng: number}|null} Place center, or null if unavailable.
 */
export function getPlaceCenter(place) {
	if (!place) {
		return null;
	}

	const loc = place.geometry?.location;

	if (loc) {
		const lat = typeof loc.lat === "function" ? loc.lat() : loc.lat;
		const lng = typeof loc.lng === "function" ? loc.lng() : loc.lng;

		if (Number.isFinite(lat) && Number.isFinite(lng)) {
			return { lat, lng };
		}
	}

	const viewport = place.geometry?.viewport;

	if (viewport) {
		const sw = viewport.getSouthWest();
		const ne = viewport.getNorthEast();
		const lat = (sw.lat() + ne.lat()) / 2;
		const lng = (sw.lng() + ne.lng()) / 2;
		return { lat, lng };
	}

	return null;
}
