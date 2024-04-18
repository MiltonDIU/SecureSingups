<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_filter('registration_errors', 'secure_signups_email_registration', 10, 3);
function secure_signups_email_registration($errors, $sanitized_user_login, $user_email) {
    global $wpdb;

    $settings_table = $wpdb->prefix . 'secure_signups_settings';

    $is_restriction_active = $wpdb->get_var( $wpdb->prepare( "SELECT is_restriction FROM $settings_table LIMIT 1" ) );

    $message = $wpdb->get_var($wpdb->prepare("SELECT message FROM $settings_table LIMIT 1"));

    $publicly_view = $wpdb->get_var($wpdb->prepare("SELECT publicly_view FROM $settings_table LIMIT 1"));

    if ($is_restriction_active != 1) {
        return $errors;
    }

    $domains_table = $wpdb->prefix . 'secure_signups_list_of_domains';

    $allowed_domains = $wpdb->get_col($wpdb->prepare("SELECT domain_name FROM $domains_table WHERE is_active = 1"));

    $user_email_parts = explode('@', $user_email);
    $domain = end($user_email_parts);

    if (!in_array($domain, $allowed_domains)) {
        $allowed_domains_str = implode(', ', $allowed_domains);
        if ($publicly_view == 1) {
            $errors->add('invalid_email', sprintf(__($message, 'secure-signups')));
        } else {
            $errors->add('invalid_email', sprintf(__('Only selected domain emails are allowed for registration.', 'secure-signups')));
        }
    }

    return $errors;
}
