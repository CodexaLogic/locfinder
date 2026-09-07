/**
 * Handles the Clear Cache button on the Tools settings page.
 *
 * Uses window.locfinderConfig for the AJAX URL and
 * window.locfinderClearCache for translated strings.
 */

import { setStatusMessage } from "@common/utils.js";

const btn = document.getElementById("locfinder-clear-cache-btn");
const msgEl = document.getElementById("locfinder-clear-cache-message");

if (btn) {
	btn.addEventListener("click", handleClearCache);
}

/**
 * Handles the Clear Cache request.
 *
 * Disables the button while the request is running and displays the
 * resulting success or error message.
 *
 * @returns {Promise<void>}
 */
async function handleClearCache() {
	const { action, nonce } = btn.dataset;

	if (!action || !nonce) {
		return;
	}

	btn.disabled = true;
	setStatusMessage(
		msgEl,
		window.locfinderClearCache.translations.clearing,
		false
	);

	const body = new FormData();

	body.append("action", action);
	body.append("nonce", nonce);

	try {
		const res = await fetch(window.locfinderConfig.ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			body,
		});

		if (!res.ok) {
			throw new Error(`Request failed with status ${res.status}`);
		}

		const data = await res.json();

		if (data.success) {
			setStatusMessage(
				msgEl,
				data.data?.message ||
					window.locfinderClearCache.translations.success,
				false
			);
		} else {
			setStatusMessage(
				msgEl,
				data.data?.message ||
					window.locfinderClearCache.translations.error,
				true
			);
		}
	} catch (err) {
		console.error("Locfinder: clear cache request failed:", err);
		setStatusMessage(
			msgEl,
			window.locfinderClearCache.translations.requestFailed,
			true
		);
	} finally {
		btn.disabled = false;
	}
}
