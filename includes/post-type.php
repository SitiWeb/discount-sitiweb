<?php

function wcsd_register_discount_rule_cpt()
{
    $args = array(
        'public' => true,
        'label'  => 'Discount Rules',
        'show_in_menu' => 'woocommerce',
        'supports' => array('title', 'custom-fields'),
        'capabilities' => array(
            'edit_post'          => 'manage_woocommerce',
            'read_post'          => 'manage_woocommerce',
            'delete_post'        => 'manage_woocommerce',
            'edit_posts'         => 'manage_woocommerce',
            'edit_others_posts'  => 'manage_woocommerce',
            'publish_posts'      => 'manage_woocommerce',
            'read_private_posts' => 'manage_woocommerce'
        ),
    );
    register_post_type('wcsd_discount_rule', $args);
}
add_action('init', 'wcsd_register_discount_rule_cpt');

add_action('add_meta_boxes', 'wcsd_add_meta_boxes');
function wcsd_add_meta_boxes()
{
    add_meta_box(
        'wcsd_discount_details', // Unique ID
        __('Discount Details', 'woocommerce-scheduled-discounts'), // Box title
        'wcsd_discount_details_callback', // Content callback, must be of type callable
        'wcsd_discount_rule', // Post type
        'normal', // Context
        'high' // Priority
    );
    if (get_option('discount_debug_mode', false)) {
        add_meta_box(
            'wcsd_discount_debug', // Unique ID
            __('Discount Debug', 'woocommerce-scheduled-discounts'), // Box title
            'wcsd_discount_debug_callback', // Content callback, must be of type callable
            'wcsd_discount_rule', // Post type
            'normal', // Context
            'high' // Priority
        );
    }



    add_meta_box(
        'general_settings_meta_box',          // Meta box ID
        __('General Settings', 'textdomain'), // Meta box title
        'display_general_settings_meta_box',  // Callback function to display the meta box content
        'wcsd_discount_rule',                     // Post type where the meta box will appear
        'side',                               // Context (where on the screen)
        'default'                             // Priority
    );
}

function wcsd_discount_debug_callback($post)
{

    $debug = new SitiWebDiscount($post->ID);
    $debug->set_debug(true);

    $debug->fetch_applicable_products();


?>
    <div>
        <table>
            <tr>
                <th width="200px">Applicable Product count:</th>
                <td><?php echo $debug->get_applicable_products_count(); ?></td>
            </tr>
            <tr>
                <th>Applicable Products:</th>
                <td><?php echo $debug->loop_through_applicable_products(); ?></td>
            </tr>
            <tr>
                <th>Total number of discount this discount:</th>
                <td><?php echo $debug->number_sw_discount(); ?></td>
            </tr>
            <tr>
                <th>Status:</th>
                <td><?php status_meta_box_callback($post); ?></td>
            </tr>
            <tr>
                <th>Last query:</th>
                <td><?php echo $debug->get_last_query(); ?></td>
            </tr>
            <tr>
                <th>Total number of discount:</th>
                <td><?php echo $debug->total_number_sw_discount(); ?></td>
            </tr>
        </table>
    </div>
<?php
    $debug->set_debug(false);
    my_custom_meta_box_content($post);
}
function my_custom_meta_box_content($post)
{
    // Output the nonce field for security
    wp_nonce_field('my_delete_action', 'my_delete_nonce');

    // Add a button
    echo '<button type="submit" name="my_delete_action" class="button">Delete All _sw_discount_id</button>';
    wp_nonce_field('delete_orphaned_posts', 'delete_orphaned_nonce');
    echo '<button type="submit" name="delete_orphaned_posts" class="button">Delete All orphaned sale price</button>';
    // Include any other meta box content here
}

