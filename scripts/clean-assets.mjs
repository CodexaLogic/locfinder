/**
 * Removes the Vite output directory before a build.
 *
 * vite.config.js sets emptyOutDir:false on purpose, because Vite and
 * wp-scripts both write into build/ and letting Vite wipe the whole
 * directory would destroy the compiled blocks. The tradeoff is that
 * content-hashed chunks from previous builds are never cleaned up, so
 * renamed chunks accumulate as orphans and end up in the release ZIP.
 *
 * This clears only build/assets, which Vite owns outright, and leaves
 * build/blocks alone.
 *
 * Usage: node scripts/clean-assets.mjs
 */

import { rmSync } from "node:fs";

rmSync("build/assets", { recursive: true, force: true });
