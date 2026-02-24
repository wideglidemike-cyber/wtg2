/**
 * Gift Certificate Validation
 *
 * Handles AJAX validation of gift certificate codes on the booking form.
 * When a valid code is entered, reduces the displayed GF total in real time.
 * Hides the credit card field when the GC fully covers the deposit.
 *
 * @package WTG2
 */

(function($) {
	'use strict';

	// Configuration — form ID is passed from PHP via wp_localize_script.
	const FORM_ID = (typeof wtgAjax !== 'undefined' && wtgAjax.bookingFormId) ? parseInt(wtgAjax.bookingFormId) : 2;
	const FIELD_ID = 14;
	const FIELD_SELECTOR = '#input_' + FORM_ID + '_' + FIELD_ID;
	const VALIDATION_DELAY = 800; // ms to wait after typing stops
	const CC_FIELD_SELECTOR = '.gfield--type-square_creditcard, .gfield_creditcard, .gfield_stripe_creditcard';

	let validationTimeout = null;
	let lastValidatedCode = '';
	let validatedCertAmount = 0;
	let gcFilterRegistered = false;

	/**
	 * Initialize validation on document ready.
	 */
	$(document).ready(function() {
		// Check if gift cert field exists on this page
		if ($(FIELD_SELECTOR).length === 0) {
			return;
		}

		initGiftCertValidation();
	});

	/**
	 * Register the GF product total filter for gift cert discount.
	 * Called once after first successful validation.
	 */
	function registerGiftCertFilter() {
		if (gcFilterRegistered) {
			return;
		}
		gcFilterRegistered = true;

		if (typeof gform !== 'undefined' && typeof gform.addFilter === 'function') {
			gform.addFilter('gform_product_total', function(total, formId) {
				if (formId != FORM_ID || validatedCertAmount <= 0) {
					return total;
				}
				return Math.max(0, total - validatedCertAmount);
			}, 60);
		}
	}

	/**
	 * Trigger GF price recalculation.
	 */
	function recalculateTotal() {
		if (typeof gformCalculateTotalPrice === 'function') {
			gformCalculateTotalPrice(FORM_ID);
		}
	}

	/**
	 * Get the current deposit total (before GC discount) by reading the displayed
	 * GF total and adding back the current GC discount amount.
	 */
	function getDepositTotal() {
		var displayedTotal = parseFloat(
			$('#gform_' + FORM_ID + ' .ginput_total').first().text().replace(/[^0-9.]/g, '')
		) || 0;
		return displayedTotal + validatedCertAmount;
	}

	/**
	 * Show or hide the credit card field based on whether the GC covers the deposit.
	 */
	function toggleCreditCardField() {
		var $ccField = $(CC_FIELD_SELECTOR);
		if ($ccField.length === 0) {
			return;
		}

		var depositTotal = getDepositTotal();
		var gcCoversDeposit = validatedCertAmount > 0 && validatedCertAmount >= depositTotal && depositTotal > 0;

		if (gcCoversDeposit) {
			$ccField.slideUp(200);
			if ($ccField.prev('.wtg-gc-no-payment-msg').length === 0) {
				$('<div class="wtg-gc-no-payment-msg" style="padding: 12px 16px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 10px; font-weight: 500;">Your gift certificate covers the deposit &mdash; no credit card needed.</div>')
					.insertBefore($ccField)
					.hide()
					.fadeIn(200);
			}
		} else {
			$ccField.slideDown(200);
			$('.wtg-gc-no-payment-msg').fadeOut(200, function() { $(this).remove(); });
		}
	}

	/**
	 * Initialize gift certificate validation.
	 */
	function initGiftCertValidation() {
		const $field = $(FIELD_SELECTOR);
		const $fieldContainer = $field.closest('.gfield');

		// Create validation message container
		if ($fieldContainer.find('.wtg-gc-validation-message').length === 0) {
			$fieldContainer.append('<div class="wtg-gc-validation-message" style="margin-top: 8px;"></div>');
		}

		// Bind input event with debouncing
		$field.on('input', function() {
			clearTimeout(validationTimeout);

			const code = $(this).val().trim();

			// Clear validation message if field is empty
			if (code === '') {
				clearValidationMessage();
				return;
			}

			// Don't validate the same code twice
			if (code === lastValidatedCode) {
				return;
			}

			// Show loading state
			showValidationMessage('Validating...', 'loading');

			// Debounce validation
			validationTimeout = setTimeout(function() {
				validateGiftCertCode(code);
			}, VALIDATION_DELAY);
		});

		// Also validate on blur
		$field.on('blur', function() {
			const code = $(this).val().trim();
			if (code !== '' && code !== lastValidatedCode) {
				validateGiftCertCode(code);
			}
		});
	}

	/**
	 * Validate gift certificate code via AJAX.
	 *
	 * @param {string} code The gift certificate code to validate.
	 */
	function validateGiftCertCode(code) {
		lastValidatedCode = code;

		$.ajax({
			url: wtgAjax.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wtg_validate_gift_cert',
				nonce: wtgAjax.nonceGiftCert,
				code: code
			},
			success: function(response) {
				if (response.success) {
					// Store the validated amount and update GF total.
					validatedCertAmount = parseFloat(response.data.amount.replace(/,/g, ''));
					registerGiftCertFilter();
					recalculateTotal();

					showValidationMessage(
						'Valid! Credit: $' + response.data.amount,
						'success'
					);
					toggleCreditCardField();
				} else {
					// Clear discount on invalid code.
					validatedCertAmount = 0;
					recalculateTotal();

					showValidationMessage(
						response.data.message || 'Invalid gift certificate code.',
						'error'
					);
					toggleCreditCardField();
				}
			},
			error: function() {
				validatedCertAmount = 0;
				recalculateTotal();

				showValidationMessage(
					'Unable to validate code. Please try again.',
					'error'
				);
				toggleCreditCardField();
			}
		});
	}

	/**
	 * Show validation message.
	 *
	 * @param {string} message The message to display.
	 * @param {string} type    The message type (loading, success, error).
	 */
	function showValidationMessage(message, type) {
		const $container = $('.wtg-gc-validation-message');

		// Remove existing classes
		$container.removeClass('wtg-loading wtg-success wtg-error');

		// Add type class
		$container.addClass('wtg-' + type);

		// Set message
		$container.html(message);

		// Apply styles based on type
		let styles = {
			padding: '8px 12px',
			borderRadius: '4px',
			fontSize: '14px',
			fontWeight: '500'
		};

		switch(type) {
			case 'loading':
				styles.backgroundColor = '#f0f0f0';
				styles.color = '#666';
				break;
			case 'success':
				styles.backgroundColor = '#d4edda';
				styles.color = '#155724';
				styles.border = '1px solid #c3e6cb';
				break;
			case 'error':
				styles.backgroundColor = '#f8d7da';
				styles.color = '#721c24';
				styles.border = '1px solid #f5c6cb';
				break;
		}

		$container.css(styles).fadeIn(200);
	}

	/**
	 * Clear validation message.
	 */
	function clearValidationMessage() {
		$('.wtg-gc-validation-message').fadeOut(200, function() {
			$(this).html('').removeAttr('style').removeClass('wtg-loading wtg-success wtg-error');
		});
		lastValidatedCode = '';

		// Reset discount and recalculate.
		if (validatedCertAmount > 0) {
			validatedCertAmount = 0;
			recalculateTotal();
		}
		toggleCreditCardField();
	}

})(jQuery);
