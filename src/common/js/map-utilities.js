/**
 * Google Maps utilities for Locfinder.
 *
 * Initializes the map and provides methods for managing markers, radius
 * overlays, and marker selection. Uses AdvancedMarkerElement when a Map ID
 * is available and falls back to legacy markers otherwise.
 *
 * @module map-utilities
 */

import { ensureMaps } from "./google-loader.js";
import { escapeHtml, escapeUrl } from "./utils.js";
import { GridAlgorithm, MarkerClusterer } from "@googlemaps/markerclusterer";

/** Miles to meters conversion factor. */
const METERS_PER_MILE = 1609.34;

/** Kilometers to meters conversion factor. */
const METERS_PER_KM = 1000;

/**
 * Creates a pin element for AdvancedMarkerElement.
 *
 * Supports active, high-contrast, and custom-icon states.
 *
 * @param   {string}  color        - Pin color.
 * @param   {boolean} isActive     - Whether the marker is active.
 * @param   {boolean} highContrast - Whether high-contrast styling is enabled.
 * @param   {string}  iconUrl      - Optional custom icon URL.
 * @returns {HTMLElement}          Pin element.
 */
function createPinElement(
	color,
	isActive = false,
	highContrast = false,
	iconUrl = ""
) {
	const el = document.createElement("div");
	const strokeAttr = highContrast
		? ' stroke="#000000" stroke-width="1.5"'
		: "";
	const safeIconUrl = escapeUrl(iconUrl);
	const safeColor = escapeHtml(color);

	el.className = `lf-marker${isActive ? " lf-marker--active" : ""}${safeIconUrl ? " lf-marker--custom-icon" : ""}`;

	el.innerHTML = safeIconUrl
		? `<img src="${safeIconUrl}" alt="" width="${isActive ? 40 : 32}" height="${isActive ? 40 : 32}" class="lf-marker__icon" style="object-fit: contain;" />`
		: `<svg viewBox="0 0 24 36" width="24" height="36" aria-hidden="true">
        <path d="M12 0C5.4 0 0 5.4 0 12c0 9 12 24 12 24S24 21 24 12C24 5.4 18.6 0 12 0z" fill="${safeColor}"${strokeAttr}/>
        <circle cx="12" cy="12" r="5" fill="white"/>
    </svg>`;

	return el;
}

/**
 * Builds the icon configuration for a legacy Google Maps marker.
 *
 * Supports active, high-contrast, and custom-icon states matching the
 * AdvancedMarkerElement treatment.
 *
 * @param   {string}  color        - Marker color.
 * @param   {boolean} isActive     - Whether the marker is active.
 * @param   {boolean} highContrast - Whether high-contrast styling is enabled.
 * @param   {string}  iconUrl      - Optional custom icon URL.
 * @returns {Object}               Google Maps icon configuration.
 */
function legacyMarkerIcon(
	color,
	isActive = false,
	highContrast = false,
	iconUrl = ""
) {
	if (iconUrl) {
		const size = isActive ? 40 : 32;
		return {
			url: iconUrl,
			scaledSize: new google.maps.Size(size, size),
		};
	}

	return {
		path: google.maps.SymbolPath.CIRCLE,
		fillColor: color,
		fillOpacity: 1,
		strokeColor: isActive ? "#ffffff" : highContrast ? "#000000" : color,
		strokeWeight: isActive ? 3 : highContrast ? 2 : 1,
		scale: isActive ? 10 : 8,
	};
}

/**
 * Creates a cluster badge for AdvancedMarkerElement.
 *
 * @param   {string} color - Badge color.
 * @param   {number} count - Locations in the cluster.
 * @returns {HTMLElement}  Cluster badge element.
 */
function createClusterElement(color, count) {
	const el = document.createElement("div");
	const safeColor = escapeHtml(color);

	el.className = "lf-marker lf-cluster";
	el.innerHTML = `<svg class="lf-cluster__badge" viewBox="0 0 52 52" width="52" height="52" aria-hidden="true">
        <circle cx="26" cy="26" r="26" fill="${safeColor}" fill-opacity="0.25"/>
        <circle cx="26" cy="26" r="18" fill="${safeColor}"/>
    </svg>
    <span class="lf-cluster__count">${escapeHtml(String(count))}</span>`;

	return el;
}

/**
 * Builds the icon configuration for a legacy cluster marker.
 *
 * Uses a data URI to reproduce the AdvancedMarkerElement cluster badge.
 *
 * @param   {string} color - Badge color.
 * @returns {Object}       Google Maps icon configuration.
 */
