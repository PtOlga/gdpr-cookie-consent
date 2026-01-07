<?php
/**
 * Plugin Name: GDPR Cookie Consent
 * Plugin URI: https://github.com/PtOlga/gdpr-cookie-consent
 * Description: Lightweight GDPR cookie consent banner. Minimal, non-intrusive, customizable.
 * Version: 1.0.0
 * Author: PtOlga
 * License: GPL v2 or later
 * Text Domain: gdpr-cookie-consent
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GDPR_CC_VERSION', '1.0.0');
define('GDPR_CC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GDPR_CC_PLUGIN_URL', plugin_dir_url(__FILE__));

class GDPR_Cookie_Consent {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // CRITICAL: Consent Mode must load BEFORE any Google scripts
        add_action('wp_head', [$this, 'render_consent_mode_default'], 1);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_footer', [$this, 'render_banner']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_gdpr_save_consent', [$this, 'ajax_save_consent']);
        add_action('wp_ajax_nopriv_gdpr_save_consent', [$this, 'ajax_save_consent']);
    }
    
    /**
     * Google Consent Mode v2 - Default State
     * MUST run before gtag.js / Google Tag Manager / Site Kit
     */
    public function render_consent_mode_default() {
        $consent = $_COOKIE['gdpr_consent'] ?? null;
        $analytics = ($consent === 'accepted') ? 'granted' : 'denied';
        $ads = ($consent === 'accepted') ? 'granted' : 'denied';
        
        ?>
        <script>
        // Google Consent Mode v2 - Initialize BEFORE any Google tags
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        
        // Set default consent state
        gtag('consent', 'default', {
            'ad_storage': '<?php echo $ads; ?>',
            'ad_user_data': '<?php echo $ads; ?>',
            'ad_personalization': '<?php echo $ads; ?>',
            'analytics_storage': '<?php echo $analytics; ?>',
            'functionality_storage': 'granted',
            'personalization_storage': '<?php echo $analytics; ?>',
            'security_storage': 'granted',
            'wait_for_update': 500
        });
        
        <?php if (!$consent): ?>
        // No consent yet - ensure denied state
        gtag('set', 'ads_data_redaction', true);
        gtag('set', 'url_passthrough', true);
        <?php endif; ?>
        </script>
        <?php
    }
    
    public function enqueue_assets() {
        if ($this->has_consent()) {
            return; // Don't show banner if already consented
        }
        
        wp_enqueue_style(
            'gdpr-cc-style',
            GDPR_CC_PLUGIN_URL . 'assets/css/banner.css',
            [],
            GDPR_CC_VERSION
        );
        
        wp_enqueue_script(
            'gdpr-cc-script',
            GDPR_CC_PLUGIN_URL . 'assets/js/consent.js',
            [],
            GDPR_CC_VERSION,
            true
        );
        
        wp_localize_script('gdpr-cc-script', 'gdprCC', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdpr_cc_nonce'),
            'expires' => $this->get_option('cookie_expiry', 365),
        ]);
    }
    
    public function render_banner() {
        if ($this->has_consent()) {
            return;
        }
        
        $options = $this->get_all_options();
        
        $banner_bg = esc_attr($options['banner_bg_color']);
        $banner_text = esc_attr($options['banner_text_color']);
        $btn_bg = esc_attr($options['button_bg_color']);
        $btn_text = esc_attr($options['button_text_color']);
        $message = esc_html($options['banner_message']);
        $btn_accept = esc_html($options['button_accept_text']);
        $btn_reject = esc_html($options['button_reject_text']);
        $privacy_url = esc_url($options['privacy_policy_url']);
        $privacy_text = esc_html($options['privacy_link_text']);
        
        ?>
        <div id="gdpr-cookie-banner" class="gdpr-banner" style="background-color: <?php echo $banner_bg; ?>; color: <?php echo $banner_text; ?>;">
            <div class="gdpr-banner-content">
                <span class="gdpr-banner-message">
                    <?php echo $message; ?>
                    <?php if ($privacy_url): ?>
                        <a href="<?php echo $privacy_url; ?>" target="_blank" style="color: <?php echo $banner_text; ?>;"><?php echo $privacy_text; ?></a>
                    <?php endif; ?>
                </span>
                <div class="gdpr-banner-buttons">
                    <button type="button" class="gdpr-btn gdpr-btn-reject" data-action="reject" style="color: <?php echo $banner_text; ?>; border-color: <?php echo $banner_text; ?>;">
                        <?php echo $btn_reject; ?>
                    </button>
                    <button type="button" class="gdpr-btn gdpr-btn-accept" data-action="accept" style="background-color: <?php echo $btn_bg; ?>; color: <?php echo $btn_text; ?>;">
                        <?php echo $btn_accept; ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function has_consent() {
        return isset($_COOKIE['gdpr_consent']);
    }
    
    public function ajax_save_consent() {
        check_ajax_referer('gdpr_cc_nonce', 'nonce');
        
        $consent = sanitize_text_field($_POST['consent'] ?? 'rejected');
        $expiry = $this->get_option('cookie_expiry', 365);
        
        // Log consent for GDPR compliance
        $this->log_consent($consent);
        
        wp_send_json_success(['consent' => $consent]);
    }
    
    private function log_consent($consent) {
        if (!$this->get_option('log_consents', false)) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'gdpr_consent_log';
        
        $wpdb->insert($table, [
            'ip_hash' => wp_hash($_SERVER['REMOTE_ADDR'] ?? ''),
            'consent' => $consent,
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'created_at' => current_time('mysql'),
        ]);
    }
    
    // ========== ADMIN SETTINGS ==========
    
    public function add_admin_menu() {
        add_options_page(
            __('GDPR Cookie Consent', 'gdpr-cookie-consent'),
            __('Cookie Consent', 'gdpr-cookie-consent'),
            'manage_options',
            'gdpr-cookie-consent',
            [$this, 'render_settings_page']
        );
    }
    
    public function register_settings() {
        register_setting('gdpr_cc_settings', 'gdpr_cc_options', [$this, 'sanitize_options']);
        
        // Appearance Section
        add_settings_section(
            'gdpr_cc_appearance',
            __('Appearance', 'gdpr-cookie-consent'),
            null,
            'gdpr-cookie-consent'
        );
        
        add_settings_field('banner_bg_color', __('Banner Background', 'gdpr-cookie-consent'), 
            [$this, 'render_color_field'], 'gdpr-cookie-consent', 'gdpr_cc_appearance', ['field' => 'banner_bg_color', 'default' => '#1f2937']);
        
        add_settings_field('banner_text_color', __('Banner Text', 'gdpr-cookie-consent'), 
            [$this, 'render_color_field'], 'gdpr-cookie-consent', 'gdpr_cc_appearance', ['field' => 'banner_text_color', 'default' => '#ffffff']);
        
        add_settings_field('button_bg_color', __('Accept Button Background', 'gdpr-cookie-consent'), 
            [$this, 'render_color_field'], 'gdpr-cookie-consent', 'gdpr_cc_appearance', ['field' => 'button_bg_color', 'default' => '#22c55e']);
        
        add_settings_field('button_text_color', __('Accept Button Text', 'gdpr-cookie-consent'), 
            [$this, 'render_color_field'], 'gdpr-cookie-consent', 'gdpr_cc_appearance', ['field' => 'button_text_color', 'default' => '#ffffff']);
        
        // Text Section
        add_settings_section(
            'gdpr_cc_text',
            __('Text & Labels', 'gdpr-cookie-consent'),
            null,
            'gdpr-cookie-consent'
        );
        
        add_settings_field('banner_message', __('Banner Message', 'gdpr-cookie-consent'), 
            [$this, 'render_textarea_field'], 'gdpr-cookie-consent', 'gdpr_cc_text', 
            ['field' => 'banner_message', 'default' => 'We use cookies to improve your experience.']);
        
        add_settings_field('button_accept_text', __('Accept Button', 'gdpr-cookie-consent'), 
            [$this, 'render_text_field'], 'gdpr-cookie-consent', 'gdpr_cc_text', 
            ['field' => 'button_accept_text', 'default' => 'Accept']);
        
        add_settings_field('button_reject_text', __('Reject Button', 'gdpr-cookie-consent'), 
            [$this, 'render_text_field'], 'gdpr-cookie-consent', 'gdpr_cc_text', 
            ['field' => 'button_reject_text', 'default' => 'Reject']);
        
        add_settings_field('privacy_policy_url', __('Privacy Policy URL', 'gdpr-cookie-consent'), 
            [$this, 'render_url_field'], 'gdpr-cookie-consent', 'gdpr_cc_text', 
            ['field' => 'privacy_policy_url', 'default' => '']);
        
        add_settings_field('privacy_link_text', __('Privacy Link Text', 'gdpr-cookie-consent'), 
            [$this, 'render_text_field'], 'gdpr-cookie-consent', 'gdpr_cc_text', 
            ['field' => 'privacy_link_text', 'default' => 'Learn more']);
        
        // Settings Section
        add_settings_section(
            'gdpr_cc_settings_section',
            __('Settings', 'gdpr-cookie-consent'),
            null,
            'gdpr-cookie-consent'
        );
        
        add_settings_field('cookie_expiry', __('Cookie Expiry (days)', 'gdpr-cookie-consent'), 
            [$this, 'render_number_field'], 'gdpr-cookie-consent', 'gdpr_cc_settings_section', 
            ['field' => 'cookie_expiry', 'default' => 365]);
    }
    
    public function render_color_field($args) {
        $value = $this->get_option($args['field'], $args['default']);
        printf(
            '<input type="color" name="gdpr_cc_options[%s]" value="%s" />',
            esc_attr($args['field']),
            esc_attr($value)
        );
    }
    
    public function render_text_field($args) {
        $value = $this->get_option($args['field'], $args['default']);
        printf(
            '<input type="text" name="gdpr_cc_options[%s]" value="%s" class="regular-text" />',
            esc_attr($args['field']),
            esc_attr($value)
        );
    }
    
    public function render_textarea_field($args) {
        $value = $this->get_option($args['field'], $args['default']);
        printf(
            '<textarea name="gdpr_cc_options[%s]" class="large-text" rows="2">%s</textarea>',
            esc_attr($args['field']),
            esc_textarea($value)
        );
    }
    
    public function render_url_field($args) {
        $value = $this->get_option($args['field'], $args['default']);
        printf(
            '<input type="url" name="gdpr_cc_options[%s]" value="%s" class="regular-text" placeholder="https://" />',
            esc_attr($args['field']),
            esc_url($value)
        );
    }
    
    public function render_number_field($args) {
        $value = $this->get_option($args['field'], $args['default']);
        printf(
            '<input type="number" name="gdpr_cc_options[%s]" value="%s" min="1" max="730" class="small-text" />',
            esc_attr($args['field']),
            esc_attr($value)
        );
    }
    
    public function sanitize_options($input) {
        $sanitized = [];
        
        $sanitized['banner_bg_color'] = sanitize_hex_color($input['banner_bg_color'] ?? '#1f2937');
        $sanitized['banner_text_color'] = sanitize_hex_color($input['banner_text_color'] ?? '#ffffff');
        $sanitized['button_bg_color'] = sanitize_hex_color($input['button_bg_color'] ?? '#22c55e');
        $sanitized['button_text_color'] = sanitize_hex_color($input['button_text_color'] ?? '#ffffff');
        $sanitized['banner_message'] = sanitize_textarea_field($input['banner_message'] ?? '');
        $sanitized['button_accept_text'] = sanitize_text_field($input['button_accept_text'] ?? 'Accept');
        $sanitized['button_reject_text'] = sanitize_text_field($input['button_reject_text'] ?? 'Reject');
        $sanitized['privacy_policy_url'] = esc_url_raw($input['privacy_policy_url'] ?? '');
        $sanitized['privacy_link_text'] = sanitize_text_field($input['privacy_link_text'] ?? 'Learn more');
        $sanitized['cookie_expiry'] = absint($input['cookie_expiry'] ?? 365);
        
        return $sanitized;
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #22c55e;">
                <strong>Preview:</strong> The banner will appear at the bottom of your site for visitors who haven't made a choice yet.
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('gdpr_cc_settings');
                do_settings_sections('gdpr-cookie-consent');
                submit_button(__('Save Settings', 'gdpr-cookie-consent'));
                ?>
            </form>
            
            <div style="margin-top: 30px; padding: 20px; background: #f9fafb; border-radius: 8px;">
                <h3>🔍 Test with GDPR Scanner</h3>
                <p>Check if your cookie consent is properly configured:</p>
                <a href="https://gdpr-scanner.example.com" target="_blank" class="button">Scan My Website</a>
            </div>
        </div>
        <?php
    }
    
    private function get_option($key, $default = '') {
        $options = get_option('gdpr_cc_options', []);
        return $options[$key] ?? $default;
    }
    
    private function get_all_options() {
        $defaults = [
            'banner_bg_color' => '#1f2937',
            'banner_text_color' => '#ffffff',
            'button_bg_color' => '#22c55e',
            'button_text_color' => '#ffffff',
            'banner_message' => 'We use cookies to improve your experience.',
            'button_accept_text' => 'Accept',
            'button_reject_text' => 'Reject',
            'privacy_policy_url' => '',
            'privacy_link_text' => 'Learn more',
            'cookie_expiry' => 365,
        ];
        
        return wp_parse_args(get_option('gdpr_cc_options', []), $defaults);
    }
}

// Initialize plugin
GDPR_Cookie_Consent::get_instance();

// Add Settings link on Plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="options-general.php?page=gdpr-cookie-consent">' . __('Settings', 'gdpr-cookie-consent') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Activation hook - create log table
register_activation_hook(__FILE__, function() {
    global $wpdb;
    $table = $wpdb->prefix . 'gdpr_consent_log';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        ip_hash varchar(64) NOT NULL,
        consent varchar(20) NOT NULL,
        user_agent text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY consent (consent),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});
