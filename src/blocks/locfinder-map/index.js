import metadata from "./block.json";
import Edit from "./edit.jsx";

import "./editor.scss";
import "./style.scss";

import { registerBlockType } from "@wordpress/blocks";

registerBlockType(metadata.name, {
	edit: Edit,
	// Dynamic block: save returns null.
	save: () => null,
});
