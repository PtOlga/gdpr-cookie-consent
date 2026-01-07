# GDPR Cookie Consent

[![WordPress Plugin](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-green.svg)](LICENSE)

Lightweight GDPR cookie consent banner for WordPress. Minimal, non-intrusive, fully customizable.

## ✨ Features

- 🎨 **Fully Customizable** - Colors, text, and privacy policy links
- 📱 **Mobile Responsive** - Works seamlessly on all devices
- ⚡ **Lightweight** - Only ~5KB, no bloat
- 🔒 **GDPR Compliant** - Proper consent management
- 🌍 **Translation Ready** - Easy to localize
- ♿ **Accessible** - Keyboard navigation and screen reader support
- 📊 **Consent Logging** - Optional database logging for compliance
- 🎯 **Google Consent Mode v2** - Integrated support for Google Analytics/Ads

## 🚀 Why This Plugin?

Most cookie plugins are bloated with features you don't need. This plugin does **one thing well**: shows a consent banner and remembers the user's choice.

- **No complex cookie scanning** - You know what cookies you use
- **No unnecessary features** - Just consent management
- **Developer-friendly** - Clean code, easy to customize
- **Performance-focused** - Minimal impact on page load

## 📦 Installation

### From GitHub

1. Download the latest release or clone this repository
2. Upload to `/wp-content/plugins/gdpr-cookie-consent/`
3. Activate through the WordPress 'Plugins' menu
4. Configure at **Settings → Cookie Consent**

### Manual Installation

```bash
cd wp-content/plugins/
git clone https://github.com/PtOlga/gdpr-cookie-consent.git
```

Then activate the plugin in WordPress admin.

## ⚙️ Configuration

Navigate to **Settings → Cookie Consent** in WordPress admin to customize:

- Banner text and button labels
- Colors (background, text, buttons)
- Privacy policy page link
- Cookie expiry duration
- Consent logging (optional)

## 🔧 Usage

### Basic Setup

The banner appears automatically on all pages. Users can:
- **Accept** - Allows all cookies
- **Reject** - Blocks tracking cookies
- **View Privacy Policy** - Links to your privacy page

### Blocking Scripts Until Consent

Add `data-gdpr-consent` attribute to any script that requires consent:

```html
<!-- Google Analytics -->
<script data-gdpr-consent src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>

<!-- Facebook Pixel -->
<script data-gdpr-consent>
  !function(f,b,e,v,n,t,s){...}
</script>
```

Scripts will only load after user accepts cookies.

### Google Consent Mode v2

The plugin automatically integrates with Google Consent Mode v2. No additional configuration needed.

```javascript
// Consent state is automatically managed
gtag('consent', 'default', {
  'analytics_storage': 'denied',
  'ad_storage': 'denied'
});
```

### JavaScript API

```javascript
// Check consent status
if (GDPRConsent.hasConsent()) {
  // User has accepted cookies
}

// Reset consent (for testing)
GDPRConsent.resetConsent();

// Listen for consent changes
document.addEventListener('gdpr-consent-accepted', function() {
  // User accepted cookies
});

document.addEventListener('gdpr-consent-rejected', function() {
  // User rejected cookies
});
```

## 🎨 Customization

### CSS Customization

Override default styles in your theme:

```css
.gdpr-cookie-banner {
  /* Your custom styles */
}
```

### Programmatic Configuration

```php
// Filter banner text
add_filter('gdpr_cc_banner_text', function($text) {
  return 'Your custom message';
});

// Filter button labels
add_filter('gdpr_cc_accept_label', function($label) {
  return 'I Agree';
});
```

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 👤 Author

**PtOlga**

- GitHub: [@PtOlga](https://github.com/PtOlga)

## 🙏 Support

If you find this plugin helpful, please consider:
- ⭐ Starring the repository
- 🐛 Reporting bugs
- 💡 Suggesting new features
- 📖 Improving documentation

## 📚 Resources

- [GDPR Official Website](https://gdpr.eu/)
- [Google Consent Mode v2](https://support.google.com/analytics/answer/9976101)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)

