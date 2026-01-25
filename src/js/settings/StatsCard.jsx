/**
 * Statistics Card component.
 *
 * @package VmfaEditorialWorkflow
 */

import { __ } from '@wordpress/i18n';

/**
 * Single stat item component.
 *
 * @param {Object} props       Component props.
 * @param {string} props.value The value to display.
 * @param {string} props.label The label for the value.
 * @param {string} props.color Optional color for the value.
 * @return {JSX.Element} Stat item.
 */
function StatItem( { value, label, color } ) {
	return (
		<div className="vmfa-stat-item">
			<div className="vmfa-stat-value" style={ color ? { color } : undefined }>
				{ value }
			</div>
			<div className="vmfa-stat-label">{ label }</div>
		</div>
	);
}

/**
 * Statistics Card component.
 *
 * Displays workflow statistics in a horizontal card.
 *
 * @param {Object} props       Component props.
 * @param {Object} props.stats Statistics data.
 * @return {JSX.Element} Stats card.
 */
export default function StatsCard( { stats = {} } ) {
	const {
		totalMedia = 0,
		needsReview = 0,
		approved = 0,
		rolesConfigured = 0,
	} = stats;

	return (
		<div className="vmfa-stats-card vmfa-card">
			<StatItem
				value={ totalMedia }
				label={ __( 'Total Media', 'vmfa-editorial-workflow' ) }
			/>
			<StatItem
				value={ needsReview }
				label={ __( 'Needs Review', 'vmfa-editorial-workflow' ) }
				color={ needsReview > 0 ? '#d63638' : undefined }
			/>
			<StatItem
				value={ approved }
				label={ __( 'Approved', 'vmfa-editorial-workflow' ) }
				color={ approved > 0 ? '#008a20' : undefined }
			/>
			<StatItem
				value={ rolesConfigured }
				label={ __( 'Roles Configured', 'vmfa-editorial-workflow' ) }
			/>
		</div>
	);
}
