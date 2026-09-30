/**
 * Address autocomplete support for both Google Places APIs (new and legacy).
 *
 * Primary: Places API (New) via the programmatic AutocompleteSuggestion API,
 * attached to the plugin's existing <input> as a WAI-ARIA 1.2 combobox.
 *
 * Compatibility: google.maps.places.Autocomplete (Places API Legacy) for keys
 * created before March 1, 2025 that don't have Places API (New) enabled.
 * Legacy is unavailable to new Google Cloud customers, so it is only used
 * after the new API has failed for this key. Remove the legacy branch once
 * Google announces the legacy API's shutdown date.
 *
 * Both paths hand their result to the same onSelect callback;
 * parsePlaceResult() reads either result shape.
 *
 * @module places-autocomplete
 */

import { ensurePlaces } from "./google-loader.js";

/** Characters typed before suggestions are requested (limits billable calls). */
const MIN_QUERY_LENGTH = 2;

/** Pause after typing before a request is sent. */
const DEBOUNCE_MS = 250;

/** Fields fetched for the selected place (new API); only what parsePlaceResult() reads. */
const PLACE_FIELDS = [
	"addressComponents",
	"location",
	"viewport",
	"formattedAddress",
	"id",
];

/** Equivalent fields for the legacy widget. */
const LEGACY_FIELDS = [
	"address_components",
	"geometry",
	"formatted_address",
	"place_id",
];

/** Guarantees unique listbox IDs when several inputs exist on one page. */
let instanceCount = 0;

/** Required Places attribution. Google's policy forbids translating or restyling this text. */
const GOOGLE_ATTRIBUTION_TEXT = "Google Maps";

/**
 * Attaches the legacy Places Autocomplete widget to an input.
 *
 * Temporary compatibility path for keys without Places API (New).
 *
 * @param   {HTMLInputElement} input    - The address input.
 * @param   {Object}           places   - The loaded "places" library.
 * @param   {Function}         onSelect - Receives the legacy PlaceResult.
 * @returns {boolean}                   True if attached, false if the legacy class isn't available.
 */
function attachLegacy(input, places, onSelect) {
	const { Autocomplete } = places;

	if (!Autocomplete) {
		return false;
	}

	const legacy = new Autocomplete(input, { fields: LEGACY_FIELDS });

	legacy.addListener("place_changed", () => {
		const place = legacy.getPlace();

		// Pressing Enter on text that matches no suggestion fires place_changed
		// with no geometry. Ignore it, the geocoding fallback resolves typed text.
		if (place?.geometry) {
			onSelect?.(place);
		}
	});

	return true;
}

/**
 * Attaches address suggestions to an existing text input.
 *
 * Uses Places API (New) when available and falls back to the legacy widget
 * for keys that only have the legacy Places API enabled.
 *
 * @param   {HTMLInputElement} input                     - The address input.
 * @param   {Object}           [options]                 - Configuration.
 * @param   {Object}           [options.loaderArgs]      - Google loader settings: { apiKey, language, region }.
 * @param   {Object}           [options.labels]          - Translated strings: { listbox }.
 * @param   {Function}         [options.onSelect]        - Receives a Place (new API) or PlaceResult (legacy).
 * @returns {Promise<{pending: (Promise<void>|null), mode: string, close: Function}>} Controller for the attached input.
 * @throws  {Error} When neither Places API is available.
 */
