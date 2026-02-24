<?php
/**
 * Date Picker Controller
 *
 * Generates date picker dropdown with only Friday/Saturday dates.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Date picker controller class.
 */
class WTG_Date_Picker_Controller {

	/**
	 * Get next N weekends (Friday-Saturday pairs).
	 *
	 * @param int $count Number of weekends to return (default: 8).
	 * @return array Array of weekend data with Friday and Saturday dates.
	 */
	public static function get_upcoming_weekends( $count = 12 ) {
		$weekends = array();
		$current_date = strtotime( 'today' );

		// Find the next Friday
		$day_of_week = date( 'N', $current_date ); // 1 = Monday, 7 = Sunday

		if ( $day_of_week <= 5 ) {
			// Today is Monday-Friday, get next Friday
			$days_until_friday = 5 - $day_of_week;
			$next_friday = strtotime( "+{$days_until_friday} days", $current_date );
		} else {
			// Today is Saturday or Sunday, get next week's Friday
			$days_until_friday = ( 7 - $day_of_week ) + 5;
			$next_friday = strtotime( "+{$days_until_friday} days", $current_date );
		}

		// Generate N weekends starting from next Friday
		for ( $i = 0; $i < $count; $i++ ) {
			$friday = strtotime( "+{$i} weeks", $next_friday );
			$saturday = strtotime( '+1 day', $friday );

			$weekends[] = array(
				'friday'         => date( 'Y-m-d', $friday ),
				'saturday'       => date( 'Y-m-d', $saturday ),
				'friday_label'   => date( 'l, F j, Y', $friday ), // e.g., "Friday, December 15, 2025"
				'saturday_label' => date( 'l, F j, Y', $saturday ),
				'weekend_label'  => date( 'M j', $friday ) . ' - ' . date( 'M j, Y', $saturday ), // e.g., "Dec 15 - Dec 16, 2025"
			);
		}

		return $weekends;
	}

	/**
	 * Get dropdown options for date picker.
	 *
	 * Returns array of both Friday and Saturday dates as separate options.
	 *
	 * @param int $count Number of weekends to include (default: 8).
	 * @return array Array of date options with 'value' and 'label' keys.
	 */
	public static function get_date_options( $count = 8 ) {
		$weekends = self::get_upcoming_weekends( $count );
		$options = array();

		foreach ( $weekends as $weekend ) {
			// Add Friday option
			$options[] = array(
				'value' => $weekend['friday'],
				'label' => $weekend['friday_label'],
			);

			// Add Saturday option
			$options[] = array(
				'value' => $weekend['saturday'],
				'label' => $weekend['saturday_label'],
			);
		}

		return $options;
	}

	/**
	 * Generate HTML for custom date picker dropdown.
	 *
	 * @param int    $form_id  Gravity Forms form ID.
	 * @param int    $field_id Gravity Forms field ID.
	 * @param string $selected_value Currently selected date value.
	 * @return string HTML for date picker dropdown.
	 */
	public static function render_date_picker( $form_id, $field_id, $selected_value = '' ) {
		$options = self::get_date_options();
		$field_name = "input_{$field_id}";
		$field_id_attr = "input_{$form_id}_{$field_id}";

		$html = sprintf(
			'<select name="%s" id="%s" class="wtg-date-picker large">',
			esc_attr( $field_name ),
			esc_attr( $field_id_attr )
		);

		// Add placeholder option
		$html .= '<option value="">Select a tour date...</option>';

		// Add date options with formatted display (e.g., "Sat • Dec 20, 2025")
		foreach ( $options as $option ) {
			$selected = selected( $selected_value, $option['value'], false );
			$date = strtotime( $option['value'] );
			$formatted_label = date( 'D', $date ) . ' • ' . date( 'M j, Y', $date );
			$html .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $option['value'] ),
				$selected,
				esc_html( $formatted_label )
			);
		}

		$html .= '</select>';

		return $html;
	}
}
