/**
 * Hours field controls for the location editor.
 *
 * Keeps "Closed" and "Open 24 Hours" mutually exclusive and disables the
 * Opens/Closes fields whenever either option is selected.
 *
 * Uses event delegation to support all day rows in the hours table.
 */

const ROW_SELECTOR = ".locfinder-meta__hours-table tbody tr";

/**
 * Gets the controls for an hours row.
 *
 * @param {HTMLTableRowElement} row - Hours row.
 * @returns {{closed: HTMLInputElement|null, is24: HTMLInputElement|null, open: HTMLInputElement|null, close: HTMLInputElement|null}}
 */
function getRowControls(row) {
	return {
		closed: row.querySelector(".locfinder-hours-closed"),
		is24: row.querySelector(".locfinder-hours-is24"),
		open: row.querySelector(".locfinder-hours-open"),
		close: row.querySelector(".locfinder-hours-close"),
	};
}

/**
 * Updates the enabled state of an hours row.
 *
 * @param {HTMLTableRowElement} row - Hours row to update.
 * @returns {void}
 */
function updateRowState(row) {
	const { closed, is24, open, close } = getRowControls(row);

	if (!closed || !is24 || !open || !close) {
		return;
	}

	const disableTimes = closed.checked || is24.checked;

	open.disabled = disableTimes;
	close.disabled = disableTimes;
}

/**
 * Initializes hours field behavior.
 *
 * @returns {void}
 */
export function initHoursFields() {
	const rows = document.querySelectorAll(ROW_SELECTOR);

	if (!rows.length) {
		return;
	}

	rows.forEach(updateRowState);

	document.addEventListener("change", (e) => {
		const closedBox = e.target.closest(".locfinder-hours-closed");
		const is24Box = e.target.closest(".locfinder-hours-is24");

		if (!closedBox && !is24Box) {
			return;
		}

		const row = e.target.closest("tr");

		if (!row) {
			return;
		}

		const { closed, is24 } = getRowControls(row);

		// "Closed" and "Open 24 Hours" are mutually exclusive.
		if (closedBox && closedBox.checked && is24) {
			is24.checked = false;
		}

		if (is24Box && is24Box.checked && closed) {
			closed.checked = false;
		}

		updateRowState(row);
	});
}
