<?php
/**
 * Logger for diagnostics and debugging.
 *
 * @package SureCartShippo
 */

namespace SureCartShippo\Diagnostics;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger class.
 */
class Logger
{
    /**
     * Log levels.
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_ERROR = 'error';

    /**
     * Maximum log entries to keep.
     *
     * @var int
     */
    const MAX_LOG_ENTRIES = 1000;

    /**
     * Get configured logging level.
     *
     * @return string
     */
    private function get_logging_level()
    {
        return get_option('surecart_shippo_logging_level', 'errors');
    }

    /**
     * Check if a log level should be logged.
     *
     * @param string $level Log level.
     * @return bool
     */
    private function should_log($level)
    {
        $configured_level = $this->get_logging_level();

        if ($configured_level === 'none') {
            return false;
        }

        if ($configured_level === 'debug') {
            return true;
        }

        if ($configured_level === 'errors' && $level === self::LEVEL_ERROR) {
            return true;
        }

        if ($configured_level === 'all') {
            return true;
        }

        return false;
    }

    /**
     * Log a message.
     *
     * @param string $level Log level.
     * @param string $message Log message.
     * @param array  $context Additional context.
     */
    private function log($level, $message, $context = [])
    {
        if (!$this->should_log($level)) {
            return;
        }

        $logs = get_option('surecart_shippo_logs', []);

        $entry = [
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        array_unshift($logs, $entry);

        // Limit log size.
        if (count($logs) > self::MAX_LOG_ENTRIES) {
            $logs = array_slice($logs, 0, self::MAX_LOG_ENTRIES);
        }

        update_option('surecart_shippo_logs', $logs, false);
    }

    /**
     * Log a debug message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     */
    public function debug($message, $context = [])
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     */
    public function info($message, $context = [])
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log an error message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     */
    public function error($message, $context = [])
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Get all log entries.
     *
     * @param int $limit Number of entries to retrieve.
     * @return array
     */
    public function get_logs($limit = 100)
    {
        $logs = get_option('surecart_shippo_logs', []);
        return array_slice($logs, 0, $limit);
    }

    /**
     * Clear all logs.
     */
    public function clear_logs()
    {
        delete_option('surecart_shippo_logs');
    }

    /**
     * Get log statistics.
     *
     * @return array
     */
    public function get_statistics()
    {
        $logs = get_option('surecart_shippo_logs', []);

        $stats = [
            'total' => count($logs),
            'debug' => 0,
            'info' => 0,
            'error' => 0,
        ];

        foreach ($logs as $log) {
            if (isset($log['level'])) {
                $stats[$log['level']]++;
            }
        }

        return $stats;
    }
}
