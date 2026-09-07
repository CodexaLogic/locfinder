/**
 * Vite configuration for Locfinder plugin assets.
 *
 * The locfinder-map Gutenberg block is built separately, via
 * wp-scripts (see package.json's build:blocks script), not Vite.
 * wp-scripts already handles JSX compiled against @wordpress/element,
 * @wordpress/* externalization against WordPress's own wp.* globals,
 * and .asset.php dependency manifest generation, all of which the
 * ES-module, code-split output here cannot cleanly produce alongside
 * everything else in one Rollup config.
 */

import { defineConfig } from "vite";
import { resolve } from "path";

/**
 * Entry names that produce public-facing bundles.
 */
const publicEntries = new Set(["public", "single"]);

export default defineConfig(({ mode }) => {
	const isProd = mode === "production";

	return {
		/**
		 * Prevents Vite from treating the public/ PHP folder as a static assets source
		 * and copying its contents into build/ on every build.
		 */
		publicDir: false,

		build: {
			outDir: "build",
			emptyOutDir: false,

			/**
			 * Full source maps in dev, none in production.
			 */
			sourcemap: !isProd,

			rollupOptions: {
				input: {
					admin: resolve("src/admin/js/admin.js"),
					"clear-cache": resolve("src/admin/js/clear-cache.js"),
					public: resolve("src/public/js/public.js"),
					single: resolve("src/public/js/single.js"),
				},

				output: {
					/**
					 * Pin third-party libraries to their own named chunks so
					 * builds stay predictable and browser caching works correctly.
					 */
					manualChunks: {
						markerclusterer: ["@googlemaps/markerclusterer"],
					},

					/**
					 * Routes JS entry bundles by entry name.
					 */
					entryFileNames: (chunk) => {
						if (publicEntries.has(chunk.name)) {
							return "assets/public/js/[name].js";
						}
						return "assets/admin/js/[name].js";
					},

					/**
					 * Shared chunks go to common/js/ with a content hash in
					 * production so browsers pick up changes automatically.
					 * No hash in development for easier debugging.
					 */
					chunkFileNames: isProd
						? "assets/common/js/[name].[hash].js"
						: "assets/common/js/[name].js",

					/**
					 * Routes extracted CSS by exact filename to avoid false
					 * matches on filenames that happen to contain "public".
					 * Any other assets fall through to assets/.
					 */
					assetFileNames: (asset) => {
						const name = asset.name ?? "";

						if (name.endsWith(".css")) {
							if (
								name === "public.css" ||
								name === "single.css"
							) {
								return "assets/public/css/[name][extname]";
							}
							if (name === "admin.css") {
								return "assets/admin/css/[name][extname]";
							}
						}

						return "assets/[name][extname]";
					},
				},
			},
		},

		/**
		 * Includes legal comments inline within the bundles.
		 */
		esbuild: {
			legalComments: "inline",
		},

		/**
		 * Import aliases.
		 */
		resolve: {
			alias: {
				"@common": resolve("src/common/js"),
				"@admin": resolve("src/admin/js"),
				"@public": resolve("src/public/js"),
				"@blocks": resolve("src/blocks"),
				"@scss": resolve("src/scss"),
			},
		},

		css: {
			devSourcemap: !isProd,
		},
	};
});
