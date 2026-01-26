/**
 * Media Library Enforcer.
 *
 * Forces non-admin users to use the folder sidebar view in Media Library.
 *
 * @package VmfaEditorialWorkflow
 */

import '../css/media-library-enforcer.css';

( function () {
	'use strict';

	/**
	 * Force folder sidebar view for Virtual Media Folders.
	 */
	function enforceFolderView() {
		// Find the VMF folder toggle button.
		var toggleButton = document.querySelector( '.vmf-folder-toggle-button' );

		// If toggle exists and is not active, click it to show folder sidebar.
		if ( toggleButton && ! toggleButton.classList.contains( 'is-active' ) ) {
			toggleButton.click();
		}
	}

	/**
	 * Block clicks on folder toggle and view switch buttons.
	 *
	 * @param {Event} e Click event.
	 */
	function blockViewToggle( e ) {
		// Block folder toggle off.
		var folderToggle = e.target.closest( '.vmf-folder-toggle-button' );
		if ( folderToggle && folderToggle.classList.contains( 'is-active' ) ) {
			e.preventDefault();
			e.stopPropagation();
			return false;
		}

		// Block list/grid view switch (these remove the folder sidebar).
		var viewSwitch = e.target.closest(
			'.view-switch a:not(.vmf-folder-toggle-button)'
		);
		if ( viewSwitch ) {
			e.preventDefault();
			e.stopPropagation();
			return false;
		}
	}

	/**
	 * Initialize the enforcer.
	 */
	function init() {
		// Run on DOM ready.
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', enforceFolderView );
		} else {
			enforceFolderView();
		}

		// Also run after delays to catch late-loading UI.
		setTimeout( enforceFolderView, 500 );
		setTimeout( enforceFolderView, 1000 );
		setTimeout( enforceFolderView, 2000 );

		// Prevent user from toggling off the folder view or switching views.
		document.addEventListener( 'click', blockViewToggle, true );
	}

	init();
} )();
