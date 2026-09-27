import { __ } from "@wordpress/i18n";

/**
 * Renders a "Pro" feature upsell: a lock badge, a plain-text description
 * of the locked feature, and an optional "Learn more" link.
 *
 * @param   {Object} props
 * @param   {string} props.message       Plain-text description of the locked feature.
 * @param   {string} props.url           Destination for the "Learn more" link. Omitted entirely when empty.
 * @param   {string} [props.badgeLabel]  Pill text. Defaults to "Pro".
 * @param   {string} [props.linkText]    Link text before the arrow. Defaults to "Learn more".
 * @returns {JSX.Element}                The rendered upsell note.
 */
export function ProUpsell({
	message,
	url,
	badgeLabel = __("Pro", "locfinder"),
	linkText = __("Learn more", "locfinder"),
}) {
	return (
		<div className="locfinder-pro-note">
			<span className="locfinder-pro-badge">
				<span className="dashicons dashicons-lock" aria-hidden="true" />
				{badgeLabel}
			</span>
			<span className="locfinder-pro-note__text">{message}</span>
			{url && (
				<a
					className="locfinder-pro-note__link"
					href={url}
					target="_blank"
					rel="noopener noreferrer"
				>
					{linkText} <span aria-hidden="true">→</span>
				</a>
			)}
		</div>
	);
}
