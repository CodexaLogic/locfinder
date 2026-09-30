/**
 * Geocodes free-text addresses.
 *
 * Fallback for when an editor or visitor types an address without choosing
 * a suggestion, or when no Places autocomplete API is enabled for the key.
 * Uses the Geocoding API, which remains available to all Google Cloud
 * customers (unlike the legacy Places Autocomplete).
 *
 * Returns a GeocoderResult, which parsePlaceResult() and getPlaceCenter()
 * in place-parser.js already understand.
 *
 * @module geocoder
 */

import { ensureLibrary } from "./google-loader.js";

/**
 * Resolves a free-text address to its best geocoder match.
 *
 * @param   {string} address               - Address text to resolve.
 * @param   {Object} loaderArgs            - Google loader settings.
 * @param   {string} loaderArgs.apiKey     - Google Maps API key.
 * @param   {string} [loaderArgs.language] - Result language, e.g. "en".
 * @param   {string} [loaderArgs.region]   - Region bias, e.g. "US".
 * @returns {Promise<google.maps.GeocoderResult|null>} Best match, or null when nothing matches.
 */
export async function geocodeAddress(address, loaderArgs = {}) {
	const query = String(address ?? "").trim();

	// Nothing to resolve, skip billable request.
	if (query === "") {
		return null;
	}

	const { Geocoder } = await ensureLibrary("geocoding", loaderArgs);

	try {
		const { results } = await new Geocoder().geocode({
			address: query,
			language: loaderArgs.language,
			region: loaderArgs.region,
		});

		// Return the first result, which is the best match according to Google.
		return results?.[0] ?? null;
	} catch (err) {
		// "No match" is an expected outcome for typos or vague input, not a failure.
		if (err?.code === "ZERO_RESULTS") {
			return null;
		}

		// Real failures (API not enabled, quota, network) are passed through so callers can handle them.
		throw err;
	}
}
