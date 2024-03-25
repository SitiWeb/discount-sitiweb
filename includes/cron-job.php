<?php
  class DiscountCron {

    public function __construct() {
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
        add_action('wp', array($this, 'setup_cron_job'));
        add_action('task_runner_discount', array($this, 'execute_cron_job'));
    }

    public function add_cron_interval($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60, // Interval in seconds
            'display'  => esc_html__('Every Minute'), // Display name for the interval
        );
        return $schedules;
    }

    public function setup_cron_job() {
        if (!wp_next_scheduled('task_runner_discount')) {
            wp_schedule_event(time(), 'every_minute', 'task_runner_discount');
        }
    }

    public function execute_cron_job() {
        // Your cron job task here.
        $timeout_duration = get_option('discount_timeout'); // Timeout duration in seconds
        $start_time = time(); // Current Unix timestamp
        echo 'Timeout: '.$timeout_duration.PHP_EOL;
        set_transient('task_runner_discount_start_time', $start_time, $timeout_duration);
        discount_run_task();
    }
}