add_action('save_post', 'handle_my_delete_action');
function handle_my_delete_action($post_id)
{
    // Check if our nonce is set and verify it.
    if (!isset($_POST['delete_orphaned_nonce']) || !wp_verify_nonce($_POST['delete_orphaned_nonce'], 'delete_orphaned_posts')) {
        return;
    }



    // Check if the button was clicked and not just a save action
    if (isset($_POST['delete_orphaned_posts'])) {
        // Perform the deletion of meta keys
        global $wpdb;

        // SQL to find distinct _sw_discount_id values that do not have a corresponding post
        $sql = "
            SELECT DISTINCT pm.meta_value 
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON pm.meta_value = p.ID
            WHERE pm.meta_key = '_sw_discount_id' 
            AND p.ID IS NULL
        ";

        // Execute the query
        $orphaned_discount_ids = $wpdb->get_col($sql);

        // Check if there are any orphaned discount IDs
        if (!empty($orphaned_discount_ids)) {
            // Handle the orphaned IDs as needed
            foreach ($orphaned_discount_ids as $orphaned_id) {
                $args = array(
                    'post_type' => 'any', // Adjust as necessary to target specific post types
                    'posts_per_page' => -1, // Consider pagination for performance on large datasets
                    'meta_query' => array(
                        array(
                            'key' => '_sw_discount_id',
                            'value' => $orphaned_id,
                            'compare' => '='
                        )
                    )
                );
        
                $query = new WP_Query($args);
        
                if ($query->have_posts()) {
                    echo "Posts with orphaned discount ID $orphaned_id:<br>";
                    while ($query->have_posts()) {

                        // Example: Output the orphaned IDs
                        $product = wc_get_product($orphaned_id);
                        if ($product) {
                            // Check if the product is a variable product
                            if ($product->is_type('variable')) {
                                // Get all variations of the variable product
                                $variations = $product->get_children();
                                foreach ($variations as $variation_id) {
                                    // Load the variation product
                                    $variation = wc_get_product($variation_id);
                                    // Delete the sale price for the variation
                                    $variation->set_sale_price('');
                                    $variation->save();
                                    // Delete the custom meta for the variation
                                    
                                }
                                delete_post_meta($product->get_id(), '_sw_discount_id');
                            } elseif($product->is_type('simple')) {
                                // This is for simple products or any other product type that is not variable
                                $product->set_sale_price('');
                                $product->save();
                                delete_post_meta($product->get_id(), '_sw_discount_id');
                            }
                            echo ' Discount deleted';
                        }
                    
                       
                
                        // Reset post data to ensure global post data is restored for subsequent loops or operations
                        wp_reset_postdata();
                    }
            
                } else {
                    echo "No orphaned discount IDs found.";
                    wp_die();
                }
            }
        }
    }

       
}

add_action('save_post', 'handle_my_delete_action2');
function handle_my_delete_action2($post_id)
{
    // Check if our nonce is set and verify it.
    if (!isset($_POST['my_delete_nonce']) || !wp_verify_nonce($_POST['my_delete_nonce'], 'my_delete_action')) {
        return;
    }

    // Check if the button was clicked and not just a save action
    if (isset($_POST['my_delete_action'])) {
        // Perform the deletion of meta keys
        global $wpdb;
        $wpdb->delete(
            $wpdb->postmeta,
            array(
                'meta_key' => '_sw_discount_id',
                'meta_value' => $post_id
            ),
            array('%s', '%d') // data format (%s for string, %d for decimal/integer)
        );
    }


}


function status_meta_box_callback($post)
{
    $current_status = get_post_meta($post->ID, '_discount_status', true);
    wp_nonce_field('save_discount_status', 'discount_status_nonce');

    echo '<select name="discount_status" id="discount_status">';

    foreach (SitiWebDiscount::STATUSES as $status => $label) {
        echo sprintf(
            '<option value="%s"%s>%s</option>',
            esc_attr($status),
            selected($current_status, $status, false),
            esc_html($label)
        );
    }
    echo '</select>';
}


