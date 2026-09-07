/**
 * Public entry point for the single Location map.
 *
 * Initializes the map for a singular Location page. Instance configuration
 * is scoped to the map section to avoid conflicting with embedded locators.
 */

import "../scss/single.scss";
import { initMap } from "@common/map-utilities.js";
import { parseBool, querySelector } from "@common/utils.js";

document.addEventListener("DOMContentLoaded", () => {
	const mapElement = querySelector("[data-locfinder-single]");

	// Prevent duplicate initialization.
	if (!mapElement || mapElement.dataset.locfinderInit === "1") {
		return;
	}

	mapElement.dataset.locfinderInit = "1";
	initSingleLocationMap(mapElement);
});

/**
 * Parses the embedded single-location configuration.
 *
 * @param   {HTMLElement|null} configEl - Configuration script element.
 * @returns {Object}                    Parsed configuration, or an empty object on error.
 */
function parseInstanceConfig(configEl) {
	if (!configEl) {
		return {};
	}

	try {
		return JSON.parse(configEl.textContent || "{}");
	} catch {
		console.warn("Locfinder: error parsing single location config.");
		return {};
	}
}

/**
 * Replaces the loading message with an error message.
 *
 * @param   {HTMLElement|null} loadingEl - Loading message element.
 * @param   {string}           message   - Error message.
 * @returns {void}
 */
function showError(loadingEl, message) {
	if (!loadingEl) {
		return;
	}

	loadingEl.textContent = message;
	loadingEl.classList.add("locfinder-single__map-loading--error");
}

/**
 * Initializes a single Location map.
 *
 * @param   {HTMLElement} mapElement - Map element.
 * @returns {Promise<void>}
 */
async function initSingleLocationMap(mapElement) {
	// Scope configuration and loading state to this map section.
	const section = mapElement.closest(".locfinder-single__map-section");
	const scope = section ?? mapElement.parentElement ?? document;
	const loadingEl = querySelector("[data-locfinder-single-loading]", scope);
	const configEl = querySelector(".locfinder-config", scope);

	const instanceConfig = parseInstanceConfig(configEl);
	const mapConfig = window.locfinderSingleConfig || {};

	const apiKey = mapConfig.googleMapsApiKey || "";

	if (!apiKey) {
		console.warn("Locfinder: Google Maps API key is missing.");
		showError(
			loadingEl,
			mapConfig.singleMapNotConfiguredMessage || "Map is not configured."
		);
		return;
	}

	const lat = parseFloat(instanceConfig.lat);
	const lng = parseFloat(instanceConfig.lng);

	if (
		!Number.isFinite(lat) ||
		!Number.isFinite(lng) ||
		lat < -90 ||
		lat > 90 ||
		lng < -180 ||
		lng > 180
	) {
		showError(
			loadingEl,
			mapConfig.singleMapNoCoordsMessage ||
				"No coordinates are set for this location."
		);
		return;
	}

	try {
		const mapApi = await initMap({
			apiKey,
			mapId: mapConfig.mapId || "",
			language: mapConfig.language || "en",
			region: mapConfig.region || "US",
			mapElement,
			pinColor:
				instanceConfig.pinColor || mapConfig.pinColor || "#00606b",
			pinIconUrl: mapConfig.pinIconUrl || "",
			termPinStyles: mapConfig.termPinStyles || {},
			defaultLat: lat,
			defaultLng: lng,
			defaultZoom: 15,
			mapHeight: mapConfig.mapHeight || "",
			minMapHeight: mapConfig.minMapHeight || "300px",
			autoFit: false,
			clustering: false,
			zoomControl: parseBool(mapConfig.zoomControl ?? true),
			fullscreenControl: parseBool(mapConfig.fullscreenControl ?? false),
			streetViewControl: parseBool(mapConfig.streetViewControl ?? false),
			mapTypeControl: parseBool(mapConfig.mapTypeControl ?? false),
			highContrast: parseBool(mapConfig.highContrastMode ?? false),
			mapStyles: Array.isArray(mapConfig.mapStyles)
				? mapConfig.mapStyles
				: [],
			showInfoWindow: false,
		});

		if (!mapApi) {
			throw new Error("Map failed to initialize.");
		}

		mapApi.setPins([
			{
				id: instanceConfig.postId,
				lat,
				lng,
				pinColor: instanceConfig.pinColor || mapConfig.pinColor,
				postTitle: instanceConfig.postTitle || "",
			},
		]);

		loadingEl?.remove();
	} catch (err) {
		console.error(
			"Locfinder: single location map failed to initialize:",
			err
		);
		showError(
			loadingEl,
			mapConfig.singleMapLoadErrorMessage ||
				"The map could not be loaded."
		);
	}
}
