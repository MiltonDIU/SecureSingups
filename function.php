<?php
/**
 * Plugin Name: Secure Signups
 * Plugin URI:https://daffodilweb.com/secure-signups.php
 * Description: Secure Signups: Safeguard WordPress registrations. Restrict signups to approved domain emails, manage domains from the admin panel.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP:7.3
 * WordPress tested up to: 6.5
 * Author: Daffodil Web & E-commerce
 * Author URI: https://daffodilweb.com
 * Text Domain: SecureSignups
 * License:GPL v2 or later
 * License URI:https://www.gnu.org/licenses/gpl-2.0.html
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

register_activation_hook(__FILE__, 'secure_signups_install');
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
    wp_enqueue_script('secure-signups-custom-script', plugins_url('js/custom-script.js', __FILE__), array('jquery'), '1.0.0', true);
    wp_localize_script('secure-signups-custom-script', 'secure_signups_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('secure-signups-ajax-nonce'),
    ));
}
add_action('wp_enqueue_scripts', 'secure_signups_enqueue_scripts');
// Define the constant
define('MAX_ALLOWED_ROWS', 10);
function secure_signups_create_trigger() {
    global $wpdb;
    $dbconnect = $wpdb;
    $table_name = $dbconnect->prefix . 'secure_signups_list_of_domains';
    $max_allowed_rows = MAX_ALLOWED_ROWS;
    $trigger_sql = $dbconnect->prepare("
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
        $max_allowed_rows,
        "Cannot insert more than $max_allowed_rows rows into $table_name"
    );
    $dbconnect->query( $trigger_sql );
}


function secure_signups_install() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    global $wpdb;
    $dbconnect = $wpdb;

    $list_of_domains_table = $dbconnect->prefix . 'secure_signups_list_of_domains';
    $settings_table = $dbconnect->prefix . 'secure_signups_settings';
    $charset_collate = $dbconnect->get_charset_collate();

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

    // Include wp-admin/includes/upgrade.php for dbDelta function
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Execute SQL queries using dbDelta
    dbDelta( $sql_list_of_domains );
    dbDelta( $sql_settings );

    // Check if settings table is empty and insert default values if needed
    $existing_settings = $dbconnect->get_var( $dbconnect->prepare( "SELECT COUNT(*) FROM $settings_table" ));
    if ( $existing_settings == 0 ) {
        $dbconnect->insert(
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

register_deactivation_hook(__FILE__, 'secure_signups_uninstall');

function secure_signups_uninstall() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    global $wpdb;
    $dbconnect = $wpdb;

    $settings_table = $dbconnect->prefix . 'secure_signups_settings';
    $list_of_domains_table = $dbconnect->prefix . 'secure_signups_list_of_domains';

    // Check if the settings table exists
    if ( $dbconnect->get_var($dbconnect->prepare("SHOW TABLES LIKE '$settings_table'")) == $settings_table ) {

        // Check if retain_plugin_data is set to 1
        $retain_data =$dbconnect->get_var($dbconnect->prepare("SELECT retain_plugin_data FROM $settings_table"));
        // If retain_plugin_data is set to 1, exit without deleting tables
        if ( $retain_data == 1 ) {
            return;
        }
        // Drop tables if retain_plugin_data is not set
       $dbconnect->query( "DROP TABLE IF EXISTS $list_of_domains_table" );
       $dbconnect->query( "DROP TABLE IF EXISTS $settings_table" );
    }
}

function secure_signups_menu() {
    add_menu_page('Secure Signups', 'Secure Signups', 'manage_options', 'secure-signups-menu', 'secure_signups_settings_page');
    add_submenu_page('secure-signups-menu', 'Settings', 'Settings', 'manage_options', 'secure-signups-menu', 'secure_signups_settings_page');
    add_submenu_page('secure-signups-menu', 'List of Domain', 'List of Domain', 'manage_options', 'secure-signups-add-new-domain', 'secure_signups_add_new_domain_page');
}
add_action('admin_menu', 'secure_signups_menu');

function secure_signups_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    global $wpdb;
    $dbconnect = $wpdb;
    $settings_table =$dbconnect->prefix . 'secure_signups_settings';

    // Prepare SQL query with$dbconnect->prepare()
    $current_setting =$dbconnect->get_row($dbconnect->prepare("SELECT is_restriction,message,publicly_view,retain_plugin_data FROM $settings_table LIMIT 1"));


    // Include settings.php file using plugin_dir_path()
    include plugin_dir_path( __FILE__ ) . 'include/settings.php';
}

add_action('wp_ajax_secure_signups_save_settings', 'secure_signups_save_settings');

function secure_signups_save_settings() {
    global $wpdb;
    $dbconnect = $wpdb;

    // Check nonce verification
    if (!isset($_POST['secure_signups_nonce']) || !wp_verify_nonce($_POST['secure_signups_nonce'], 'secure_signups_save_settings_action')) {
        wp_send_json_error("Error: Nonce verification failed.");
        return;
    }

    // Check if the user has the required capabilities
    if ( isset( $_POST['message'] ) ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error($dbconnect->prepare("Error: You do not have permission to perform this action."));
            return;
        }
        $settings_table =$dbconnect->prefix . 'secure_signups_settings';
        $is_restriction = isset( $_POST['is_restriction'] ) ? 1 : 0;
        $message = sanitize_text_field( $_POST['message'] );
        $publicly_view = isset( $_POST['publicly_view'] ) ? 1 : 0;
        $retain_plugin_data = isset( $_POST['retain_plugin_data'] ) ? 1 : 0;

        // Prepare the SQL query with$dbconnect->prepare()
        $result =$dbconnect->update(
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
            wp_send_json_success($dbconnect->prepare("Success: The domain settings were successfully updated!" ));
        } else {
            wp_send_json_error($dbconnect->prepare("Error: There was an error updating the domain settings." ));
        }
    } else {
        wp_send_json_error($dbconnect->prepare("Error: Insufficient data!") );
    }
}

function secure_signups_add_new_domain_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    global $wpdb;
    $dbconnect = $wpdb;
    $domain_table =$dbconnect->prefix . 'secure_signups_list_of_domains';

    // Include files using plugin_dir_path() to generate the correct file paths
    include plugin_dir_path( __FILE__ ) . 'include/new-domain.php';

    // Use$dbconnect->prepare() to prepare the SQL query
    $domains =$dbconnect->get_results($dbconnect->prepare( "SELECT * FROM $domain_table" ) );

    // Include list-of-domain.php file
    include plugin_dir_path( __FILE__ ) . 'include/list-of-domain.php';
}


add_action('wp_ajax_secure_signups_save_new_domain', 'secure_signups_save_new_domain');

function secure_signups_save_new_domain() {
    global $wpdb;
    $dbconnect = $wpdb;// Declare global variable
    if (!isset($_POST['secure_signups_nonce']) || !wp_verify_nonce($_POST['secure_signups_nonce'], 'secure_signups_save_new_domain_action')) {
        wp_send_json_error("Error: Nonce verification failed.");
        return;
    }

    if ( isset( $_POST['domain_name'] ) ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error($dbconnect->prepare("Error: You do not have permission to perform this action.") );
            return;
        }

        $domains_table =$dbconnect->prefix . 'secure_signups_list_of_domains';

        // Convert domain name to lowercase
        $domain_name = strtolower( sanitize_text_field( $_POST['domain_name'] ) );
        // Prepare and execute query to check if domain already exists
        $existing_domain =$dbconnect->get_row($dbconnect->prepare( "SELECT * FROM $domains_table WHERE domain_name = %s", $domain_name ) );
        if ( $existing_domain ) {
            wp_send_json_error("Error: The domain already exists in the list." );
            return;
        }

        // Validate domain name format
        if ( ! preg_match( "/^[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$/", $domain_name ) ) {
            wp_send_json_error("Invalid: The domain name format is invalid! Please enter a valid domain name.");
            return;
        }

        // Check maximum allowed rows
        $existing_rows_count =$dbconnect->get_var($dbconnect->prepare( "SELECT COUNT(*) FROM $domains_table" ) );
        if ( $existing_rows_count >= MAX_ALLOWED_ROWS ) {
            wp_send_json_error($wpdb->prepare("Info: A maximum of %s domains can be whitelisted in the free version of the plugin.", MAX_ALLOWED_ROWS) );
            return;
        }

        // Insert new domain
        $new =$dbconnect->insert(
            $domains_table,
            array(
                'domain_name' => $domain_name,
                'is_active' => 1
            ),
            array( '%s', '%d' )
        );

        if ( $new ) {
            wp_send_json_success( $dbconnect->prepare("Success: New domain successfully added!" ));
        } else {
            wp_send_json_error( $dbconnect->prepare("Error: Failed to add the domain. Please try again." ));
        }
    } else {
        wp_send_json_error( $dbconnect->prepare("Error: Insufficient data!" ));
    }
}

add_action('wp_ajax_secure_signups_get_domain_list', 'secure_signups_get_domain_list');

function secure_signups_get_domain_list() {
    global $wpdb;
    $dbconnect = $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error($dbconnect->prepare("Error: You do not have permission to perform this action." ));
        return;
    }


    $domains_table =$dbconnect->prefix . 'secure_signups_list_of_domains';

    // Prepare and execute query to fetch domain list
    $query =$dbconnect->prepare( "SELECT * FROM $domains_table" );
    $domains =$dbconnect->get_results( $query, ARRAY_A );

    if ( $domains === null ) {
        wp_send_json_error($dbconnect->prepare("Error: Failed to retrieve domain list.") );
        return;
    }

    wp_send_json_success( $domains );
}

add_action('admin_post_submit_domain', 'secure_signups_submit_domain');
add_action('wp_ajax_secure_signups_update_domain_status', 'secure_signups_update_domain_status');

function secure_signups_update_domain_status() {
    global $wpdb;
    $dbconnect = $wpdb;

    if ( !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'secure_signups_update_domain_status') ) {
        wp_send_json_error('Nonce verification failed.');
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error($dbconnect->prepare( "Error: You do not have permission to perform this action.") );
        return;
    }
    $domain_table =$dbconnect->prefix . 'secure_signups_list_of_domains';
    $domainId = isset( $_POST['domain_id'] ) ? intval( $_POST['domain_id'] ) : 0;
    $newStatus = isset( $_POST['new_status'] ) ? intval( $_POST['new_status'] ) : 0;

    if ( $domainId > 0 ) {
        // Prepare and execute the update query
        $result =$dbconnect->update(
            $domain_table,
            array( 'is_active' => $newStatus ),
            array( 'id' => $domainId ),
            array( '%d' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            wp_send_json_success($dbconnect->prepare( "Success: The domain status was successfully updated!" ));
        } else {
            wp_send_json_error( $dbconnect->prepare("Error: Failed to update the domain status." ));
        }
    } else {
        wp_send_json_error( $dbconnect->prepare("Error: Invalid domain ID provided." ));
    }
}

add_action('wp_ajax_secure_signups_update_domain_name', 'secure_signups_update_domain_name');

function secure_signups_update_domain_name() {
    global $wpdb;
    $dbconnect = $wpdb;
    if ( !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'secure_signups_update_domain_name') ) {
        wp_send_json_error('Nonce verification failed.');
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( $dbconnect->prepare("Error: You do not have permission to perform this action." ));
        return;
    }

    if ( isset( $_POST['domain_id'] ) && isset( $_POST['new_domain_name'] ) ) {

        $domain_table =$dbconnect->prefix . 'secure_signups_list_of_domains';
        $domainId = intval( $_POST['domain_id'] );
        $newDomainName = strtolower(sanitize_text_field( $_POST['new_domain_name'] ));


        if ( ! preg_match( "/^[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$/", $newDomainName ) ) {
            wp_send_json_error( $dbconnect->prepare("Invalid: The domain name format is invalid! Please enter a valid domain name." ));
            return;
        }

        $updated =$dbconnect->update(
            $domain_table,
            array( 'domain_name' => $newDomainName ),
            array( 'id' => $domainId ),
            array( '%s' ),
            array( '%d' )
        );

        if ( $updated === false ) {
            wp_send_json_error( $dbconnect->prepare("Error: Failed to update the domain name." ));
        } else {
            wp_send_json_success( $dbconnect->prepare("Success: Domain name successfully updated!" ));
        }
    } else {
        wp_send_json_error( $dbconnect->prepare("Error: Insufficient data!") );
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
