/**
 * Loads the Google Maps JavaScript API using the Dynamic Library Import pattern.
 *
 * Installs a bootstrap shim on window.google.maps.importLibrary the first time
 * it is called. The shim appends the Google Maps script tag and queues library
 * requests until the script has loaded. Once loaded Google replaces the shim
 * with its own importLibrary implementation.
 *
 * All public functions are safe to call multiple times and from multiple modules.
 * The script is only ever appended once regardless of how many callers request it.
 *
 * @module google-loader
 */

/** Tracks the bootstrap promise so it is only created once. */
let bootstrapPromise = null;

/** Caches library promises by name so each library is only loaded once. */
const libraryPromises = new Map();

/**
 * Ensures window.google.maps.importLibrary is available.
 *
 * Installs the bootstrap shim if it is not already present, appends the
 * Google Maps script tag, and resolves once the shim is installed. Subsequent
 * calls return the same promise so the bootstrap only runs once per page.
 *
 * @param {Object} args
 * @param {string} args.apiKey           - Google Maps API key.
 * @param {string} [args.language='en']  - Language code.
 * @param {string} [args.region='US']    - Region code.
 * @param {string} [args.version='weekly'] - API version.
 * @returns {Promise<typeof window.google>} - Resolves to window.google.
 */
export function ensureGoogleBootstrap({
	apiKey,
	language = "en",
	region = "US",
	version = "weekly",
} = {}) {
	// Already bootstrapped with the real implementation, return immediately.
	if (
		window.google?.maps?.importLibrary &&
		!window.google.maps.importLibrary.__lfShim
	) {
		return Promise.resolve(window.google);
	}

	// Return the existing promise if bootstrap is already in progress.
	if (bootstrapPromise) {
		return bootstrapPromise;
	}

	bootstrapPromise = new Promise((resolve, reject) => {
		if (!apiKey) {
			reject(new Error("Locfinder: Google Maps API key is required."));
			return;
		}

		// Ensure the google.maps namespace exists before installing the shim.
		window.google = window.google || {};
		window.google.maps = window.google.maps || {};

		// Only install the shim if importLibrary is not already present.
		if (!window.google.maps.importLibrary) {
			installShim(window.google.maps, apiKey, language, region, version);
		}

		resolve(window.google);
	}).catch((err) => {
		bootstrapPromise = null;
		throw err;
	});

	return bootstrapPromise;
}

/**
 * Installs the importLibrary shim on the google.maps namespace.
 *
 * The shim queues library requests and triggers the script load on the
 * first call. Once Google's script loads it replaces the shim with its
 * own implementation and all queued requests are fulfilled.
 *
 * @param   {Object} googleMaps - The window.google.maps namespace object.
 * @param   {string} apiKey     - Google Maps API key.
 * @param   {string} language   - Language code.
 * @param   {string} region     - Region code.
 * @param   {string} version    - API version.
 * @returns {void}
 */
function installShim(googleMaps, apiKey, language, region, version) {
	const CALLBACK_KEY = "__lf_maps_callback__";
	const SCRIPT_ATTR = "data-locfinder-google";
	const requestedLibraries = new Set();
	let scriptLoadPromise = null;

	/**
	 * Appends the Google Maps script tag once and returns a promise that
	 * resolves when the script has loaded and the callback has fired.
	 *
	 * @returns {Promise<void>}
	 */
	function loadScript() {
		if (scriptLoadPromise) {
			return scriptLoadPromise;
		}

		scriptLoadPromise = new Promise((resolve, reject) => {
			const existing = document.querySelector(`script[${SCRIPT_ATTR}]`);

			// If the script tag exists and the real importLibrary is already
			// installed resolve immediately.
			if (
				existing &&
				googleMaps.importLibrary &&
				!googleMaps.importLibrary.__lfShim
			) {
				resolve();
				return;
			}

			// If the script tag exists but hasn't finished loading wait for it.
			if (existing) {
				existing.addEventListener("load", () => resolve(), {
					once: true,
				});
				existing.addEventListener(
					"error",
					() =>
						reject(
							new Error(
								"Locfinder: Google Maps script failed to load."
							)
						),
					{ once: true }
				);
				return;
			}

			// Build the script URL with all required parameters.
			const params = new URLSearchParams({
				key: apiKey,
				v: version,
				language,
				region,
				libraries: [...requestedLibraries].join(","),
				callback: `google.maps.${CALLBACK_KEY}`,
			});

			// Install the callback before appending the script so it is
			// available when Google calls it after loading.
			googleMaps[CALLBACK_KEY] = () => resolve();

			const script = document.createElement("script");

			script.async = true;
			script.defer = true;
			script.src = `https://maps.googleapis.com/maps/api/js?${params}`;
			script.setAttribute(SCRIPT_ATTR, "1");
			script.addEventListener(
				"error",
				() => {
					script.remove();
					reject(
						new Error(
							"Locfinder: Google Maps script failed to load."
						)
					);
				},
				{ once: true }
			);

			document.head.appendChild(script);
		}).catch((err) => {
			scriptLoadPromise = null;
			throw err;
		});

		return scriptLoadPromise;
	}

	/**
	 * The shim implementation of importLibrary.
	 *
	 * Queues the requested library name, triggers the script load, then
	 * delegates to Google's real importLibrary once it is available.
	 *
	 * @param   {string} libraryName - The name of the Google Maps library to load.
	 * @returns {Promise<Object>}    - Resolves to the requested library.
	 */
	const shim = (libraryName) => {
		requestedLibraries.add(libraryName);

		return loadScript().then(() => {
			const real = window.google?.maps?.importLibrary;

			if (!real || real === shim || real.__lfShim) {
				throw new Error(
					"Locfinder: importLibrary not ready after Google Maps script loaded."
				);
			}

			return real(libraryName);
		});
	};

	shim.__lfShim = true;
	googleMaps.importLibrary = shim;
}

/**
 * Ensures a specific Google Maps library is loaded and cached.
 *
 * Safe to call multiple times for the same library, the promise is cached
 * so the library is only loaded once regardless of how many callers request it.
 *
 * @param   {string} libraryName - Library name: 'maps', 'places', 'marker', 'geocoding', etc.
 * @param   {Object} args        - Bootstrap arguments passed to ensureGoogleBootstrap.
 * @returns {Promise<Object>}    - Resolves to the requested library.
 */
export function ensureLibrary(libraryName, args = {}) {
	if (libraryPromises.has(libraryName)) {
		return libraryPromises.get(libraryName);
	}

	const promise = ensureGoogleBootstrap(args)
		.then(() => window.google.maps.importLibrary(libraryName))
		.catch((err) => {
			libraryPromises.delete(libraryName);
			throw err;
		});

	libraryPromises.set(libraryName, promise);

	return promise;
}

/**
 * Ensures the Maps core library is available.
 *
 * @param   {Object} args - Bootstrap arguments.
 * @returns {Promise<Object>}
 */
export function ensureMaps(args = {}) {
	return ensureLibrary("maps", args);
}

/**
 * Ensures the Places library is available.
 *
 * @param   {Object} args - Bootstrap arguments.
 * @returns {Promise<Object>}
 */
export function ensurePlaces(args = {}) {
	return ensureLibrary("places", args);
}
