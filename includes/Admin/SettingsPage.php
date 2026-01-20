<?php
/**
 * Admin settings page.
 *
 * @package SureCartShippo
 */

namespace SureCartShippo\Admin;

use SureCartShippo\Core\Encryption;
use SureCartShippo\Packaging\BoxCatalog;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings page class.
 */
class SettingsPage
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_surecart_shippo_test_connection', [$this, 'ajaxTestConnection']);
        add_action('wp_ajax_surecart_shippo_seed_boxes', [$this, 'ajaxSeedBoxes']);
        add_action('wp_ajax_surecart_shippo_clear_cache', [$this, 'ajaxClearCache']);
        add_action('wp_ajax_surecart_shippo_clear_logs', [$this, 'ajaxClearLogs']);
    }

    /**
     * Add menu page.
     */
    public function addMenuPage()
    {
        add_menu_page(
            __('SureCart Shippo', 'surecart-shippo'),
            __('Shippo Settings', 'surecart-shippo'),
            'manage_options',
            'surecart-shippo',
            [$this, 'renderPage'],
            'dashicons-admin-site',
            56
        );
    }

    /**
     * Register settings.
     */
    public function registerSettings()
    {
        // Shippo API settings.
        register_setting('surecart_shippo_api', 'surecart_shippo_mode');
        register_setting('surecart_shippo_api', 'surecart_shippo_api_token_test', [
            'sanitize_callback' => [$this, 'sanitizeApiToken'],
        ]);
        register_setting('surecart_shippo_api', 'surecart_shippo_api_token_live', [
            'sanitize_callback' => [$this, 'sanitizeApiToken'],
        ]);

        // Origin address settings.
        register_setting('surecart_shippo_origin', 'surecart_shippo_origin_name');
        register_setting('surecart_shippo_origin', 'surecart_shippo_origin_company');
        register_setting('surecart_shippo_origin', 'surecart_shippo_origin_street1');
        register_setting('surecart_shippo_origin', 'surecart_shippo_origin_street2');
        register_setting('surecart_shippo_origin', 'surecart_shippo_origin_city');
        register_setting('surecart_shippo_origin', 'surecart_shippo_origin_state');
        register_setting('surecart_shippo_origin', 'surecart_shippo_origin_zip');
        register_setting('surecart_shippo_origin', 'surecart_shippo_origin_country');
        register_setting('surecart_shippo_origin', 'surecart_shippo_origin_phone');
        register_setting('surecart_shippo_origin', 'surecart_shippo_origin_email');

        // Carrier settings.
        register_setting('surecart_shippo_carriers', 'surecart_shippo_allowed_carriers');

        // Packaging settings.
        register_setting('surecart_shippo_packaging', 'surecart_shippo_split_threshold');
        register_setting('surecart_shippo_packaging', 'surecart_shippo_pack_factor_rugged');
        register_setting('surecart_shippo_packaging', 'surecart_shippo_pack_factor_fragile');
        register_setting('surecart_shippo_packaging', 'surecart_shippo_pack_factor_irregular');
        register_setting('surecart_shippo_packaging', 'surecart_shippo_padding_small');
        register_setting('surecart_shippo_packaging', 'surecart_shippo_padding_medium');
        register_setting('surecart_shippo_packaging', 'surecart_shippo_padding_large');
        register_setting('surecart_shippo_packaging', 'surecart_shippo_padding_long');
        register_setting('surecart_shippo_packaging', 'surecart_shippo_packaging_weight_percent');
        register_setting('surecart_shippo_packaging', 'surecart_shippo_packaging_weight_min');
        register_setting('surecart_shippo_packaging', 'surecart_shippo_external_dim_expansion');

        // Fallback settings.
        register_setting('surecart_shippo_fallback', 'surecart_shippo_fallback_enabled');
        register_setting('surecart_shippo_fallback', 'surecart_shippo_fallback_on_review');
        register_setting('surecart_shippo_fallback', 'surecart_shippo_fallback_label');
        register_setting('surecart_shippo_fallback', 'surecart_shippo_fallback_amount');

        // Diagnostics settings.
        register_setting('surecart_shippo_diagnostics', 'surecart_shippo_logging_level');
        register_setting('surecart_shippo_diagnostics', 'surecart_shippo_cache_ttl');
    }

    /**
     * Sanitize API token.
     *
     * @param string $value Token value.
     * @return string Encrypted token.
     */
    public function sanitizeApiToken($value)
    {
        if (empty($value)) {
            return '';
        }

        // If it's already encrypted (not changed), return as-is.
        if (strpos($value, '****') !== false) {
            return current_filter() === 'sanitize_option_surecart_shippo_api_token_test'
                ? get_option('surecart_shippo_api_token_test', '')
                : get_option('surecart_shippo_api_token_live', '');
        }

        return Encryption::encrypt($value);
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueueAssets($hook)
    {
        if ($hook !== 'toplevel_page_surecart-shippo') {
            return;
        }

        wp_enqueue_style(
            'surecart-shippo-admin',
            SURECART_SHIPPO_PLUGIN_URL . 'assets/css/admin.css',
            [],
            SURECART_SHIPPO_VERSION
        );

        wp_enqueue_script(
            'surecart-shippo-admin',
            SURECART_SHIPPO_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            SURECART_SHIPPO_VERSION,
            true
        );

        wp_localize_script('surecart-shippo-admin', 'surecartShippoAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('surecart_shippo_admin'),
            'strings' => [
                'testingConnection' => __('Testing connection...', 'surecart-shippo'),
                'connectionSuccess' => __('Connection successful!', 'surecart-shippo'),
                'connectionFailed' => __('Connection failed:', 'surecart-shippo'),
                'seedingBoxes' => __('Creating default boxes...', 'surecart-shippo'),
                'boxesSeeded' => __('Default boxes created successfully!', 'surecart-shippo'),
                'clearingCache' => __('Clearing cache...', 'surecart-shippo'),
                'cacheCleared' => __('Cache cleared successfully!', 'surecart-shippo'),
                'clearingLogs' => __('Clearing logs...', 'surecart-shippo'),
                'logsCleared' => __('Logs cleared successfully!', 'surecart-shippo'),
            ],
        ]);
    }

    /**
     * Render settings page.
     */
    public function renderPage()
    {
        $active_tab = $_GET['tab'] ?? 'api';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('SureCart Shippo Integration', 'surecart-shippo'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=surecart-shippo&tab=api" class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Shippo API', 'surecart-shippo'); ?>
                </a>
                <a href="?page=surecart-shippo&tab=origin" class="nav-tab <?php echo $active_tab === 'origin' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Origin Address', 'surecart-shippo'); ?>
                </a>
                <a href="?page=surecart-shippo&tab=carriers" class="nav-tab <?php echo $active_tab === 'carriers' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Carriers', 'surecart-shippo'); ?>
                </a>
                <a href="?page=surecart-shippo&tab=packaging" class="nav-tab <?php echo $active_tab === 'packaging' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Packaging', 'surecart-shippo'); ?>
                </a>
                <a href="?page=surecart-shippo&tab=boxes" class="nav-tab <?php echo $active_tab === 'boxes' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Box Catalog', 'surecart-shippo'); ?>
                </a>
                <a href="?page=surecart-shippo&tab=fallback" class="nav-tab <?php echo $active_tab === 'fallback' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Fallback', 'surecart-shippo'); ?>
                </a>
                <a href="?page=surecart-shippo&tab=diagnostics" class="nav-tab <?php echo $active_tab === 'diagnostics' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Diagnostics', 'surecart-shippo'); ?>
                </a>
            </nav>

            <div class="surecart-shippo-settings-content">
                <?php
                switch ($active_tab) {
                    case 'api':
                        $this->renderApiTab();
                        break;
                    case 'origin':
                        $this->renderOriginTab();
                        break;
                    case 'carriers':
                        $this->renderCarriersTab();
                        break;
                    case 'packaging':
                        $this->renderPackagingTab();
                        break;
                    case 'boxes':
                        $this->renderBoxesTab();
                        break;
                    case 'fallback':
                        $this->renderFallbackTab();
                        break;
                    case 'diagnostics':
                        $this->renderDiagnosticsTab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render API tab.
     */
    private function renderApiTab()
    {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('surecart_shippo_api');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Mode', 'surecart-shippo'); ?></th>
                    <td>
                        <select name="surecart_shippo_mode">
                            <option value="test" <?php selected(get_option('surecart_shippo_mode'), 'test'); ?>>
                                <?php esc_html_e('Test', 'surecart-shippo'); ?>
                            </option>
                            <option value="live" <?php selected(get_option('surecart_shippo_mode'), 'live'); ?>>
                                <?php esc_html_e('Live', 'surecart-shippo'); ?>
                            </option>
                        </select>
                        <p class="description"><?php esc_html_e('Use test mode for development and testing.', 'surecart-shippo'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Test API Token', 'surecart-shippo'); ?></th>
                    <td>
                        <input type="password" name="surecart_shippo_api_token_test" value="<?php echo esc_attr(get_option('surecart_shippo_api_token_test') ? '********' : ''); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e('Your Shippo test API token.', 'surecart-shippo'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Live API Token', 'surecart-shippo'); ?></th>
                    <td>
                        <input type="password" name="surecart_shippo_api_token_live" value="<?php echo esc_attr(get_option('surecart_shippo_api_token_live') ? '********' : ''); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e('Your Shippo live API token.', 'surecart-shippo'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <hr>

        <h2><?php esc_html_e('Test Connection', 'surecart-shippo'); ?></h2>
        <button type="button" class="button" id="test-shippo-connection">
            <?php esc_html_e('Test Connection', 'surecart-shippo'); ?>
        </button>
        <span id="test-connection-result"></span>
        <?php
    }

    /**
     * Render origin address tab.
     */
    private function renderOriginTab()
    {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('surecart_shippo_origin');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Name', 'surecart-shippo'); ?></th>
                    <td>
                        <input type="text" name="surecart_shippo_origin_name" value="<?php echo esc_attr(get_option('surecart_shippo_origin_name')); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Company', 'surecart-shippo'); ?></th>
                    <td>
                        <input type="text" name="surecart_shippo_origin_company" value="<?php echo esc_attr(get_option('surecart_shippo_origin_company')); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Street 1', 'surecart-shippo'); ?></th>
                    <td>
                        <input type="text" name="surecart_shippo_origin_street1" value="<?php echo esc_attr(get_option('surecart_shippo_origin_street1')); ?>" class="regular-text" required />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Street 2', 'surecart-shippo'); ?></th>
                    <td>
                        <input type="text" name="surecart_shippo_origin_street2" value="<?php echo esc_attr(get_option('surecart_shippo_origin_street2')); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('City', 'surecart-shippo'); ?></th>
                    <td>
                        <input type="text" name="surecart_shippo_origin_city" value="<?php echo esc_attr(get_option('surecart_shippo_origin_city')); ?>" class="regular-text" required />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('State', 'surecart-shippo'); ?></th>
                    <td>
                        <input type="text" name="surecart_shippo_origin_state" value="<?php echo esc_attr(get_option('surecart_shippo_origin_state')); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('ZIP Code', 'surecart-shippo'); ?></th>
                    <td>
                        <input type="text" name="surecart_shippo_origin_zip" value="<?php echo esc_attr(get_option('surecart_shippo_origin_zip')); ?>" class="regular-text" required />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Country', 'surecart-shippo'); ?></th>
                    <td>
                        <input type="text" name="surecart_shippo_origin_country" value="<?php echo esc_attr(get_option('surecart_shippo_origin_country', 'US')); ?>" class="regular-text" required />
                        <p class="description"><?php esc_html_e('2-letter country code (e.g., US, CA)', 'surecart-shippo'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Phone', 'surecart-shippo'); ?></th>
                    <td>
                        <input type="text" name="surecart_shippo_origin_phone" value="<?php echo esc_attr(get_option('surecart_shippo_origin_phone')); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Email', 'surecart-shippo'); ?></th>
                    <td>
                        <input type="email" name="surecart_shippo_origin_email" value="<?php echo esc_attr(get_option('surecart_shippo_origin_email')); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render remaining tabs (stubs for now).
     */
    private function renderCarriersTab()
    {
        echo '<p>' . esc_html__('Carrier settings will be implemented here.', 'surecart-shippo') . '</p>';
    }

    private function renderPackagingTab()
    {
        echo '<p>' . esc_html__('Packaging settings will be implemented here.', 'surecart-shippo') . '</p>';
    }

    private function renderBoxesTab()
    {
        echo '<p>' . esc_html__('Box catalog will be implemented here.', 'surecart-shippo') . '</p>';
    }

    private function renderFallbackTab()
    {
        echo '<p>' . esc_html__('Fallback settings will be implemented here.', 'surecart-shippo') . '</p>';
    }

    private function renderDiagnosticsTab()
    {
        echo '<p>' . esc_html__('Diagnostics will be implemented here.', 'surecart-shippo') . '</p>';
    }

    /**
     * AJAX: Test Shippo connection.
     */
    public function ajaxTestConnection()
    {
        check_ajax_referer('surecart_shippo_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'surecart-shippo')]);
        }

        $result = \surecart_shippo()->shippo_client->testConnection();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Connection successful!', 'surecart-shippo')]);
    }

    /**
     * AJAX: Seed default boxes.
     */
    public function ajaxSeedBoxes()
    {
        check_ajax_referer('surecart_shippo_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'surecart-shippo')]);
        }

        $box_catalog = new BoxCatalog();
        $created = $box_catalog->seedDefaults();

        wp_send_json_success([
            'message' => sprintf(__('%d boxes created.', 'surecart-shippo'), $created),
        ]);
    }

    /**
     * AJAX: Clear cache.
     */
    public function ajaxClearCache()
    {
        check_ajax_referer('surecart_shippo_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'surecart-shippo')]);
        }

        $rate_service = new \SureCartShippo\Services\RateService();
        $rate_service->clearCache();

        wp_send_json_success(['message' => __('Cache cleared!', 'surecart-shippo')]);
    }

    /**
     * AJAX: Clear logs.
     */
    public function ajaxClearLogs()
    {
        check_ajax_referer('surecart_shippo_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'surecart-shippo')]);
        }

        \surecart_shippo()->logger->clear_logs();

        wp_send_json_success(['message' => __('Logs cleared!', 'surecart-shippo')]);
    }
}
