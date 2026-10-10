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

import { __, sprintf } from "@wordpress/i18n";
import { useBlockProps } from "@wordpress/block-editor";
import { Placeholder } from "@wordpress/components";
import { info } from "@wordpress/icons";

export default function Edit() {
	const blockProps = useBlockProps();
	const labels = window.locfinderEditor?.itemLabels ?? {};
	const singular = labels.singular || __("Location", "locfinder");
	const singularLower = labels.singularLower || __("location", "locfinder");

	return (
		<div {...blockProps}>
			<Placeholder
				icon={info}
				/* translators: %s: singular name chosen in settings, e.g. "Location" or "Program" */
				label={sprintf(__("%s Details", "locfinder"), singular)}
				instructions={sprintf(
					/* translators: %s: singular name chosen in settings, mid-sentence, e.g. "location" or "program" */
					__(
						"Shows the address, hours, contact info, and categories of the %s being viewed. There is nothing to configure here. This placeholder only appears in the editor.",
						"locfinder"
					),
					singularLower
				)}
			/>
		</div>
	);
}
