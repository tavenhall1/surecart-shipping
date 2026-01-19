<?php
/**
 * Plugin Name: SureCart Shippo Integration
 * Plugin URI: https://github.com/yourusername/surecart-shipping
 * Description: Integrates SureCart with Shippo for address validation, live shipping rates, and label printing.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: surecart-shippo
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Requires Plugins: surecart
 *
 * @package SureCartShippo
 */

namespace SureCartShippo;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants.
define('SURECART_SHIPPO_VERSION', '1.0.0');
define('SURECART_SHIPPO_PLUGIN_FILE', __FILE__);
define('SURECART_SHIPPO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SURECART_SHIPPO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SURECART_SHIPPO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader.
require_once SURECART_SHIPPO_PLUGIN_DIR . 'includes/Autoloader.php';
Autoloader::register();

/**
 * Main plugin class.
 */
class Plugin
{
    /**
     * The single instance of the class.
     *
     * @var Plugin
     */
    private static $instance = null;

    /**
     * Shippo client instance.
     *
     * @var Core\ShippoClient
     */
    public $shippo_client;

    /**
     * Logger instance.
     *
     * @var Diagnostics\Logger
     */
    public $logger;

    /**
     * Get the singleton instance.
     *
     * @return Plugin
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks()
    {
        // Activation and deactivation hooks.
        register_activation_hook(SURECART_SHIPPO_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(SURECART_SHIPPO_PLUGIN_FILE, [$this, 'deactivate']);

        // Initialize plugin after WordPress and SureCart are loaded.
        add_action('plugins_loaded', [$this, 'init'], 20);

        // Load text domain for translations.
        add_action('init', [$this, 'load_textdomain']);
    }

    /**
     * Plugin activation.
     */
    public function activate()
    {
        // Check dependencies.
        if (!$this->check_dependencies()) {
            deactivate_plugins(SURECART_SHIPPO_PLUGIN_BASENAME);
            wp_die(
                esc_html__('SureCart Shippo Integration requires SureCart to be installed and activated.', 'surecart-shippo'),
                esc_html__('Plugin Activation Error', 'surecart-shippo'),
                ['back_link' => true]
            );
        }

        // Create database tables.
        $this->create_database_tables();

        // Set default options.
        $this->set_default_options();

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate()
    {
        // Clear scheduled hooks if any.
        wp_clear_scheduled_hook('surecart_shippo_cleanup_cache');

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Check if required dependencies are active.
     *
     * @return bool
     */
    private function check_dependencies()
    {
        // Check if SureCart is active.
        if (!function_exists('surecart') && !class_exists('SureCart\SureCart')) {
            return false;
        }

        return true;
    }

    /**
     * Initialize the plugin.
     */
    public function init()
    {
        // Check dependencies.
        if (!$this->check_dependencies()) {
            add_action('admin_notices', [$this, 'dependency_notice']);
            return;
        }

        // Initialize core components.
        $this->logger = new Diagnostics\Logger();
        $this->shippo_client = new Core\ShippoClient();

        // Initialize admin components.
        if (is_admin()) {
            new Admin\SettingsPage();
            new Admin\OrderPanel();
            new Admin\ProductMetaBox();
        }

        // Initialize frontend components.
        if (!is_admin()) {
            new Frontend\AddressValidation();
            new Frontend\ShippingRates();
        }

        // Initialize SureCart integration.
        new Integration\SureCartIntegration();

        // Initialize services.
        new Services\RateService();
        new Services\LabelService();

        // Schedule cleanup cron.
        if (!wp_next_scheduled('surecart_shippo_cleanup_cache')) {
            wp_schedule_event(time(), 'hourly', 'surecart_shippo_cleanup_cache');
        }

        // Add cleanup action.
        add_action('surecart_shippo_cleanup_cache', [$this, 'cleanup_cache']);
    }

    /**
     * Show dependency notice.
     */
    public function dependency_notice()
    {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                echo esc_html__(
                    'SureCart Shippo Integration requires SureCart to be installed and activated.',
                    'surecart-shippo'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Load plugin text domain for translations.
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'surecart-shippo',
            false,
            dirname(SURECART_SHIPPO_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Create database tables.
     */
    private function create_database_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'surecart_shippo_boxes';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            box_id varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            internal_length decimal(10,2) NOT NULL,
            internal_width decimal(10,2) NOT NULL,
            internal_height decimal(10,2) NOT NULL,
            empty_weight decimal(10,2) NOT NULL DEFAULT 0,
            max_weight decimal(10,2) NOT NULL,
            box_type varchar(50) NOT NULL DEFAULT 'RSC',
            enabled tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY box_id (box_id),
            KEY enabled (enabled)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Store database version.
        update_option('surecart_shippo_db_version', '1.0.0');
    }

    /**
     * Set default options.
     */
    private function set_default_options()
    {
        $defaults = [
            'surecart_shippo_mode' => 'test',
            'surecart_shippo_logging_level' => 'errors',
            'surecart_shippo_cache_ttl' => 600,
            'surecart_shippo_split_threshold' => 40,
            'surecart_shippo_pack_factor_rugged' => 1.15,
            'surecart_shippo_pack_factor_fragile' => 1.25,
            'surecart_shippo_pack_factor_irregular' => 1.30,
            'surecart_shippo_padding_small' => 0.5,
            'surecart_shippo_padding_medium' => 0.75,
            'surecart_shippo_padding_large' => 1.0,
            'surecart_shippo_padding_long' => 1.25,
            'surecart_shippo_packaging_weight_percent' => 2,
            'surecart_shippo_packaging_weight_min' => 0.2,
            'surecart_shippo_fallback_enabled' => true,
            'surecart_shippo_fallback_on_review' => true,
            'surecart_shippo_external_dim_expansion' => 0.25,
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Cleanup expired cache entries.
     */
    public function cleanup_cache()
    {
        global $wpdb;

        // Delete expired transients.
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_timeout_surecart_shippo_%'
            AND option_value < UNIX_TIMESTAMP()"
        );

        // Delete the corresponding transient values.
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_surecart_shippo_%'
            AND option_name NOT IN (
                SELECT CONCAT('_transient_', SUBSTRING(option_name, 20))
                FROM {$wpdb->options}
                WHERE option_name LIKE '_transient_timeout_surecart_shippo_%'
            )"
        );
    }

    /**
     * Get plugin instance.
     *
     * @return Plugin
     */
    public static function get_instance()
    {
        return self::instance();
    }
}

/**
 * Get the main plugin instance.
 *
 * @return Plugin
 */
function surecart_shippo()
{
    return Plugin::instance();
}

// Initialize the plugin.
surecart_shippo();
