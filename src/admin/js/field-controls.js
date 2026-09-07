/**
 * Shared admin field controls.
 *
 * Adds color input syncing and WordPress media-library interactions to controls
 * rendered by FieldRenderer. Uses event delegation to support multiple controls
 * on the same settings page.
 */

import { escapeHtml, escapeUrl } from "@common/utils.js";

/**
 * Keeps paired color and hex inputs in sync.
 *
 * @returns {void}
 */
function initColorFields() {
	document.addEventListener("input", (e) => {
		const swatch = e.target.closest(".locfinder-color-swatch");

		if (swatch) {
			const field = swatch.closest(".locfinder-color-field");
			const text = field?.querySelector(".locfinder-color-text");

			if (text) {
				text.value = swatch.value;
			}
			return;
		}

		const text = e.target.closest(".locfinder-color-text");

		if (text) {
			const field = text.closest(".locfinder-color-field");
			const swatchInput = field?.querySelector(".locfinder-color-swatch");
			const value = text.value.trim();

			if (swatchInput && /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value)) {
				swatchInput.value = value;
			}
		}
	});
}

/**
 * Gets localized media-control strings.
 *
 * @returns {{frameTitle: string, frameButton: string, noIconSet: string}}
 */
function getMediaFieldStrings() {
	const strings = window.locfinderConfig?.mediaField || {};

	return {
		frameTitle: strings.frameTitle || "Select Icon",
		frameButton: strings.frameButton || "Use this icon",
		noIconSet: strings.noIconSet || "No icon set.",
	};
}

/**
 * Wires media controls to the WordPress media library.
 *
 * @returns {void}
 */
function initMediaFields() {
	document.addEventListener("click", (e) => {
		const uploadBtn = e.target.closest(".locfinder-media-upload");

		if (uploadBtn) {
			e.preventDefault();

			if (typeof wp === "undefined" || !wp.media) {
				console.warn(
					"Locfinder: wp.media is not available on this page."
				);
				return;
			}

			const field = uploadBtn.closest(".locfinder-media-field");
			const idInput = field?.querySelector(".locfinder-media-id");
			const urlInput = field?.querySelector(".locfinder-media-url");
			const preview = field?.querySelector(".locfinder-media-preview");

			const strings = getMediaFieldStrings();

			const frame = wp.media({
				title: strings.frameTitle,
				button: { text: strings.frameButton },
				multiple: false,
				library: { type: "image" },
			});

			frame.on("select", () => {
				const attachment = frame
					.state()
					.get("selection")
					.first()
					.toJSON();

				if (idInput) {
					idInput.value = String(attachment.id || "");
				}
				if (urlInput) {
					urlInput.value = String(attachment.url || "");
				}
				if (preview) {
					const safeUrl = escapeUrl(attachment.url);
					preview.innerHTML = safeUrl
						? `<img src="${safeUrl}" alt="" />`
						: `<span class="description">${escapeHtml(strings.noIconSet)}</span>`;
				}
			});

			frame.open();
			return;
		}

		const clearBtn = e.target.closest(".locfinder-media-clear");

		if (clearBtn) {
			e.preventDefault();

			const field = clearBtn.closest(".locfinder-media-field");
			const idInput = field?.querySelector(".locfinder-media-id");
			const urlInput = field?.querySelector(".locfinder-media-url");
			const preview = field?.querySelector(".locfinder-media-preview");

			if (idInput) {
				idInput.value = "";
			}
			if (urlInput) {
				urlInput.value = "";
			}
			if (preview) {
				const { noIconSet } = getMediaFieldStrings();
				preview.innerHTML = `<span class="description">${escapeHtml(noIconSet)}</span>`;
			}
		}
	});
}

/**
 * Initializes shared field controls.
 *
 * @returns {void}
 */
export function initFieldControls() {
	initColorFields();
	initMediaFields();
}
