import { __ } from "@wordpress/i18n";

/**
 * Renders a Pro upsell for a locked feature: an optional description, then
 * the Pro badge, a short title, and a "Learn more" link on one line.
 *
 * @param   {Object} props
 * @param   {string} props.title         Short phrase next to the badge, e.g. "Style your map".
 * @param   {string} [props.description] Longer explanation of the feature, shown above the badge.
 * @param   {string} props.url           Destination for the "Learn more" link. Omitted entirely when empty.
 * @param   {string} [props.badgeLabel]  Badge text. Defaults to "Pro".
 * @param   {string} [props.linkText]    Link text before the arrow. Defaults to "Learn more".
 * @returns {JSX.Element}                The rendered upsell.
 */
export function ProUpsell({
	title,
	description,
	url,
	badgeLabel = __("Pro", "locfinder"),
	linkText = __("Learn more", "locfinder"),
}) {
	return (
		<>
			{description && (
				<p className="locfinder-pro-note__description">{description}</p>
			)}
			<div className="locfinder-pro-note">
				<span className="locfinder-pro-badge">
					<span
						className="dashicons dashicons-lock"
						aria-hidden="true"
					/>
					{badgeLabel}
				</span>
				<span className="locfinder-pro-note__text">{title}</span>
				{url && (
					<a
						className="locfinder-pro-note__link"
						href={url}
						target="_blank"
						rel="noopener noreferrer"
					>
						{linkText}
						<span className="screen-reader-text">
							{" "}
							{__("(opens in a new tab)", "locfinder")}
						</span>
						<span aria-hidden="true">→</span>
					</a>
				)}
			</div>
		</>
	);
}
