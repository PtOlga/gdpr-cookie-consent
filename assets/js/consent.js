/**
 * GDPR Cookie Consent JavaScript
 * Handles consent storage, banner interaction, and Google Consent Mode v2
 */

(function() {
    'use strict';
    
    const COOKIE_NAME = 'gdpr_consent';
    
    // Check if already consented
    if (getCookie(COOKIE_NAME)) {
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
            handleConsent(action === 'accept' ? 'accepted' : 'rejected');
        });
        
        // Handle keyboard (Escape to reject)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && banner && !banner.classList.contains('gdpr-hidden')) {
                handleConsent('rejected');
            }
        });
    }
    
    function handleConsent(consent) {
        const banner = document.getElementById('gdpr-cookie-banner');
        const expires = window.gdprCC?.expires || 365;
        
        // Set cookie
        setCookie(COOKIE_NAME, consent, expires);
        
        // UPDATE GOOGLE CONSENT MODE v2
        updateGoogleConsent(consent === 'accepted');
        
        // Hide banner with animation
        if (banner) {
            banner.classList.add('gdpr-hidden');
            setTimeout(() => banner.remove(), 300);
        }
        
        // Send to server for logging (optional)
        if (window.gdprCC?.ajaxUrl) {
            fetch(window.gdprCC.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'gdpr_save_consent',
                    nonce: window.gdprCC.nonce,
                    consent: consent
                })
            }).catch(() => {}); // Silent fail
        }
        
        // Trigger custom event for other scripts
        document.dispatchEvent(new CustomEvent('gdprConsent', {
            detail: { consent: consent }
        }));
        
        // If accepted, trigger blocked scripts
        if (consent === 'accepted') {
            triggerAcceptedScripts();
        }
    }
    
    /**
     * Google Consent Mode v2 - Update consent state
     * This tells Google Analytics / Ads to start or stop collecting data
     */
    function updateGoogleConsent(granted) {
        // Ensure gtag exists
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        
        const state = granted ? 'granted' : 'denied';
        
        gtag('consent', 'update', {
            'ad_storage': state,
            'ad_user_data': state,
            'ad_personalization': state,
            'analytics_storage': state,
            'personalization_storage': state
        });
        
        // If granted, disable data redaction
        if (granted) {
            gtag('set', 'ads_data_redaction', false);
        }
        
        console.log('[GDPR] Google Consent Mode updated:', state);
    }
    
    function triggerAcceptedScripts() {
        // Find and execute scripts that were waiting for consent
        document.querySelectorAll('script[data-gdpr-consent]').forEach(function(script) {
            const newScript = document.createElement('script');
            
            if (script.src) {
                newScript.src = script.src;
            } else {
                newScript.textContent = script.textContent;
            }
            
            // Copy attributes
            Array.from(script.attributes).forEach(function(attr) {
                if (attr.name !== 'data-gdpr-consent' && attr.name !== 'type') {
                    newScript.setAttribute(attr.name, attr.value);
                }
            });
            
            script.parentNode.replaceChild(newScript, script);
        });
    }
    
    // Cookie utilities
    function setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = 'expires=' + date.toUTCString();
        document.cookie = name + '=' + value + ';' + expires + ';path=/;SameSite=Lax';
    }
    
    function getCookie(name) {
        const nameEQ = name + '=';
        const cookies = document.cookie.split(';');
        
        for (let i = 0; i < cookies.length; i++) {
            let cookie = cookies[i].trim();
            if (cookie.indexOf(nameEQ) === 0) {
                return cookie.substring(nameEQ.length);
            }
        }
        return null;
    }
    
    // Expose for external use
    window.GDPRConsent = {
        hasConsent: function() {
            return getCookie(COOKIE_NAME) === 'accepted';
        },
        getConsent: function() {
            return getCookie(COOKIE_NAME);
        },
        resetConsent: function() {
            document.cookie = COOKIE_NAME + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            location.reload();
        },
        // Manual update for custom integrations
        updateGoogleConsent: function(granted) {
            updateGoogleConsent(granted);
        }
    };
    
})();
