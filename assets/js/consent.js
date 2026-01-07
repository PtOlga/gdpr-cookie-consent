/**
 * GDPR Cookie Consent JavaScript
 * Handles consent storage, categories, and Google Consent Mode v2
 */

(function() {
    'use strict';
    
    const COOKIE_NAME = 'gdpr_consent';
    
    // Check if already consented
    const existingConsent = getCookie(COOKIE_NAME);
    if (existingConsent) {
        // Apply existing consent to Google Consent Mode
        try {
            const consent = JSON.parse(existingConsent);
            updateGoogleConsent(consent);
        } catch(e) {
            // Legacy format (just 'accepted' or 'rejected')
            updateGoogleConsent({
                necessary: true,
                analytics: existingConsent === 'accepted',
                marketing: existingConsent === 'accepted'
            });
        }
        return;
    }
    
    // Wait for DOM
    document.addEventListener('DOMContentLoaded', init);
    
    function init() {
        const banner = document.getElementById('gdpr-cookie-banner');
        if (!banner) return;
        
        // Handle button clicks
        banner.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            
            const action = btn.dataset.action;
            
            if (action === 'accept') {
                // Accept all
                handleConsent({
                    necessary: true,
                    analytics: true,
                    marketing: true
                });
            } else if (action === 'reject') {
                // Reject all (except necessary)
                handleConsent({
                    necessary: true,
                    analytics: false,
                    marketing: false
                });
            } else if (action === 'save') {
                // Save selected preferences
                const consent = getSelectedCategories(banner);
                handleConsent(consent);
            }
        });
        
        // Handle keyboard (Escape to reject)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && banner && !banner.classList.contains('gdpr-hidden')) {
                handleConsent({
                    necessary: true,
                    analytics: false,
                    marketing: false
                });
            }
        });
    }
    
    function getSelectedCategories(banner) {
        const consent = {
            necessary: true // Always true
        };
        
        banner.querySelectorAll('input[data-category]').forEach(function(checkbox) {
            const category = checkbox.dataset.category;
            if (category !== 'necessary') {
                consent[category] = checkbox.checked;
            }
        });
        
        return consent;
    }
    
    function handleConsent(consent) {
        const banner = document.getElementById('gdpr-cookie-banner');
        const expires = window.gdprCC?.expires || 365;
        
        // Save consent as JSON
        setCookie(COOKIE_NAME, JSON.stringify(consent), expires);
        
        // Update Google Consent Mode v2
        updateGoogleConsent(consent);
        
        // Hide banner with animation
        if (banner) {
            banner.classList.add('gdpr-hidden');
            setTimeout(() => banner.remove(), 300);
        }
        
        // Send to server for logging
        if (window.gdprCC?.ajaxUrl) {
            fetch(window.gdprCC.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'gdpr_save_consent',
                    nonce: window.gdprCC.nonce,
                    consent: JSON.stringify(consent)
                })
            }).catch(() => {}); // Silent fail
        }
        
        // Trigger custom event
        document.dispatchEvent(new CustomEvent('gdprConsent', {
            detail: consent
        }));
        
        // Trigger scripts for accepted categories
        if (consent.analytics || consent.marketing) {
            triggerAcceptedScripts(consent);
        }
        
        console.log('[GDPR] Consent saved:', consent);
    }
    
    /**
     * Google Consent Mode v2 - Update consent state
     */
    function updateGoogleConsent(consent) {
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        
        const analyticsState = consent.analytics ? 'granted' : 'denied';
        const marketingState = consent.marketing ? 'granted' : 'denied';
        
        gtag('consent', 'update', {
            'analytics_storage': analyticsState,
            'ad_storage': marketingState,
            'ad_user_data': marketingState,
            'ad_personalization': marketingState,
            'personalization_storage': analyticsState
        });
        
        // Update data redaction based on marketing consent
        gtag('set', 'ads_data_redaction', !consent.marketing);
        
        console.log('[GDPR] Google Consent Mode updated - Analytics:', analyticsState, 'Marketing:', marketingState);
    }
    
    function triggerAcceptedScripts(consent) {
        // Trigger analytics scripts
        if (consent.analytics) {
            document.querySelectorAll('script[data-gdpr-category="analytics"]').forEach(activateScript);
        }
        
        // Trigger marketing scripts
        if (consent.marketing) {
            document.querySelectorAll('script[data-gdpr-category="marketing"]').forEach(activateScript);
        }
        
        // Legacy: scripts with just data-gdpr-consent (accept all)
        if (consent.analytics && consent.marketing) {
            document.querySelectorAll('script[data-gdpr-consent]').forEach(activateScript);
        }
    }
    
    function activateScript(script) {
        const newScript = document.createElement('script');
        
        if (script.src) {
            newScript.src = script.src;
        } else {
            newScript.textContent = script.textContent;
        }
        
        Array.from(script.attributes).forEach(function(attr) {
            if (!['data-gdpr-consent', 'data-gdpr-category', 'type'].includes(attr.name)) {
                newScript.setAttribute(attr.name, attr.value);
            }
        });
        
        script.parentNode.replaceChild(newScript, script);
    }
    
    // Cookie utilities
    function setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = 'expires=' + date.toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + ';' + expires + ';path=/;SameSite=Lax';
    }
    
    function getCookie(name) {
        const nameEQ = name + '=';
        const cookies = document.cookie.split(';');
        
        for (let i = 0; i < cookies.length; i++) {
            let cookie = cookies[i].trim();
            if (cookie.indexOf(nameEQ) === 0) {
                return decodeURIComponent(cookie.substring(nameEQ.length));
            }
        }
        return null;
    }
    
    // Public API
    window.GDPRConsent = {
        // Check if specific category is consented
        hasConsent: function(category) {
            const consent = this.getConsent();
            if (!consent) return false;
            if (category) return consent[category] === true;
            return consent.analytics || consent.marketing;
        },
        
        // Get full consent object
        getConsent: function() {
            const cookie = getCookie(COOKIE_NAME);
            if (!cookie) return null;
            
            try {
                return JSON.parse(cookie);
            } catch(e) {
                // Legacy format
                return {
                    necessary: true,
                    analytics: cookie === 'accepted',
                    marketing: cookie === 'accepted'
                };
            }
        },
        
        // Reset consent and reload
        resetConsent: function() {
            document.cookie = COOKIE_NAME + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            location.reload();
        },
        
        // Manually update consent (for custom UI)
        updateConsent: function(consent) {
            handleConsent(consent);
        },
        
        // Show banner again (for "Cookie Settings" link)
        showBanner: function() {
            document.cookie = COOKIE_NAME + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            location.reload();
        }
    };
    
})();
