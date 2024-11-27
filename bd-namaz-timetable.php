<?php
/*
 * Plugin Name: BD Namaz Timetable
 * Plugin URI : https://bdislamicqa.xyz/wordpress-plugin/
 * Description: BD Namaz Timetable is a comprehensive WordPress plugin designed to provide accurate daily Namaz (prayer) timings for districts across Bangladesh. add the shortcode `[bd_namaz_timetable]` to any page or post where you wish to display the prayer timetable.
 * Version: 1.0
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Author: Moursalin Islam & OnexusDev
 * Author URI: https://www.facebook.com/morsalinislam.bd
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bd-namaz-timetable
 * Domain Path: /languages
 * Tags: bangladesh, namaz timetable, prayer times, islamic calendar, salah times, hijri date, aladhan api, district-based timings, sahri and iftar, ramadan, islamic prayer, muslim prayer times, prayer countdown
 * Tested up to: 6.2
 * Stable tag: 1.0.0
 *
 * ------------------------------------------------------------------------
 * This plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * This plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this plugin. If not, see <https://www.gnu.org/licenses/>.
 * ------------------------------------------------------------------------
*/

if (!defined('ABSPATH')) exit;

class BD_Namaz_Timetable {
    public function __construct() {
        // Register the shortcode
        add_shortcode('bd_namaz_timetable', [$this, 'display_timetable']);
        
        // Enqueue custom styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
    }

   public function enqueue_styles() {
    if (has_shortcode(get_post()->post_content, 'bd_namaz_timetable')) {
        wp_enqueue_style('bd-namaz-timetable-styles', plugins_url('assets/style.css', __FILE__));
    }
}


    // Function to get current date in Gregorian and Hijri
    public function get_current_dates() {
        $gregorian_date = date('l, F j, Y'); // Example: "Tuesday, November 22, 2024"
        $hijri_date = $this->get_hijri_date();
        return [
            'gregorian' => $gregorian_date,
            'hijri' => $hijri_date
        ];
    }

    // Get the Hijri date (use an API or manual function as needed)
    public function get_hijri_date() {
        // Placeholder for actual Hijri date logic
        return '14 Rabi al-Awwal 1446 AH'; // Replace with actual Hijri date calculation
    }

    // Function to get prayer timings data from Aladhan API
    public function get_namaz_data($district) {
        $api_url = 'http://api.aladhan.com/v1/timingsByCity';
        $params = [
            'city' => $district,
            'country' => 'Bangladesh',
            'method' => 1, // University of Islamic Sciences, Karachi
            'school' => 1  // Hanafi School of Jurisprudence
        ];

        $response = wp_remote_get(add_query_arg($params, $api_url));

        if (is_wp_error($response)) {
            return false; // If API request fails, return false
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['data']['timings'])) {
            return $data['data']['timings']; // Return timings if available
        }

