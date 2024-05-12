<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1 class="section-title"><?php esc_html_e('Add New Domain', 'secure-signups'); ?></h1>
    <form id="secure-signups-new-domain-form">
        <?php wp_nonce_field('secure_signups_save_new_domain_action', 'secure_signups_nonce'); ?>
        <fieldset>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="domain_name"><?php esc_html_e('Domain Name', 'secure-signups'); ?></label></th>
                    <td>
                        <input type="text" name="domain_name" id="domain_name" class="regular-text" placeholder="<?php esc_attr_e('gmail.com', 'secure-signups'); ?>" required>
                        &nbsp;&nbsp;
                        <input type="submit" name="secure_signups_submit_domain" id="secure_signups_submit_domain" class="button button-primary" value="<?php esc_attr_e('+', 'secure-signups'); ?>">
                    </td>
                </tr>
            </table>
        </fieldset>
    </form>
    <div id="save-message" class="alert" style="display: none;"></div>
</div>

