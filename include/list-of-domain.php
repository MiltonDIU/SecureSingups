<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<div class="wrap">
    <h2><?php esc_html_e('List of Domains', 'secure-signups'); ?></h2>
    <table class="wp-list-table widefat striped">
        <thead>
        <tr>
            <th scope="col" class="manage-column" colspan="2"><?php esc_html_e('Domain Name', 'secure-signups'); ?></th>
            <th scope="col" class="manage-column"><?php esc_html_e('Active', 'secure-signups'); ?></th>
        </tr>
        </thead>
        <tbody id="domain-list">
        </tbody>
    </table>
    <h3>
        <?php esc_html_e('Want more flexibility and control over your site signups? Stay tuned for the Secure Signups Pro plugin release. Join the waitlist', 'secure-signups'); ?>
        <a href="https://forms.gle/5ssm5t1ANYFtfrUE9" target="_blank"><?php esc_html_e('here', 'secure-signups'); ?></a>
    </h3>
</div>

