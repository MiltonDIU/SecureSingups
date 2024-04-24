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
                        <input type="submit" name="secure_signups_submit_domain" id="secure_signups_submit_domain" class="button button-primary" value="+">
                    </td>
                </tr>
            </table>
        </fieldset>
    </form>
    <div id="save-message" class="alert" style="display: none;"></div>
</div>
<script type="text/javascript">
    var secure_signups_update_domain_status_nonce = '<?php echo esc_js(wp_create_nonce('secure_signups_update_domain_status')); ?>';
</script>


<script>
    jQuery(document).ready(function($) {
        function loadDomainList() {
            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    action: 'secure_signups_get_domain_list'
                },
                success: function(response) {
                    if (response.success) {
                        var domains = response.data;
                        var domainList = $('#domain-list');
                        domainList.empty();
                        domains.forEach(function(domain) {
                            var row = `
                            <tr>
                                <td class="column-domain_name edit-domain" data-id="${domain.id}">
                                    ${domain.domain_name}
                                </td>
                                <td class="modify">Modify</td>
                                <td class="column-is_active">
                                    <div class="toggle-switch">

                                        <input class="form-check-input toggle-status" type="checkbox" id="domainSwitch-${domain.id}" data-domain-id="${domain.id}" ${domain.is_active == 1 ? 'checked' : ''}>
                                        <label class="toggle-label" for="domainSwitch-${domain.id}">
                                            <span>${domain.is_active == 1 ? 'On' : 'Off'}</span>
                                            <span>${domain.is_active == 0 ? 'Off' : 'On'}</span>
                                        </label>

                                    </div>
                                </td>
                            </tr>`;
                            domainList.append(row);
                        });
                    }
                },
                error: function(xhr, status, errorThrown) {
                    console.log('Error fetching domain list: ' + errorThrown);
                }
            });
        }

        loadDomainList();

        $('#secure-signups-new-domain-form').submit(function(e) {
            e.preventDefault();
            var formData = $(this).serialize();
            // Append the nonce value to the form data
            formData += '&nonce=' + $('#secure_signups_nonce').val();
            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: formData + '&action=secure_signups_save_new_domain',
                success: function(response) {
                    console.log(response);
                    if (response.success) {
                        $('#save-message').removeClass().addClass('alert alert-success').html(response.data).show();
                        setTimeout(function() {
                            $('#save-message').empty().hide();
                        }, 5000);
                        loadDomainList();
                    } else {
                        console.log(response);
                        $('#save-message').removeClass().addClass('alert alert-warning').html(response.data).show();
                        setTimeout(function() {
                            $('#save-message').empty().hide();
                        }, 5000);
                    }
                },
                error: function(xhr, status, errorThrown) {
                    console.log(xhr.responseText);
                    $('#save-message').removeClass().addClass('alert alert-warning').html('Error occurred while adding domain.').show();
                    setTimeout(function() {
                        $('#save-message').empty().hide();
                    }, 5000);
                }
            });
        });

        $(document).on('change', '.toggle-status', function() {
            var domainId = $(this).data('domain-id');
            var newStatus = $(this).prop('checked') ? 1 : 0;
            var statusLabel = $(this).siblings('.toggle-label');

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    action: 'secure_signups_update_domain_status',
                    domain_id: domainId,
                    new_status: newStatus,
                    nonce: secure_signups_update_domain_status_nonce // Add the nonce to the AJAX request
                },
                success: function(response) {
                    if (response.success) {
                        var labelText = newStatus === 1 ? 'On' : 'Off';
                        statusLabel.find('span:first-child').text(labelText);
                        statusLabel.find('span:last-child').text(newStatus === 1 ? 'On' : 'OFF');
                        $('#save-message').removeClass().addClass('alert alert-success').html(response.data).show();
                        setTimeout(function() {
                            $('#save-message').empty().hide();
                        }, 5000);
                    } else {
                        $('#save-message').removeClass().addClass('alert alert-danger').html(response.data).show();
                        setTimeout(function() {
                            $('#save-message').empty().hide();
                        }, 5000);
                        $(this).prop('checked', !$(this).prop('checked'));
                    }
                },
                error: function(errorThrown) {
                    $('#save-message').removeClass().addClass('alert alert-danger').html('Error occurred while updating domain status!').show();
                    setTimeout(function() {
                        $('#save-message').empty().hide();
                    }, 5000);
                    $(this).prop('checked', !$(this).prop('checked'));
                }
            });
        });

    });
</script>
