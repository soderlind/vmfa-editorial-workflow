/**
 * Logs Page Component for Editorial Workflow.
 *
 * Placeholder for future activity logs feature.
 *
 * @package VmfaEditorialWorkflow
 */

import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Logs Page component.
 *
 * @return {JSX.Element} The logs page content.
 */
export function LogsPage() {
	return (
		<Card className="vmfo-logs-card">
			<CardHeader>
				<h3>{ __( 'Workflow Activity Logs', 'vmfa-editorial-workflow' ) }</h3>
			</CardHeader>
			<CardBody>
				<div className="vmfo-addon-shell__empty-state">
					<p>
						{ __(
							'Activity logging is coming in a future update. This will show a history of media approvals, rejections, and workflow changes.',
							'vmfa-editorial-workflow'
						) }
					</p>
				</div>
			</CardBody>
		</Card>
	);
}

export default LogsPage;
