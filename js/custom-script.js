jQuery(document).ready(function($) {
    $('a.deactivate').on('click', function(e) {
        e.preventDefault(); // Prevent default behavior of deactivation link

        var confirmation = confirm('Are you sure you want to deactivate this plugin?'); // Show confirmation dialog

        if (confirmation) {
            window.location.href = $(this).attr('href'); // If confirmed, proceed with deactivation
        }
    });
});

//this script for settings save page

jQuery(document).ready(function($) {
    // Handle form submission
    $('#secure-signups-settings-form').submit(function(e) {
        e.preventDefault();

        // Serialize form data
        var formData = $(this).serialize();
        // Append nonce to form data
        formData += '&nonce=' + secure_signups_ajax.security;
        // Perform the AJAX request
        $.ajax({
            type: 'POST',
            url: secure_signups_ajax.ajax_url,
            data: formData + '&action=secure_signups_save_settings',
            success: function(response) {
                // Handle successful response
                if (response.success) {
                    $('#save-message')
                        .removeClass()
                        .addClass('alert alert-success')
                        .html(response.data)
                        .show();
                } else {
                    $('#save-message')
                        .removeClass()
                        .addClass('alert alert-warning')
                        .html(response.data)
                        .show();
                }
                // Hide the message after 5 seconds
                setTimeout(function() {
                    $('#save-message').empty().hide();
                }, 5000);
            },
            error: function(errorThrown) {
                // Handle error
                console.log(errorThrown);
                $('#save-message')
                    .removeClass()
                    .addClass('alert alert-warning')
                    .html('Error occurred while saving settings.')
                    .show();

                // Hide the message after 5 seconds
                setTimeout(function() {
                    $('#save-message').empty().hide();
                }, 5000);
            }
        });
    });
});


///this script for new domain page
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
    var secure_signups_update_domain_status_nonce = secure_signups_ajax.update_domain_status_nonce;

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

//this script for list of domain page

jQuery(document).ready(function($) {
    var secure_signups_update_domain_name_nonce = secure_signups_ajax.update_domain_name_nonce;

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
                        new_domain_name: newDomainName,
                        nonce:secure_signups_update_domain_name_nonce
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