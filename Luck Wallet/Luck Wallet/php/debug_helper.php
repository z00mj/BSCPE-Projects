<?php
/**
 * Debug helper functions
 */

if (!function_exists('debug_log')) {
    /**
     * Log debug messages to a file
     *
     * @param mixed $message The message to log
     * @param string $level The log level (info, error, warning, debug)
     * @return void
     */
    function debug_log($message, $level = 'info') {
        // Format the message
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = is_string($message) ? $message : print_r($message, true);
        $logMessage = "[$timestamp] [$level] $formattedMessage" . PHP_EOL;
        
        // Log to error log
        error_log($logMessage, 3, __DIR__ . '/../debug.log');
    }
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_errors.log');
