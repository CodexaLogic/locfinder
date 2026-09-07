/**
 * Public entry point for Locfinder.
 *
 * Initializes and manages multiple isolated locator instances on the page.
 */
import "../scss/public.scss";
import { initLocfinderAddressMap } from "@common/address-autocomplete.js";
import {
	debounce,
	ensureElementId,
	escapeHtml,
	escapeUrl,
	formatDistance,
	parseBool,
	querySelector,
	querySelectorAll,
} from "@common/utils.js";

/**
 * Keeps a `--locfinder-scrollbar-width` custom property on the root element
 * in sync with the browser's actual vertical scrollbar width. The
 * `calc(50% - 50vw)` full-bleed technique used for left/right layouts
 * needs this to compensate, `vw` units include the scrollbar gutter,
 * while the plugin's own containers don't, which otherwise leaves the
 * full-bleed map and search row misaligned by half the scrollbar's width.
 *
 * @returns {void}
 */
function updateScrollbarWidthVar() {
	const scrollbarWidth =
		window.innerWidth - document.documentElement.clientWidth;

	document.documentElement.style.setProperty(
		"--locfinder-scrollbar-width",
		`${scrollbarWidth}px`
	);
}

updateScrollbarWidthVar();
window.addEventListener("resize", debounce(updateScrollbarWidthVar, 150));

/**
 * Initializes all Locfinder instances on the page.
 *
 * @returns {void}
 */
document.addEventListener("DOMContentLoaded", () => {
	const roots = querySelectorAll("[data-locfinder]");

	if (!roots.length) {
		return;
	}

	// Initialize each instance only once.
	roots.forEach((root) => {
		if (root.dataset.locfinderInit === "1") {
			return;
		}

		root.dataset.locfinderInit = "1";
		initLocfinderInstance(root);
	});
});

/**
 * Whether debug logging is enabled.
 *
 * @type {boolean}
 */
const debugMode = Boolean(window.locfinderConfig?.debugMode);

/**
 * Zoom level used when centering on a searched address without a radius.
 *
 * @type {number}
 */
const SEARCHED_ADDRESS_ZOOM = 14;

/**
 * Logs debug information to the console if debug mode is enabled.
 *
 * @param   {string} label - Label for the debug message.
 * @param   {*}      data  - Data to log.
 * @returns {void}
 */
function debugLog(label, data) {
	if (!debugMode) {
		return;
	}

	console.groupCollapsed(`Locfinder Debug: ${label}`);
	console.log(data);
	console.groupEnd();
}

/**
 * Parses the JSON config from the script element within a Locfinder instance.
 *
 * @param   {HTMLElement} root - The root locfinder element.
 * @returns {Object}             The parsed config object, or an empty object on error.
 */
function parseInstanceConfig(root) {
	const configEl = querySelector(".locfinder-config", root);

	if (!configEl) {
		return {};
	}

	try {
		return JSON.parse(configEl.textContent || "{}");
	} catch {
		console.warn("Locfinder: error parsing instance config.");
		return {};
	}
}

/**
 * Normalizes per-instance configuration.
 *
 * @param   {Object} raw - Raw instance configuration.
 * @returns {Object}     Normalized configuration.
 */
function normalizeConfig(raw = {}) {
	return {
		instanceId: String(raw.instanceId ?? raw.instance_id ?? ""),
		resultsLayout: String(
			raw.resultsLayout ?? raw.results_layout ?? "grid"
		),
		resultsPosition: String(
			raw.resultsPosition ?? raw.results_position ?? "left"
		),
		gridCols: Number(raw.gridCols ?? raw.grid_cols ?? 3),
		resultsColumns: Number(raw.resultsColumns ?? raw.results_columns ?? 2),
		order: String(raw.order ?? "ASC"),
		orderby: String(raw.orderby ?? "post_title"),
		pinColor: String(raw.pinColor ?? "#00606b"),
		postsPerPage: Number(raw.postsPerPage ?? raw.posts_per_page ?? 12),
		showExcerpt: Boolean(raw.showExcerpt ?? raw.show_excerpt ?? false),
		showImage: Boolean(raw.showImage ?? raw.show_image ?? false),
		showAddress: Boolean(raw.showAddress ?? raw.show_address ?? true),
		showDirections: Boolean(
			raw.showDirections ?? raw.show_directions ?? true
		),
		showPhone: Boolean(raw.showPhone ?? raw.show_phone ?? true),
		showEmail: Boolean(raw.showEmail ?? raw.show_email ?? false),
		showWebsite: Boolean(raw.showWebsite ?? raw.show_website ?? true),
		showHours: Boolean(raw.showHours ?? raw.show_hours ?? false),
		showCategories: Boolean(
			raw.showCategories ?? raw.show_categories ?? true
		),
		showOpenBadge: Boolean(
			raw.showOpenBadge ?? raw.show_open_badge ?? true
		),
		taxonomy: String(raw.taxonomy ?? "locfinder_category"),
		termPinStyles:
			raw.termPinStyles && typeof raw.termPinStyles === "object"
				? raw.termPinStyles
				: {},
	};
}

