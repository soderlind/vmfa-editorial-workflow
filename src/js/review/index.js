/**
 * Review page entry point.
 *
 * @package VmfaEditorialWorkflow
 */

( function () {
	'use strict';

	const { nonce, i18n } = window.vmfaReview || {};

	/**
	 * Helper to select elements.
	 *
	 * @param {string}           selector CSS selector.
	 * @param {Element|Document} context  Context element.
	 * @return {Element|null} First matching element.
	 */
	function $( selector, context = document ) {
		return context.querySelector( selector );
	}

	/**
	 * Helper to select all elements.
	 *
	 * @param {string}           selector CSS selector.
	 * @param {Element|Document} context  Context element.
	 * @return {NodeList} All matching elements.
	 */
	function $$( selector, context = document ) {
		return context.querySelectorAll( selector );
	}

	/**
	 * Update selection count and button states.
	 */
	function updateSelectionState() {
		const checkboxes = $$( 'input[name="vmfa-items[]"]:checked' );
		const count = checkboxes.length;

		const countEl = $( '.vmfa-selection-count' );
		if ( countEl ) {
			countEl.textContent = count > 0 ? `${ count } selected` : '';
		}

		[ '#vmfa-bulk-approve', '#vmfa-bulk-assign', '#vmfa-assign-folder' ].forEach( ( sel ) => {
			const el = $( sel );
			if ( el ) {
				el.disabled = count === 0;
			}
		} );
	}

	/**
	 * Handle select all checkbox.
	 */
	const selectAll = $( '#vmfa-select-all' );
	if ( selectAll ) {
		selectAll.addEventListener( 'change', function () {
			const checked = this.checked;
			$$( 'input[name="vmfa-items[]"]' ).forEach( ( cb ) => {
				cb.checked = checked;
			} );
			updateSelectionState();
		} );
	}

	/**
	 * Handle individual checkbox changes.
	 */
	document.addEventListener( 'change', function ( e ) {
		if ( e.target.matches( 'input[name="vmfa-items[]"]' ) ) {
			updateSelectionState();

			// Update select all state.
			const all = $$( 'input[name="vmfa-items[]"]' );
			const checked = $$( 'input[name="vmfa-items[]"]:checked' );
			const selectAllEl = $( '#vmfa-select-all' );
			if ( selectAllEl ) {
				selectAllEl.checked = all.length === checked.length;
			}
		}
	} );

	/**
	 * Get selected attachment IDs.
	 *
	 * @return {Array} Selected IDs.
	 */
	function getSelectedIds() {
		return Array.from( $$( 'input[name="vmfa-items[]"]:checked' ) ).map( ( cb ) =>
			parseInt( cb.value, 10 )
		);
	}

	/**
	 * Fade out and remove an element.
	 *
	 * @param {Element}  el       Element to fade out.
	 * @param {Function} callback Callback after removal.
	 */
	function fadeOutAndRemove( el, callback ) {
		el.style.transition = 'opacity 0.3s ease';
		el.style.opacity = '0';
		setTimeout( () => {
			el.remove();
			if ( callback ) {
				callback();
			}
		}, 300 );
	}

	/**
	 * Make AJAX request.
	 *
	 * @param {Object}   options           Request options.
	 * @param {string}   options.action    AJAX action.
	 * @param {Object}   options.data      Additional data.
	 * @param {Function} options.onSuccess Success callback.
	 * @param {Function} options.onError   Error callback.
	 * @param {Function} options.onComplete Complete callback.
	 */
	function ajax( { action, data = {}, onSuccess, onError, onComplete } ) {
		const formData = new FormData();
		formData.append( 'action', action );
		formData.append( 'nonce', nonce );

		Object.entries( data ).forEach( ( [ key, value ] ) => {
			if ( Array.isArray( value ) ) {
				value.forEach( ( v ) => formData.append( `${ key }[]`, v ) );
			} else {
				formData.append( key, value );
			}
		} );

		fetch( window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( ( response ) => response.json() )
			.then( ( response ) => {
				if ( response.success ) {
					if ( onSuccess ) {
						onSuccess( response );
					}
				} else {
					if ( onError ) {
						onError( response );
					}
				}
			} )
			.catch( () => {
				if ( onError ) {
					onError( null );
				}
			} )
			.finally( () => {
				if ( onComplete ) {
					onComplete();
				}
			} );
	}

	/**
	 * Handle bulk approve.
	 */
	const bulkApproveBtn = $( '#vmfa-bulk-approve' );
	if ( bulkApproveBtn ) {
		bulkApproveBtn.addEventListener( 'click', function () {
			const ids = getSelectedIds();

			if ( ids.length === 0 ) {
				return;
			}

			if ( ! confirm( i18n?.confirmApprove || 'Approve selected items?' ) ) {
				return;
			}

			const originalText = this.textContent;
			this.disabled = true;
			this.textContent = i18n?.saving || 'Processing…';

			ajax( {
				action: 'vmfa_bulk_approve',
				data: { ids },
				onSuccess: ( response ) => {
					ids.forEach( ( id ) => {
						const item = $( `.vmfa-review-item[data-id="${ id }"]` );
						if ( item ) {
							fadeOutAndRemove( item, updateEmptyState );
						}
					} );
					showNotice( 'success', response.data.message );
				},
				onError: ( response ) => {
					showNotice( 'error', response?.data?.message || i18n?.error || 'An error occurred.' );
				},
				onComplete: () => {
					this.disabled = false;
					this.textContent = originalText;
					updateSelectionState();
				},
			} );
		} );
	}

	/**
	 * Handle bulk assign.
	 */
	const bulkAssignBtn = $( '#vmfa-bulk-assign' );
	if ( bulkAssignBtn ) {
		bulkAssignBtn.addEventListener( 'click', function () {
			const ids = getSelectedIds();
			const folderSelect = $( '#vmfa-assign-folder' );
			const folderId = folderSelect?.value;

			if ( ids.length === 0 || ! folderId ) {
				return;
			}

			const originalText = this.textContent;
			this.disabled = true;
			this.textContent = i18n?.saving || 'Processing…';

			ajax( {
				action: 'vmfa_bulk_assign',
				data: { ids, folder_id: folderId },
				onSuccess: ( response ) => {
					ids.forEach( ( id ) => {
						const item = $( `.vmfa-review-item[data-id="${ id }"]` );
						if ( item ) {
							fadeOutAndRemove( item, updateEmptyState );
						}
					} );
					showNotice( 'success', response.data.message );
				},
				onError: ( response ) => {
					showNotice( 'error', response?.data?.message || i18n?.error || 'An error occurred.' );
				},
				onComplete: () => {
					this.disabled = false;
					this.textContent = originalText;
					if ( folderSelect ) {
						folderSelect.value = '';
					}
					updateSelectionState();
				},
			} );
		} );
	}

	/**
	 * Handle single approve button.
	 */
	document.addEventListener( 'click', function ( e ) {
		const button = e.target.closest( '.vmfa-approve-single' );
		if ( ! button ) {
			return;
		}

		const id = parseInt( button.dataset.id, 10 );
		const originalText = button.textContent;

		button.disabled = true;
		button.textContent = '…';

		ajax( {
			action: 'vmfa_bulk_approve',
			data: { ids: [ id ] },
			onSuccess: () => {
				const item = button.closest( '.vmfa-review-item' );
				if ( item ) {
					fadeOutAndRemove( item, updateEmptyState );
				}
			},
			onError: ( response ) => {
				showNotice( 'error', response?.data?.message || i18n?.error || 'An error occurred.' );
				button.disabled = false;
				button.textContent = originalText;
			},
		} );
	} );

	/**
	 * Show admin notice.
	 *
	 * @param {string} type    Notice type (success, error).
	 * @param {string} message Notice message.
	 */
	function showNotice( type, message ) {
		const notice = document.createElement( 'div' );
		notice.className = `notice notice-${ type } is-dismissible`;
		notice.innerHTML = `<p>${ message }</p>`;

		const heading = $( '.wrap > h1' );
		if ( heading ) {
			heading.insertAdjacentElement( 'afterend', notice );
		}

		// Auto-dismiss after 5 seconds.
		setTimeout( () => {
			fadeOutAndRemove( notice );
		}, 5000 );
	}

	/**
	 * Check if list is empty and show message.
	 */
	function updateEmptyState() {
		if ( $$( '.vmfa-review-item' ).length === 0 ) {
			const grid = $( '.vmfa-review-grid' );
			if ( grid ) {
				const empty = document.createElement( 'div' );
				empty.className = 'vmfa-review-empty';
				empty.innerHTML = '<p>No media items need review.</p>';
				grid.replaceWith( empty );
			}
			const actions = $( '.vmfa-review-actions' );
			if ( actions ) {
				actions.style.display = 'none';
			}
		}
	}

	// Initialize.
	document.addEventListener( 'DOMContentLoaded', updateSelectionState );
} )();
