<?php
/**
 * Plugin Name: GDPR Cookie Consent
 * Plugin URI: https://ptolga.github.io
 * Description: Lightweight GDPR cookie consent banner with Google Consent Mode v2. Minimal, non-intrusive, customizable.
 * Version: 1.0.0
 * Author: PtOlga
 * Author URI: https://ptolga.github.io
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
        $btn_save = esc_html($options['button_save_text'] ?? 'Save preferences');
        $privacy_url = esc_url($options['privacy_policy_url']);
        $privacy_text = esc_html($options['privacy_link_text']);
        
        // Category labels
        $cat_necessary = esc_html($options['category_necessary_label'] ?? 'Necessary');
        $cat_analytics = esc_html($options['category_analytics_label'] ?? 'Analytics');
        $cat_marketing = esc_html($options['category_marketing_label'] ?? 'Marketing');
        $cat_necessary_desc = esc_html($options['category_necessary_desc'] ?? 'Essential for the website to function');
        $cat_analytics_desc = esc_html($options['category_analytics_desc'] ?? 'Help us understand how visitors use our site');
        $cat_marketing_desc = esc_html($options['category_marketing_desc'] ?? 'Used for targeted advertising');
        
        ?>
        <div id="gdpr-cookie-banner" class="gdpr-banner" style="background-color: <?php echo $banner_bg; ?>; color: <?php echo $banner_text; ?>;">
            <div class="gdpr-banner-content">
                <div class="gdpr-banner-main">
                    <span class="gdpr-banner-message">
                        <?php echo $message; ?>
                        <?php if ($privacy_url): ?>
                            <a href="<?php echo $privacy_url; ?>" target="_blank" style="color: <?php echo $banner_text; ?>;"><?php echo $privacy_text; ?></a>
                        <?php endif; ?>
                    </span>
                    
                    <div class="gdpr-categories">
                        <label class="gdpr-category gdpr-category-necessary">
                            <input type="checkbox" checked disabled data-category="necessary">
                            <span class="gdpr-category-name"><?php echo $cat_necessary; ?></span>
                            <span class="gdpr-category-desc"><?php echo $cat_necessary_desc; ?></span>
                        </label>
                        
                        <label class="gdpr-category">
                            <input type="checkbox" data-category="analytics">
                            <span class="gdpr-category-name"><?php echo $cat_analytics; ?></span>
                            <span class="gdpr-category-desc"><?php echo $cat_analytics_desc; ?></span>
                        </label>
                        
                        <label class="gdpr-category">
                            <input type="checkbox" data-category="marketing">
                            <span class="gdpr-category-name"><?php echo $cat_marketing; ?></span>
                            <span class="gdpr-category-desc"><?php echo $cat_marketing_desc; ?></span>
                        </label>
                    </div>
                </div>
                
                <div class="gdpr-banner-buttons">
                    <button type="button" class="gdpr-btn gdpr-btn-reject" data-action="reject" style="color: <?php echo $banner_text; ?>; border-color: <?php echo $banner_text; ?>;">
                        <?php echo $btn_reject; ?>
                    </button>
                    <button type="button" class="gdpr-btn gdpr-btn-save" data-action="save" style="color: <?php echo $banner_text; ?>; border-color: <?php echo $banner_text; ?>;">
                        <?php echo $btn_save; ?>
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
        
        add_settings_field('use_theme_colors', __('Use Theme Colors', 'gdpr-cookie-consent'), 
            [$this, 'render_checkbox_field'], 'gdpr-cookie-consent', 'gdpr_cc_appearance', 
            ['field' => 'use_theme_colors', 'default' => false, 'description' => __('Automatically use colors from your WordPress theme', 'gdpr-cookie-consent')]);
        
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
            ['field' => 'button_accept_text', 'default' => 'Accept all']);
        
        add_settings_field('button_reject_text', __('Reject Button', 'gdpr-cookie-consent'), 
            [$this, 'render_text_field'], 'gdpr-cookie-consent', 'gdpr_cc_text', 
            ['field' => 'button_reject_text', 'default' => 'Reject all']);
        
        add_settings_field('button_save_text', __('Save Button', 'gdpr-cookie-consent'), 
            [$this, 'render_text_field'], 'gdpr-cookie-consent', 'gdpr_cc_text', 
            ['field' => 'button_save_text', 'default' => 'Save preferences']);
        
        add_settings_field('privacy_policy_url', __('Privacy Policy URL', 'gdpr-cookie-consent'), 
            [$this, 'render_url_field'], 'gdpr-cookie-consent', 'gdpr_cc_text', 
            ['field' => 'privacy_policy_url', 'default' => '']);
        
        add_settings_field('privacy_link_text', __('Privacy Link Text', 'gdpr-cookie-consent'), 
            [$this, 'render_text_field'], 'gdpr-cookie-consent', 'gdpr_cc_text', 
            ['field' => 'privacy_link_text', 'default' => 'Learn more']);
        
        // Categories Section
        add_settings_section(
            'gdpr_cc_categories',
            __('Cookie Categories', 'gdpr-cookie-consent'),
            function() {
                echo '<p>' . __('Customize the labels and descriptions for each cookie category.', 'gdpr-cookie-consent') . '</p>';
            },
            'gdpr-cookie-consent'
        );
        
        add_settings_field('category_necessary_label', __('Necessary - Label', 'gdpr-cookie-consent'), 
            [$this, 'render_text_field'], 'gdpr-cookie-consent', 'gdpr_cc_categories', 
            ['field' => 'category_necessary_label', 'default' => 'Necessary']);
        
        add_settings_field('category_necessary_desc', __('Necessary - Description', 'gdpr-cookie-consent'), 
            [$this, 'render_text_field'], 'gdpr-cookie-consent', 'gdpr_cc_categories', 
            ['field' => 'category_necessary_desc', 'default' => 'Essential for the website to function']);
        
        add_settings_field('category_analytics_label', __('Analytics - Label', 'gdpr-cookie-consent'), 
            [$this, 'render_text_field'], 'gdpr-cookie-consent', 'gdpr_cc_categories', 
            ['field' => 'category_analytics_label', 'default' => 'Analytics']);
        
        add_settings_field('category_analytics_desc', __('Analytics - Description', 'gdpr-cookie-consent'), 
            [$this, 'render_text_field'], 'gdpr-cookie-consent', 'gdpr_cc_categories', 
            ['field' => 'category_analytics_desc', 'default' => 'Help us understand how visitors use our site']);
        
        add_settings_field('category_marketing_label', __('Marketing - Label', 'gdpr-cookie-consent'), 
            [$this, 'render_text_field'], 'gdpr-cookie-consent', 'gdpr_cc_categories', 
            ['field' => 'category_marketing_label', 'default' => 'Marketing']);
        
        add_settings_field('category_marketing_desc', __('Marketing - Description', 'gdpr-cookie-consent'), 
            [$this, 'render_text_field'], 'gdpr-cookie-consent', 'gdpr_cc_categories', 
            ['field' => 'category_marketing_desc', 'default' => 'Used for targeted advertising']);
        
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
    
    public function render_checkbox_field($args) {
        $value = $this->get_option($args['field'], $args['default']);
        printf(
            '<label><input type="checkbox" name="gdpr_cc_options[%s]" value="1" %s /> %s</label>',
            esc_attr($args['field']),
            checked($value, true, false),
            esc_html($args['description'] ?? '')
        );
        
        // Show detected theme colors
        if ($args['field'] === 'use_theme_colors') {
            $theme_colors = $this->get_theme_colors();
            $detected = array_filter($theme_colors);
            
            if ($detected) {
                echo '<div style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-radius: 4px;">';
                echo '<strong>' . __('Detected theme colors:', 'gdpr-cookie-consent') . '</strong><br>';
                foreach ($detected as $name => $color) {
                    printf(
                        '<span style="display: inline-block; margin: 5px 10px 0 0;"><span style="display: inline-block; width: 16px; height: 16px; background: %s; border: 1px solid #ccc; vertical-align: middle; border-radius: 3px;"></span> %s: %s</span>',
                        esc_attr($color),
                        esc_html(ucfirst($name)),
                        esc_html($color)
                    );
                }
                echo '</div>';
            } else {
                echo '<p style="color: #666; margin-top: 5px;"><em>' . __('No theme colors detected. Using manual colors.', 'gdpr-cookie-consent') . '</em></p>';
            }
        }
    }
    
    public function sanitize_options($input) {
        $sanitized = [];
        
        $sanitized['use_theme_colors'] = !empty($input['use_theme_colors']);
        $sanitized['banner_bg_color'] = sanitize_hex_color($input['banner_bg_color'] ?? '#1f2937');
        $sanitized['banner_text_color'] = sanitize_hex_color($input['banner_text_color'] ?? '#ffffff');
        $sanitized['button_bg_color'] = sanitize_hex_color($input['button_bg_color'] ?? '#22c55e');
        $sanitized['button_text_color'] = sanitize_hex_color($input['button_text_color'] ?? '#ffffff');
        $sanitized['banner_message'] = sanitize_textarea_field($input['banner_message'] ?? '');
        $sanitized['button_accept_text'] = sanitize_text_field($input['button_accept_text'] ?? 'Accept all');
        $sanitized['button_reject_text'] = sanitize_text_field($input['button_reject_text'] ?? 'Reject all');
        $sanitized['button_save_text'] = sanitize_text_field($input['button_save_text'] ?? 'Save preferences');
        $sanitized['privacy_policy_url'] = esc_url_raw($input['privacy_policy_url'] ?? '');
        $sanitized['privacy_link_text'] = sanitize_text_field($input['privacy_link_text'] ?? 'Learn more');
        $sanitized['cookie_expiry'] = absint($input['cookie_expiry'] ?? 365);
        
        // Category labels
        $sanitized['category_necessary_label'] = sanitize_text_field($input['category_necessary_label'] ?? 'Necessary');
        $sanitized['category_necessary_desc'] = sanitize_text_field($input['category_necessary_desc'] ?? 'Essential for the website to function');
        $sanitized['category_analytics_label'] = sanitize_text_field($input['category_analytics_label'] ?? 'Analytics');
        $sanitized['category_analytics_desc'] = sanitize_text_field($input['category_analytics_desc'] ?? 'Help us understand how visitors use our site');
        $sanitized['category_marketing_label'] = sanitize_text_field($input['category_marketing_label'] ?? 'Marketing');
        $sanitized['category_marketing_desc'] = sanitize_text_field($input['category_marketing_desc'] ?? 'Used for targeted advertising');
        
        return $sanitized;
    }
    
    public function render_settings_page() {
        $options = $this->get_all_options();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #22c55e;">
                <strong><?php _e('Live Preview:', 'gdpr-cookie-consent'); ?></strong>
                <div id="gdpr-preview" style="margin-top: 15px; padding: 15px 20px; border-radius: 4px; font-size: 14px; background: <?php echo esc_attr($options['banner_bg_color']); ?>; color: <?php echo esc_attr($options['banner_text_color']); ?>;">
                    <div style="margin-bottom: 12px;">
                        <?php echo esc_html($options['banner_message']); ?> 
                        <a href="#" style="color: inherit; text-decoration: underline;"><?php echo esc_html($options['privacy_link_text']); ?></a>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                        <label style="display: flex; align-items: flex-start; gap: 8px; padding: 8px 12px; background: rgba(255,255,255,0.1); border-radius: 6px; opacity: 0.7;">
                            <input type="checkbox" checked disabled style="margin-top: 2px;">
                            <span>
                                <strong style="display: block;"><?php echo esc_html($options['category_necessary_label']); ?></strong>
                                <small style="opacity: 0.8;"><?php echo esc_html($options['category_necessary_desc']); ?></small>
                            </span>
                        </label>
                        <label style="display: flex; align-items: flex-start; gap: 8px; padding: 8px 12px; background: rgba(255,255,255,0.1); border-radius: 6px; cursor: pointer;">
                            <input type="checkbox" style="margin-top: 2px;">
                            <span>
                                <strong style="display: block;"><?php echo esc_html($options['category_analytics_label']); ?></strong>
                                <small style="opacity: 0.8;"><?php echo esc_html($options['category_analytics_desc']); ?></small>
                            </span>
                        </label>
                        <label style="display: flex; align-items: flex-start; gap: 8px; padding: 8px 12px; background: rgba(255,255,255,0.1); border-radius: 6px; cursor: pointer;">
                            <input type="checkbox" style="margin-top: 2px;">
                            <span>
                                <strong style="display: block;"><?php echo esc_html($options['category_marketing_label']); ?></strong>
                                <small style="opacity: 0.8;"><?php echo esc_html($options['category_marketing_desc']); ?></small>
                            </span>
                        </label>
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" style="padding: 8px 16px; border-radius: 4px; font-size: 13px; cursor: default; background: transparent; border: 1px solid <?php echo esc_attr($options['banner_text_color']); ?>; color: <?php echo esc_attr($options['banner_text_color']); ?>;"><?php echo esc_html($options['button_reject_text']); ?></button>
                        <button type="button" style="padding: 8px 16px; border-radius: 4px; font-size: 13px; cursor: default; background: transparent; border: 1px solid <?php echo esc_attr($options['banner_text_color']); ?>; color: <?php echo esc_attr($options['banner_text_color']); ?>;"><?php echo esc_html($options['button_save_text']); ?></button>
                        <button type="button" style="padding: 8px 16px; border-radius: 4px; font-size: 13px; cursor: default; border: none; background: <?php echo esc_attr($options['button_bg_color']); ?>; color: <?php echo esc_attr($options['button_text_color']); ?>;"><?php echo esc_html($options['button_accept_text']); ?></button>
                    </div>
                </div>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('gdpr_cc_settings');
                do_settings_sections('gdpr-cookie-consent');
                submit_button(__('Save Settings', 'gdpr-cookie-consent'));
                ?>
            </form>
            
            <div style="margin-top: 30px; padding: 20px; background: #f9fafb; border-radius: 8px;">
                <h3>🔍 <?php _e('Test with GDPR Scanner', 'gdpr-cookie-consent'); ?></h3>
                <p><?php _e('Check if your cookie consent is properly configured:', 'gdpr-cookie-consent'); ?></p>
                <a href="https://web-production-0704b.up.railway.app" target="_blank" class="button"><?php _e('Scan My Website', 'gdpr-cookie-consent'); ?></a>
            </div>
        </div>
        <?php
    }
    
    private function get_option($key, $default = '') {
        $options = get_option('gdpr_cc_options', []);
        return $options[$key] ?? $default;
    }
    
    /**
     * Get colors from WordPress theme (Customizer, theme.json, or Global Styles)
     */
    private function get_theme_colors() {
        $colors = [
            'primary' => null,
            'secondary' => null,
            'background' => null,
            'text' => null,
        ];
        
        // Method 1: Block themes (FSE) - WordPress 5.9+
        if (function_exists('wp_get_global_styles')) {
            $global_styles = wp_get_global_styles();
            
            if (!empty($global_styles['color']['background'])) {
                $colors['background'] = $global_styles['color']['background'];
            }
            if (!empty($global_styles['color']['text'])) {
                $colors['text'] = $global_styles['color']['text'];
            }
        }
        
        // Method 2: Theme.json palette (WordPress 5.8+)
        if (function_exists('wp_get_global_settings')) {
            $settings = wp_get_global_settings();
            $palette = $settings['color']['palette']['theme'] ?? [];
            
            foreach ($palette as $color) {
                $slug = $color['slug'] ?? '';
                $hex = $color['color'] ?? '';
                
                if (in_array($slug, ['primary', 'accent', 'brand'])) {
                    $colors['primary'] = $colors['primary'] ?? $hex;
                }
                if (in_array($slug, ['secondary', 'contrast'])) {
                    $colors['secondary'] = $colors['secondary'] ?? $hex;
                }
                if (in_array($slug, ['base', 'background', 'white'])) {
                    $colors['background'] = $colors['background'] ?? $hex;
                }
                if (in_array($slug, ['contrast', 'text', 'black'])) {
                    $colors['text'] = $colors['text'] ?? $hex;
                }
            }
        }
        
        // Method 3: Customizer colors (classic themes)
        $custom_bg = get_background_color();
        if ($custom_bg && !$colors['background']) {
            $colors['background'] = '#' . ltrim($custom_bg, '#');
        }
        
        $header_text = get_theme_mod('header_textcolor');
        if ($header_text && $header_text !== 'blank' && !$colors['text']) {
            $colors['text'] = '#' . ltrim($header_text, '#');
        }
        
        // Method 4: Common theme mods
        $primary_color = get_theme_mod('primary_color') ?: get_theme_mod('accent_color') ?: get_theme_mod('link_color');
        if ($primary_color && !$colors['primary']) {
            $colors['primary'] = $primary_color;
        }
        
        return $colors;
    }
    
    /**
     * Determine if a color is light or dark
     */
    private function is_light_color($hex) {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Calculate luminance
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        
        return $luminance > 0.5;
    }
    
    private function get_all_options() {
        $defaults = [
            'banner_bg_color' => '#1f2937',
            'banner_text_color' => '#ffffff',
            'button_bg_color' => '#22c55e',
            'button_text_color' => '#ffffff',
            'banner_message' => 'We use cookies to improve your experience.',
            'button_accept_text' => 'Accept all',
            'button_reject_text' => 'Reject all',
            'button_save_text' => 'Save preferences',
            'privacy_policy_url' => '',
            'privacy_link_text' => 'Learn more',
            'cookie_expiry' => 365,
            'use_theme_colors' => false,
            'category_necessary_label' => 'Necessary',
            'category_necessary_desc' => 'Essential for the website to function',
            'category_analytics_label' => 'Analytics',
            'category_analytics_desc' => 'Help us understand how visitors use our site',
            'category_marketing_label' => 'Marketing',
            'category_marketing_desc' => 'Used for targeted advertising',
        ];
        
        $options = wp_parse_args(get_option('gdpr_cc_options', []), $defaults);
        
        // Override with theme colors if enabled
        if (!empty($options['use_theme_colors'])) {
            $theme_colors = $this->get_theme_colors();
            
            // Use theme primary as button color
            if ($theme_colors['primary']) {
                $options['button_bg_color'] = $theme_colors['primary'];
                $options['button_text_color'] = $this->is_light_color($theme_colors['primary']) ? '#1f2937' : '#ffffff';
            }
            
            // Use dark background with light text (or inverse based on theme)
            if ($theme_colors['text']) {
                $options['banner_bg_color'] = $theme_colors['text'];
                $options['banner_text_color'] = $this->is_light_color($theme_colors['text']) ? '#1f2937' : '#ffffff';
            }
        }
        
        return $options;
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
