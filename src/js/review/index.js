/**
 * Review page entry point.
 *
 * @package VmfaEditorialWorkflow
 */

import '../../css/review.css';

( function () {
	'use strict';

	const { nonce, i18n } = window.vmfaReview || {};

	/**
	 * Track items currently being processed to prevent double-actions.
	 * @type {Set<number>}
	 */
	const processingIds = new Set();

	/**
	 * Track if a bulk operation is in progress.
	 * @type {boolean}
	 */
	let isBulkProcessing = false;

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

		// Update card selected states.
		$$( '.vmfa-media-card' ).forEach( ( card ) => {
			const checkbox = $( 'input[name="vmfa-items[]"]', card );
			card.classList.toggle( 'is-selected', checkbox?.checked );
		} );

		// Enable/disable destination dropdown and action button.
		const destinationSelect = $( '#vmfa-destination-folder' );
		const actionBtn = $( '#vmfa-bulk-action' );

		if ( destinationSelect ) {
			destinationSelect.disabled = count === 0;
		}

		if ( actionBtn ) {
			// Button enabled only when items selected AND destination chosen.
			const hasDestination = destinationSelect && destinationSelect.value !== '';
			actionBtn.disabled = count === 0 || ! hasDestination;
		}
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
				selectAllEl.indeterminate = checked.length > 0 && checked.length < all.length;
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
		el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
		el.style.opacity = '0';
		el.style.transform = 'scale(0.95)';
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
	 * Handle destination dropdown change.
	 */
	const destinationSelect = $( '#vmfa-destination-folder' );
	if ( destinationSelect ) {
		destinationSelect.addEventListener( 'change', function () {
			const actionBtn = $( '#vmfa-bulk-action' );
			if ( actionBtn ) {
				const hasSelection = $$( 'input[name="vmfa-items[]"]:checked' ).length > 0;
				actionBtn.disabled = ! hasSelection || this.value === '';
			}
		} );
	}

	/**
	 * Handle bulk action (approve or move).
	 */
	const bulkActionBtn = $( '#vmfa-bulk-action' );
	if ( bulkActionBtn ) {
		bulkActionBtn.addEventListener( 'click', function () {
			// Prevent concurrent bulk operations.
			if ( isBulkProcessing ) {
				return;
			}

			const ids = getSelectedIds().filter( ( id ) => ! processingIds.has( id ) );
			const destination = $( '#vmfa-destination-folder' )?.value;

			if ( ids.length === 0 || ! destination ) {
				return;
			}

			const isApprove = destination === 'approve';
			const confirmMessage = isApprove
				? ( i18n?.confirmApprove || 'Approve selected items?' )
				: ( i18n?.confirmMove || 'Move selected items to this folder?' );

			if ( ! confirm( confirmMessage ) ) {
				return;
			}

			isBulkProcessing = true;
			ids.forEach( ( id ) => processingIds.add( id ) );

			const originalText = this.textContent;
			this.disabled = true;
			this.textContent = i18n?.saving || 'Processing…';

			const ajaxAction = isApprove ? 'vmfa_bulk_approve' : 'vmfa_bulk_assign';
			const ajaxData = isApprove ? { ids } : { ids, folder_id: destination };

			ajax( {
				action: ajaxAction,
				data: ajaxData,
				onSuccess: ( response ) => {
					ids.forEach( ( id ) => {
						const item = $( `.vmfa-media-card[data-id="${ id }"]` );
						if ( item ) {
							fadeOutAndRemove( item, updateEmptyState );
						}
					} );
					showNotice( 'success', response.data.message );
				},
				onError: ( response ) => {
					showNotice( 'error', response?.data?.message || i18n?.error || 'An error occurred.' );
					ids.forEach( ( id ) => processingIds.delete( id ) );
				},
				onComplete: () => {
					this.disabled = false;
					this.textContent = originalText;
					isBulkProcessing = false;
					const destSelect = $( '#vmfa-destination-folder' );
					if ( destSelect ) {
						destSelect.value = '';
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

		// Prevent double-click or action on already processing item.
		if ( processingIds.has( id ) ) {
			return;
		}

		processingIds.add( id );
		const card = button.closest( '.vmfa-media-card' );

		if ( card ) {
			card.classList.add( 'is-processing' );
		}
		button.disabled = true;

		ajax( {
			action: 'vmfa_bulk_approve',
			data: { ids: [ id ] },
			onSuccess: () => {
				if ( card ) {
					fadeOutAndRemove( card, updateEmptyState );
				}
			},
			onError: ( response ) => {
				showNotice( 'error', response?.data?.message || i18n?.error || 'An error occurred.' );
				processingIds.delete( id );
				button.disabled = false;
				if ( card ) {
					card.classList.remove( 'is-processing' );
				}
			},
		} );
	} );

	/**
	 * Handle thumbnail click for preview modal.
	 */
	document.addEventListener( 'click', function ( e ) {
		const thumbnail = e.target.closest( '.vmfa-card-thumbnail' );
		if ( ! thumbnail ) {
			return;
		}

		// Don't open modal if clicking on checkbox.
		if ( e.target.closest( '.vmfa-card-checkbox' ) ) {
			return;
		}

		const fullUrl = thumbnail.dataset.full;
		const title = thumbnail.dataset.title;

		if ( ! fullUrl ) {
			return;
		}

		openPreviewModal( fullUrl, title );
	} );

	/**
	 * Open image preview modal.
	 *
	 * @param {string} imageUrl Image URL.
	 * @param {string} title    Image title.
	 */
	function openPreviewModal( imageUrl, title ) {
		// Create modal overlay.
		const overlay = document.createElement( 'div' );
		overlay.className = 'vmfa-modal-overlay';
		overlay.innerHTML = `
			<div class="vmfa-modal-content">
				<button type="button" class="vmfa-modal-close">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
				<img src="${ imageUrl }" alt="${ title }" class="vmfa-modal-image" />
				<div class="vmfa-modal-details">
					<h3 class="vmfa-modal-title">${ title }</h3>
				</div>
			</div>
		`;

		document.body.appendChild( overlay );
		document.body.style.overflow = 'hidden';

		// Close on overlay click.
		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay || e.target.closest( '.vmfa-modal-close' ) ) {
				closePreviewModal( overlay );
			}
		} );

		// Close on Escape key.
		document.addEventListener( 'keydown', function escHandler( e ) {
			if ( e.key === 'Escape' ) {
				closePreviewModal( overlay );
				document.removeEventListener( 'keydown', escHandler );
			}
		} );
	}

	/**
	 * Close preview modal.
	 *
	 * @param {Element} overlay Modal overlay element.
	 */
	function closePreviewModal( overlay ) {
		overlay.style.opacity = '0';
		document.body.style.overflow = '';
		setTimeout( () => {
			overlay.remove();
		}, 200 );
	}

	/**
	 * Show admin notice.
	 *
	 * @param {string} type    Notice type (success, error).
	 * @param {string} message Notice message.
	 */
	function showNotice( type, message ) {
		// Remove any existing notices first.
		$$( '.vmfa-notice' ).forEach( ( n ) => n.remove() );

		const notice = document.createElement( 'div' );
		notice.className = `notice notice-${ type } is-dismissible vmfa-notice`;
		notice.innerHTML = `<p>${ message }</p><button type="button" class="notice-dismiss"></button>`;

		const header = $( '.vmfa-review-header' );
		if ( header ) {
			header.insertAdjacentElement( 'afterend', notice );
		}

		// Handle dismiss button.
		const dismissBtn = $( '.notice-dismiss', notice );
		if ( dismissBtn ) {
			dismissBtn.addEventListener( 'click', () => fadeOutAndRemove( notice ) );
		}

		// Auto-dismiss after 5 seconds.
		setTimeout( () => {
			if ( notice.parentNode ) {
				fadeOutAndRemove( notice );
			}
		}, 5000 );
	}

	/**
	 * Check if list is empty and show message.
	 */
	function updateEmptyState() {
		if ( $$( '.vmfa-media-card' ).length === 0 ) {
			const grid = $( '.vmfa-review-grid' );
			if ( grid ) {
				const empty = document.createElement( 'div' );
				empty.className = 'vmfa-review-empty';
				empty.innerHTML = `
					<span class="dashicons dashicons-yes-alt"></span>
					<h2>All caught up!</h2>
					<p>No media items are waiting for review. New uploads will appear here.</p>
				`;
				grid.replaceWith( empty );
			}
			const toolbar = $( '.vmfa-review-toolbar' );
			if ( toolbar ) {
				toolbar.style.display = 'none';
			}

			// Update header badge.
			const badge = $( '.vmfa-review-count-badge' );
			if ( badge ) {
				badge.remove();
			}
		}

		// Update count badge.
		const remaining = $$( '.vmfa-media-card' ).length;
		const badge = $( '.vmfa-review-count-badge' );
		if ( badge && remaining > 0 ) {
			badge.textContent = remaining;
		}
	}

	// Initialize.
	document.addEventListener( 'DOMContentLoaded', updateSelectionState );
} )();
