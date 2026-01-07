=== GDPR Cookie Consent ===
Contributors: ptolga
Donate link: https://ptolga.github.io
Tags: gdpr, cookie, consent, privacy, cookie banner, cookie notice, eu cookie law, google consent mode
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight GDPR cookie consent banner with Google Consent Mode v2. Minimal, non-intrusive, fully customizable.

== Description ==

A simple, lightweight cookie consent solution for WordPress. Displays a slim, non-intrusive banner at the bottom of the screen that doesn't interfere with browsing.

**Features:**

* 🎨 Fully customizable colors (or auto-detect from theme)
* 📱 Mobile responsive
* ⚡ Lightweight (~5KB)
* 🔒 GDPR compliant
* 🔗 Google Consent Mode v2 integration
* 🌍 Easy to translate
* ♿ Accessibility ready
* 📊 Consent logging (optional)

**Google Consent Mode v2**

This plugin automatically integrates with Google Consent Mode v2, which means:

* Google Analytics will NOT collect data until user accepts cookies
* Works with Google Site Kit, GTM, and direct GA4 installation
* Compliant with EU regulations requiring explicit consent

**Why another cookie plugin?**

Most cookie plugins are bloated with features you don't need. This plugin does one thing well: shows a consent banner, remembers the user's choice, and properly blocks Google tracking until consent is given.

**Test Your Website**

After installation, test your GDPR compliance at [GDPR Scanner](https://web-production-0704b.up.railway.app)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/gdpr-cookie-consent/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings → Cookie Consent to customize

== Frequently Asked Questions ==

= How do I change the banner colors? =

Go to Settings → Cookie Consent. You'll find color pickers for background, text, and button colors. You can also enable "Use Theme Colors" to automatically match your site's design.

= Does it work with Google Analytics / Site Kit? =

Yes! The plugin includes Google Consent Mode v2 which automatically tells Google Analytics to wait for user consent before collecting data.

= How do I block scripts until consent? =

Add `data-gdpr-consent` attribute to any script tag:

`<script data-gdpr-consent src="analytics.js"></script>`

The script will only load after the user accepts cookies.

= How long is consent remembered? =

By default, 365 days. You can change this in Settings → Cookie Consent → Cookie Expiry.

= Can I reset my consent choice for testing? =

Yes! Open browser console and run: `GDPRConsent.resetConsent()`

== Screenshots ==

1. Banner appearance on desktop
2. Banner on mobile devices
3. Settings page in WordPress admin

== Changelog ==

= 1.0.0 =
* Initial release
* Google Consent Mode v2 integration
* Customizable colors
* Auto-detect theme colors option
* Accept/Reject buttons
* Privacy policy link
* Consent logging
* Mobile responsive design

== Upgrade Notice ==

= 1.0.0 =
Initial release with Google Consent Mode v2 support.
