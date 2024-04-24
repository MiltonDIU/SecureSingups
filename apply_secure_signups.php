<?php
// Ensure direct access is blocked
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add a filter to check registration errors
add_filter('registration_errors', 'secure_signups_email_registration', 10, 3);

/**
 * Validate the user registration email domain.
 *
 * @param WP_Error $errors The WP_Error object to store registration errors.
 * @param string $sanitized_user_login The sanitized username of the user.
 * @param string $user_email The email address of the user.
 * @return WP_Error Modified WP_Error object with potential errors added.
 */
function secure_signups_email_registration($errors, $sanitized_user_login, $user_email) {
    global $wpdb;
    $dbconnect = $wpdb;
    $settings_cache_key = 'secure_signups_settings';
    $settings = wp_cache_get($settings_cache_key);
    if ($settings === false) {
        $settings = $dbconnect->get_row(
            "SELECT is_restriction, message, publicly_view FROM {$dbconnect->prefix}secure_signups_settings LIMIT 1"
        );
        wp_cache_set($settings_cache_key, $settings);
    }
    if ($settings->is_restriction != 1) {
        return $errors;
    }
    $domains_cache_key = 'secure_signups_domains';
    $allowed_domains = wp_cache_get($domains_cache_key);
    if ($allowed_domains === false) {
        $allowed_domains = $dbconnect->get_col(
            "SELECT domain_name FROM {$dbconnect->prefix}secure_signups_list_of_domains WHERE is_active = 1"
        );
        wp_cache_set($domains_cache_key, $allowed_domains);
    }
    // Process user email and domains...
    $user_email_parts = explode('@', $user_email);
    $domain = end($user_email_parts);
    if (!in_array($domain, $allowed_domains)) {
        $allowed_domains_str = implode(', ', $allowed_domains);
        if ($settings->publicly_view == 1) {
            $txt = sprintf("%s.",$settings->message);
            $errors->add('invalid_email',$txt);
        } else {
            $errors->add('invalid_email', __('Only selected domain emails are allowed for registration.', 'secure-signups'));
        }
    }
    return $errors;
}
