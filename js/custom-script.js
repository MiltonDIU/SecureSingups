jQuery(document).ready(function($) {
    $('a.deactivate').on('click', function(e) {
        e.preventDefault(); // Prevent default behavior of deactivation link

        var confirmation = confirm('Are you sure you want to deactivate this plugin?'); // Show confirmation dialog

        if (confirmation) {
            window.location.href = $(this).attr('href'); // If confirmed, proceed with deactivation
        }
    });
});
