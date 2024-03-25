jQuery(document).ready(function($) {
    $('button[name="my_delete_action"]').click(function(e) {
        e.preventDefault();
        console.log('etst');
        var data = {
            'action': 'handle_delete_action_ajax',
            'post_id': $(this).data('postid'), // Assuming you add a data attribute to your button
            'nonce': myAjax.nonce // Assuming you pass a nonce for verification
        };

        $.post(myAjax.ajaxurl, data, function(response) {
            alert('Deleted successfully'); // Handle response
        });
    });

    $('button[name="delete_orphaned_posts"]').click(function(e) {
        e.preventDefault();
        console.log('etst');
        var data = {
            'action': 'delete_orphaned_posts',
            'post_id': $(this).data('postid'), // Assuming you add a data attribute to your button
            'nonce': myAjax.nonce // Assuming you pass a nonce for verification
        };

        $.post(myAjax.ajaxurl, data, function(response) {
            alert('Deleted successfully'); // Handle response
        });
    });
	
	   $('#wcsd_include_products, #wcsd_exclude_products').select2({
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    term: params.term,
                    action: 'wcsd_product_search', // Action to handle the AJAX request
                };
            },
            processResults: function(data) {
                return {
                    results: data,
                };
            },
            cache: true,
        },
        minimumInputLength: 3, // Minimum character limit
    });
});
 
