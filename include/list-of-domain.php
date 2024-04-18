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

<script>
    jQuery(document).ready(function($) {
        $(document).on('click', '.column-domain_name, .modify', function() {
            if ($(this).hasClass('modify')) {
                var $domainRow = $(this).closest('tr');
                var $domainNameCell = $domainRow.find('.column-domain_name');
            } else {
                var $domainNameCell = $(this);
            }
            var domainName = $domainNameCell.text().trim();
            var domainId = $domainNameCell.data('id') || $domainRow.data('domain-id');

            if (!$domainNameCell.hasClass('editing')) {
                var $input = $('<input>', {
                    type: 'text',
                    value: domainName,
                    class: 'edit-domain-name-input'
                });

                $domainNameCell.empty().append($input).addClass('editing');

                $input.focus();

                $input.on('blur', function() {
                    var newDomainName = $(this).val().trim();

                    $domainNameCell.text(newDomainName);

                    $.ajax({
                        type: 'POST',
                        url: ajaxurl,
                        data: {
                            action: 'secure_signups_update_domain_name',
                            domain_id: domainId,
                            new_domain_name: newDomainName
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#save-message').removeClass().addClass('alert alert-success').html(response.data).show();
                                setTimeout(function() {
                                    $('#save-message').empty().hide(); // Remove the message after 5 seconds
                                }, 5000);
                                $domainNameCell.text(newDomainName);
                            } else {
                                $('#save-message').removeClass().addClass('alert alert-success').html(response.data).show();
                                setTimeout(function() {
                                    $('#save-message').empty().hide(); // Remove the message after 5 seconds
                                }, 5000);
                                $domainNameCell.text(domainName);
                            }
                            $domainNameCell.removeClass('editing');
                        },
                        error: function(errorThrown) {
                            $('#save-message').removeClass().addClass('alert alert-success').html(response.data).show();
                            setTimeout(function() {
                                $('#save-message').empty().hide(); // Remove the message after 5 seconds
                            }, 5000);
                            $domainNameCell.text(domainName);
                            $domainNameCell.text(domainName);
                            $domainNameCell.removeClass('editing');
                        }
                    });
                });

                // Enable saving with Enter key
                $input.on('keypress', function(event) {
                    if (event.which === 13) {
                        $(this).blur();
                    }
                });
            }
        });
    });
</script>