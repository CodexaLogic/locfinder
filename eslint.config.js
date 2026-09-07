/**
 * ESLint flat config for Locfinder plugin.
 *
 * Targets browser ES modules (public.js, admin.js, common utilities)
 * and Node.js scripts (vite.config.js, scripts/bundle.mjs).
 *
 * Scope: correctness only. Prettier owns all formatting (indentation,
 * quotes, semicolons, spacing, trailing commas, line breaks), so the
 * matching ESLint rules are deliberately absent rather than set to a
 * value that would contradict it. Run `npm run format` for formatting.
 *
 * Run:
 *   npm run lint, check for errors
 *   npm run lint:fix, auto-fix where possible
 */

import js from "@eslint/js";
import globals from "globals";

export default [
	// ─────────────────────────────────────────────────────────────────────────
	// Files to ignore
	// ─────────────────────────────────────────────────────────────────────────
	{
		ignores: ["build/**", "node_modules/**", "vendor/**", "*.min.js"],
	},

	// ─────────────────────────────────────────────────────────────────────────
	// Base config, applies to all JS files
	// ─────────────────────────────────────────────────────────────────────────
	{
		languageOptions: {
			ecmaVersion: "latest",
			sourceType: "module",

			globals: {
				// Browser globals
				...globals.browser,

				// WordPress globals available via wp_localize_script
				locfinderConfig: "readonly",
				locfinderLicense: "readonly",

				// WordPress core JS API, wp.media is used by the admin
				// media pickers, enqueued via wp_enqueue_media().
				wp: "readonly",

				// Google Maps, loaded async via google-loader.js
				google: "readonly",
			},
		},

		rules: {
			// ESLint's recommended correctness rules (no-dupe-keys, no-undef,
			// no-unused-vars, no-unreachable, and ~57 others). Spread here
			// rather than on the config object itself, because a sibling
			// `rules` key would replace the spread wholesale and silently
			// disable every one of them.
			...js.configs.recommended.rules,

			// ─────────────────────────────────────────────────────────────────
			// Possible errors
			// ─────────────────────────────────────────────────────────────────
			"no-await-in-loop": "error",
			"no-template-curly-in-string": "error",
			"no-prototype-builtins": "error",

			// ─────────────────────────────────────────────────────────────────
			// Best practices
			// ─────────────────────────────────────────────────────────────────
			"array-callback-return": "error",
			"consistent-return": "error",
			curly: "error",
			"default-case": "error",
			"dot-notation": "error",
			eqeqeq: ["error", "always"],
			"no-caller": "error",
			"no-eval": "error",
			"no-extend-native": "error",
			"no-extra-bind": "error",
			"no-floating-decimal": "error",
			"no-implicit-coercion": "error",
			"no-implied-eval": "error",
			"no-loop-func": "error",
			"no-new-func": "error",
			"no-new-wrappers": "error",
			"no-return-assign": "error",
			"no-self-compare": "error",
			"no-sequences": "error",
			"no-throw-literal": "error",
			"no-unused-expressions": "error",
			"no-useless-call": "error",
			"no-useless-concat": "error",
			"no-useless-return": "error",
			radix: "error",
			"require-await": "error",
			yoda: "error",

			// Magic numbers, ignore common values used in map/math contexts.
			"no-magic-numbers": [
				"warn",
				{
					ignore: [-1, 0, 1, 2, 100, 180, 360, 3959, 6371],
					ignoreArrayIndexes: true,
					ignoreDefaultValues: true,
					ignoreClassFieldInitialValues: true,
				},
			],

			// ─────────────────────────────────────────────────────────────────
			// Variables
			// ─────────────────────────────────────────────────────────────────
			"no-shadow-restricted-names": "error",
			"no-undef-init": "error",

			// Off, undefined is valid and safe in ES modules.
			"no-undefined": "off",

			// ─────────────────────────────────────────────────────────────────
			// Naming and clarity
			//
			// Formatting rules (indent, quotes, semi, comma-dangle, spacing,
			// object-curly-newline, eol-last, linebreak-style, trailing
			// whitespace) are intentionally omitted, Prettier owns those.
			// Only rules Prettier cannot express live here.
			// ─────────────────────────────────────────────────────────────────
			camelcase: "error",
			"no-lonely-if": "error",
			"no-multi-assign": "error",
			"no-unneeded-ternary": "error",
			"one-var": ["error", "never"],
			"spaced-comment": "error",

			// ─────────────────────────────────────────────────────────────────
			// ES6+
			// ─────────────────────────────────────────────────────────────────
			"no-var": "error",
			"prefer-const": "error",
			"prefer-template": "error",
			"prefer-rest-params": "error",
			"prefer-spread": "error",
			"no-duplicate-imports": "error",
			"no-useless-constructor": "error",
			"no-useless-rename": "error",
			"no-useless-computed-key": "error",
			"object-shorthand": "error",
			"prefer-arrow-callback": "error",

			// Import sorting, allow logical grouping, only sort members.
			"sort-imports": [
				"error",
				{
					ignoreDeclarationSort: true,
					ignoreMemberSort: false,
				},
			],

			// Off, strict mode is automatic in ES modules.
			strict: "off",

			// Off, vars-on-top is a var-era rule, irrelevant with prefer-const.
			"vars-on-top": "off",
		},
	},

	// ─────────────────────────────────────────────────────────────────────────
	// Node.js scripts, vite.config.js, scripts/bundle.mjs etc.
	// ─────────────────────────────────────────────────────────────────────────
	{
		files: ["vite.config.js", "scripts/**/*.mjs", "scripts/**/*.js"],

		languageOptions: {
			globals: {
				...globals.node,
			},
		},

		rules: {
			// Magic numbers are common in build config files.
			"no-magic-numbers": "off",
		},
	},
];