export async function attachPlacesAutocomplete(
	input,
	{ loaderArgs = {}, labels = {}, onSelect } = {}
) {
	const places = await ensurePlaces(loaderArgs);
	const { AutocompleteSuggestion, AutocompleteSessionToken } = places;

	// Same shape as the new-API return value, so callers never branch on mode.
	const legacyController = { pending: null, close: () => {}, mode: "legacy" };

	if (!AutocompleteSuggestion || !AutocompleteSessionToken) {
		if (!attachLegacy(input, places, onSelect)) {
			throw new Error(
				"Locfinder: no Places autocomplete API is available."
			);
		}

		return legacyController;
	}

	const listboxId = `${input.id || "locfinder-address"}-suggestions-${++instanceCount}`;

	// Create the panel that will contain the listbox and attribution.
	const panel = document.createElement("div");
	panel.className = "locfinder-autocomplete";
	panel.hidden = true;

	const listbox = document.createElement("ul");
	listbox.id = listboxId;
	listbox.className = "locfinder-autocomplete__listbox";
	listbox.setAttribute("role", "listbox");
	listbox.setAttribute("aria-label", labels.listbox || "Address suggestions");

	const attribution = document.createElement("div");
	attribution.className = "locfinder-autocomplete__attribution";
	attribution.setAttribute("translate", "no");
	attribution.textContent = GOOGLE_ATTRIBUTION_TEXT;

	panel.append(listbox, attribution);

	// Anchor the panel to the input's parent element to ensure correct positioning.
	const anchor = input.parentElement;

	if (anchor && getComputedStyle(anchor).position === "static") {
		anchor.style.position = "relative";
	}

	input.insertAdjacentElement("afterend", panel);

	const comboboxAttrs = {
		role: "combobox",
		"aria-autocomplete": "list",
		"aria-expanded": "false",
		"aria-controls": listboxId,
	};

	Object.entries(comboboxAttrs).forEach(([name, value]) =>
		input.setAttribute(name, value)
	);

	// Prevent the browser's own autofill dropdown from stacking on ours.
	input.setAttribute("autocomplete", "off");

	// One signal removes every combobox listener if we fall back to legacy.
	const listeners = new AbortController();
	const { signal } = listeners;

	let sessionToken = new AutocompleteSessionToken();
	let predictions = [];
	let activeIndex = -1;
	let requestSeq = 0;
	let debounceTimer = null;
	let pending = null;
	let newApiConfirmed = false;
	let mode = "new";

	/**
	 * Aligns the suggestion panel directly under the input.
	 *
	 * @returns {void}
	 */
	function position() {
		panel.style.top = `${input.offsetTop + input.offsetHeight}px`;
		panel.style.left = `${input.offsetLeft}px`;
		panel.style.width = `${input.offsetWidth}px`;
	}

	/**
	 * Hides the suggestion panel and resets the combobox state.
	 *
	 * @returns {void}
	 */
	function close() {
		panel.hidden = true;
		input.setAttribute("aria-expanded", "false");
		input.removeAttribute("aria-activedescendant");
		activeIndex = -1;
	}

	/**
	 * Moves the highlighted option and announces it to assistive technology.
	 *
	 * @param   {number} index - Option index, or -1 for none.
	 * @returns {void}
	 */
	function setActive(index) {
		const options = listbox.children;

		options[activeIndex]?.setAttribute("aria-selected", "false");
		activeIndex = index;

		const option = options[index];

		if (!option) {
			input.removeAttribute("aria-activedescendant");
			return;
		}

		option.setAttribute("aria-selected", "true");
		input.setAttribute("aria-activedescendant", option.id);
		option.scrollIntoView({ block: "nearest" });
	}

	/**
	 * Renders predictions as listbox options and opens the panel.
	 *
	 * Closes the panel instead when there are no predictions.
	 *
	 * @param   {google.maps.places.PlacePrediction[]} items - Predictions to display.
	 * @returns {void}
	 */
	function render(items) {
		predictions = items;
		activeIndex = -1;
		listbox.replaceChildren();

		if (items.length === 0) {
			close();
			return;
		}

		items.forEach((prediction, index) => {
			const option = document.createElement("li");
			option.id = `${listboxId}-${index}`;
			option.className = "locfinder-autocomplete__option";
			option.setAttribute("role", "option");
			option.setAttribute("aria-selected", "false");

			// textContent only: prediction text is third-party data.
			const main = document.createElement("span");
			main.className = "locfinder-autocomplete__main";
			main.textContent =
				prediction.mainText?.text ?? prediction.text?.text ?? "";
			option.append(main);

			const secondary = prediction.secondaryText?.text;

			if (secondary) {
				const sub = document.createElement("span");
				sub.className = "locfinder-autocomplete__secondary";
				sub.textContent = secondary;
				option.append(sub);
			}

			// No abort signal needed: option elements are discarded on every render
			// and on teardown, and their listeners are garbage-collected with them.
			option.addEventListener("click", () => select(index));
			listbox.append(option);
		});

		position();
		panel.hidden = false;
		input.setAttribute("aria-expanded", "true");
	}

	/**
	 * Tears down the combobox and hands the input to the legacy widget.
	 *
	 * Temporary compatibility path; see the module docblock.
	 *
	 * @returns {void}
	 */
	function switchToLegacy() {
		clearTimeout(debounceTimer);
		requestSeq++;
		listeners.abort();
		panel.remove();

		[...Object.keys(comboboxAttrs), "aria-activedescendant"].forEach(
			(name) => input.removeAttribute(name)
		);

		mode = attachLegacy(input, places, onSelect) ? "legacy" : "none";
	}

	/**
	 * Requests suggestions, ignoring responses that arrive out of order.
	 *
	 * Switches to the legacy widget if the first-ever request fails.
	 *
	 * @param   {string}        query - Trimmed text from the address input.
	 * @returns {Promise<void>} Resolves once the request settles: rendered, failed, or discarded as stale.
	 */
	async function fetchSuggestions(query) {
		const seq = ++requestSeq;

		try {
			const { suggestions } =
				await AutocompleteSuggestion.fetchAutocompleteSuggestions({
					input: query,
					sessionToken,
					language: loaderArgs.language,
					region: loaderArgs.region,
				});

			newApiConfirmed = true;

			if (seq === requestSeq) {
				render(
					suggestions.map((s) => s.placePrediction).filter(Boolean)
				);
			}
		} catch (err) {
			if (seq === requestSeq) {
				close();
			}

			// A failure before any success means the key most likely has only the
			// legacy Places API (keys created before March 2025). The first failure is
			// used as the signal because Google's error text is not a stable contract.
			// Failures after a success are treated as transient and do not switch modes.
			if (!newApiConfirmed) {
				console.warn(
					"Locfinder: Places API (New) is unavailable for this key; using the legacy Places Autocomplete. Enable Places API (New) in Google Cloud.",
					err
				);
				switchToLegacy();
				return;
			}

			console.warn("Locfinder: address suggestions request failed.", err);
		}
	}

	/**
	 * Selects a prediction and fetches the fields Locfinder stores.
	 *
	 * Exposes the in-flight request through the controller's `pending` getter.
	 *
	 * @param   {number} index - Index of the prediction to select.
	 * @returns {void}
	 */
	function select(index) {
		const prediction = predictions[index];

		if (!prediction) {
			return;
		}

		close();
		requestSeq++; // Discard any suggestion request still in flight.
		input.value = prediction.text?.text ?? input.value;

		const place = prediction.toPlace();

		pending = place
			.fetchFields({ fields: PLACE_FIELDS })
			.then(() => onSelect?.(place))
			.catch((err) =>
				console.warn(
					"Locfinder: could not load the selected place.",
					err
				)
			)
			.finally(() => {
				pending = null;
				// fetchFields() ends the billing session; start a new one.
				sessionToken = new AutocompleteSessionToken();
			});
	}

	/**
	 * Handles typing in the address field.
	 *
	 * Debounces suggestion requests and closes the panel for short queries.
	 *
	 * @returns {void}
	 */
	function onInput() {
		clearTimeout(debounceTimer);

		const query = input.value.trim();

		if (query.length < MIN_QUERY_LENGTH) {
			requestSeq++;
			close();
			return;
		}

		debounceTimer = setTimeout(() => fetchSuggestions(query), DEBOUNCE_MS);
	}

	/**
	 * Handles keyboard navigation per the WAI-ARIA 1.2 combobox pattern.
	 *
	 * @param   {KeyboardEvent} event - Keydown event from the address input.
	 * @returns {void}
	 */
	function onKeydown(event) {
		if (panel.hidden) {
			// Reopen the last results without a new (billable) request.
			if (event.key === "ArrowDown" && predictions.length > 0) {
				event.preventDefault();
				render(predictions);
			}
			return;
		}

		// eslint-disable-next-line default-case
		switch (event.key) {
			case "ArrowDown":
				event.preventDefault();
				setActive((activeIndex + 1) % predictions.length);
				break;
			case "ArrowUp":
				event.preventDefault();
				setActive(
					activeIndex <= 0 ? predictions.length - 1 : activeIndex - 1
				);
				break;
			case "Enter":
				// Only intercept Enter when an option is highlighted; otherwise
				// let the form submit (and the typed-address fallback run).
				if (activeIndex >= 0) {
					event.preventDefault();
					select(activeIndex);
				}
				break;
			case "Escape":
				event.preventDefault();
				close();
				break;
			case "Tab":
				close();
				break;
		}
	}

	/**
	 * Keeps the open panel aligned with the input when the window resizes.
	 *
	 * @returns {void}
	 */
	function onResize() {
		if (!panel.hidden) {
			position();
		}
	}

	input.addEventListener("input", onInput, { signal });
	input.addEventListener("keydown", onKeydown, { signal });
	input.addEventListener("blur", close, { signal });

	// Keep focus in the input when an option is clicked, so blur doesn't
	// close the list before the click registers.
	panel.addEventListener("mousedown", (event) => event.preventDefault(), {
		signal,
	});
	window.addEventListener("resize", onResize, { signal });

	return {
		/**
		 * Gets the in-flight selection request, if any.
		 *
		 * Lets callers wait for a selection before running a geocoding fallback.
		 *
		 * @returns {Promise<void>|null} The pending request, or null when idle.
		 */
		get pending() {
			return pending;
		},
		/**
		 * Gets the active autocomplete mode for this input.
		 *
		 * @returns {"new"|"legacy"|"none"} Current mode; useful for debugging and QA.
		 */
		get mode() {
			return mode;
		},
		close,
	};
}
