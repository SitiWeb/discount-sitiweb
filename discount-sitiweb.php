<?php
/**
 * Plugin Name: WooCommerce Scheduled Discounts
 * Description: Automatically sets scheduled discounts for products in selected categories.
 * Version: 1.1
 * Author: Roberto
 * Author URI: https://sitiweb.nl/
 */
if( ! class_exists( 'SitiWeb_Updater' ) ){
	include_once( plugin_dir_path( __FILE__ ) . 'updater.php' );
}

$updater = new SitiWeb_Updater( __FILE__ );
$updater->set_username( 'SitiWeb' );
$updater->set_repository( 'discount-sitiweb' );
$updater->initialize();

register_activation_hook(__FILE__, 'wcsd_activate');
register_deactivation_hook(__FILE__, 'wcsd_deactivate');

require_once 'includes/post-type.php' ;
require_once 'includes/discount-class.php' ;
require_once 'includes/cron-job.php';
new DiscountCron();

add_action('admin_enqueue_scripts', 'enqueue_my_custom_script');
function enqueue_my_custom_script() {
    wp_enqueue_script('discount-admin',  plugins_url('admin.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('discount-admin', 'myAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
}

add_action('wp_ajax_handle_delete_action_ajax', 'handle_my_delete_action_ajax');
function handle_my_delete_action_ajax() {
    // Check nonce and permissions, then perform deletion logic similar to the non-AJAX version
    // Don't forget to die() at the end
    wp_die();
}
add_action('wp_ajax_handle_delete_orphaned_posts', 'handle_my_delete_delete_orphaned_posts_ajax');
function handle_my_delete_delete_orphaned_posts_ajax() {
    // Check nonce and permissions, then perform deletion logic similar to the non-AJAX version
    // Don't forget to die() at the end
    wp_die();
}





function wcsd_activate() {
    // Code to run on plugin activation
}

function wcsd_deactivate() {
    // Code to run on plugin deactivation
}

//add_action('wp_head','remove_all_sale_prices');

function discount_run_task(){
   
    $args = array(
        'fields' => 'ids',
        'post_type' => 'wcsd_discount_rule', // Your custom post type
        'posts_per_page' => -1, // Adjust the number of posts per page according to your needs
        'meta_query' => array(
            'relation' => 'OR', // Logical relationship between the conditions
            array(

                'relation' => 'AND',
                array(
                    'key' => '_discount_status',
                    'value' => 'finished',
                    'compare' => '!=', // Exclude posts that have 'finished' as the value
                    'type' => 'CHAR',
                ),
               
             ),
            array(
                'key' => '_discount_status',
                'compare' => 'NOT EXISTS' // Include posts that do not have this meta key at all
            )
        )
    );
    
    
    $ids = new WP_Query($args);

    foreach($ids->posts as $id){
        echo 'Task ID: '. $id . PHP_EOL;
        $discount = new SitiWebDiscount($id);
        $discount->run_tasks();
    }
    
} 

function remove_all_sale_prices() {
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids', // Only get product IDs to improve performance
        'meta_query'     => array(
            array(
                'key'     => '_sale_price',
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC',
            ),
        ),
    );

    $products = get_posts($args);

    if (!empty($products)) {
        foreach ($products as $product_id) {
            // Remove sale price
            update_post_meta($product_id, '_sale_price', '');
            update_post_meta($product_id, '_sale_price_dates_from', '');
            update_post_meta($product_id, '_sale_price_dates_to', '');

            // Clear transients
            wc_delete_product_transients($product_id);
        }
    }
}
add_action('wp_ajax_wcsd_product_search', 'wcsd_product_search_callback');

function wcsd_product_search_callback() {
    $search_query = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        's' => $search_query,
    );

    $products = get_posts($args);

    $results = array();

    if (!empty($products)) {
        foreach ($products as $product) {
            $results[] = array(
                'id' => $product->ID,
                'text' => $product->post_title,
            );
        }
    }

    wp_send_json($results);
    wp_die();
}
