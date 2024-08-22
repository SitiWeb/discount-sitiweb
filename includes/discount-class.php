<?php 

class SitiWebDiscount{

    public $discount_id;
    public $discount;
    public $posts_per_page;
    private $applicable_products = [];
    private $search_for_dicount_id;
    private $check_for_sale_price;
    private $last_query;
    private $debug;

    public function __construct($discount_id){
        $this->discount_id = $discount_id;
        $this->discount = get_post($discount_id);
       
    }

    const STATUSES = [
        'waiting' => 'Waiting till applicable',
        'pending' => 'Pending',
        'active' => 'Active',
        'removing' => 'Removing',
        'finished' => 'Finised',
        'cancelled' => 'Cancelled',
    ];

    public function set_debug($value){
        $this->debug = $value;
    }

    public function get_debug(){
        if (isset($this->debug)){
		if ($this->debug){
			$this->set_status(1);
		}
            return $this->debug;
        }
        return false;
    }

    public function get_included_categories(){
        return get_post_meta($this->discount_id, '_wcsd_include_categories', true);
    }
	
    public function get_excluded_categories(){
        return get_post_meta($this->discount_id, '_wcsd_exclude_categories', true);
    }

	public function get_included_tags(){
    	return get_post_meta($this->discount_id, '_wcsd_include_tags', true);
	}

	public function get_excluded_tags(){
		return get_post_meta($this->discount_id, '_wcsd_exclude_tags', true);
	}

	public function get_included_brands(){
		return get_post_meta($this->discount_id, '_wcsd_include_brands', true);
	}

	public function get_excluded_brands(){
		return get_post_meta($this->discount_id, '_wcsd_exclude_brands', true);
	}

	  public function get_included_products(){
        return get_post_meta($this->discount_id, '_wcsd_include_products', true);
    }

    public function get_excluded_products(){
        return get_post_meta($this->discount_id, '_wcsd_exclude_products', true);
    }

    public function get_start_date(){
        return get_post_meta($this->discount_id, '_wcsd_start_date', true);
    }

    public function get_end_date(){
        return get_post_meta($this->discount_id, '_wcsd_end_date', true);
    }

    public function get_discount_percentage(){
        return get_post_meta($this->discount_id, '_wcsd_discount_percentage', true);
    }

    public function get_status(){
        return get_post_meta($this->discount_id, '_discount_status', true);
    }

    public function is_discount_period() {
        $today = date('Y-m-d');
        $start_date = $this->get_start_date();
        $end_date = $this->get_end_date();
    
        return $today >= $start_date && $today <= $end_date;
    }