/**
 * Initializes a single Locfinder instance.
 *
 * @param   {HTMLElement} root - The root locfinder element.
 * @returns {void}
 */
function initLocfinderInstance(root) {
	const rawConfig = parseInstanceConfig(root);
	const instanceConfig = normalizeConfig(rawConfig);

	const form = querySelector(".locfinder-form", root);
	const results = querySelector(".locfinder__results", root);

	if (!form || !results) {
		return;
	}

	// Wrap results to scope the loading overlay to this instance.
	const resultsWrap = document.createElement("div");
	resultsWrap.className = "locfinder__results-wrap";
	results.parentNode.insertBefore(resultsWrap, results);
	resultsWrap.appendChild(results);

	const spinner = document.createElement("div");
	spinner.className = "locfinder-spinner locfinder-hidden";
	spinner.innerHTML = '<div class="locfinder-spinner__indicator"></div>';
	resultsWrap.appendChild(spinner);

	/**
	 * Toggles this instance's loading spinner.
	 *
	 * @param   {boolean} show - Whether to show the spinner.
	 * @returns {void}
	 */
	function toggleSpinner(show) {
		spinner.classList.toggle("locfinder-hidden", !show);
	}

	const mapElement = querySelector(".locfinder__map", root);

	const mapWidth = window.locfinderConfig?.mapWidth || "";
	if (mapWidth && mapElement) {
		if (instanceConfig.resultsPosition === "bottom") {
			mapElement.style.width = mapWidth;
		} else {
			mapElement.style.setProperty("--locfinder-map-width", mapWidth);
		}
	}

	const totalCount = querySelector(".locfinder__total", root);
	const announceCount = parseBool(
		window.locfinderConfig?.announceCount ?? true
	);

	if (totalCount && !announceCount) {
		totalCount.setAttribute("aria-live", "off");
	}

	const focusStyle = String(window.locfinderConfig?.focusStyle ?? "browser");
	root.classList.toggle("locfinder--focus-high", focusStyle === "high");

	const reducedMotion = parseBool(
		window.locfinderConfig?.reducedMotion ?? false
	);
	root.classList.toggle("locfinder--reduced-motion", reducedMotion);

	const addressInput = querySelector('[name="filter_address"]', form);
	const addressPlaceholder = addressInput?.placeholder ?? "";
	const addressWarningText =
		window.locfinderConfig?.addressWarningText ??
		"Please select an address from the dropdown";
	const latInput = querySelector('[name="filter_lat"]', form);
	const lngInput = querySelector('[name="filter_lng"]', form);

	let pagination = querySelector(".locfinder-pagination", root);

	if (!pagination) {
		pagination = document.createElement("div");
		pagination.className = "locfinder-pagination";
		pagination.setAttribute("role", "navigation");
		root.appendChild(pagination);
	}

	const ajaxUrl = window.locfinderConfig?.ajaxUrl;

	if (!ajaxUrl) {
		console.warn(
			"Locfinder: ajaxUrl not defined in window.locfinderConfig for instance.",
			instanceConfig.instanceId
		);
		return;
	}

	ensureElementId(root, instanceConfig.instanceId);

	const unit = window.locfinderConfig?.distanceUnit ?? "mi";
	const resultLinkTarget = window.locfinderConfig?.resultLinkTarget ?? "same";
	const noResultsMessage =
		window.locfinderConfig?.noResultsMessage ?? "No results found.";

	const noResultsHtml = `<li class="locfinder-result__fallback-text" role="listitem">${escapeHtml(noResultsMessage)}</li>`;

	// Instance state.
	let mapApi = null;
	let activeCardPostId = null;
	let requestSequence = 0;
	let pendingMapPayload = null;
	let pendingMapFormData = null;

	// ─────────────────────────────────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Sets the latitude and longitude hidden inputs.
	 *
	 * @param   {number|null} lat - Latitude.
	 * @param   {number|null} lng - Longitude.
	 * @returns {void}
	 */
	function setLatLng(lat, lng) {
		if (!latInput || !lngInput) {
			return;
		}

		latInput.value = Number.isFinite(lat) ? String(lat) : "";
		lngInput.value = Number.isFinite(lng) ? String(lng) : "";
	}

	/**
	 * Clears the latitude and longitude hidden input values.
	 *
	 * @returns {void}
	 */
	function clearLatLng() {
		setLatLng(null, null);
	}

	/**
	 * Renders the current result count, or the configured no-results message
	 * when the search returned zero matches.
	 *
	 * @param   {number} total - Total results.
	 * @returns {void}
	 */
	function renderTotalCount(total) {
		if (!totalCount) {
			return;
		}

		if (typeof total !== "number" || total <= 0) {
			totalCount.textContent =
				window.locfinderConfig?.noResultsMessage || "No results found.";
			return;
		}

		const template =
			total === 1
				? window.locfinderConfig?.resultsCountSingular ||
					"%d result returned"
				: window.locfinderConfig?.resultsCountPlural ||
					"%d results returned";

		totalCount.textContent = template.replace("%d", String(total));
	}

	/**
	 * Updates pagination controls for the loading state.
	 *
	 * Loading temporarily disables every control. Restoring the controls
	 * preserves their normal boundary-disabled state.
	 *
	 * @param   {boolean} disabled - Whether loading is active.
	 * @returns {void}
	 */
	function setPagerDisabled(disabled) {
		querySelectorAll("[data-page]", pagination).forEach((btn) => {
			const isDisabled = disabled
				? true
				: btn.dataset.boundaryDisabled === "true";

			btn.setAttribute("aria-disabled", isDisabled ? "true" : "false");
			btn.disabled = isDisabled;
		});
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Result card active state
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Activates the result card for a post ID.
	 *
	 * Scrolling is optional because marker clicks already provide feedback
	 * within the map.
	 *
	 * @param   {number}  postId        - Location post ID.
	 * @param   {Object}  [options]     - Activation options.
	 * @param   {boolean} [options.scroll] - Whether to scroll the card into view.
	 * @returns {void}
	 */
	function activateResultCard(postId, { scroll = true } = {}) {
		if (activeCardPostId !== null) {
			querySelector(
				`[data-post-id="${activeCardPostId}"]`,
				results
			)?.classList.remove("locfinder-result--active");
		}

		const card = querySelector(`[data-post-id="${postId}"]`, results);

		if (!card) {
			return;
		}

		card.classList.add("locfinder-result--active");
		activeCardPostId = postId;

		if (scroll) {
			card.scrollIntoView({
				behavior: "smooth",
				block: "nearest",
			});
		}
	}

	/**
	 * Selects a result and synchronizes its marker.
	 *
	 * @param   {number} postId - Location post ID.
	 * @returns {void}
	 */
	function selectResult(postId) {
		activateResultCard(postId);
		mapApi?.activateMarkerByPostId?.(postId);
		mapElement?.scrollIntoView({ behavior: "smooth", block: "nearest" });
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Pagination
	// ─────────────────────────────────────────────────────────────────────────

	const PAGINATION_WINDOW = 3;

	/**
	 * Renders pagination buttons into the pagination container.
	 *
	 * @param   {number} totalPages   - Total number of pages.
	 * @param   {number} currentPage  - Currently active page.
	 * @returns {void}
	 */
	function renderPagination(totalPages, currentPage) {
		pagination.innerHTML = "";

		if (typeof totalPages !== "number" || totalPages <= 1) {
			return;
		}

		/**
		 * Creates and appends a pagination button.
		 *
		 * @param   {Object}  options
		 * @param   {string}  options.label       - Button label.
		 * @param   {number}  options.page        - Target page.
		 * @param   {boolean} [options.disabled]  - Whether the button is disabled.
		 * @param   {boolean} [options.isCurrent] - Whether this is the current page.
		 * @param   {boolean} [options.isNav]     - Whether this is a Prev/Next control.
		 * @returns {void}
		 */
		const addBtn = ({
			label,
			page,
			disabled = false,
			isCurrent = false,
			isNav = false,
		}) => {
			const btn = document.createElement("button");
			const isBoundaryDisabled = Boolean(disabled) || isCurrent;

			btn.type = "button";
			btn.textContent = label;
			btn.disabled = isBoundaryDisabled;
			btn.setAttribute("data-page", String(page));

			if (!isNav) {
				btn.setAttribute(
					"aria-label",
					(
						window.locfinderConfig?.paginationGoToPageLabel ||
						"Go to page %d"
					).replace("%d", String(page))
				);
			}

			// Preserve the normal disabled state while loading temporarily overrides it.
			btn.dataset.boundaryDisabled = isBoundaryDisabled
				? "true"
				: "false";
			btn.setAttribute("aria-disabled", btn.disabled ? "true" : "false");

			if (isCurrent) {
				btn.setAttribute("aria-current", "page");
			}

			if (!btn.disabled) {
				btn.addEventListener("click", () => {
					const formData = new FormData(form);

					formData.set("paged", String(page));
					fetchResults(formData, { scrollBehavior: "auto" });
				});
			}

			pagination.appendChild(btn);
		};

		/**
		 * Appends a non-interactive pagination ellipsis.
		 *
		 * @returns {void}
		 */
		const addEllipsis = () => {
			const span = document.createElement("span");
			span.className = "locfinder-pagination__ellipsis";
			span.setAttribute("aria-hidden", "true");
			span.textContent = "…";

			pagination.appendChild(span);
		};

		const prevLabel = window.locfinderConfig?.paginationPrevLabel || "Prev";
		const nextLabel = window.locfinderConfig?.paginationNextLabel || "Next";

		if (currentPage > 1) {
			addBtn({
				label: prevLabel,
				page: currentPage - 1,
				isNav: true,
			});
		}

		// Always show page 1 and the last page, and a window of pages around the current page.
		const pagesToShow = [];

		for (let i = 1; i <= totalPages; i++) {
			if (
				i === 1 ||
				i === totalPages ||
				(i >= currentPage - PAGINATION_WINDOW &&
					i <= currentPage + PAGINATION_WINDOW)
			) {
				pagesToShow.push(i);
			}
		}

		let previousPage = null;

		for (const page of pagesToShow) {
			if (previousPage !== null && page - previousPage > 1) {
				addEllipsis();
			}

			addBtn({
				label: String(page),
				page,
				isCurrent: page === currentPage,
			});

			previousPage = page;
		}

		if (currentPage < totalPages) {
			addBtn({
				label: nextLabel,
				page: currentPage + 1,
				isNav: true,
			});
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// AJAX
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Applies instance defaults to a search request.
	 *
	 * @param   {FormData} formData - Request data.
	 * @returns {void}
	 */
	function applyRequestDefaults(formData) {
		formData.set("action", "locfinder_get_results");
		formData.set("grid_cols", String(instanceConfig.gridCols));
		formData.set("order", String(instanceConfig.order));
		formData.set("orderby", String(instanceConfig.orderby));
		formData.set("pin_color", String(instanceConfig.pinColor));
		formData.set("posts_per_page", String(instanceConfig.postsPerPage));
		formData.set("show_excerpt", instanceConfig.showExcerpt ? "1" : "0");
		formData.set("show_address", instanceConfig.showAddress ? "1" : "0");
		formData.set(
			"show_directions",
			instanceConfig.showDirections ? "1" : "0"
		);
		formData.set("show_phone", instanceConfig.showPhone ? "1" : "0");
		formData.set("show_email", instanceConfig.showEmail ? "1" : "0");
		formData.set("show_website", instanceConfig.showWebsite ? "1" : "0");
		formData.set("show_hours", instanceConfig.showHours ? "1" : "0");
		formData.set(
			"show_categories",
			instanceConfig.showCategories ? "1" : "0"
		);
		formData.set(
			"show_open_badge",
			instanceConfig.showOpenBadge ? "1" : "0"
		);
		formData.set("taxonomy", String(instanceConfig.taxonomy));

		if (!formData.has("paged")) {
			formData.set("paged", "1");
		}

		if (!formData.has("filter_lat")) {
			formData.set("filter_lat", latInput?.value || "");
		}

		if (!formData.has("filter_lng")) {
			formData.set("filter_lng", lngInput?.value || "");
		}
	}

	/**
	 * Builds the keyboard-accessible "Show on map" control.
	 *
	 * The result card itself handles pointer selection, while this control
	 * provides an explicit keyboard path to the corresponding marker.
	 *
	 * @param   {string} postTitle - Location title.
	 * @returns {string}           Button markup.
	 */
	function getShowOnMapMarkup(postTitle) {
		const label = window.locfinderConfig?.showOnMapLabel || "Show on map";
		const ariaTemplate =
			window.locfinderConfig?.showOnMapAriaLabel || "Show %s on map";
		const ariaLabel = ariaTemplate.replace("%s", postTitle ?? "");

		return `<button type="button" class="locfinder-result__locate" aria-label="${escapeHtml(ariaLabel)}">${escapeHtml(label)}</button>`;
	}

	/**
	 * Renders location results.
	 *
	 * @param   {Array} posts - Location results.
	 * @returns {void}
	 */
	function renderResults(posts) {
		activeCardPostId = null;
		results.innerHTML = "";

		if (!Array.isArray(posts) || posts.length === 0) {
			results.innerHTML = noResultsHtml;
			return;
		}

		// Apply this instance's resolved results layout.
		results.classList.toggle(
			"locfinder__results--list",
			instanceConfig.resultsLayout === "list"
		);

		// Side layouts use their dedicated results-column setting.
		const gridCols =
			instanceConfig.resultsPosition === "bottom"
				? Number(posts[0]?.gridCols ?? instanceConfig.gridCols ?? 3)
				: instanceConfig.resultsColumns;

		results.style.setProperty("--grid-cols", String(gridCols));

		const itemTemplate = window.locfinderConfig?.resultItemTemplate ?? "";

		posts.forEach((post) => {
			const item = document.createElement("li");
			item.className = `locfinder-result ${post.taxClasses || ""}`.trim();
			item.dataset.postId = String(post.id);

			item.setAttribute("role", "listitem");

			if (itemTemplate) {
				const templateWithBreaks = itemTemplate.replace(
					/\r?\n/g,
					"<br>"
				);

				const icons = window.locfinderConfig?.icons ?? {};

				const addressHtml = post.address
					? `${icons.address ?? ""}${escapeHtml(post.address)}`
					: "";

				// Use a tel: link when a dialable number is available.
				const phoneHtml = post.phone
					? `${icons.phone ?? ""}${
							post.phoneTel
								? `<a href="${escapeUrl(`tel:${post.phoneTel}`)}">${escapeHtml(post.phone)}</a>`
								: escapeHtml(post.phone)
						}`
					: "";

				const emailLabel =
					window.locfinderConfig?.sendEmailLabel || "Send Email";
				const websiteLabel =
					window.locfinderConfig?.visitWebsiteLabel ||
					"Visit Website";

				const emailHtml = post.email
					? `${icons.email ?? ""}<a href="${escapeUrl(`mailto:${post.email}`)}">${escapeHtml(emailLabel)}</a>`
					: "";

				const websiteHtml = post.website
					? `${icons.website ?? ""}<a href="${escapeUrl(post.website)}" target="_blank" rel="noopener">${escapeHtml(websiteLabel)}</a>`
					: "";

				let html = templateWithBreaks
					.replace(/\{title\}/g, () =>
						escapeHtml(post.postTitle ?? "")
					)
					.replace(/\{address\}/g, () => addressHtml)
					.replace(/\{phone\}/g, () => phoneHtml)
					.replace(/\{email\}/g, () => emailHtml)
					.replace(/\{website\}/g, () => websiteHtml)
					.replace(/\{hours\}/g, () => post.hoursHtml ?? "")
					.replace(/\{distance\}/g, () =>
						typeof post.distance === "number"
							? formatDistance(post.distance, unit)
							: ""
					)
					.replace(/\{link\}/g, () =>
						escapeUrl(post.permalink ?? "")
					);

				html = html
					.replace(/(?:\s*<br\s*\/?>\s*){2,}/gi, "<br>")
					.replace(/^(\s*<br\s*\/?>\s*)+/i, "")
					.replace(/(\s*<br\s*\/?>\s*)+$/i, "");

				item.innerHTML = html;

				// Always provide a keyboard-accessible path to the marker.
				item.insertAdjacentHTML(
					"beforeend",
					getShowOnMapMarkup(post.postTitle)
				);
			} else {
				// Render the standard result card.
				const thumbnailHtml = instanceConfig.showImage
					? post.thumbnailUrl
						? `<div class="locfinder-result__thumbnail"><img src="${escapeUrl(post.thumbnailUrl)}" alt="" loading="lazy" /></div>`
						: `<div class="locfinder-result__thumbnail locfinder-result__thumbnail--empty" aria-hidden="true"></div>`
					: "";

				item.innerHTML = `
				${thumbnailHtml}
				<div class="locfinder-result__body">
					<div class="locfinder-result__title-row">
						${post.postTitle ? `<h3 class="locfinder-result__title"><a href="${escapeUrl(post.permalink)}"${resultLinkTarget === "new" ? ' target="_blank" rel="noopener"' : ""}>${escapeHtml(post.postTitle)}${resultLinkTarget === "new" ? '<span class="screen-reader-text"> (opens in a new tab)</span>' : ""}</a></h3>` : ""}
						${post.openBadgeHtml || ""}
					</div>
					${typeof post.distance === "number" ? `<div class="locfinder-result__distance">${formatDistance(post.distance, unit)}</div>` : ""}
					${post.locationInfo}
					${instanceConfig.showExcerpt && post.excerpt ? `<div class="locfinder-result__excerpt">${escapeHtml(post.excerpt)}</div>` : ""}
					${getShowOnMapMarkup(post.postTitle)}
				</div>
		`;
			}

			const locateButton = item.querySelector(
				".locfinder-result__locate"
			);

			if (locateButton) {
				locateButton.addEventListener("click", (e) => {
					e.stopPropagation();
					selectResult(post.id);
				});
			}

			item.addEventListener("click", (e) => {
				if (e.target.closest("a, button")) {
					return;
				}
				selectResult(post.id);
			});

			results.appendChild(item);
		});
	}

	/**
	 * Scrolls to the results container.
	 *
	 * @param   {ScrollBehavior} [behavior] - Scroll behavior. Defaults to "smooth".
	 * @returns {void}
	 */
	function scrollToResults(behavior = "smooth") {
		const top = results.getBoundingClientRect().top + window.scrollY - 80;

		window.scrollTo({
			top,
			behavior,
		});
	}

	/**
	 * Fits the map to the searched address and returned result markers.
	 *
	 * Used for searches without a radius, where results may extend well beyond
	 * the searched address. Falls back to the standard address zoom when no
	 * valid result markers are available.
	 *
	 * @param   {number}   lat       - Searched latitude.
	 * @param   {number}   lng       - Searched longitude.
	 * @param   {Object[]} locations - Result locations.
	 * @returns {void}
	 */
	function fitMapToAddressAndResults(lat, lng, locations) {
		const bounds = new google.maps.LatLngBounds();
		bounds.extend({ lat, lng });

		let pinCount = 0;

		if (Array.isArray(locations)) {
			locations.forEach((location) => {
				const pinLat =
					typeof location.lat === "number"
						? location.lat
						: parseFloat(location.lat);
				const pinLng =
					typeof location.lng === "number"
						? location.lng
						: parseFloat(location.lng);

				if (
					Number.isFinite(pinLat) &&
					Number.isFinite(pinLng) &&
					pinLat >= -90 &&
					pinLat <= 90 &&
					pinLng >= -180 &&
					pinLng <= 180
				) {
					bounds.extend({ lat: pinLat, lng: pinLng });
					pinCount++;
				}
			});
		}

		if (pinCount === 0) {
			mapApi.map.setCenter({ lat, lng });
			mapApi.map.setZoom(SEARCHED_ADDRESS_ZOOM);
			return;
		}

		mapApi.map.fitBounds(bounds);

		// Prevent fitBounds() from zooming too far into nearly identical points.
		google.maps.event.addListenerOnce(mapApi.map, "bounds_changed", () => {
			if (mapApi.map.getZoom() > SEARCHED_ADDRESS_ZOOM) {
				mapApi.map.setZoom(SEARCHED_ADDRESS_ZOOM);
			}
		});
	}

	/**
	 * Updates the map from a results payload.
	 *
	 * Stores the latest payload until map initialization completes, preventing
	 * results from being lost when the map and initial AJAX request finish in
	 * either order.
	 *
	 * @param   {Object}   payload  - Results payload.
	 * @param   {FormData} formData - Request data.
	 * @returns {void}
	 */
	function updateMapFromPayload(payload, formData) {
		pendingMapPayload = payload;
		pendingMapFormData = formData;

		if (!mapApi) {
			return;
		}

		const locations =
			payload?.mapLocations ?? payload?.map_locations ?? null;

		const radiusRaw = parseFloat(formData.get("filter_radius") || "");
		const latRaw = parseFloat(formData.get("filter_lat") || "");
		const lngRaw = parseFloat(formData.get("filter_lng") || "");

		// Zero is a valid coordinate; distinguish it from an invalid numeric value.
		const radius = Number.isNaN(radiusRaw) ? 0 : radiusRaw;
		const lat = Number.isNaN(latRaw) ? null : latRaw;
		const lng = Number.isNaN(lngRaw) ? null : lngRaw;

		// A searched address controls the viewport; a positive radius also draws
		// and fits the corresponding search circle.
		const hasAddress = lat !== null && lng !== null;
		const hasRadius = hasAddress && radius > 0;

		if (Array.isArray(locations)) {
			mapApi.setPins(locations, {
				skipAutoFit: hasAddress,
			});
		}

		if (hasRadius) {
			// drawRadius() also fits the circle bounds.
			mapApi.drawRadius(lat, lng, radius);
		} else {
			mapApi.clearRadius?.();

			if (hasAddress) {
				// Without a radius, fit the address and all result markers together.
				fitMapToAddressAndResults(lat, lng, locations);
			}
		}
	}

	/**
	 * Fetches results from the server and updates the UI.
	 *
	 * @param   {FormData}       formData              - The FormData object containing search parameters.
	 * @param   {Object}         [opts]                - Options object.
	 * @param   {boolean}        [opts.scroll]         - Whether to scroll to the results after they load. Defaults to true.
	 * @param   {ScrollBehavior} [opts.scrollBehavior] - Scroll behavior when opts.scroll is true. Defaults to "smooth".
	 * @returns {Promise<void>}
	 */
	async function fetchResults(
		formData,
		{ scroll = true, scrollBehavior = "smooth" } = {}
	) {
		const sequence = ++requestSequence;

		toggleSpinner(true);
		setPagerDisabled(true);

		const resultsHeight = results.offsetHeight;

		results.style.minHeight = `${resultsHeight}px`;
		results.classList.add("locfinder-hidden");

		applyRequestDefaults(formData);

		debugLog("AJAX Request", Object.fromEntries(formData.entries()));

		try {
			const res = await fetch(ajaxUrl, {
				method: "POST",
				credentials: "same-origin",
				body: formData,
			});

			if (!res.ok) {
				throw new Error(`Request failed with status ${res.status}`);
			}

			const json = await res.json();

			debugLog("AJAX Response", json);

			if (sequence !== requestSequence) {
				return;
			}

			if (!json?.success) {
				throw new Error(
					json?.data?.message ||
						"Unknown error fetching location results."
				);
			}

			const payload = json.data || {};
			const total = Number(payload?.total ?? 0);
			const paged = Number(payload?.paged ?? 1);
			const totalPages = Number(payload?.totalPages ?? 1);

			renderTotalCount(total);
			renderResults(payload?.posts);
			renderPagination(totalPages, paged);
			updateMapFromPayload(payload, formData);

			setTimeout(() => {
				results.classList.remove("locfinder-hidden");
				results.style.minHeight = "";

				if (scroll) {
					scrollToResults(scrollBehavior);
				}
			}, 100);
		} catch (err) {
			if (sequence !== requestSequence) {
				return;
			}

			console.error("Locfinder: error fetching results:", err);
			renderTotalCount(0);
			results.innerHTML = noResultsHtml;
			results.classList.remove("locfinder-hidden");
			results.style.minHeight = "";
		} finally {
			if (sequence === requestSequence) {
				toggleSpinner(false);
				setPagerDisabled(false);
			}
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Pin popup details
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Fetches detail fields for a marker info window.
	 *
	 * @param   {number} postId - Location post ID.
	 * @returns {Promise<Object>} Location detail data.
	 */
	async function fetchLocationDetails(postId) {
		const formData = new FormData();

		formData.set("action", "locfinder_get_location_details");
		formData.set("post_id", String(postId));
		formData.set("show_address", instanceConfig.showAddress ? "1" : "0");
		formData.set(
			"show_directions",
			instanceConfig.showDirections ? "1" : "0"
		);
		formData.set("show_phone", instanceConfig.showPhone ? "1" : "0");
		formData.set("show_website", instanceConfig.showWebsite ? "1" : "0");
		formData.set(
			"show_open_badge",
			instanceConfig.showOpenBadge ? "1" : "0"
		);

		const res = await fetch(ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			body: formData,
		});

		if (!res.ok) {
			throw new Error(`Request failed with status ${res.status}`);
		}

		const json = await res.json();

		if (!json?.success) {
			throw new Error(
				json?.data?.message ||
					"Unknown error fetching location details."
			);
		}

		return json.data || {};
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Map
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Renders a button that defers map initialization until clicked.
	 *
	 * Used when loadMapOnClick is enabled in Accessibility settings, so the
	 * Google Maps script and its network requests are not loaded until the
	 * user explicitly opts in. Useful for GDPR compliance and page performance
	 * on map-heavy pages. Removed as soon as it is clicked, replaced by the
	 * real map via initMaps().
	 *
	 * @returns {void}
	 */
	function renderLoadMapButton() {
		if (!mapElement) {
			return;
		}

		const label = window.locfinderConfig?.loadMapButtonLabel || "Load Map";
		const button = document.createElement("button");

		button.type = "button";
		button.className = "locfinder__load-map-button";
		button.textContent = label;

		mapElement.classList.add("locfinder__map--pending");

		button.addEventListener(
			"click",
			() => {
				mapElement.classList.remove("locfinder__map--pending");
				button.remove();
				initMaps();
			},
			{
				once: true,
			}
		);
		mapElement.appendChild(button);
	}

	/**
	 * Initializes Google Maps and Places for this instance.
	 *
	 * @returns {Promise<void>}
	 */
	async function initMaps() {
		if (!mapElement) {
			return;
		}

		const config = window.locfinderConfig || {};
		const apiKey = config.googleMapsApiKey || "";
		const mapId = config.mapId || "";
		const language = config.language || "en";
		const region = config.region || "US";

		if (!apiKey) {
			return;
		}

		try {
			mapApi = await initLocfinderAddressMap({
				apiKey,
				mapId,
				language,
				region,
				root,
				showPreviewPin: false,
				panOnSelect: false,
				addressSelector: '[name="filter_address"]',
				mapSelector: ".locfinder__map",
				latSelector: '[name="filter_lat"]',
				lngSelector: '[name="filter_lng"]',
				pinColor: instanceConfig.pinColor,
				pinIconUrl: config.pinIconUrl || "",
				termPinStyles:
					Object.keys(instanceConfig.termPinStyles).length > 0
						? instanceConfig.termPinStyles
						: config.termPinStyles || {},
				unit,
				defaultCenter: {
					lat: Number(config.defaultLat ?? 39.8283),
					lng: Number(config.defaultLng ?? -98.5795),
				},
				defaultZoom: Number(config.defaultZoom ?? 11),
				mapHeight: config.mapHeight || "",
				minMapHeight: config.minMapHeight || "300px",
				autoFit: parseBool(config.autoFit ?? true),
				clustering: parseBool(config.clustering ?? true),
				clusterMaxZoom: Number(config.clusterMaxZoom ?? 14),
				zoomControl: parseBool(config.zoomControl ?? false),
				fullscreenControl: parseBool(config.fullscreenControl ?? false),
				streetViewControl: parseBool(config.streetViewControl ?? false),
				mapTypeControl: parseBool(config.mapTypeControl ?? false),
				highContrast: parseBool(config.highContrastMode ?? false),
				mapStyles: Array.isArray(config.mapStyles)
					? config.mapStyles
					: [],
				viewDetailsLabel: config.viewDetailsLabel || "View details",
				loadingLabel: config.loadingDetailsMessage || "Loading…",
				resultLinkTarget,
				onMarkerClick: (postId) =>
					activateResultCard(postId, { scroll: false }),
				fetchLocationDetails,
			});

			// Apply results that arrived before map initialization completed.
			if (pendingMapPayload) {
				updateMapFromPayload(pendingMapPayload, pendingMapFormData);
			}
		} catch (err) {
			console.warn(
				"Locfinder: map initialization failed, continuing without map:",
				err
			);
			mapApi = null;
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Address input handlers
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Shows an invalid state on the address input.
	 *
	 * @returns {void}
	 */
	function showAddressWarning() {
		if (!addressInput) {
			return;
		}

		addressInput.classList.add("locfinder-form__field--invalid");
		addressInput.placeholder = addressWarningText;
	}

	/**
	 * Clears the invalid state from the address input.
	 *
	 * @returns {void}
	 */
	function clearAddressWarning() {
		if (!addressInput) {
			return;
		}

		addressInput.classList.remove("locfinder-form__field--invalid");
		addressInput.placeholder = addressPlaceholder;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Form submission
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Handles search form submission.
	 *
	 * An entered address without resolved coordinates is flagged before the
	 * request to prevent an invalid radius search.
	 *
	 * @param   {Event} event - Submit event.
	 * @returns {void}
	 */
	function handleSubmit(event) {
		event.preventDefault();

		const address = addressInput?.value.trim() || "";
		const latVal = parseFloat(latInput?.value || "");
		const lngVal = parseFloat(lngInput?.value || "");
		const hasLatLng = !Number.isNaN(latVal) && !Number.isNaN(lngVal);
		const formData = new FormData(form);

		if (address && !hasLatLng) {
			showAddressWarning();
			formData.set("filter_lat", "");
			formData.set("filter_lng", "");
		}

		formData.set("paged", "1");

		// Search submissions keep the current viewport; pagination handles scrolling.
		fetchResults(formData, { scroll: false });
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Event listeners
	// ─────────────────────────────────────────────────────────────────────────

	form.addEventListener("submit", handleSubmit);

	if (addressInput) {
		addressInput.addEventListener(
			"input",
			debounce(() => {
				clearLatLng();

				if (addressInput.value.trim() === "") {
					clearAddressWarning();
				}
			}, 300)
		);

		addressInput.addEventListener("focus", clearAddressWarning);

		const geolocateButton = querySelector(
			".locfinder-form__geolocate",
			form
		);
		if (geolocateButton && navigator.geolocation) {
			geolocateButton.addEventListener("click", async () => {
				geolocateButton.disabled = true;
				geolocateButton.classList.add(
					"locfinder-form__geolocate--loading"
				);

				try {
					const position = await new Promise((resolve, reject) => {
						const GEOLOCATION_TIMEOUT_MS = 10000; // 10 seconds
						const GEOLOCATION_MAX_AGE_MS = 60000; // 1 minute

						navigator.geolocation.getCurrentPosition(
							resolve,
							reject,
							{
								timeout: GEOLOCATION_TIMEOUT_MS,
								maximumAge: GEOLOCATION_MAX_AGE_MS,
							}
						);
					});

					const { latitude: lat, longitude: lng } = position.coords;

					setLatLng(lat, lng);
					clearAddressWarning();

					if (typeof window.google?.maps?.Geocoder === "function") {
						const geocoder = new google.maps.Geocoder();
						const { results: geoResults } = await geocoder.geocode({
							location: {
								lat,
								lng,
							},
						});
						if (geoResults?.[0] && addressInput) {
							addressInput.value =
								geoResults[0].formatted_address;
						}
					}
				} catch (err) {
					console.warn("Locfinder: geolocation failed:", err);
				} finally {
					geolocateButton.disabled = false;
					geolocateButton.classList.remove(
						"locfinder-form__geolocate--loading"
					);
				}
			});
		} else if (geolocateButton) {
			geolocateButton.hidden = true;
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Boot
	// ─────────────────────────────────────────────────────────────────────────
	const loadMapOnClick = parseBool(
		window.locfinderConfig?.loadMapOnClick ?? false
	);

	if (loadMapOnClick) {
		renderLoadMapButton();
	} else {
		initMaps();
	}

	const initialData = new FormData(form);
	initialData.set("filter_lat", latInput?.value || "");
	initialData.set("filter_lng", lngInput?.value || "");
	initialData.set("paged", "1");

	// Initial results should not move the visitor's viewport.
	fetchResults(initialData, { scroll: false });
}
