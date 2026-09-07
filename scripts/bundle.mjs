/**
 * Packages the plugin into a distributable ZIP.
 *
 * Reads .distignore for exclusions, so packaging and any other tooling
 * that respects .distignore stay in agreement instead of drifting apart.
 * Files are nested under a top-level plugin folder inside the archive,
 * which WordPress requires for manual "Upload Plugin" installs.
 *
 * Usage: npm run bundle
 */

import archiver from "archiver";
import {
	createWriteStream,
	existsSync,
	mkdirSync,
	readFileSync,
	readdirSync,
} from "node:fs";
import { join, relative, resolve, sep } from "node:path";

const ROOT = process.cwd();
const SLUG = "locfinder";
const OUT_DIR = "packaged";

/**
 * Always excluded, whether or not .distignore lists them. "packaged" is
 * here so re-running the bundler never packages a previous archive.
 * "node_modules" is a hard backstop: .distignore already excludes it
 * too, but that's a plain text file anyone can edit or accidentally
 * empty, and a build that silently zips up the entire dependency tree
 * is a much worse failure mode than one that's merely missing a file
 * .distignore was supposed to catch.
 */
const ALWAYS_IGNORE = [
	".git",
	".DS_Store",
	"Thumbs.db",
	"packaged",
	"node_modules",
];

/**
 * Reads exclusion patterns from .distignore, ignoring blanks and comments.
 */
function readDistignore() {
	const file = join(ROOT, ".distignore");

	if (!existsSync(file)) {
		return [];
	}

	return readFileSync(file, "utf8")
		.split("\n")
		.map((line) => line.trim())
		.filter((line) => line && !line.startsWith("#"));
}

/**
 * Reads the first chunk of the plugin's main file, where both the header
 * comment and the LOCFINDER_VERSION constant live. Read once and shared by
 * the two extractors below so they can never see different file contents.
 */
function readPluginFileHead() {
	return readFileSync(join(ROOT, `${SLUG}.php`), "utf8").slice(0, 8192);
}

/**
 * Reads the Version field from the plugin's main file header. This is the
 * version WordPress itself reports, so it is treated as the source of truth.
 */
function readPluginVersion(head) {
	const match = head.match(/^\s*\*\s*Version:\s*(.+)$/m);

	if (!match) {
		throw new Error(`Could not read "Version:" from ${SLUG}.php`);
	}

	return match[1].trim();
}

/**
 * Reads the LOCFINDER_VERSION constant from the plugin's main file. This is
 * the value the running plugin actually uses at runtime — e.g. cache-busting
 * query strings on enqueued scripts and styles — so it must match the header
 * above. Nothing else in the build checks the two against each other.
 */
function readVersionConstant(head) {
	const match = head.match(
		/define\(\s*['"]LOCFINDER_VERSION['"]\s*,\s*['"]([^'"]+)['"]\s*\)/
	);

	if (!match) {
		throw new Error(`Could not read LOCFINDER_VERSION from ${SLUG}.php`);
	}

	return match[1].trim();
}

/**
 * Reads a single field from readme.txt, e.g. "Stable tag".
 */
function readReadmeField(field) {
	const file = join(ROOT, "readme.txt");

	if (!existsSync(file)) {
		return null;
	}

	const match = readFileSync(file, "utf8").match(
		new RegExp(`^${field}:\\s*(.+)$`, "im")
	);

	return match ? match[1].trim() : null;
}

/**
 * Recursively collects every shippable file path, relative to the plugin root.
 *
 * Entries in .distignore match either a bare name at any depth (node_modules)
 * or a specific relative path (src/admin/js).
 */
function collectFiles(dir, ignore, found = []) {
	for (const entry of readdirSync(dir, {
		withFileTypes: true,
	})) {
		if (ignore.has(entry.name)) {
			continue;
		}

		const absolute = join(dir, entry.name);
		const relPath = relative(ROOT, absolute).split(sep).join("/");

		if (ignore.has(relPath)) {
			continue;
		}

		if (entry.isDirectory()) {
			collectFiles(absolute, ignore, found);
		} else {
			found.push(relPath);
		}
	}

	return found;
}

const pluginFileHead = readPluginFileHead();
const version = readPluginVersion(pluginFileHead);
const versionConstant = readVersionConstant(pluginFileHead);
const stableTag = readReadmeField("Stable tag");
const pkgVersion = JSON.parse(
	readFileSync(join(ROOT, "package.json"), "utf8")
).version;

// A Stable tag that disagrees with the plugin header is one of the most
// common causes of a WordPress.org release shipping the wrong code, so it
// is worth failing loudly here rather than discovering it after upload.
if (stableTag && stableTag !== version) {
	console.error(
		`✗ Version mismatch: ${SLUG}.php says ${version}, readme.txt Stable tag says ${stableTag}.`
	);
	process.exit(1);
}

// LOCFINDER_VERSION is what enqueued assets are actually cache-busted with
// at runtime. Without this check, the header and readme.txt checks above
// stay green while every script and style tag on the site still reports
// the old version.
if (versionConstant !== version) {
	console.error(
		`✗ Version mismatch: ${SLUG}.php header says ${version}, LOCFINDER_VERSION constant says ${versionConstant}.`
	);
	process.exit(1);
}

if (pkgVersion && pkgVersion !== version) {
	console.warn(
		`! package.json version (${pkgVersion}) differs from ${SLUG}.php (${version}).`
	);
}

const ignore = new Set([...ALWAYS_IGNORE, ...readDistignore()]);
const files = collectFiles(ROOT, ignore);

if (!files.some((f) => f === `${SLUG}.php`)) {
	throw new Error(`${SLUG}.php was excluded from the package.`);
}

mkdirSync(OUT_DIR, {
	recursive: true,
});

const outFile = join(OUT_DIR, `${SLUG}-${version}.zip`);
const output = createWriteStream(resolve(outFile));
const archive = archiver("zip", {
	zlib: {
		level: 9,
	},
});

output.on("close", () => {
	const kb = (archive.pointer() / 1024).toFixed(1);
	console.log(`✓ ${outFile} — ${kb} KB, ${files.length} files`);
});

archive.on("error", (err) => {
	throw err;
});

archive.pipe(output);

for (const file of files) {
	archive.file(join(ROOT, file), {
		name: `${SLUG}/${file}`,
	});
}

archive.finalize();
