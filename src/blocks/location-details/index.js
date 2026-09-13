/**
 * Registers the locfinder/location-details block with the editor.
 *
 * This is a dynamic block: its actual output (address, hours, contact
 * info, categories) is produced server-side by
 * BlockRegistrar::renderLocationDetailsBlock(), which delegates to
 * Frontend::renderSingleLocationBody() - the same method the classic
 * single-location template uses. Nothing is ever saved to post_content
 * for this block, so `save` always returns null.
 *
 * Registering it here (even though it has no real editing UI) matters:
 * without a client-side registration, the Block Editor doesn't recognize
 * this block type and renders it as "unexpected or invalid content"
 * wherever it appears - which is how it previously got deleted from the
 * Single Location template by accident.
 */

import metadata from "./block.json";
import Edit from "./edit.jsx";

import "./editor.scss";

import { registerBlockType } from "@wordpress/blocks";

registerBlockType(metadata.name, {
	edit: Edit,
	save: () => null,
});