function legacyClusterIcon(color) {
	const safeColor = escapeHtml(color);
	const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 52 52">
        <circle cx="26" cy="26" r="26" fill="${safeColor}" fill-opacity="0.25"/>
        <circle cx="26" cy="26" r="18" fill="${safeColor}"/>
    </svg>`;

	return {
		url: `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`,
		scaledSize: new google.maps.Size(52, 52),
		anchor: new google.maps.Point(26, 26),
	};
}

/**
 * Converts a distance to meters for Google Maps.
 *
 * @param   {number} radius - Distance in miles or kilometers.
 * @param   {string} unit   - Distance unit: 'mi' or 'km'.
 * @returns {number}        Distance in meters.
 */
function toMeters(radius, unit) {
	return radius * (unit === "km" ? METERS_PER_KM : METERS_PER_MILE);
}

/**
 * Initializes a Google Map and returns its public API.
 *
 * Uses AdvancedMarkerElement when a Map ID is configured and falls back to
 * legacy markers otherwise. Marker, cluster, radius, and selection state
 * remain private within the map instance.
 *
 * @param {Object} config
 * @param {string}      config.apiKey              - Google Maps API key.
 * @param {string}      [config.mapId]             - Map ID for AdvancedMarkerElement support.
 * @param {string}      [config.language]          - Language code.
 * @param {string}      [config.region]            - Region code.
 * @param {HTMLElement} config.mapElement          - The DOM element to render the map into.
 * @param {string}      [config.pinColor]          - Default pin color.
 * @param {string}      [config.pinIconUrl]        - Default custom pin icon URL, used instead of the color pin when set.
 * @param {Object}      [config.termPinStyles]     - Per-category pin overrides, keyed by term ID: { [termId]: { color, iconUrl } }.
 * @param {number}      [config.defaultLat]        - Default map center latitude.
 * @param {number}      [config.defaultLng]        - Default map center longitude.
 * @param {number}      [config.defaultZoom]       - Default zoom level.
 * @param {string}      [config.unit]              - Distance unit, 'mi' or 'km'.
 * @param {string}      [config.mapHeight]         - CSS height value for the map element e.g. '500px'.
 * @param {string}      [config.minMapHeight]      - CSS min-height value for the map element e.g. '300px'.
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
 * @param {Function} [config.fetchLocationDetails] - Callback that resolves location detail fields for the info window.
 * @param {boolean}  [config.showInfoWindow]       - Whether marker clicks open an info window.
 * @param {string}   [config.resultLinkTarget]     - Info-window link target: 'same' or 'new'.
 * @returns {Promise<Object|null>}                 mapApi object or null if initialization fails.
 */
export async function initMap(config = {}) {
	const {
		apiKey,
		mapId = "",
		language = "en",
		region = "US",
		mapElement,
		pinColor = "#135E96",
		pinIconUrl = "",
		termPinStyles = {},
		defaultLat = 39.8283,
		defaultLng = -98.5795,
		defaultZoom = 11,
		unit = "mi",
		mapHeight = "60vh",
		minMapHeight = "300px",
		autoFit = true,
		clustering = true,
		clusterMaxZoom = 14,
		zoomControl = false,
		fullscreenControl = false,
		streetViewControl = false,
		mapTypeControl = false,
		highContrast = false,
		mapStyles = [],
		viewDetailsLabel = "View details",
		loadingLabel = "Loading…",
		showInfoWindow = true,
		resultLinkTarget = "same",
		onMarkerClick,
		fetchLocationDetails,
	} = config;

	const permalinkTargetAttrs =
		resultLinkTarget === "new" ? ' target="_blank" rel="noopener"' : "";

	if (!mapElement) {
		console.warn(
			"Locfinder: mapElement is required to initialize the map."
		);
		return null;
	}

	if (mapHeight) {
		mapElement.style.setProperty("--locfinder-map-height", mapHeight);
	}

	if (minMapHeight) {
		mapElement.style.minHeight = minMapHeight;
	}

	await ensureMaps({ apiKey, language, region });

	const { Map } = await google.maps.importLibrary("maps");
	const useAdvanced = Boolean(mapId);

	const markerLib = useAdvanced
		? await google.maps.importLibrary("marker")
		: null;
	const AdvancedMarker = markerLib?.AdvancedMarkerElement ?? null;
	const LegacyMarker = useAdvanced ? null : google.maps.Marker;

	const map = new Map(mapElement, {
		center: { lat: defaultLat, lng: defaultLng },
		zoom: defaultZoom,
		mapId: useAdvanced ? mapId : undefined,
		zoomControl,
		fullscreenControl,
		streetViewControl,
		mapTypeControl,
		gestureHandling: "cooperative",
		clickableIcons: false,
		styles: !useAdvanced && mapStyles.length > 0 ? mapStyles : undefined,
	});

	if (typeof ResizeObserver !== "undefined") {
		let lastSize = "";
		const resizeObserver = new ResizeObserver((entries) => {
			const { width, height } = entries[0].contentRect;
			const size = `${width}x${height}`;

			if (size !== lastSize && width > 0 && height > 0) {
				lastSize = size;
				google.maps.event.trigger(map, "resize");
				map.setCenter(map.getCenter());
			}
		});
		resizeObserver.observe(mapElement);
	}

	// Private map state.
	let markers = [];
	let clusterer = null;
	let radiusCircle = null;
	let activePostId = null;

	const infoWindow = new google.maps.InfoWindow({ maxWidth: 280 });

	/**
	 * Clears the currently active marker state.
	 *
	 * @returns {void}
	 */
	function deactivateMarker() {
		if (activePostId === null) {
			return;
		}

		const prev = markers.find((m) => m.postId === activePostId);

		if (prev) {
			if (useAdvanced) {
				prev.content.classList.remove("lf-marker--active");
			} else {
				prev.setIcon(
					legacyMarkerIcon(
						prev.color,
						false,
						highContrast,
						prev.iconUrl
					)
				);
			}
		}

		activePostId = null;
	}

	/**
	 * Removes all markers and the active clusterer from the map.
	 *
	 * Resets marker selection and closes the shared info window.
	 *
	 * @returns {void}
	 */
	function clearMarkers() {
		if (clusterer) {
			clusterer.setMap(null);
			clusterer = null;
		}

		markers.forEach((m) => {
			m.map = null;
		});
		markers = [];
		activePostId = null;
		infoWindow.close();
	}

	/**
	 * Opens the info window for a marker.
	 *
	 * Displays available marker data immediately, then replaces it with loaded
	 * details when fetchLocationDetails is provided.
	 *
	 * @param   {Object} marker - Marker to display.
	 * @returns {void}
	 */
	function showMarkerInfo(marker) {
		const title = escapeHtml(marker.postTitle || "");
		const address = marker.address
			? `<div class="lf-marker-info__address">${escapeHtml(marker.address)}</div>`
			: "";

		if (!fetchLocationDetails) {
			const link = marker.permalink
				? `<a class="lf-marker-info__link" href="${escapeUrl(marker.permalink)}"${permalinkTargetAttrs}>${escapeHtml(viewDetailsLabel)}${resultLinkTarget === "new" ? '<span class="screen-reader-text"> (opens in a new tab)</span>' : ""}</a>`
				: "";

			infoWindow.setContent(
				`<div class="lf-marker-info">
                    <div class="lf-marker-info__title">${title}</div>
                    ${address}
                    ${link}
                </div>`
			);
			infoWindow.open({ anchor: marker, map });
			return;
		}

		infoWindow.setContent(
			`<div class="lf-marker-info">
                <div class="lf-marker-info__title">${title}</div>
                ${address}
                <div class="lf-marker-info__loading">${escapeHtml(loadingLabel)}</div>
            </div>`
		);
		infoWindow.open({ anchor: marker, map });

		fetchLocationDetails(marker.postId)
			.then((data) => renderMarkerInfo(marker, data))
			.catch((err) => {
				console.error("Locfinder: error fetching pin details:", err);
			});
	}

	/**
	 * Renders a marker's loaded info-window content.
	 *
	 * Ignores stale responses when the active marker has changed before the
	 * request completes.
	 *
	 * @param   {Object} marker - The marker this data belongs to.
	 * @param   {Object} data   - Detail fields from locfinder_get_location_details.
	 * @returns {void}
	 */
	function renderMarkerInfo(marker, data) {
		if (activePostId !== marker.postId) {
			return;
		}

		const address = data.address
			? `<div class="lf-marker-info__address">${escapeHtml(data.address)}</div>`
			: "";

		const viewDetailsLink = marker.permalink
			? `<div class="locfinder-location__view-details"><a href="${escapeUrl(marker.permalink)}"${permalinkTargetAttrs}>${escapeHtml(viewDetailsLabel)}${resultLinkTarget === "new" ? '<span class="screen-reader-text"> (opens in a new tab)</span>' : ""}</a></div>`
			: "";

		infoWindow.setContent(
			`<div class="lf-marker-info">
                <div class="lf-marker-info__title-row">
                    <span class="lf-marker-info__title">${escapeHtml(marker.postTitle || "")}</span>
                    ${data.openBadgeHtml || ""}
                </div>
                ${address}
                ${data.phoneHtml || ""}
                ${data.websiteHtml || ""}
                <hr class="lf-marker-info__divider" />
                <div class="lf-marker-info__actions">
                    ${data.directionsHtml || ""}
                    ${viewDetailsLink}
                </div>
            </div>`
		);
	}

	/**
	 * Handles a marker click.
	 *
	 * Updates the active marker state, optionally opens its info window, and
	 * notifies the caller through onMarkerClick when provided.
	 *
	 * @param   {Object} marker - Clicked marker instance.
	 * @param   {number} postId - Location post ID.
	 * @returns {void}
	 */
	function handleMarkerClick(marker, postId) {
		deactivateMarker();

		if (useAdvanced) {
			marker.content.classList.add("lf-marker--active");
		} else {
			marker.setIcon(
				legacyMarkerIcon(
					marker.color,
					true,
					highContrast,
					marker.iconUrl
				)
			);
		}

		activePostId = postId;

		if (showInfoWindow) {
			showMarkerInfo(marker);
		}

		onMarkerClick?.(postId);
	}

	/**
	 * Activates and pans to a marker by post ID.
	 *
	 * Mirrors marker-click activation without firing onMarkerClick, allowing
	 * callers to select a marker without triggering a feedback loop.
	 *
	 * @param   {number} postId - Location post ID.
	 * @returns {void}
	 */
	function activateMarkerByPostId(postId) {
		const marker = markers.find((m) => m.postId === postId);

		if (!marker) {
			return;
		}

		deactivateMarker();

		if (useAdvanced) {
			marker.content.classList.add("lf-marker--active");
		} else {
			marker.setIcon(
				legacyMarkerIcon(
					marker.color,
					true,
					highContrast,
					marker.iconUrl
				)
			);
		}

		activePostId = postId;

		// Zoom past the clusterer's maxZoom first so the marker isn't
		// still hidden inside a cluster badge when we pan to it.
		if (clusterer && map.getZoom() <= clusterMaxZoom) {
			map.setZoom(clusterMaxZoom + 1);
		}

		map.panTo({ lat: marker.lat, lng: marker.lng });
	}

	/**
	 * Resolves the effective pin style for a location.
	 *
	 * Uses the first matching term override, then falls back to the location
	 * color and global icon settings.
	 *
	 * @param   {Object} location - Location data.
	 * @returns {{color: string, iconUrl: string}}
	 */
	function resolveLocationStyle(location) {
		const termIds = Array.isArray(location.termIds) ? location.termIds : [];

		for (const termId of termIds) {
			const override = termPinStyles[termId];

			if (override && (override.color || override.iconUrl)) {
				return {
					color: override.color || location.pinColor || pinColor,
					iconUrl: override.iconUrl || pinIconUrl,
				};
			}
		}

		return {
			color: location.pinColor || pinColor,
			iconUrl: pinIconUrl,
		};
	}

	/**
	 * Creates a marker for a location.
	 *
	 * Returns null when the coordinates are invalid, allowing callers to filter
	 * unusable locations from the marker list.
	 *
	 * @param   {Object} location           - Location data.
	 * @param   {number} location.id        - WordPress post ID.
	 * @param   {number} location.lat       - Latitude.
	 * @param   {number} location.lng       - Longitude.
	 * @param   {string} location.postTitle - Location title.
	 * @param   {string} location.address   - Location address.
	 * @param   {string} location.permalink - Location permalink.
	 * @returns {Object|null}               Marker instance, or null if invalid.
	 */
	function createMarker(location) {
		const {
			id,
			lat,
			lng,
			postTitle = "",
			address = "",
			permalink = "",
		} = location;

		const latNum = typeof lat === "number" ? lat : parseFloat(lat);
		const lngNum = typeof lng === "number" ? lng : parseFloat(lng);

		if (
			!Number.isFinite(latNum) ||
			!Number.isFinite(lngNum) ||
			latNum < -90 ||
			latNum > 90 ||
			lngNum < -180 ||
			lngNum > 180
		) {
			return null;
		}

		const { color, iconUrl } = resolveLocationStyle(location);
		const position = { lat: latNum, lng: lngNum };
		let marker;

		if (useAdvanced) {
			marker = new AdvancedMarker({
				map,
				position,
				content: createPinElement(color, false, highContrast, iconUrl),
				title: postTitle,
			});
			marker.addEventListener("click", () =>
				handleMarkerClick(marker, id)
			);
		} else {
			marker = new LegacyMarker({
				map,
				position,
				title: postTitle,
				icon: legacyMarkerIcon(color, false, highContrast, iconUrl),
			});
			marker.addListener("click", () => handleMarkerClick(marker, id));
		}

		marker.postId = id;
		marker.postTitle = postTitle;
		marker.address = address;
		marker.permalink = permalink;
		marker.color = color;
		marker.iconUrl = iconUrl;
		marker.lat = latNum;
		marker.lng = lngNum;

		return marker;
	}

	/**
	 * Fits the map viewport to the current markers.
	 *
	 * Honors the autoFit setting and uses the default zoom for a single marker.
	 *
	 * @returns {void}
	 */
	function fitMarkersInView() {
		if (!autoFit || markers.length === 0) {
			return;
		}

		const bounds = new google.maps.LatLngBounds();

		markers.forEach((marker) => {
			const pos = useAdvanced ? marker.position : marker.getPosition();

			if (pos) {
				bounds.extend(pos);
			}
		});

		if (markers.length === 1) {
			const pos = useAdvanced
				? markers[0].position
				: markers[0].getPosition();

			if (pos) {
				map.setCenter(pos);
				map.setZoom(defaultZoom);
			}

			return;
		}

		map.fitBounds(bounds);
	}

	/**
	 * Renders cluster badges using the plugin's pin color.
	 *
	 * MarkerClusterer continues to handle cluster interaction and zoom behavior.
	 *
	 * @type {{render: function({count: number, position: Object}): Object}}
	 */
	const clusterRenderer = {
		render({ count, position }) {
			if (useAdvanced && AdvancedMarker) {
				return new AdvancedMarker({
					map,
					position,
					content: createClusterElement(pinColor, count),
				});
			}

			return new LegacyMarker({
				map,
				position,
				icon: legacyClusterIcon(pinColor),
				label: {
					text: String(count),
					color: "#ffffff",
					fontSize: "14px",
					fontWeight: "700",
				},
			});
		},
	};

	/**
	 * Places markers on the map for the provided locations.
	 *
	 * Existing markers are cleared first, and locations with invalid coordinates
	 * are skipped. A new clusterer is created for each result set when clustering
	 * is enabled.
	 *
	 * @param   {Object[]} locations             - Location data.
	 * @param   {Object}   [options]             - Display options.
	 * @param   {boolean}  [options.skipAutoFit] - Whether to skip fitting markers in view.
	 * @returns {void}
	 */
	function setPins(locations, { skipAutoFit = false } = {}) {
		clearMarkers();

		if (!Array.isArray(locations) || locations.length === 0) {
			return;
		}

		markers = locations.map(createMarker).filter(Boolean);

		if (clustering && markers.length > 0) {
			clusterer = new MarkerClusterer({
				map,
				markers,
				algorithm: new GridAlgorithm({ maxZoom: clusterMaxZoom }),
				renderer: clusterRenderer,
			});
		}

		if (!skipAutoFit) {
			fitMarkersInView();
		}
	}

	/**
	 * Draws a radius circle centered on the given coordinates.
	 *
	 * Replaces any existing circle and converts the configured distance unit
	 * to meters for Google Maps.
	 *
	 * @param   {number} lat    - Center latitude.
	 * @param   {number} lng    - Center longitude.
	 * @param   {number} radius - Radius in the configured distance unit.
	 * @returns {void}
	 */
	function drawRadius(lat, lng, radius) {
		clearRadius();

		try {
			radiusCircle = new google.maps.Circle({
				map,
				center: { lat, lng },
				radius: toMeters(radius, unit),
				strokeColor: pinColor,
				strokeOpacity: 0.8,
				strokeWeight: 2,
				fillColor: pinColor,
				fillOpacity: 0.12,
			});

			map.fitBounds(radiusCircle.getBounds());
		} catch (err) {
			console.error("Locfinder: drawRadius error:", err);
		}
	}

	/**
	 * Removes the current radius circle from the map, if present.
	 *
	 * @returns {void}
	 */
	function clearRadius() {
		if (radiusCircle) {
			radiusCircle.setMap(null);
			radiusCircle = null;
		}
	}

	return { map, setPins, drawRadius, clearRadius, activateMarkerByPostId };
}