    public function query_applicable_products() {
        $args = $this->query_args(); // Assuming this method builds your query args based on class properties
        
        $query = new WP_Query($args);
       
        $products = [];
        $this->last_query = $query->request;
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                global $product;
                $products[] = get_the_ID();
            }
        }
    
        wp_reset_postdata(); // Reset the global post object

        return $products;
    }

    public function fetch_applicable_products() {
        $this->set_query_args();
        if ($this->is_discount_period() || $this->get_status() == 'removing') {
            $this->applicable_products = $this->query_applicable_products();
        } else {
            $this->applicable_products = $this->query_applicable_products(); // Ensure it's reset if not in discount period
        }
        $this->set_status();
    }
    public function fetch_task_products() {
        $this->set_query_args();
        if ($this->is_discount_period() || $this->get_status() == 'removing') {
            $this->applicable_products = $this->query_applicable_products();
        } else {
            $this->applicable_products = []; // Ensure it's reset if not in discount period
        }
    }

    public function run_tasks(){
        
        $this->set_post_per_page(get_option('discount_posts_per_page'));
        $timeout = get_option('discount_timeout');
        $this->fetch_task_products();
        $this->set_status();
        if ($this->get_status() != 'active'){
            $this->loop_through_applicable_products();
        }
        
    }

    // Method to get the count of applicable products
    public function get_applicable_products_count() {
        return count($this->applicable_products);
    }

    // Method to get applicable products
    public function get_applicable_products() {
        return $this->applicable_products;
    }

    
    // Method to get applicable products
    public function get_last_query() {
        if (isset($this->last_query)){
            return $this->last_query;
        }
        return false;
    }

    // Method to loop through applicable products (example usage)
    public function loop_through_applicable_products() {
        foreach ($this->applicable_products as $product_id) {
            // Perform operations with $product
            if (!$this->check_timeout()){
                return;
            }
            echo 'Product: '. $product_id.' ';
            if (!$this->get_debug()){
				$product = wc_get_product($product_id);

            
                if ($this->get_status() == 'pending'){
                    $this->update_product($product);
                }
                elseif($this->get_status() == 'removing'){
                    $this->delete_discount_product($product);
                }
            }
            echo PHP_EOL;
            
            
            // For example, you could apply discounts here or just output product information
        }
    }

    private function update_product($product) {
        if (!$product) {
            return;
        }
    
        // Check if the product is a variable product
        if ($product->is_type('variable')) {
            // Get all variations of the product
            $variations = $product->get_children();
            foreach ($variations as $variation_id) {
                $variation = wc_get_product($variation_id); // Get the product variation object
                $discount_price = $this->calculate_discount($variation);
                if ($discount_price) {
                    echo 'Discount price for variation ' . $variation_id . ': ' . $discount_price . ' ';
                    $variation->set_sale_price($discount_price);
                    $variation->save(); // Save the variation
                    echo 'Saved variation ' . $variation_id . PHP_EOL;
                }
            }
        } elseif($product->is_type('simple')) {
            // Handle simple products
            $discount_price = $this->calculate_discount($product);
            if ($discount_price) {
                echo 'Discount price: ' . $discount_price . ' ';
                $product->set_sale_price($discount_price);
                $product->save(); // Save the product
                echo 'Saved';
            }
        }
    
        // Update the custom meta for both simple and variable products
        update_post_meta($product->get_id(), '_sw_discount_id', $this->discount_id);
    }
    

    private function delete_discount_product($product) {
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
    }

    public function total_number_sw_discount(){
        $args = array(
            'post_type'      => 'any', // Use 'any' to search all post types, or specify a custom post type
            'posts_per_page' => -1,    // Retrieve all posts
            'fields'         => 'ids', // Fetch only the IDs to improve performance
            'meta_query'     => array(
                array(
                    'key'     => '_sw_discount_id',
                    'compare' => 'EXISTS', // Find posts where '_sw_discount_id' meta key exists
                ),
            ),
            'no_found_rows'  => true,  // Speeds up the query, since we don't need pagination
        );
        
        $query = new WP_Query($args);
        $total_posts_with_discount_id = count($query->posts);
        return $total_posts_with_discount_id;
    }
    public function number_sw_discount(){
        $args = array(
            'post_type'      => 'any', // Use 'any' to search all post types, or specify a custom post type
            'posts_per_page' => -1,    // Retrieve all posts
            'fields'         => 'ids', // Fetch only the IDs to improve performance
            'meta_query'     => array(
                array(
                    'key'     => '_sw_discount_id',
                    'value'   => $this->discount_id,
                    'compare' => '=', // Find posts where '_sw_discount_id' meta key exists
                ),
            ),
            'no_found_rows'  => true,  // Speeds up the query, since we don't need pagination
        );
        
        $query = new WP_Query($args);
        $total_posts_with_discount_id = count($query->posts);
        return $total_posts_with_discount_id;
    }
    
    private function calculate_discount($product){
        $regular_price = $product->get_regular_price();
        
        // Calculate the discount and round to two decimal places
        $discounted_price = round(floatval($regular_price) * ((100 - $this->get_discount_percentage()) / 100), 2);
        
        return $discounted_price;
    }
    
    
    public function set_post_per_page($value){
        $this->posts_per_page = $value;
    }

    private function get_post_per_page(){
        if (!isset($this->posts_per_page)){
            return -1;
        }
        return $this->posts_per_page;
    }

    private function query_args(){
		$included_categories = $this->get_included_categories();
		$excluded_categories = $this->get_excluded_categories();
		$included_products = $this->get_included_products();
		$excluded_products = $this->get_excluded_products();
		$included_tags = $this->get_included_tags();
		$excluded_tags = $this->get_excluded_tags();
		$included_brands = $this->get_included_brands();
		$excluded_brands = $this->get_excluded_brands();


		$sw_discount_id = $this->discount_id; // Assuming this method exists and returns the ID if set

		// Initial query args for products
		$args = [
			'post_type' => 'product',
			'posts_per_page' => $this->get_post_per_page(),
			//'post__not_in' => $excluded_products, // Exclude specific products
			'tax_query' => [],
			'meta_query' => [],
		];

		// Include categories condition
		if (!empty($included_categories)) {
			$args['tax_query'][] = [
				'taxonomy' => 'product_cat',
				'field' => 'term_id',
				'terms' => $included_categories,
				'operator' => 'IN',
			];
		}

		// Exclude categories condition
		if (!empty($excluded_categories)) {
			$args['tax_query'][] = [
				'taxonomy' => 'product_cat',
				'field' => 'term_id',
				'terms' => $excluded_categories,
				'operator' => 'NOT IN',
			];
		}

		// Include tags condition
		if (!empty($included_tags)) {
			$args['tax_query'][] = [
				'taxonomy' => 'product_tag',
				'field' => 'term_id',
				'terms' => $included_tags,
				'operator' => 'IN',
			];
		}

		// Exclude tags condition
		if (!empty($excluded_tags)) {
			$args['tax_query'][] = [
				'taxonomy' => 'product_tag',
				'field' => 'term_id',
				'terms' => $excluded_tags,
				'operator' => 'NOT IN',
			];
		}

		// Include brands condition
		if (!empty($included_brands)) {
			$args['tax_query'][] = [
				'taxonomy' => 'merk',
				'field' => 'term_id',
				'terms' => $included_brands,
				'operator' => 'IN',
			];
		}

		// Exclude brands condition
		if (!empty($excluded_brands)) {
			$args['tax_query'][] = [
				'taxonomy' => 'merk',
				'field' => 'term_id',
				'terms' => $excluded_brands,
				'operator' => 'NOT IN',
			];
		}

    
        // Ensure tax_query has more than one condition
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }
		
		    // Include products condition
		if (!empty($included_products)) {
			$args['post__in'] = $included_products;
		}

		// Exclude products condition
		if (!empty($excluded_products)) {
			$args['post__not_in'] = $excluded_products;
		}
		
        if ($this->get_check_for_sale_price()){
            $args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key'     => '_sale_price',
                    'value'   => 0,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => '_sale_price',
                    'compare' => 'NOT EXISTS',
                ],
            ];
        }
       
       
        if ($this->get_search_for_discount_id() !== 0){
    
            // SW Discount ID condition
            if ($this->get_search_for_discount_id() === 1){
                if (!empty($sw_discount_id)) {
                    $args['meta_query'][] = [
                        'key' => '_sw_discount_id',
                        'value' => $sw_discount_id,
                        'compare' => '='
                    ];
                }
            }
            if ($this->get_search_for_discount_id() === 2){
                if (!empty($sw_discount_id)) {
                    $args['meta_query'][] = 
                    [
                        'relation' => 'OR',
                        [
                            'key'     => '_sw_discount_id',
                            'value'   => $sw_discount_id,
                            'compare' => '!=',
                
                        ],
                        [
                            'key'     => '_sw_discount_id',
                            'compare' => 'NOT EXISTS',
                        ],
                    ];
                }
            }
            if ($this->get_search_for_discount_id() === 3){
                if (!empty($sw_discount_id)) {
                    $args['meta_query'][] = [
                        'key' => '_sw_discount_id',
                      
                        'compare' => 'NOT_EXISTS'
                    ];
                }
            }
        }
   
        
    
        // Ensure meta_query has more than one condition and set the relation to AND if needed
        if (count($args['meta_query']) > 1) {
            $args['meta_query']['relation'] = 'AND';
        }
        return $args;
    }

    private function set_search_for_discount_id($value){
        if ($value){
            // off
            // 1 search where _sw_dicount_id = {id}
            // 2 search where _sw_dicount_id != {id}
            // 3 search where _sw_discount_id not exist

            $this->search_for_dicount_id = $value;
        }
    }

    private function get_search_for_discount_id(){
        if ( isset($this->search_for_dicount_id)){
            return $this->search_for_dicount_id;
        }
        return 0;
    }

    private function set_check_for_sale_price($value){
        $this->check_for_sale_price = $value;
    }

    private function get_check_for_sale_price(){
        if (isset($this->check_for_sale_price)){
            return $this->check_for_sale_price;
        }
        return false;
        
    }
    
    
    public function set_status() {
        $current_status = $this->get_status(); // Retrieve the current status
    
        if (!$current_status) {
            $current_status = 'waiting'; // Default to 'waiting' if no status is set
        }
    
        $new_status = $current_status; // Initialize new_status with the current status
    
        // Determine the new status based on your conditions
        if ($current_status === 'waiting' && $this->is_discount_period()) {
            $new_status = 'pending';
        }
    
        if ($current_status === 'pending' && $this->get_applicable_products_count() === 0) {
            $new_status = 'active';
        }

        if ($current_status === 'active' && !$this->is_discount_period()) {
            $new_status = 'removing';
        }

        if ($current_status === 'removing' && $this->get_applicable_products_count() === 0) {
            $new_status = 'finished';
        }
    
        // Only update if the status has changed
        if ($new_status !== $current_status) {
           $this->update_status($new_status);
        }
    }
    
    private function update_status($new_status) {
        // Assuming $this->discount_id is the ID of the current post
        update_post_meta($this->discount_id, '_discount_status', $new_status);
    }
    
    private function set_query_args(){
        $status = $this->get_status(); // Retrieve the current status

        switch($status){
            case 'waiting':
                $this->set_check_for_sale_price(true);
                break;
            case 'pending':
                $this->set_check_for_sale_price(true);
                $this->set_search_for_discount_id(2);
                break;
            case 'active':
                $this->set_search_for_discount_id(1);
                break;
            case 'removing':
                $this->set_check_for_sale_price(false);
                $this->set_search_for_discount_id(1);
                break;
            case 'finished':
                $this->set_check_for_sale_price(true);
                $this->set_search_for_discount_id(0);
                break;

        }
    }

    public function check_timeout() {
        $start_time = get_transient('task_runner_discount_start_time');
        $timeout_duration = get_option('discount_timeout'); // Ensure this matches the duration used when setting the transient
    
        if ($start_time === false) {
            // Transient does not exist or expired, which means either the timeout duration passed
            // or the process hasn't been started properly.
            return false; // Indicate that the process should not continue.
        }
    
        if ((time() - $start_time) >= $timeout_duration) {
            // Timeout duration has been reached or exceeded.
            return false; // Indicate that the process should not continue.
        }
    
        return true; // Indicate that the process can continue.
    }

}
