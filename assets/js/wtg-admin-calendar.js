/**
 * Admin Calendar Interactions
 *
 * Handles click events on calendar slots to toggle date overrides.
 *
 * @package WTG2
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		initCalendarInteractions();
	});

	/**
	 * Initialize calendar click handlers.
	 */
	function initCalendarInteractions() {
		// Handle slot clicks.
		$(document).on('click', '.wtg-calendar-slot:not(.locked)', function() {
			const $slot = $(this);
			const date = $slot.data('date');
			const timeSlot = $slot.data('slot');
			const isOverride = $slot.data('override') === 1;

			// Don't allow toggling naturally full slots.
			if ($slot.hasClass('naturally-full')) {
				alert('This slot is naturally full from bookings. It cannot be manually toggled.');
				return;
			}

			// Confirm action.
			let confirmMessage;
			if (isOverride) {
				confirmMessage = 'Remove manual override and make this slot available?';
			} else {
				confirmMessage = 'Mark this slot as manually full and unavailable?';
			}

			if (!confirm(confirmMessage)) {
				return;
			}

			// Show loading state.
			$slot.css('opacity', '0.5');

			// AJAX request to toggle override.
			$.ajax({
				url: wtgAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wtg_toggle_date_override',
					nonce: wtgAdmin.nonce,
					date: date,
					time_slot: timeSlot,
					reason: isOverride ? '' : 'Manually marked as full by admin'
				},
				success: function(response) {
					if (response.success) {
						// Toggle the override state.
						const newOverrideState = response.data.is_full;
						$slot.data('override', newOverrideState ? 1 : 0);

						// Update classes and title.
						if (newOverrideState) {
							$slot.removeClass('available').addClass('manually-full');
							$slot.attr('title', 'Manually marked as full. Click to remove override.');
						} else {
							$slot.removeClass('manually-full').addClass('available');
							const sold = parseInt($slot.data('sold')) || 0;
							const capacity = parseInt($slot.data('capacity')) || 14;
							$slot.attr('title', `Available (${sold}/${capacity} seats sold). Click to mark as full.`);
						}

						// Restore opacity.
						$slot.css('opacity', '1');
					} else {
						alert('Error: ' + (response.data.message || 'Failed to update override.'));
						$slot.css('opacity', '1');
					}
				},
				error: function(xhr, status, error) {
					alert('AJAX error: ' + error);
					$slot.css('opacity', '1');
				}
			});
		});

		// Show tooltip on hover.
		$(document).on('mouseenter', '.wtg-calendar-slot', function() {
			const title = $(this).attr('title');
			if (title) {
				$(this).attr('data-title', title);
			}
		});
	}

})(jQuery);
