import {
	InspectorControls,
	PanelColorSettings,
	useBlockProps,
} from "@wordpress/block-editor";
import {
	Button,
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from "@wordpress/components";
import { store as coreStore } from "@wordpress/core-data";
import { createInterpolateElement } from "@wordpress/element";
import { useSelect } from "@wordpress/data";
import { __, sprintf } from "@wordpress/i18n";
import ServerSideRender from "@wordpress/server-side-render";

const SHOW_FIELD_CONFIG = [
	{ key: "showImage", label: __("Image", "locfinder") },
	{ key: "showAddress", label: __("Address", "locfinder") },
	{ key: "showDirections", label: __("Directions", "locfinder") },
	{ key: "showPhone", label: __("Phone", "locfinder") },
	{ key: "showEmail", label: __("Email", "locfinder") },
	{ key: "showWebsite", label: __("Website", "locfinder") },
	{ key: "showHours", label: __("Hours", "locfinder") },
	{ key: "showCategories", label: __("Categories", "locfinder") },
	{ key: "showExcerpt", label: __("Excerpt", "locfinder") },
];

const {
	allowedTaxonomies = [],
	openBadgeAvailable = false,
	proUrl = "",
	defaultResultsLayout = "grid",
	defaultResultsPosition = "bottom",
	defaultResultsSideColumns = 2,
} = window.locfinderBlockEditor ?? {};

/**
 * Builds a short, human-readable summary of the block's active
 * configuration, shown above the server-rendered preview.
 *
 * @param {Object} attributes - The block's current attribute values.
 * @returns {Object[]} Summary rows, each with a label and either chip items or a plain value.
 */
function buildConfigSummary(attributes) {
	const {
		keywordSearch,
		addressSearch,
		radiusSearch,
		categorySearch,
		resultsLayout,
		resultsPosition,
		gridCols,
		resultsColumns,
		postsPerPage,
	} = attributes;

	const searchTypes = [
		keywordSearch && __("Keyword", "locfinder"),
		addressSearch && __("Address", "locfinder"),
		radiusSearch && __("Radius", "locfinder"),
		categorySearch && __("Category", "locfinder"),
	].filter(Boolean);

	const fields = SHOW_FIELD_CONFIG.filter(({ key }) => attributes[key]).map(
		({ label }) => label
	);

	if (openBadgeAvailable && (attributes.showOpenBadge ?? true)) {
		fields.push(__("Open/Closed Status", "locfinder"));
	}

	const effectiveLayout =
		resultsLayout === "default" ? defaultResultsLayout : resultsLayout;

	const effectivePosition =
		resultsPosition === "default"
			? defaultResultsPosition
			: resultsPosition;

	const positionLabels = {
		bottom: __("Bottom", "locfinder"),
		left: __("Left", "locfinder"),
		right: __("Right", "locfinder"),
	};
	const positionLabel =
		positionLabels[effectivePosition] ?? positionLabels.bottom;

	const effectiveColumns =
		effectivePosition === "bottom"
			? gridCols
			: resultsColumns || defaultResultsSideColumns;

	const postsPerPageText =
		postsPerPage > 0 ? postsPerPage : __("site default", "locfinder");

	const layoutValue =
		effectiveLayout === "list"
			? sprintf(
					/* translators: 1: results position (Bottom/Left/Right). 2: results per page, either a number or the words "site default". */
					__("%1$s, List, %2$s per page", "locfinder"),
					positionLabel,
					postsPerPageText
				)
			: sprintf(
					/* translators: 1: results position (Bottom/Left/Right). 2: number of grid columns. 3: results per page, either a number or the words "site default". */
					__("%1$s, Grid, %2$d columns, %3$s per page", "locfinder"),
					positionLabel,
					effectiveColumns,
					postsPerPageText
				);

	return [
		{
			label: __("Search", "locfinder"),
			type: "chips",
			items: searchTypes,
			emptyText: __("No filters enabled", "locfinder"),
		},
		{
			label: __("Layout", "locfinder"),
			type: "text",
			value: layoutValue,
		},
		{
			label: __("Showing", "locfinder"),
			type: "chips",
			items: fields,
			emptyText: __("No fields explicitly enabled", "locfinder"),
		},
	];
}

export default function Edit({ attributes, setAttributes }) {
	const {
		keywordSearch,
		addressSearch,
		radiusSearch,
		categorySearch,
		defaultRadius,
		keywordPlaceholder,
		addressPlaceholder,
		searchButtonLabel,
		radiusLabel,
		categoryLabel,
		anyDistanceText,
		allCategoriesText,
		sortBy,
		resultsLayout,
		resultsPosition,
		gridCols,
		resultsColumns,
		postsPerPage,
		showOpenBadge,
		pinColor,
		taxonomy,
	} = attributes;

	const effectiveLayout =
		resultsLayout === "default" ? defaultResultsLayout : resultsLayout;

	const effectivePosition =
		resultsPosition === "default"
			? defaultResultsPosition
			: resultsPosition;

	const taxonomyOptions = useSelect(
		(select) => {
			const taxonomies = select(coreStore).getTaxonomies({
				per_page: -1,
			});

			return (taxonomies ?? [])
				.filter((tax) => tax.types?.includes("locfinder_location"))
				.filter((tax) => allowedTaxonomies.includes(tax.slug))
				.map((tax) => ({ label: tax.name, value: tax.slug }));
		},
		[allowedTaxonomies]
	);

	const blockProps = useBlockProps({ className: "locfinder-block" });
	const summaryRows = buildConfigSummary(attributes);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__("Search Options", "locfinder")}
					initialOpen={true}
				>
					<ToggleControl
						label={__("Keyword Search", "locfinder")}
						checked={!!keywordSearch}
						onChange={(value) =>
							setAttributes({ keywordSearch: value })
						}
					/>
					<ToggleControl
						label={__("Address Search", "locfinder")}
						checked={!!addressSearch}
						onChange={(value) =>
							setAttributes(
								value
									? { addressSearch: value }
									: {
											addressSearch: value,
											radiusSearch: false,
										}
							)
						}
					/>
					{addressSearch && (
						<>
							<ToggleControl
								label={__("Radius Search", "locfinder")}
								checked={!!radiusSearch}
								onChange={(value) =>
									setAttributes({ radiusSearch: value })
								}
							/>
							<RangeControl
								label={__("Default Radius", "locfinder")}
								value={defaultRadius}
								onChange={(value) =>
									setAttributes({ defaultRadius: value })
								}
								min={-1}
								max={500}
								disabled={!radiusSearch}
								help={__(
									'-1 = use the site default. 0 = pre-select "Any distance." No maximum is enforced on the server — this field\'s 500 cap is just a practical UI limit for the slider.',
									"locfinder"
								)}
							/>
						</>
					)}
					<ToggleControl
						label={__("Category Search", "locfinder")}
						checked={!!categorySearch}
						onChange={(value) =>
							setAttributes({ categorySearch: value })
						}
					/>
					<SelectControl
						label={__("Category Taxonomy", "locfinder")}
						value={taxonomy}
						options={[
							{
								label: __("Use site default", "locfinder"),
								value: "",
							},
							...taxonomyOptions,
						]}
						onChange={(value) => setAttributes({ taxonomy: value })}
						help={
							allowedTaxonomies.length > 1
								? undefined
								: createInterpolateElement(
										__(
											"Filter by any custom taxonomy with the <a>Pro add-on</a>.",
											"locfinder"
										),
										{
											a: (
												<a
													href={proUrl}
													target="_blank"
													rel="noopener noreferrer"
												>
													Pro add-on
												</a>
											),
										}
									)
						}
					/>
				</PanelBody>

				<PanelBody
					title={__("Labels & Placeholders", "locfinder")}
					initialOpen={false}
				>
					<TextControl
						label={__("Keyword Placeholder", "locfinder")}
						help={__(
							"Leave blank to use the plugin default.",
							"locfinder"
						)}
						value={keywordPlaceholder}
						onChange={(value) =>
							setAttributes({ keywordPlaceholder: value })
						}
					/>
					{addressSearch && (
						<TextControl
							label={__("Address Placeholder", "locfinder")}
							help={__(
								"Leave blank to use the plugin default.",
								"locfinder"
							)}
							value={addressPlaceholder}
							onChange={(value) =>
								setAttributes({ addressPlaceholder: value })
							}
						/>
					)}
					<TextControl
						label={__("Search Button Label", "locfinder")}
						help={__(
							"Leave blank to use the plugin default.",
							"locfinder"
						)}
						value={searchButtonLabel}
						onChange={(value) =>
							setAttributes({ searchButtonLabel: value })
						}
					/>
					{addressSearch && radiusSearch && (
						<>
							<TextControl
								label={__("Radius Field Label", "locfinder")}
								help={__(
									"Screen-reader label. Leave blank to use the plugin default.",
									"locfinder"
								)}
								value={radiusLabel}
								onChange={(value) =>
									setAttributes({ radiusLabel: value })
								}
							/>
							<TextControl
								label={__(
									'"Any Distance" Option Text',
									"locfinder"
								)}
								help={__(
									"Leave blank to use the plugin default.",
									"locfinder"
								)}
								value={anyDistanceText}
								onChange={(value) =>
									setAttributes({ anyDistanceText: value })
								}
							/>
						</>
					)}
					<TextControl
						label={__("Category Field Label", "locfinder")}
						help={__(
							"Screen-reader label. Leave blank to use the plugin default.",
							"locfinder"
						)}
						value={categoryLabel}
						onChange={(value) =>
							setAttributes({ categoryLabel: value })
						}
					/>
					<TextControl
						label={__('"All Categories" Option Text', "locfinder")}
						help={__(
							"Leave blank to use the plugin default.",
							"locfinder"
						)}
						value={allCategoriesText}
						onChange={(value) =>
							setAttributes({ allCategoriesText: value })
						}
					/>
				</PanelBody>

				<PanelBody
					title={__("Results Display", "locfinder")}
					initialOpen={false}
				>
					<SelectControl
						label={__("Sort Results By", "locfinder")}
						value={sortBy}
						options={[
							{
								label: __("Use plugin default", "locfinder"),
								value: "default",
							},
							{
								label: __("Title (A-Z)", "locfinder"),
								value: "title_asc",
							},
							{
								label: __("Date (Newest First)", "locfinder"),
								value: "date_desc",
							},
							{
								label: __("Random", "locfinder"),
								value: "random",
							},
						]}
						onChange={(value) => setAttributes({ sortBy: value })}
					/>
					<SelectControl
						label={__("Layout", "locfinder")}
						value={resultsLayout}
						options={[
							{
								label: sprintf(
									/* translators: %s: the site-wide default value for this control — a layout name (Grid/List) or a position name (Bottom/Left/Right), depending on which dropdown this label appears in. */
									__("Use site default (%s)", "locfinder"),
									defaultResultsLayout === "list"
										? __("List", "locfinder")
										: __("Grid", "locfinder")
								),
								value: "default",
							},
							{ label: __("Grid", "locfinder"), value: "grid" },
							{ label: __("List", "locfinder"), value: "list" },
						]}
						onChange={(value) =>
							setAttributes({ resultsLayout: value })
						}
						help={__(
							"Overrides the site-wide Results List layout setting for this block only.",
							"locfinder"
						)}
					/>
					<SelectControl
						label={__("Position", "locfinder")}
						value={resultsPosition}
						options={[
							{
								label: sprintf(
									/* translators: %s: the site-wide default value for this control — a layout name (Grid/List) or a position name (Bottom/Left/Right), depending on which dropdown this label appears in. */
									__("Use site default (%s)", "locfinder"),
									{
										bottom: __("Bottom", "locfinder"),
										left: __("Left", "locfinder"),
										right: __("Right", "locfinder"),
									}[defaultResultsPosition] ??
										__("Bottom", "locfinder")
								),
								value: "default",
							},
							{
								label: __(
									"Bottom (below the map)",
									"locfinder"
								),
								value: "bottom",
							},
							{
								label: __("Left of the map", "locfinder"),
								value: "left",
							},
							{
								label: __("Right of the map", "locfinder"),
								value: "right",
							},
						]}
						onChange={(value) =>
							setAttributes({ resultsPosition: value })
						}
						help={__(
							"Overrides the site-wide Results Position setting for this block only.",
							"locfinder"
						)}
					/>
					<RangeControl
						label={__("Grid Columns", "locfinder")}
						value={gridCols}
						onChange={(value) => setAttributes({ gridCols: value })}
						min={1}
						max={6}
						disabled={effectiveLayout === "list"}
						help={
							effectiveLayout === "list"
								? __(
										"Not used while the layout above is List.",
										"locfinder"
									)
								: undefined
						}
					/>
					<SelectControl
						label={__("Results Columns", "locfinder")}
						value={String(resultsColumns)}
						options={[
							{
								label: sprintf(
									/* translators: %d: number of columns, 1 or 2, the site-wide Results Columns setting. */
									__("Use site default (%d)", "locfinder"),
									defaultResultsSideColumns
								),
								value: "0",
							},
							{ label: __("1 column", "locfinder"), value: "1" },
							{ label: __("2 columns", "locfinder"), value: "2" },
						]}
						disabled={effectivePosition === "bottom"}
						onChange={(value) =>
							setAttributes({ resultsColumns: Number(value) })
						}
						help={
							effectivePosition === "bottom"
								? __(
										"Only used while Position above is Left or Right.",
										"locfinder"
									)
								: undefined
						}
					/>
					<RangeControl
						label={__("Results Per Page", "locfinder")}
						value={postsPerPage}
						onChange={(value) =>
							setAttributes({ postsPerPage: value })
						}
						min={0}
						max={100}
						help={__(
							"0 = use the site default. Clamped to 100 on the server regardless of this value.",
							"locfinder"
						)}
					/>
				</PanelBody>

				<PanelColorSettings
					title={__("Map Pin", "locfinder")}
					initialOpen={false}
					colors={[]}
					colorSettings={[
						{
							value: pinColor,
							onChange: (value) =>
								setAttributes({
									pinColor: value || "",
								}),
							label: __("Pin Color", "locfinder"),
						},
					]}
				>
					{pinColor && (
						<Button
							variant="link"
							onClick={() => setAttributes({ pinColor: "" })}
						>
							{__("Reset to default", "locfinder")}
						</Button>
					)}
				</PanelColorSettings>

				<PanelBody
					title={__("Show Fields", "locfinder")}
					initialOpen={false}
				>
					{SHOW_FIELD_CONFIG.map(({ key, label }) => (
						<ToggleControl
							key={key}
							label={label}
							checked={!!attributes[key]}
							onChange={(value) =>
								setAttributes({ [key]: value })
							}
						/>
					))}

					{openBadgeAvailable ? (
						<ToggleControl
							label={__("Open/Closed Status", "locfinder")}
							checked={showOpenBadge ?? true}
							onChange={(value) =>
								setAttributes({ showOpenBadge: value })
							}
						/>
					) : (
						<div className="components-base-control">
							<span className="components-base-control__label">
								{__("Open/Closed Status", "locfinder")}
							</span>
							<p className="components-base-control__help">
								{createInterpolateElement(
									__(
										"Show a live open/closed status with the <a>Pro add-on</a>.",
										"locfinder"
									),
									{
										a: (
											<a
												href={proUrl}
												target="_blank"
												rel="noopener noreferrer"
											>
												Pro add-on
											</a>
										),
									}
								)}
							</p>
						</div>
					)}
				</PanelBody>
			</InspectorControls>

			<div {...blockProps} onSubmit={(event) => event.preventDefault()}>
				<div className="locfinder-block__summary">
					{summaryRows.map((row, index) => (
						<div
							className="locfinder-block__summary-row"
							key={index}
						>
							<span className="locfinder-block__summary-label">
								{row.label}
							</span>
							{row.type === "chips" ? (
								row.items.length > 0 ? (
									<span className="locfinder-block__summary-chips">
										{row.items.map((item) => (
											<span
												className="locfinder-block__summary-chip"
												key={item}
											>
												{item}
											</span>
										))}
									</span>
								) : (
									<span className="locfinder-block__summary-empty">
										{row.emptyText}
									</span>
								)
							) : (
								<span className="locfinder-block__summary-value">
									{row.value}
								</span>
							)}
						</div>
					))}
				</div>
				<ServerSideRender
					block="locfinder/locfinder-map"
					attributes={attributes}
				/>
			</div>
		</>
	);
}
