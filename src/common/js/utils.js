/**
 * Shared utility functions for public and admin scripts.
 */

/**
 * Delays a function until calls have stopped for the specified interval.
 *
 * @param   {Function} fn    - Function to debounce.
 * @param   {number}   delay - Delay in milliseconds.
 * @returns {Function}       Debounced function.
 */
export function debounce(fn, delay) {
	let timer;

	return function (...args) {
		clearTimeout(timer);
		timer = setTimeout(() => fn.apply(this, args), delay);
	};
}

/**
 * Formats a numeric distance with its unit.
 *
 * @param   {number} distance - Distance value.
 * @param   {string} unit     - Distance unit: 'mi' or 'km'.
 * @returns {string}          Formatted distance, or an empty string if invalid.
 */
export function formatDistance(distance, unit = "mi") {
	if (!Number.isFinite(distance)) {
		return "";
	}

	const rounded = Math.round(distance * 10) / 10;
	const label = unit === "km" ? "km" : "mi";

	return `${rounded} ${label}`;
}

/**
 * Escapes HTML special characters in a string.
 *
 * Suitable for HTML text and quoted non-URL attribute values.
 *
 * @param   {string} str - String to escape.
 * @returns {string}     Escaped string.
 */
export function escapeHtml(str) {
	return String(str).replace(
		/[&<>"']/g,
		(char) =>
			({
				"&": "&amp;",
				"<": "&lt;",
				">": "&gt;",
				'"': "&quot;",
				"'": "&#39;",
			})[char]
	);
}

/**
 * Validates and escapes a URL for use in an href or src attribute.
 *
 * Rejects explicitly specified protocols outside the allowed list. This is
 * client-side defense in depth and does not replace server-side sanitization.
 *
 * @param   {string} url - URL to validate and escape.
 * @returns {string}     Escaped URL, or an empty string if invalid.
 */
export function escapeUrl(url) {
	const value = String(url ?? "").trim();

	if (value === "") {
		return "";
	}

	const allowedProtocols = ["http:", "https:", "mailto:", "tel:"];

	// Only URLs with an explicit protocol need validation.
	if (/^[a-z][a-z0-9+.-]*:/i.test(value)) {
		try {
			const parsed = new URL(value);

			if (!allowedProtocols.includes(parsed.protocol)) {
				return "";
			}
		} catch {
			return "";
		}
	}

	return escapeHtml(value);
}

/**
 * Normalizes common boolean representations to a boolean.
 *
 * @param   {*} value - Value to normalize.
 * @returns {boolean} Normalized boolean.
 */
export function parseBool(value) {
	if (typeof value === "boolean") {
		return value;
	}

	return ["1", "true", "yes", "on"].includes(
		String(value).trim().toLowerCase()
	);
}

/**
 * Generates a random ID with an optional prefix.
 *
 * @param   {string} prefix - ID prefix.
 * @returns {string}        Generated ID.
 */
export function generateId(prefix = "locfinder") {
	return `${prefix}-${Math.random().toString(16).slice(2)}`;
}

/**
 * Ensures an element has an ID.
 *
 * Uses a sanitized instance ID when provided, otherwise generates one.
 * Existing IDs are preserved.
 *
 * @param   {HTMLElement} el           - Element to update.
 * @param   {string}      [instanceId] - Preferred ID suffix.
 * @param   {string}      [prefix]     - ID prefix.
 * @returns {string}                   Element ID.
 */
export function ensureElementId(el, instanceId = "", prefix = "locfinder") {
	if (el.id) {
		return el.id;
	}

	const safe = instanceId
		? String(instanceId).replace(/[^a-zA-Z0-9_-]/g, "")
		: "";

	el.id = safe ? `${prefix}-${safe}` : generateId(prefix);

	return el.id;
}

/**
 * Finds an element within a root element.
 *
 * @param   {string} selector               - CSS selector.
 * @param   {HTMLElement|Document} root     - Search root.
 * @returns {HTMLElement|null}              Matching element, or null.
 */
export function querySelector(selector, root = document) {
	return root.querySelector(selector);
}

/**
 * Finds all matching elements within a root element.
 *
 * @param   {string} selector           - CSS selector.
 * @param   {HTMLElement|Document} root - Search root.
 * @returns {HTMLElement[]}             Matching elements.
 */
export function querySelectorAll(selector, root = document) {
	return Array.from(root.querySelectorAll(selector));
}

/**
 * Updates an admin status message and its success or error state.
 *
 * @param   {HTMLElement|null} el      - Message element.
 * @param   {string}           text    - Message text.
 * @param   {boolean}          isError - Whether the message represents an error.
 * @returns {void}
 */
export function setStatusMessage(el, text, isError) {
	if (!el) {
		return;
	}

	el.textContent = text;
	el.classList.add("locfinder-admin__message");
	el.classList.toggle("locfinder-admin__message--error", isError);
	el.classList.toggle("locfinder-admin__message--success", !isError);
}