function wcsd_discount_details_callback($post)
{
    // Add a nonce for security and authentication.
    wp_nonce_field('wcsd_discount_nonce_action', 'wcsd_discount_nonce');

    // Retrieve existing values from the database.
    $discount_percentage = get_post_meta($post->ID, '_wcsd_discount_percentage', true);
    $start_date = get_post_meta($post->ID, '_wcsd_start_date', true);
    $end_date = get_post_meta($post->ID, '_wcsd_end_date', true);
    // Output the fields
?>
    <table style="text-align:left;">

        <tr>
            <td>
                <label for="wcsd_discount_percentage"><?php _e('Discount Percentage:', 'woocommerce-scheduled-discounts'); ?></label>
                </td>
            <td>
                <input type="number" id="wcsd_discount_percentage" name="wcsd_discount_percentage" value="<?php echo esc_attr($discount_percentage); ?>" />
            </td>
        </tr>
        <tr>
            <td>
                <label for="wcsd_start_date"><?php _e('Start Date:', 'woocommerce-scheduled-discounts'); ?></label>
            </td>
            <td>
                <input type="date" id="wcsd_start_date" name="wcsd_start_date" value="<?php echo esc_attr($start_date); ?>" />
            </td>
        </tr>
        <tr>
            <td>
                <label for="wcsd_end_date"><?php _e('End Date:', 'woocommerce-scheduled-discounts'); ?></label>
            </td>
            <td>
                <input type="date" id="wcsd_end_date" name="wcsd_end_date" value="<?php echo esc_attr($end_date); ?>" />
            </td>
        </tr>



        <?php
        // Here you can add more fields as needed

        // Existing nonce and meta field code...

           // Categories
        $include_categories = get_post_meta($post->ID, '_wcsd_include_categories', true);
        $exclude_categories = get_post_meta($post->ID, '_wcsd_exclude_categories', true);
        $all_categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        
        // Tags
        $include_tags = get_post_meta($post->ID, '_wcsd_include_tags', true);
        $exclude_tags = get_post_meta($post->ID, '_wcsd_exclude_tags', true);
        $all_tags = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false]);

        // Brands
        $include_brands = get_post_meta($post->ID, '_wcsd_include_brands', true);
        $exclude_brands = get_post_meta($post->ID, '_wcsd_exclude_brands', true);

        if (taxonomy_exists('merk')) {
            $all_brands = get_terms(['taxonomy' => 'merk', 'hide_empty' => false]);
        }
        else{
            $all_brands = false;
        }
   
        // Products

       // $all_products = get_posts(['post_type' => 'product', 'numberposts' => -1]);
       $all_products =  [];
        
        // Output the category select fields
        echo '<tr><td><label for="wcsd_include_categories">' . __('Include Categories:', 'woocommerce-scheduled-discounts') . '</label></td>';
        echo '<td><select style="width:100%;" id="wcsd_include_categories" name="wcsd_include_categories[]"  class="wcsd-select2" multiple>';
        foreach ($all_categories as $category) {
            echo '<option value="' . esc_attr($category->term_id) . '"' . (in_array($category->term_id, (array)$include_categories) ? ' selected' : '') . '>' . esc_html($category->name) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><td><label for="wcsd_exclude_categories">' . __('Exclude Categories:', 'woocommerce-scheduled-discounts') . '</label></td>';
        echo '<td><select style="width:100%;" id="wcsd_exclude_categories" name="wcsd_exclude_categories[]"  class="wcsd-select2" multiple>';
        foreach ($all_categories as $category) {
            echo '<option value="' . esc_attr($category->term_id) . '"' . (in_array($category->term_id, (array)$exclude_categories) ? ' selected' : '') . '>' . esc_html($category->name) . '</option>';
        }
        echo '</select></td></tr>';

        // Output the tag select fields
        echo '<tr><td><label for="wcsd_include_tags">' . __('Include Tags:', 'woocommerce-scheduled-discounts') . '</label></td>';
        echo '<td><select style="width:100%;" id="wcsd_include_tags" name="wcsd_include_tags[]"  class="wcsd-select2" multiple>';
        foreach ($all_tags as $tag) {
            echo '<option value="' . esc_attr($tag->term_id) . '"' . (in_array($tag->term_id, (array)$include_tags) ? ' selected' : '') . '>' . esc_html($tag->name) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><td><label for="wcsd_exclude_tags">' . __('Exclude Tags:', 'woocommerce-scheduled-discounts') . '</label></td>';
        echo '<td><select style="width:100%;" id="wcsd_exclude_tags" name="wcsd_exclude_tags[]"  class="wcsd-select2" multiple>';
        foreach ($all_tags as $tag) {
            echo '<option value="' . esc_attr($tag->term_id) . '"' . (in_array($tag->term_id, (array)$exclude_tags) ? ' selected' : '') . '>' . esc_html($tag->name) . '</option>';
        }
        echo '</select></td></tr>';
        if ($all_brands){

        
        // Output the brand select fields
        echo '<tr><td><label for="wcsd_include_brands">' . __('Include Brands:', 'woocommerce-scheduled-discounts') . '</label></td>';
        echo '<td><select style="width:100%;" id="wcsd_include_brands" name="wcsd_include_brands[]"  class="wcsd-select2" multiple>';
        foreach ($all_brands as $brand) {
            echo '<option value="' . esc_attr($brand->term_id) . '"' . (in_array($brand->term_id, (array)$include_brands) ? ' selected' : '') . '>' . esc_html($brand->name) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><td><label for="wcsd_exclude_brands">' . __('Exclude Brands:', 'woocommerce-scheduled-discounts') . '</label></td>';
        echo '<td><select style="width:100%;" id="wcsd_exclude_brands" name="wcsd_exclude_brands[]"  class="wcsd-select2" multiple>';
        foreach ($all_brands as $brand) {
            echo '<option value="' . esc_attr($brand->term_id) . '"' . (in_array($brand->term_id, (array)$exclude_brands) ? ' selected' : '') . '>' . esc_html($brand->name) . '</option>';
        }
        echo '</select></td></tr>';
        }
	
		// Fetch selected product IDs from post meta
	
		$old_post = $post;
		$selected_include_products = get_post_meta($post->ID, '_wcsd_include_products', true);
		
		if ($selected_include_products) {
			
			$args_include = array(
				'post_type' => 'product',
				'post__in' => $selected_include_products, // Ensure we always have an array
				'posts_per_page' => -1,
			);
			$selected_include_products_query = new WP_Query($args_include);

			// Output the product select fields
			echo '<tr><td><label for="wcsd_include_products">' . __('Include Products:', 'woocommerce-scheduled-discounts') . '</label></td>';
			echo '<td><select style="width:100%;" id="wcsd_include_products" name="wcsd_include_products[]" class="wcsd-select2" multiple>';
			if ($selected_include_products_query->have_posts()) {
				foreach ($selected_include_products_query->posts as $product) {
					echo '<option value="' . esc_attr($product->ID) . '" selected>' . esc_html($product->post_title) . '</option>';
				}
			}

			// Reset post data immediately after the loop

			echo '</select></td></tr>';

		}

		else{
			        // Output the product select fields
		        echo '<tr><td><label for="wcsd_include_products">' . __('Include Products:', 'woocommerce-scheduled-discounts') . '</label></td>';
		        echo '<td><select style="width:100%;"  id="wcsd_include_products" name="wcsd_include_products[]"  class="wcsd-select2" multiple>';
		        foreach ($all_products as $product) {
		            echo '<option value="' . esc_attr($product->ID) . '"' . (in_array($product->ID, (array)$include_products) ? ' selected' : '') . '>' . esc_html(get_the_title($product->ID)) . '</option>';
		        }
		        echo '</select></td></tr>';
		}
		// Reset post data
		
		
	
		$selected_exclude_products = get_post_meta($post->ID, '_wcsd_exclude_products', true);
		if ($selected_exclude_products){
			// Query selected exclude products
			$args_exclude = array(
				'post_type' => 'product',
				'post__in' => !empty($selected_exclude_products) ? $selected_exclude_products : array(0), // Ensure we always have an array
				'posts_per_page' => -1,
			);

			echo '<tr><td><label for="wcsd_exclude_products">' . __('Exclude Products:', 'woocommerce-scheduled-discounts') . '</label></td>';
			echo '<td><select style="width:100%;" id="wcsd_exclude_products" name="wcsd_exclude_products[]" class="wcsd-select2" multiple>';
			$selected_exclude_products_query = new WP_Query($args_exclude);
			if ($selected_exclude_products_query->have_posts()) {
				foreach ($selected_exclude_products_query->posts as $product) {
					echo '<option value="' . esc_attr($product->ID) . '" selected>' . esc_html($product->post_title) . '</option>';
				}
			}

// 			setup_postdata($old_post);
			echo '</select></td></tr>';
		}
		else{
			        // Output the product select fields
		        echo '<tr><td><label for="wcsd_exclude_products">' . __('Exclude Products:', 'woocommerce-scheduled-discounts') . '</label></td>';
		        echo '<td><select style="width:100%;"  id="wcsd_exclude_products" name="wcsd_exclude_products[]"  class="wcsd-select2" multiple>';
		        foreach ($all_products as $product) {
		            echo '<option value="' . esc_attr($product->ID) . '"' . (in_array($product->ID, (array)$include_products) ? ' selected' : '') . '>' . esc_html(get_the_title($product->ID)) . '</option>';
		        }
		        echo '</select></td></tr>';
		}

        ?>
    </table>
<?php
}

add_action('save_post', 'wcsd_save_meta_box_data');
function wcsd_save_meta_box_data($post_id)
{
    // Check if our nonce is set.
    if (!isset($_POST['wcsd_discount_nonce'])) {
        return;
    }
    // Verify that the nonce is valid.
    if (!wp_verify_nonce($_POST['wcsd_discount_nonce'], 'wcsd_discount_nonce_action')) {
        return;
    }
    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    // Check the user's permissions.
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Sanitize and save the data
    if (isset($_POST['wcsd_discount_percentage'])) {
        update_post_meta($post_id, '_wcsd_discount_percentage', sanitize_text_field($_POST['wcsd_discount_percentage']));
    }
    if (isset($_POST['wcsd_start_date'])) {
        update_post_meta($post_id, '_wcsd_start_date', sanitize_text_field($_POST['wcsd_start_date']));
    }
    if (isset($_POST['wcsd_end_date'])) {
        update_post_meta($post_id, '_wcsd_end_date', sanitize_text_field($_POST['wcsd_end_date']));
    }

	  // Save include/exclude products
	if (isset($_POST['wcsd_include_products'])) {
		update_post_meta($post_id, '_wcsd_include_products', array_map('intval', $_POST['wcsd_include_products']));
	} else {
		update_post_meta($post_id, '_wcsd_include_products', array_map('intval', []));
	}

	if (isset($_POST['wcsd_exclude_products'])) {
		update_post_meta($post_id, '_wcsd_exclude_products', array_map('intval', $_POST['wcsd_exclude_products']));
	} else {
		update_post_meta($post_id, '_wcsd_exclude_products', array_map('intval', []));
	}

	  // Save include/exclude categories
	if (isset($_POST['wcsd_include_categories'])) {
		update_post_meta($post_id, '_wcsd_include_categories', array_map('intval', $_POST['wcsd_include_categories']));
	} else {
		update_post_meta($post_id, '_wcsd_include_categories', array_map('intval', []));
	}

	if (isset($_POST['wcsd_exclude_categories'])) {
		update_post_meta($post_id, '_wcsd_exclude_categories', array_map('intval', $_POST['wcsd_exclude_categories']));
	} else {
		update_post_meta($post_id, '_wcsd_exclude_categories', array_map('intval', []));
	}

	// Save include/exclude tags
	if (isset($_POST['wcsd_include_tags'])) {
		update_post_meta($post_id, '_wcsd_include_tags', array_map('intval', $_POST['wcsd_include_tags']));
	} else {
		update_post_meta($post_id, '_wcsd_include_tags', array_map('intval', []));
	}

	if (isset($_POST['wcsd_exclude_tags'])) {
		update_post_meta($post_id, '_wcsd_exclude_tags', array_map('intval', $_POST['wcsd_exclude_tags']));
	} else {
		update_post_meta($post_id, '_wcsd_exclude_tags', array_map('intval', []));
	}

	// Save include/exclude brands
	if (isset($_POST['wcsd_include_brands'])) {
		update_post_meta($post_id, '_wcsd_include_brands', array_map('intval', $_POST['wcsd_include_brands']));
	} else {
		update_post_meta($post_id, '_wcsd_include_brands', array_map('intval', []));
	}

	if (isset($_POST['wcsd_exclude_brands'])) {
		update_post_meta($post_id, '_wcsd_exclude_brands', array_map('intval', $_POST['wcsd_exclude_brands']));
	} else {
		update_post_meta($post_id, '_wcsd_exclude_brands', array_map('intval', []));
	}



    if (isset($_POST['discount_status']) && array_key_exists($_POST['discount_status'], SitiWebDiscount::STATUSES)) {
        if ($_POST['discount_status'] == 'active') {
            update_post_meta($post_id, '_discount_status', 'pending');
        } else {
            update_post_meta($post_id, '_discount_status', sanitize_text_field($_POST['discount_status']));
        }
    } else {
        if (get_post_meta($post_id, '_discount_status', true) == 'active') {
            update_post_meta($post_id, '_discount_status', 'pending');
        }
    }
    if (isset($_POST['posts_per_page'])) {
        update_option('discount_posts_per_page', (int) $_POST['posts_per_page']);
    }

    if (isset($_POST['discount_timeout'])) {
        update_option('discount_timeout', (int) $_POST['discount_timeout']);
    }
    // Save Debug Mode
    $debug_mode = isset($_POST['discount_debug_mode']) ? true : false;
    update_option('discount_debug_mode', $debug_mode);
    // Add more fields as necessary
}



function display_general_settings_meta_box($post)
{


    // Retrieve or set default values
    $posts_per_page = get_option('discount_posts_per_page', 10); // Default to 10 if not set
    $discount_timeout = get_option('discount_timeout', 5); // Default to 5 if not set
    $debug_mode = get_option('discount_debug_mode', false); // Default to false if not set
    $status = get_post_meta($post->ID, '_discount_status',true);


    // Display the form fields
?>
    <p>
        <label for="status"><?php _e('status:', 'textdomain'); ?></label>
        <input type="text" id="status" name="status" value="<?php echo esc_attr($status); ?>" readonly/>
    </p>
    <p>
        <label for="posts_per_page"><?php _e('Posts per page:', 'textdomain'); ?></label>
        <input type="number" id="posts_per_page" name="posts_per_page" value="<?php echo esc_attr($posts_per_page); ?>" />
    </p>
    <p>
        <label for="discount_timeout"><?php _e('Cron Timeout:', 'textdomain'); ?></label>
        <input type="number" id="discount_timeout" name="discount_timeout" value="<?php echo esc_attr($discount_timeout); ?>" />
    </p>
    <p>
        <label for="discount_debug_mode"><?php _e('Enable Debug Mode:', 'textdomain'); ?></label>
        <input type="checkbox" id="discount_debug_mode" name="discount_debug_mode" value="1" <?php checked($debug_mode, true); ?> />
    </p>
<?php
}


add_action('before_delete_post', 'prevent_post_deleting');
function prevent_post_deleting($post_id) {

    // Check if the post is of a specific type or has a specific ID
    $post = get_post($post_id);
    if ($post->post_type == 'wcsd_discount_rule' || $post_id == 'specific_post_id') {
        // Optionally, you can check for user capabilities if needed
        // if (!current_user_can('manage_options')) {
        
        // Perform a check to see if the post is being trashed
        if ('trash' == get_post_status($post_id) &&  !in_array( get_post_meta($post_id,'_discount_status',true), ['finished', 'waiting'])) {
            // Redirect or display a message instead of trashing
            // For example, redirect back to the post list with a query var to trigger a notice
            wp_redirect(admin_url('edit.php?post_type=wcsd_discount_rule&cannot_trash=true'));
            exit;
        }
    }
}
add_filter('wp_insert_post_data', 'prevent_post_trashing', 10, 2);
function prevent_post_trashing($data, $postarr) {
    $post_id = $postarr['ID'];

    // Check if the post is of a specific type or has a specific ID
    if ($data['post_type'] == 'wcsd_discount_rule' || $post_id == 'specific_post_id') {
        // Optionally, you can check for user capabilities if needed
        // if (!current_user_can('manage_options')) {
        
        // Perform a check to see if the post status is being changed to 'trash'
        if ('trash' == $data['post_status'] && 'finished' != get_post_meta($post_id, '_discount_status', true)) {
            // Prevent trashing by setting the post status back to its previous status or to 'draft' if not set
            $previous_status = !empty($postarr['post_status']) ? $postarr['post_status'] : 'draft';
            $data['post_status'] = $previous_status;
            wp_redirect(admin_url('edit.php?post_type=wcsd_discount_rule&cannot_trash=true'));
            exit;
            // Optionally, redirect or display a message
            // Note: Redirects or admin notices may not work as expected in this context without additional handling
        }
    }

    return $data;
}


// Optionally, add an admin notice if redirected with the cannot_trash query var
add_action('admin_notices', 'show_cannot_trash_notice');
function show_cannot_trash_notice() {
    if (isset($_GET['cannot_trash']) && $_GET['cannot_trash'] == 'true') {
        echo '<div class="notice notice-error"><p>' . __('Sorry, this post cannot be trashed before the status is waiting or finished.', 'your-text-domain') . '</p></div>';
    }
}

function wcsd_custom_admin_style()
{
    wp_add_inline_style('select2-css', '.select2-container--default .select2-selection--multiple { border: 1px solid #ddd; }');
}
add_action('admin_enqueue_scripts', 'wcsd_custom_admin_style');
function wcsd_enqueue_select2_assets()
{
    wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
    wp_enqueue_script('select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
    wp_add_inline_script('select2-js', 'jQuery(document).ready(function($) { $(".wcsd-select2").select2(); });');
}
add_action('admin_enqueue_scripts', 'wcsd_enqueue_select2_assets');
