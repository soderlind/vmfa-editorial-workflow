/**
 * Settings panel entry point.
 *
 * @package VmfaEditorialWorkflow
 */

import { createRoot } from '@wordpress/element';
import SettingsPanel from './SettingsPanel';
import '../../css/settings.css';

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'vmfa-settings-root' );

	if ( container ) {
		const root = createRoot( container );
		root.render( <SettingsPanel /> );
	}
} );
