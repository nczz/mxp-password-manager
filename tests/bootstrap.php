<?php
/**
 * PHPUnit Bootstrap File
 *
 * This file loads WordPress and sets up the testing environment.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Load WordPress test functions
require_once $_tests_dir . '/includes/functions.php';

// Manually load the plugin being tested
function _manually_load_plugin() {
    // Load Composer autoloader
    require __DIR__ . '/../vendor/autoload.php';

    // Load the main plugin file
    require __DIR__ . '/../mxp-password-manager.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';
