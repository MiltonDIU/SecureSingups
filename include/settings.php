<?php if ( ! defined( 'ABSPATH' ) ) exit;?>
<div class="wrap">
    <h1 class="section-title"><?php esc_html_e('Secure Signups Settings', 'secure-signups'); ?></h1>
    <form id="secure-signups-settings-form">
        <?php wp_nonce_field('secure_signups_save_settings_action', 'secure_signups_nonce'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="domain_name"><?php esc_html_e('Public Message', 'secure-signups'); ?></label></th>
                <td><input type="text" name="message" id="message" class="regular-text"  value="<?php echo esc_attr($current_setting->message); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Activate Above Message', 'secure-signups'); ?></th>
                <td>
                    <fieldset>
                        <div class="toggle-switch">
                            <input type="checkbox" id="publicly_view" name="publicly_view" class="toggle-input" <?php checked($current_setting->publicly_view, 1); ?>>
                            <label for="publicly_view" class="toggle-label">
                                <span><?php esc_html_e('On', 'secure-signups'); ?></span>
                                <span><?php esc_html_e('Off', 'secure-signups'); ?></span>
                            </label>
                        </div>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Retain Plugin Data', 'secure-signups'); ?></th>
                <td>
                    <fieldset>
                        <div class="toggle-switch">
                            <input type="checkbox" id="retain_plugin_data" name="retain_plugin_data" class="toggle-input" <?php checked($current_setting->retain_plugin_data, 1); ?>>
                            <label for="retain_plugin_data" class="toggle-label">
                                <span><?php esc_html_e('On', 'secure-signups'); ?></span>
                                <span><?php esc_html_e('Off', 'secure-signups'); ?></span>
                            </label>
                        </div>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Plugins On/Off', 'secure-signups'); ?></th>
                <td>
                    <fieldset>
                        <div class="toggle-switch">
                            <input type="checkbox" id="is_restriction" name="is_restriction" class="toggle-input" <?php checked($current_setting->is_restriction, 1); ?>>
                            <label for="is_restriction" class="toggle-label">
                                <span><?php esc_html_e('On', 'secure-signups'); ?></span>
                                <span><?php esc_html_e('Off', 'secure-signups'); ?></span>
                            </label>
                        </div>
                        <p><strong><?php esc_html_e('Note:', 'secure-signups'); ?></strong> <?php esc_html_e('Useful when you wish to deactivate the plugin without uninstalling', 'secure-signups'); ?></p>
                    </fieldset>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="secure_signups_submit_domain" id="secure_signups_submit_domain" class="button button-primary" value="<?php esc_attr_e('Submit', 'secure-signups'); ?>">
        </p>
        <h3>
            <?php esc_html_e('Want more flexibility and control over your site signups? Stay tuned for the Secure Signups Pro plugin release. Join the waitlist', 'secure-signups'); ?> <a href="<?php echo esc_url('https://forms.gle/5ssm5t1ANYFtfrUE9')?>" target="_blank"><?php esc_html_e('here', 'secure-signups'); ?></a>
        </h3>
    </form>
    <div id="save-message" class="alert" style="display: none;"></div>
</div>