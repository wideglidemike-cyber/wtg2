/**
 * Admin Bookings Interactions
 *
 * Form validation and auto-calculations for booking forms.
 *
 * @package WTG2
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		initBookingFormHandlers();
		initInvoiceActionHandlers();
	});

	/**
	 * Initialize booking form handlers.
	 */
	function initBookingFormHandlers() {
		// Auto-calculate deposit as 50% of total.
		$('#total_amount').on('input', function() {
			const total = parseFloat($(this).val()) || 0;
			const deposit = (total * 0.5).toFixed(2);
			$('#deposit_amount').val(deposit);
		});

		// Validate form before submission.
		$('form').on('submit', function(e) {
			const tourDate = $('#tour_date').val();
			const timeSlot = $('#time_slot').val();
			const customerName = $('#customer_name').val();
			const customerEmail = $('#customer_email').val();
			const tickets = parseInt($('#tickets').val()) || 0;
			const totalAmount = parseFloat($('#total_amount').val()) || 0;

			// Basic validation.
			if (!tourDate || !timeSlot || !customerName || !customerEmail || tickets < 1 || totalAmount <= 0) {
				alert('Please fill in all required fields.');
				e.preventDefault();
				return false;
			}

			// Validate email format.
			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			if (!emailRegex.test(customerEmail)) {
				alert('Please enter a valid email address.');
				e.preventDefault();
				return false;
			}

			// Validate deposit doesn't exceed total.
			const depositAmount = parseFloat($('#deposit_amount').val()) || 0;
			if (depositAmount > totalAmount) {
				alert('Deposit amount cannot exceed total amount.');
				e.preventDefault();
				return false;
			}

			return true;
		});
	}

	/**
	 * Initialize invoice action handlers.
	 */
	function initInvoiceActionHandlers() {
		// Send balance invoice button.
		$('.wtg-send-balance-invoice').on('click', function(e) {
			e.preventDefault();

			const $button = $(this);
			const bookingId = $button.data('booking-id');
			const $messageDiv = $('.wtg-invoice-message');

			if (!confirm('Send balance invoice to customer now?')) {
				return;
			}

			// Disable button and show loading state.
			$button.prop('disabled', true).text('Sending...');
			$messageDiv.html('');

			// Send AJAX request.
			$.ajax({
				url: wtgAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wtg_send_balance_invoice',
					nonce: wtgAdmin.nonce,
					booking_id: bookingId
				},
				success: function(response) {
					if (response.success) {
						$messageDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
						// Remove the button and show success state.
						$button.closest('p').html('<span style="color: #28a745;"><span class="dashicons dashicons-yes-alt"></span> Balance invoice sent successfully.</span>');
					} else {
						$messageDiv.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
						$button.prop('disabled', false).text('Send Balance Invoice');
					}
				},
				error: function() {
					$messageDiv.html('<div class="notice notice-error"><p>Error sending invoice. Please try again.</p></div>');
					$button.prop('disabled', false).text('Send Balance Invoice');
				}
			});
		});

		// Resend invoice email button.
		$('.wtg-resend-invoice-email').on('click', function(e) {
			e.preventDefault();

			const $button = $(this);
			const bookingId = $button.data('booking-id');
			const emailType = $button.data('email-type');
			const $messageDiv = $('.wtg-invoice-message');

			if (!confirm('Resend confirmation email to customer?')) {
				return;
			}

			// Disable button and show loading state.
			$button.prop('disabled', true);
			const originalText = $button.text();
			$button.text('Sending...');
			$messageDiv.html('');

			// Send AJAX request.
			$.ajax({
				url: wtgAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wtg_resend_invoice_email',
					nonce: wtgAdmin.nonce,
					booking_id: bookingId,
					email_type: emailType
				},
				success: function(response) {
					if (response.success) {
						$messageDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
						$button.prop('disabled', false).text(originalText);
						// Auto-hide success message after 3 seconds.
						setTimeout(function() {
							$messageDiv.fadeOut(400, function() {
								$(this).html('').show();
							});
						}, 3000);
					} else {
						$messageDiv.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
						$button.prop('disabled', false).text(originalText);
					}
				},
				error: function() {
					$messageDiv.html('<div class="notice notice-error"><p>Error sending email. Please try again.</p></div>');
					$button.prop('disabled', false).text(originalText);
				}
			});
		});
	}

})(jQuery);