        return false; // Return false if no timings found
    }

    // Function to format the time in AM/PM format
    public function format_time_am_pm($time) {
        return date('g:i A', strtotime($time)); // Converts to AM/PM format
    }

    // Function to display the Namaz timetable
    public function display_timetable($atts) {
        // If form is submitted, save the selected district to the options table
        if (isset($_POST['bd_namaz_district'])) {
            // Save the selected district in the options table
            update_option('bd_namaz_district', sanitize_text_field($_POST['bd_namaz_district']));
        }

        // Get the selected district from the form or use default (Dhaka) if not selected
        $selected_district = isset($_POST['bd_namaz_district']) ? sanitize_text_field($_POST['bd_namaz_district']) : get_option('bd_namaz_district', 'Dhaka');
        
        // Fetch prayer timings from API
        $timings = $this->get_namaz_data($selected_district);

        if (!$timings) {
            return '<div class="bd-namaz-timetable">Unable to fetch Namaz timings.</div>';
        }

        // Get current Gregorian and Hijri dates
        $current_dates = $this->get_current_dates();

        // Create form for selecting district
        $output = '<form method="post" class="bd-namaz-form">';
        $output .= '<label for="bd_namaz_district">Select District:</label>';
        $output .= '<select id="bd_namaz_district" name="bd_namaz_district">';
        $districts = $this->get_bangladesh_districts();
        foreach ($districts as $district) {
            $output .= '<option value="' . esc_attr($district) . '"' . selected($selected_district, $district, false) . '>' . esc_html($district) . '</option>';
        }
        $output .= '</select>';
        $output .= '<input type="submit" value="Update Timings">';
        $output .= '</form>';

        // Display the prayer timetable
        $output .= '<div class="bd-namaz-timetable">';
        $output .= '<h3>Namaz Timings for ' . esc_html($selected_district) . '</h3>';

        // Display Gregorian and Hijri dates before Sahri time
        $output .= '<table class="timing-table">';
        $output .= '<thead>';
        $output .= '<tr><th>Prayer Time</th><th>Time</th></tr>';
        $output .= '</thead>';
        $output .= '<tbody>';

        // Display Gregorian and Hijri dates
        $output .= '<tr><td><strong>Current Gregorian Date:</strong></td><td>' . esc_html($current_dates['gregorian']) . '</td></tr>';
        $output .= '<tr><td><strong>Current Hijri Date:</strong></td><td>' . esc_html($current_dates['hijri']) . '</td></tr>';

        // Display Sahri and Iftar times in AM/PM format
        if (isset($timings['Fajr'])) {
            $output .= '<tr><td><strong>Sahri Time (Fajr Start):</strong></td><td>' . $this->format_time_am_pm($timings['Fajr']) . '</td></tr>';
        }
        if (isset($timings['Maghrib'])) {
            $output .= '<tr><td><strong>Iftar Time (Maghrib):</strong></td><td>' . $this->format_time_am_pm($timings['Maghrib']) . '</td></tr>';
        }

        // Loop through prayer timings and display in AM/PM format
        foreach ($timings as $time => $value) {
            if ($time !== 'Fajr' && $time !== 'Maghrib') {  // Avoid displaying Sahri and Iftar twice
                $formatted_time = $this->format_time_am_pm($value);
                $output .= '<tr><td>' . esc_html($time) . '</td><td>' . $formatted_time . '</td></tr>';
            }
        }

        $output .= '</tbody>';
        $output .= '</table>';
        $output .= '</div>';

        return $output;
    }

    // Function to get a list of Bangladesh districts (sample)
    public function get_bangladesh_districts() {
        return [
            'Bagerhat', 'Bandarban', 'Barguna', 'Barishal', 'Bhola', 'Bogura', 'Brahmanbaria', 'Chandpur', 'Chattogram', 'Chuadanga', 'Cox s Bazar', 'Cumilla', 'Dhaka', 'Dinajpur', 'Faridpur', 'Feni', 'Gaibandha', 'Gazipur', 'Gopalganj', 'Habiganj', 'Jamalpur', 'Jashore', 'Jhalokathi', 'Jhenaidah', 'Joypurhat', 'Khagrachari', 'Khulna', 'Kishoreganj', 'Kurigram', 'Kushtia', 'Lakshmipur', 'Lalmonirhat', 'Madaripur', 'Magura', 'Manikganj', 'Meherpur', 'Moulvibazar', 'Munshiganj', 'Mymensingh', 'Naogaon', 'Narail', 'Narayanganj', 'Narsingdi', 'Natore', 'Netrokona', 'Nilphamari', 'Noakhali', 'Pabna', 'Panchagarh', 'Patuakhali', 'Pirojpur', 'Rajbari', 'Rajshahi', 'Rangamati', 'Rangpur', 'Satkhira', 'Shariatpur', 'Sherpur', 'Sirajganj', 'Sunamganj', 'Sylhet', 'Tangail', 'Thakurgaon'
        ];
    }
}

// Instantiate the class to initialize the plugin
new BD_Namaz_Timetable();
// Hook to add custom links on the plugin page
add_filter('plugin_row_meta', 'bd_namaz_timetable_add_plugin_links', 10, 2);

function bd_namaz_timetable_add_plugin_links($links, $file) {
    // Make sure we're only adding links to this specific plugin
    if ($file == plugin_basename(__FILE__)) {
        // Add custom links
        $new_links = array(
            '<a href="https://github.com/moursalinislambd/bd-namaz-timetable/releases" target="_blank">Docs & FAQs</a>',
            '<a href="https://bdislamicqa.xyz/wordpress-plugin/" target="_blank">Check update</a>'
        );

        // Merge custom links with existing ones
        $links = array_merge($links, $new_links);
    }

    return $links;
	
	
	$output = '<div id="bd-namaz-timetable-plugin">';
$output .= '<form method="post" class="bd-namaz-form">';
// Existing content here
$output .= '</div>'; // Close the container

	
}


