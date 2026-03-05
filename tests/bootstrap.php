<?php

/**
 * Test bootstrap file
 *
 * This file is loaded before running tests to set up the test environment
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';
