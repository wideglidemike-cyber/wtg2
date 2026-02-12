/**
 * Availability & Progressive Slot Unlock
 *
 * Handles dynamic time slot updates based on availability and progressive unlock logic.
 *
 * @package WTG2
 */

(function($) {
	'use strict';

	// Configuration — form ID is passed from PHP via wp_localize_script.
	const FORM_ID = (typeof wtgAjax !== 'undefined' && wtgAjax.bookingFormId) ? parseInt(wtgAjax.bookingFormId) : 2;
	const FIELD_TOUR_DATE = 1;
	const FIELD_TIME_SLOT = 2;
	const FIELD_TICKETS = 3;

	let currentDate = '';
	let currentTimeSlot = '';
	let currentTickets = 1;

	/**
	 * Initialize availability checking on document ready.
	 */
	$(document).ready(function() {
		// Check if we're on the booking form
		if ($('#gform_' + FORM_ID).length === 0) {
			return;
		}

		// Wait for Gravity Forms to fully render before initializing
		// GF sometimes renders radio buttons after document.ready
		setTimeout(function() {
			initAvailabilityChecking();

			// Load seating grid on page load
			loadSeatingGrid();
		}, 100);
	});

	/**
	 * Initialize availability checking.
	 */
	function initAvailabilityChecking() {
		// Listen for date changes
		$(document).on('change', '#input_' + FORM_ID + '_' + FIELD_TOUR_DATE, function() {
			currentDate = $(this).val();
			if (currentDate) {
				checkAvailability();
			}
			loadSeatingGrid(); // Load grid when date changes
		});

		// Listen for time slot changes
		$(document).on('change', 'input[name="input_' + FIELD_TIME_SLOT + '"]', function() {
			currentTimeSlot = $(this).val();
			loadSeatingGrid(); // Load grid when time slot changes
		});

		// Listen for ticket quantity changes
		$(document).on('change', '#input_' + FORM_ID + '_' + FIELD_TICKETS, function() {
			currentTickets = parseInt($(this).val()) || 1;

			// Check availability if date is selected
			if (currentDate) {
				checkAvailability();
			}
		});

		// Check on page load if date is already selected
		const initialDate = $('#input_' + FORM_ID + '_' + FIELD_TOUR_DATE).val();
		const initialTickets = $('#input_' + FORM_ID + '_' + FIELD_TICKETS).val();

		if (initialDate) {
			currentDate = initialDate;
			currentTickets = parseInt(initialTickets) || 1;
			checkAvailability();
		}
	}

	/**
	 * Check availability for the selected date.
	 */
	function checkAvailability() {
		if (!currentDate) {
			return;
		}

		// Show loading state
		showLoadingState();

		$.ajax({
			url: wtgAjax.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wtg_check_availability',
				nonce: wtgAjax.nonceAvailability,
				tour_date: currentDate,
				tickets: currentTickets
			},
			success: function(response) {
				if (response.success && response.data.slots) {
					updateTimeSlotField(response.data.slots);
				} else {
					console.error('WTG2: Failed to check availability', response);
					hideLoadingState();
				}
			},
			error: function(xhr, status, error) {
				console.error('WTG2: AJAX error checking availability', error);
				hideLoadingState();
			}
		});
	}

	/**
	 * Update the time slot field with availability info.
	 *
	 * @param {object} slots Slot availability data.
	 */
	function updateTimeSlotField(slots) {
		// For radio buttons, use name selector; for others use ID selector
		const $timeSlotField = $('input[name="input_' + FIELD_TIME_SLOT + '"]');
		const $fieldContainer = $timeSlotField.first().closest('.gfield');

		// Hide loading state
		hideLoadingState();

		// If it's a radio field, update each choice
		if ($timeSlotField.is(':radio')) {
			updateRadioChoices(slots);
		} else if ($timeSlotField.is('select')) {
			updateSelectOptions(slots);
		}

		// Add availability summary after the field
		addAvailabilitySummary($fieldContainer, slots);
	}

	/**
	 * Update radio button choices with availability.
	 *
	 * @param {object} slots Slot availability data.
	 */
	function updateRadioChoices(slots) {
		const slotOrder = ['sat_am', 'sat_pm', 'fri_pm', 'fri_am'];

		slotOrder.forEach(function(slotKey) {
			const slotData = slots[slotKey];
			const $radio = $('input[name="input_' + FIELD_TIME_SLOT + '"][value="' + slotKey + '"]');

			// Find label - try multiple methods for robustness
			let $label = $('label[for="' + $radio.attr('id') + '"]');
			if ($label.length === 0) {
				$label = $radio.siblings('label');
			}
			if ($label.length === 0) {
				$label = $radio.closest('li').find('label');
			}

			if (!slotData) {
				return;
			}

			if (slotData.available) {
				// Enable and show available
				$radio.prop('disabled', false);
				$label.removeClass('wtg-slot-unavailable').addClass('wtg-slot-available');

				// Add availability info
				const availInfo = ' (' + slotData.remaining + ' seats left)';
				let labelText = $label.text().replace(/\s*\(\d+\s+seats?\s+left\)/, '');
				labelText = labelText.replace(/\s*\(.*?\)/, ''); // Remove any existing parenthetical
				$label.html(labelText + '<span class="wtg-avail-info">' + availInfo + '</span>');
			} else {
				// Disable and mark unavailable
				$radio.prop('disabled', true);
				$label.removeClass('wtg-slot-available').addClass('wtg-slot-unavailable');

				// Add unavailable reason
				let labelText = $label.text().replace(/\s*\(.*?\)/, '');
				$label.html(labelText + '<span class="wtg-unavail-reason"> (Unavailable)</span>');
			}
		});
	}

	/**
	 * Update select dropdown options with availability.
	 *
	 * @param {object} slots Slot availability data.
	 */
	function updateSelectOptions(slots) {
		const $select = $('#input_' + FORM_ID + '_' + FIELD_TIME_SLOT);
		const slotOrder = ['sat_am', 'sat_pm', 'fri_pm', 'fri_am'];

		slotOrder.forEach(function(slotKey) {
			const slotData = slots[slotKey];
			const $option = $select.find('option[value="' + slotKey + '"]');

			if (!slotData || !$option.length) {
				return;
			}

			if (slotData.available) {
				$option.prop('disabled', false);
				const baseText = $option.text().replace(/\s*\(.*?\)/, '');
				$option.text(baseText + ' (' + slotData.remaining + ' seats left)');
			} else {
				$option.prop('disabled', true);
				const baseText = $option.text().replace(/\s*\(.*?\)/, '');
				$option.text(baseText + ' (Unavailable)');
			}
		});
	}

	/**
	 * Add availability summary below the time slot field.
	 *
	 * @param {jQuery} $container Field container element.
	 * @param {object} slots Slot availability data.
	 */
	function addAvailabilitySummary($container, slots) {
		// Remove existing summary
		$container.find('.wtg-availability-summary').remove();

		const $summary = $('<div class="wtg-availability-summary"></div>');
		let hasAvailable = false;

		Object.keys(slots).forEach(function(slotKey) {
			if (slots[slotKey].available) {
				hasAvailable = true;
			}
		});

		if (!hasAvailable) {
			$summary.html('<p class="wtg-no-availability"><strong>No time slots available for this date with ' + currentTickets + ' ticket(s).</strong> Try selecting fewer tickets or a different date.</p>');
			$summary.css({
				marginTop: '10px',
				padding: '10px',
				backgroundColor: '#fff3cd',
				border: '1px solid #ffc107',
				borderRadius: '4px',
				color: '#856404'
			});
		}

		if ($summary.html()) {
			$container.append($summary);
		}
	}

	/**
	 * Show loading state on time slot field.
	 */
	function showLoadingState() {
		const $fieldContainer = $('#input_' + FORM_ID + '_' + FIELD_TIME_SLOT).closest('.gfield');
		$fieldContainer.addClass('wtg-checking-availability');

		// Add spinner if it doesn't exist
		if ($fieldContainer.find('.wtg-spinner').length === 0) {
			$fieldContainer.append('<div class="wtg-spinner">Checking availability...</div>');
		}
	}

	/**
	 * Hide loading state.
	 */
	function hideLoadingState() {
		const $fieldContainer = $('#input_' + FORM_ID + '_' + FIELD_TIME_SLOT).closest('.gfield');
		$fieldContainer.removeClass('wtg-checking-availability');
		$fieldContainer.find('.wtg-spinner').remove();
	}

	/**
	 * Load seat-by-seat grid via AJAX.
	 * Only loads when both date and time slot are selected.
	 */
	function loadSeatingGrid() {
		const $gridContainer = $('#wtg-seat-grid-container');

		if ($gridContainer.length === 0) {
			return;
		}

		// Only load if both date and time slot are selected
		if (!currentDate || !currentTimeSlot) {
			$gridContainer.empty();
			return;
		}

		// Show loading indicator
		$gridContainer.html('<div class="wtg-spinner">Loading seat availability...</div>');

		$.ajax({
			url: wtgAjax.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wtg_get_seating_grid',
				nonce: wtgAjax.nonceAvailability,
				date: currentDate,
				time_slot: currentTimeSlot
			},
			success: function(response) {
				if (response.success && response.data.html) {
					$gridContainer.html(response.data.html);
				} else {
					console.error('WTG2: Failed to load seat grid', response);
					$gridContainer.html('<p>Unable to load seat availability.</p>');
				}
			},
			error: function(xhr, status, error) {
				console.error('WTG2: AJAX error loading seat grid', error);
				$gridContainer.html('<p>Unable to load seat availability.</p>');
			}
		});
	}

})(jQuery);
