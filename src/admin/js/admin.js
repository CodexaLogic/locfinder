/**
 * Admin entry point for the location editor.
 *
 * Initializes field controls, hours fields, Google Places autocomplete,
 * and the location map preview.
 *
 * Depends on window.locfinderConfig localized by Admin::enqueueFiles().
 */

import "../scss/admin.scss";
import { initLocfinderAddressMap } from "@common/address-autocomplete.js";
import { parseBool } from "@common/utils.js";
import { initFieldControls } from "./field-controls.js";
import { initHoursFields } from "./hours-fields.js";

/**
 * Initializes the location editor controls and map.
 *
 * Map initialization is skipped when the required address or map elements
 * are not present.
 *
 * @returns {Promise<void>}
 */
document.addEventListener("DOMContentLoaded", async () => {
	initFieldControls();
	initHoursFields();

	const addressEl = document.getElementById("locfinder_address");
	const mapEl = document.getElementById("locfinder-map");

	if (!addressEl || !mapEl) {
		return;
	}

	const config = window.locfinderConfig || {};
	const apiKey = config.googleMapsApiKey || "";
	const mapId = config.mapId || "";
	const language = config.language || "en";
	const region = config.region || "US";
	const pinColor = config.pinColor || "#135E96";

	if (!apiKey) {
		console.warn(
			"Locfinder: Google Maps API key is missing. Address autocomplete will not initialize."
		);
		return;
	}

	await initLocfinderAddressMap({
		apiKey,
		mapId,
		language,
		region,
		pinColor,
		addressSelector: "#locfinder_address",
		mapSelector: "#locfinder-map",
		defaultZoom: 4,
		pinZoom: 14,
		// Clustering is unnecessary because the editor preview contains one marker.
		clustering: false,
		// Use the public map configuration to keep the editor preview in sync.
		unit: config.distanceUnit || "mi",
		mapHeight: config.mapHeight || "",
		minMapHeight: config.minMapHeight || "300px",
		zoomControl: parseBool(config.zoomControl ?? true),
		fullscreenControl: parseBool(config.fullscreenControl ?? false),
		streetViewControl: parseBool(config.streetViewControl ?? false),
		mapTypeControl: parseBool(config.mapTypeControl ?? false),
		mapStyles: Array.isArray(config.mapStyles) ? config.mapStyles : [],
	});
});
