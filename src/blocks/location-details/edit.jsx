/**
 * Editor-only placeholder for the locfinder/location-details block.
 *
 * This block has no configurable attributes and no meaningful visual
 * editing surface: its markup is generated entirely server-side (see
 * BlockRegistrar::renderLocationDetailsBlock()). This component exists
 * only so the Block Editor recognizes the block type and displays a
 * clean, informative placeholder here instead of an "unrecognized
 * block" warning - the placeholder itself is never shown on the front
 * end.
 */

import { __ } from "@wordpress/i18n";
import { useBlockProps } from "@wordpress/block-editor";
import { Placeholder } from "@wordpress/components";
import { info } from "@wordpress/icons";

export default function Edit() {
	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<Placeholder
				icon={info}
				label={__("Location Details", "locfinder")}
				instructions={__(
					"Rendered automatically from the current location's address, hours, contact info, and categories. There is nothing to configure here - this placeholder only appears in the editor.",
					"locfinder"
				)}
			/>
		</div>
	);
}
