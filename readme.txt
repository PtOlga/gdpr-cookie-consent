=== GDPR Cookie Consent ===
Contributors: ptolga
Tags: gdpr, cookie, consent, privacy, cookie banner, cookie notice, eu cookie law
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight GDPR cookie consent banner. Minimal, non-intrusive, fully customizable.

== Description ==

A simple, lightweight cookie consent solution for WordPress. Displays a slim, non-intrusive banner at the bottom of the screen that doesn't interfere with browsing.

**Features:**

* 🎨 Fully customizable colors
* 📱 Mobile responsive
* ⚡ Lightweight (~5KB)
* 🔒 GDPR compliant
* 🌍 Easy to translate
* ♿ Accessibility ready
* 📊 Consent logging (optional)

**Why another cookie plugin?**

Most cookie plugins are bloated with features you don't need. This plugin does one thing well: shows a consent banner and remembers the user's choice.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/gdpr-cookie-consent/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings → Cookie Consent to customize

== Frequently Asked Questions ==

= How do I change the banner colors? =

Go to Settings → Cookie Consent. You'll find color pickers for background, text, and button colors.

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
* Customizable colors
* Accept/Reject buttons
* Privacy policy link
* Consent logging
* Mobile responsive design

== Upgrade Notice ==

= 1.0.0 =
Initial release.
