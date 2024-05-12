<?php
/**
 * Plugin Name: Secure Signups
 * Plugin URI:https://daffodilweb.com/secure-signups.php
 * Description: Secure Signups: Safeguard WordPress registrations. Restrict signups to approved domain emails, manage domains from the admin panel.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP:7.3
 * WordPress tested up to: 6.5.2
 * Author: Daffodil Web & E-commerce
 * Author URI: https://daffodilweb.com
 * Text Domain: SecureSignups
 * License:GPL v2 or later
 * License URI:https://www.gnu.org/licenses/gpl-2.0.html
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


// Define the constant
define('SECURE_SIGNUPS_MAX_ALLOWED_ROWS', 10);

function secure_signups_enqueue_styles() {
    // Enqueue the style with a version parameter
    wp_enqueue_style(
        'secure_signups_styles',
        plugins_url('css/secure_signups_styles.css', __FILE__),
        array(),
        '1.0.0'
    );
}
add_action('admin_enqueue_scripts', 'secure_signups_enqueue_styles');

function secure_signups_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script(
        'secure-signups-custom-script',
        plugins_url('js/custom-script.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );
    wp_localize_script(
        'secure-signups-custom-script',
        'secure_signups_ajax',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'security' => wp_create_nonce('secure-signups-ajax-nonce'),
            'update_domain_status_nonce' => wp_create_nonce('secure_signups_update_domain_status'),
            'update_domain_name_nonce' => wp_create_nonce('secure_signups_update_domain_name'),
        )
    );
}
add_action('admin_enqueue_scripts', 'secure_signups_enqueue_scripts');

// Register activation hook
register_activation_hook(__FILE__, 'secure_signups_install');

// Install the plugin
function secure_signups_install() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }
    global $wpdb;
    $secure_signups_dbconnect = $wpdb;
    $list_of_domains_table = $secure_signups_dbconnect->prefix . 'secure_signups_list_of_domains';
    $settings_table = $secure_signups_dbconnect->prefix . 'secure_signups_settings';
    $charset_collate = $secure_signups_dbconnect->get_charset_collate();

    // Prepare SQL queries with placeholders
    $sql_list_of_domains = "
        CREATE TABLE IF NOT EXISTS $list_of_domains_table (
            id INT NOT NULL AUTO_INCREMENT,
            domain_name VARCHAR(255) NOT NULL UNIQUE,
            is_active INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

    $sql_settings = "
        CREATE TABLE IF NOT EXISTS $settings_table (
            id INT NOT NULL AUTO_INCREMENT,
            is_restriction INT NOT NULL DEFAULT 1,
            message TEXT,
            publicly_view INT NOT NULL DEFAULT 0,
            retain_plugin_data INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

//    // Include wp-admin/includes/upgrade.php for dbDelta function
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
//
//    // Execute SQL queries using dbDelta
    dbDelta( $sql_list_of_domains );
    dbDelta( $sql_settings );

    // Check if settings table is empty and insert default values if needed
//    $existing_settings = $secure_signups_dbconnect->get_var( $secure_signups_dbconnect->prepare( "SELECT COUNT(*) FROM $settings_table" ));

    $existing_settings = $secure_signups_dbconnect->get_var( "SELECT COUNT(*) FROM $settings_table" );

    if ( $existing_settings == 0 ) {
        $secure_signups_dbconnect->insert(
            $settings_table,
            array(
                'is_restriction'      => 1,
                'publicly_view'       => 1,
                'retain_plugin_data'  => 1,
                'message'             => "Only selected domains are allowed to register. For more information or request please communicate via email."
            )
        );
    }
    // Create trigger
    secure_signups_create_trigger();
}
// Create a trigger to limit the number of domains
function secure_signups_create_trigger() {
    global $wpdb;
    $secure_signups_dbconnect = $wpdb;
    $table_name = $secure_signups_dbconnect->prefix . 'secure_signups_list_of_domains';
    $secure_signups_max_allowed_rows = SECURE_SIGNUPS_MAX_ALLOWED_ROWS;
    $trigger_sql = $secure_signups_dbconnect->prepare("
    CREATE TRIGGER secure_signups_limit_insert_trigger
    BEFORE INSERT ON $table_name
    FOR EACH ROW
    BEGIN
        DECLARE row_count INT;
        SELECT COUNT(*) INTO row_count FROM $table_name;
        IF row_count >= %d THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = %s;
        END IF;
    END;",
        $secure_signups_max_allowed_rows,
        "Cannot insert more than $secure_signups_max_allowed_rows rows into $table_name"
    );
    $secure_signups_dbconnect->query($trigger_sql);
}


// Register deactivation hook
register_deactivation_hook(__FILE__, 'secure_signups_uninstall');
// Uninstall the plugin
function secure_signups_uninstall() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    global $wpdb;
    $secure_signups_dbconnect = $wpdb;

    $settings_table = $secure_signups_dbconnect->prefix . 'secure_signups_settings';
    $list_of_domains_table = $secure_signups_dbconnect->prefix . 'secure_signups_list_of_domains';
    // Drop the trigger
    $trigger_sql = "DROP TRIGGER IF EXISTS secure_signups_limit_insert_trigger";
    $secure_signups_dbconnect->query($trigger_sql);
    // Check if the settings table exists
    $if_table_exist = $secure_signups_dbconnect->get_var($secure_signups_dbconnect->prepare("SHOW TABLES LIKE '%s'",$settings_table));
    if ( $if_table_exist == $settings_table ) {

        // Check if retain_plugin_data is set to 1
        $retain_data =$secure_signups_dbconnect->get_var($secure_signups_dbconnect->prepare("SELECT retain_plugin_data FROM $settings_table where retain_plugin_data = %s",1));
        // If retain_plugin_data is set to 1, exit without deleting tables
        if ( $retain_data == 1 ) {
            return;
        }else{
            // Drop tables if retain_plugin_data is not set
            $secure_signups_dbconnect->query( "DROP TABLE IF EXISTS $list_of_domains_table" );
            $secure_signups_dbconnect->query( "DROP TABLE IF EXISTS $settings_table" );
        }

    }
}
//// Add plugin menu
function secure_signups_menu() {
    add_menu_page('Secure Signups', 'Secure Signups', 'manage_options', 'secure-signups-menu', 'secure_signups_settings_page');
    add_submenu_page('secure-signups-menu', 'Settings', 'Settings', 'manage_options', 'secure-signups-menu', 'secure_signups_settings_page');
    add_submenu_page('secure-signups-menu', 'List of Domain', 'List of Domain', 'manage_options', 'secure-signups-add-new-domain', 'secure_signups_add_new_domain_page');
}
add_action('admin_menu', 'secure_signups_menu');

// Display settings page
function secure_signups_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    global $wpdb;
    $secure_signups_dbconnect = $wpdb;
    $settings_table =$secure_signups_dbconnect->prefix . 'secure_signups_settings';
    $current_setting = $secure_signups_dbconnect->get_row($secure_signups_dbconnect->prepare("SELECT is_restriction, message, publicly_view, retain_plugin_data FROM $settings_table WHERE id = %s", '1'));

    include plugin_dir_path( __FILE__ ) . 'include/settings.php';
}
// Save settings via AJAX
add_action('wp_ajax_secure_signups_save_settings', 'secure_signups_save_settings');

function secure_signups_save_settings() {
    global $wpdb;
    $secure_signups_dbconnect = $wpdb;
    if (!wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['secure_signups_nonce'])), 'secure_signups_save_settings_action')) {
        wp_send_json_error("Error:Security check failed. Please refresh the page and try again.");
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error($secure_signups_dbconnect->prepare("Error: You do not have permission to perform this action."));
        return;
    }

    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';

    if (!empty($message)) {
        $settings_table =$secure_signups_dbconnect->prefix . 'secure_signups_settings';
        $is_restriction = isset( $_POST['is_restriction'] ) ? 1 : 0;
        $publicly_view = isset( $_POST['publicly_view'] ) ? 1 : 0;
        $retain_plugin_data = isset( $_POST['retain_plugin_data'] ) ? 1 : 0;

        $length = strlen($message);
        if ($length < 1 || $length > 255) {
            wp_send_json_error("Invalid: The message! Please enter minimum number of character  is 1 and maximum character is 255.");
            return;
        }
        $result =$secure_signups_dbconnect->update(
            $settings_table,
            array(
                'is_restriction' => $is_restriction,
                'message' => $message,
                'publicly_view' => $publicly_view,
                'retain_plugin_data' => $retain_plugin_data,
            ),
            array( 'id' => 1 ),
            array( '%d', '%s', '%d', '%d' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            wp_send_json_success($secure_signups_dbconnect->prepare("Success: The domain settings were successfully updated!" ));
        } else {
            wp_send_json_error($secure_signups_dbconnect->prepare("Error: There was an error updating the domain settings." ));
        }
    } else {
        wp_send_json_error($secure_signups_dbconnect->prepare("Error: Insufficient data!") );
    }
}
//
//// Display add new domain page
function secure_signups_add_new_domain_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    global $wpdb;
    $secure_signups_dbconnect = $wpdb;
    $domain_table =$secure_signups_dbconnect->prefix . 'secure_signups_list_of_domains';

    // Include files using plugin_dir_path() to generate the correct file paths
    include plugin_dir_path( __FILE__ ) . 'include/new-domain.php';
// Define the query with a placeholder (e.g., %d for an integer limit) and an argument (e.g., 0 for no limit, as you want all rows)
    $query = "SELECT * FROM $domain_table LIMIT %d";
    $limit = 0; // You can specify a limit here if desired
// Prepare the query and provide the limit as an argument
    $prepared_query = $secure_signups_dbconnect->prepare($query, $limit);
// Fetch the results using the prepared query
    $domains = $secure_signups_dbconnect->get_results($prepared_query);
    // Include list-of-domain.php file
    include plugin_dir_path( __FILE__ ) . 'include/list-of-domain.php';
}


add_action('wp_ajax_secure_signups_save_new_domain', 'secure_signups_save_new_domain');

function secure_signups_save_new_domain() {
    global $wpdb;
    $secure_signups_dbconnect = $wpdb;// Declare global variable

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['secure_signups_nonce'])), 'secure_signups_save_new_domain_action')) {
        wp_send_json_error("Error: Nonce verification failed.");
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error($secure_signups_dbconnect->prepare("Error: You do not have permission to perform this action.") );
        return;
    }
    $domain_name = isset($_POST['domain_name']) ? sanitize_text_field($_POST['domain_name']) : '';

    if (!empty($domain_name)) {
        $domains_table =$secure_signups_dbconnect->prefix . 'secure_signups_list_of_domains';
        // Convert domain name to lowercase
        $domain_name = strtolower($domain_name);
        // Prepare and execute query to check if domain already exists
        $existing_domain =$secure_signups_dbconnect->get_row($secure_signups_dbconnect->prepare( "SELECT * FROM $domains_table WHERE domain_name = %s", $domain_name ) );
        if ( $existing_domain ) {
            wp_send_json_error("Error: The domain already exists in the list." );
            return;
        }


        // If the length is less than 1 or more than 63 characters, return an error
        $length = strlen($domain_name);
        if ($length < 1 || $length > 63) {
            wp_send_json_error("Invalid: The domain name format is invalid! Please enter a valid domain name.");
            return;
        }

        // Validate domain name format
        if ( ! preg_match( "/^[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$/", $domain_name ) ) {
            wp_send_json_error("Invalid: The domain name format is invalid! Please enter a valid domain name.");
            return;
        }

        // Check maximum allowed rows
        $existing_rows_count =$secure_signups_dbconnect->get_var($secure_signups_dbconnect->prepare( "SELECT COUNT(*) FROM $domains_table" ) );
        if ( $existing_rows_count >= SECURE_SIGNUPS_MAX_ALLOWED_ROWS ) {
            wp_send_json_error($secure_signups_dbconnect->prepare("Info: A maximum of %s domains can be whitelisted in the free version of the plugin.", SECURE_SIGNUPS_MAX_ALLOWED_ROWS) );
            return;
        }

        // Insert new domain
        $result  =$secure_signups_dbconnect->insert(
            $domains_table,
            array(
                'domain_name' => $domain_name,
                'is_active' => 1
            ),
            array( '%s', '%d' )
        );

        if ( $result ) {
            wp_send_json_success( $secure_signups_dbconnect->prepare("Success: New domain successfully added!" ));
        } else {
            wp_send_json_error( $secure_signups_dbconnect->prepare("Error: Failed to add the domain. Please try again." ));
        }
    } else {
        wp_send_json_error( $secure_signups_dbconnect->prepare("Error: Insufficient data!" ));
    }
}

//add_action('wp_ajax_secure_signups_get_domain_list', 'secure_signups_get_domain_list');

function secure_signups_get_domain_list() {
    global $wpdb;
    $secure_signups_dbconnect = $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error($secure_signups_dbconnect->prepare("Error: You do not have permission to perform this action." ));
        return;
    }


    $domains_table =$secure_signups_dbconnect->prefix . 'secure_signups_list_of_domains';

    // Prepare and execute query to fetch domain list
    $query =$secure_signups_dbconnect->prepare( "SELECT * FROM %s",$domains_table );
    $domains =$secure_signups_dbconnect->get_results( $query, ARRAY_A );

    if ( $domains === null ) {
        wp_send_json_error($secure_signups_dbconnect->prepare("Error: Failed to retrieve domain list.") );
        return;
    }

    wp_send_json_success( $domains );
}
//
add_action('admin_post_submit_domain', 'secure_signups_submit_domain');
add_action('wp_ajax_secure_signups_update_domain_status', 'secure_signups_update_domain_status');

function secure_signups_update_domain_status() {
    global $wpdb;
    $secure_signups_dbconnect = $wpdb;

    // Check nonce verification
    if (!wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'secure_signups_update_domain_status')) {
        wp_send_json_error("Error: Nonce verification failed.");
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error($secure_signups_dbconnect->prepare( "Error: You do not have permission to perform this action.") );
        return;
    }
    $domain_table =$secure_signups_dbconnect->prefix . 'secure_signups_list_of_domains';
    $domainId = isset( $_POST['domain_id'] ) ? intval( $_POST['domain_id'] ) : 0;
    $newStatus = isset( $_POST['new_status'] ) ? intval( $_POST['new_status'] ) : 0;

    if ( $domainId > 0 ) {
        // Prepare and execute the update query
        $result =$secure_signups_dbconnect->update(
            $domain_table,
            array( 'is_active' => $newStatus ),
            array( 'id' => $domainId ),
            array( '%d' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            wp_send_json_success($secure_signups_dbconnect->prepare( "Success: The domain status was successfully updated!" ));
        } else {
            wp_send_json_error( $secure_signups_dbconnect->prepare("Error: Failed to update the domain status." ));
        }
    } else {
        wp_send_json_error( $secure_signups_dbconnect->prepare("Error: Invalid domain ID provided." ));
    }
}

add_action('wp_ajax_secure_signups_update_domain_name', 'secure_signups_update_domain_name');

function secure_signups_update_domain_name() {
    global $wpdb;
    $secure_signups_dbconnect = $wpdb;
    // Check nonce verification
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'secure_signups_update_domain_name')) {
        wp_send_json_error("Error: Nonce verification failed.");
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( $secure_signups_dbconnect->prepare("Error: You do not have permission to perform this action." ));
        return;
    }
    // Validate and sanitize data
    $domainId = isset($_POST['domain_id']) ? intval($_POST['domain_id']) : 0;
    $newDomainName = isset($_POST['new_domain_name']) ? sanitize_text_field($_POST['new_domain_name']) : '';


//    if ( isset( $_POST['domain_id'] ) && isset( $_POST['new_domain_name'] ) ) {
        if ($domainId > 0 && !empty($newDomainName)) {
        $domain_table =$secure_signups_dbconnect->prefix . 'secure_signups_list_of_domains';

            $length = strlen($newDomainName);
            if ($length < 1 || $length > 63) {
                wp_send_json_error("Invalid: The domain name format is invalid! Please enter a valid domain name.");
                return;
            }


        if ( ! preg_match( "/^[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$/", $newDomainName ) ) {
            wp_send_json_error( $secure_signups_dbconnect->prepare("Invalid: The domain name format is invalid! Please enter a valid domain name." ));
            return;
        }
        $updated =$secure_signups_dbconnect->update(
            $domain_table,
            array( 'domain_name' => $newDomainName ),
            array( 'id' => $domainId ),
            array( '%s' ),
            array( '%d' )
        );

        if ( $updated === false ) {
            wp_send_json_error( $secure_signups_dbconnect->prepare("Error: Failed to update the domain name." ));
        } else {
            wp_send_json_success( $secure_signups_dbconnect->prepare("Success: Domain name successfully updated!" ));
        }
    } else {
        wp_send_json_error("Error: Invalid domain ID or empty domain name.");
    }
}
function secure_signups_copy_file_to_mu_plugins_folder() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    // Include the file and initialize WP_Filesystem
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    global $wp_filesystem;
    if ( ! WP_Filesystem() ) {
        // Handle error
        return;
    }

    $source_file = WP_CONTENT_DIR . '/plugins/SecureSignups/apply_secure_signups.php';
    $destination_folder = WP_CONTENT_DIR . '/mu-plugins';
    $destination_file = $destination_folder . '/apply_secure_signups.php';

    // Check if the destination folder exists, create it if it doesn't
    if ( ! $wp_filesystem->exists( $destination_folder ) ) {
        $wp_filesystem->mkdir( $destination_folder, 0755 );
        $wp_filesystem->chmod( $destination_folder, 0755 );
    }

    // Check if the file already exists in the destination folder
    if ( ! $wp_filesystem->exists( $destination_file ) ) {
        // Copy the file from source to destination
        if ( $wp_filesystem->copy( $source_file, $destination_file ) ) {
            // You can handle success here if needed, e.g., logging or notifying the user
            // wp_send_json_success( "Success: File copied to mu-plugins folder." );
        }
    }
}



function secure_signups_delete_file_from_mu_plugins_folder() {
    // Check if the current user has the 'activate_plugins' capability
    if (!current_user_can('activate_plugins')) {
        return;
    }

    // Define the path to the file you want to delete
    $destination_file = WP_CONTENT_DIR . '/mu-plugins/apply_secure_signups.php';

    // Check if the file exists
    if (file_exists($destination_file)) {
        // Use wp_delete_file() to delete the file and check if it was successful
        if (wp_delete_file($destination_file)) {
            // The file was successfully deleted
            // You can uncomment the line below to send a success message if needed
            // wp_send_json_success("Success: File deleted from mu-plugins folder.");
        }
    }
}


register_activation_hook( __FILE__, 'secure_signups_copy_file_to_mu_plugins_folder' );
register_deactivation_hook( __FILE__, 'secure_signups_delete_file_from_mu_plugins_folder' );