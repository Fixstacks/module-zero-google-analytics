/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * Zero Google Analytics: GA4 e-commerce events via gtag.js or GTM, with
 * Consent Mode v2; settings from #zero-ga-settings.
 * 1. The tag loads at once (advanced mode: cookieless pings until consent) or
 *    only after consent (basic mode; events wait).
 * 2. Consent defaults from window.zeroConsent; `zero:consent` sends updates.
 * 3. Events: the page's own from [data-zero-ga-events], select_item and
 *    checkout choices on click, and POST ones (add_to_cart, login, ...) from
 *    zero/analytics/events when the zero_ga_events cookie says so. All queued.
 */
(function () {
    'use strict';

    var settingsEl = document.getElementById('zero-ga-settings');
    if (!settingsEl) {
        return;
    }
    var settings = JSON.parse(settingsEl.textContent);
    var PURCHASES_KEY = 'zero-ga-purchases';
    var ECOMMERCE = ['view_item_list', 'select_item', 'view_item', 'add_to_cart', 'remove_from_cart', 'add_to_wishlist',
        'view_cart', 'begin_checkout', 'add_shipping_info', 'add_payment_info', 'purchase'];
    var basic = settings.consentMode === 'basic';
    var loaded = false;
    // Basic consent mode: Google's commands wait here until its tag loads, so
    // the tag reads the consent state first and the events after it.
    var waiting = basic ? [] : null;

    window.dataLayer = window.dataLayer || [];
    function direct() {
        window.dataLayer.push(arguments);
    }
    function queue(entry) {
        (waiting || window.dataLayer).push(entry);
    }
    function gtag() {
        queue(arguments);
    }
    window.gtag = window.gtag || gtag;

    function stored(key, value) {
        try {
            if (value === undefined) {
                return JSON.parse(localStorage.getItem(key) || 'null');
            }
            localStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            // No storage (private mode): the choice lasts for this page only.
        }
        return null;
    }

    function cookie(name) {
        return document.cookie.split('; ').some(function (pair) { return pair.indexOf(name + '=') === 0; });
    }

    /* ---------- consent ---------- */

    function consentState(choice) {
        var state = function (on) { return on ? 'granted' : 'denied'; };
        return {
            analytics_storage: state(choice.analytics),
            ad_storage: state(choice.marketing),
            ad_user_data: state(choice.marketing),
            ad_personalization: state(choice.marketing)
        };
    }

    // Without Zero_Consent on the page: nothing granted.
    var consent = window.zeroConsent
        ? window.zeroConsent.get()
        : { analytics: false, marketing: false, decided: false };

    // Google's first command, before any other.
    function consentDefaults() {
        var defaults = consentState(consent);
        defaults.functionality_storage = 'granted';
        defaults.security_storage = 'granted';
        if (!consent.decided) {
            defaults.wait_for_update = 500;
        }
        direct('consent', 'default', defaults);
        direct('set', 'ads_data_redaction', !consent.marketing);
    }

    document.addEventListener('zero:consent', function (event) {
        consent = event.detail;
        if (loaded) {
            direct('consent', 'update', consentState(consent));
            direct('set', 'ads_data_redaction', !consent.marketing);
        } else if (consent.analytics || consent.marketing) {
            loadTag();
        }
    });

    /* ---------- the tag ---------- */

    // Advanced consent mode: right away, cookieless pings until consent.
    // Basic: only once the visitor has agreed to analytics or marketing.
    function loadTag() {
        if (loaded) {
            return;
        }
        loaded = true;
        consentDefaults();
        var script = document.createElement('script');
        script.async = true;
        if (settings.mode === 'gtm') {
            window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
            script.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(settings.id);
        } else {
            direct('js', new Date());
            direct('config', settings.id, settings.debug ? { debug_mode: true } : {});
            script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(settings.id);
        }
        if (waiting) {
            waiting.forEach(function (entry) { window.dataLayer.push(entry); });
            waiting = null;
        }
        document.head.appendChild(script);
    }

    if (!basic || consent.analytics || consent.marketing) {
        loadTag();
    }

    /* ---------- events ---------- */

    function send(name, params) {
        params = params || {};
        if (settings.mode === 'gtm') {
            if (ECOMMERCE.indexOf(name) !== -1) {
                queue({ ecommerce: null });
                queue({ event: name, ecommerce: params });
            } else {
                queue(Object.assign({ event: name }, params));
            }
        } else {
            gtag('event', name, params);
        }
        if (settings.debug && window.console) {
            window.console.info('[Zero GA4]', name, params);
        }
    }

    var lists = [];
    var checkout = null;

    function sendPageEvents() {
        document.querySelectorAll('script[type="application/json"][data-zero-ga-events]').forEach(function (el) {
            JSON.parse(el.textContent).forEach(function (event) {
                var params = event.params;
                if (params._urls) {
                    lists.push({ urls: params._urls, params: params });
                    delete params._urls;
                }
                if (event.name === 'begin_checkout') {
                    checkout = params;
                }
                if (event.name === 'purchase') {
                    var sent = stored(PURCHASES_KEY) || [];
                    if (sent.indexOf(params.transaction_id) !== -1) {
                        return; // a reload of the success page
                    }
                    stored(PURCHASES_KEY, sent.concat(params.transaction_id).slice(-20));
                }
                send(event.name, params);
            });
        });
    }

    // select_item: a product link in a listed item list.
    document.addEventListener('click', function (event) {
        var link = event.target.closest ? event.target.closest('a[href]') : null;
        if (!link || !lists.length) {
            return;
        }
        var href = link.href.split('#')[0];
        lists.some(function (list) {
            var index = list.urls[href];
            if (index === undefined) {
                return false;
            }
            send('select_item', {
                item_list_id: list.params.item_list_id,
                item_list_name: list.params.item_list_name,
                items: [list.params.items[index]]
            });
            return true;
        });
    }, true);

    // Checkout steps: the shipping and payment method chosen. Checked on a
    // change and after every HTMX swap: a single rate or a remembered payment
    // method can arrive already selected, with no change event.
    var lastChoice = {};
    function checkoutChoices() {
        if (!checkout) {
            return;
        }
        var shipping = document.querySelector('input[name="shipping_method"]:checked');
        if (shipping && lastChoice.shipping !== shipping.value) {
            lastChoice.shipping = shipping.value;
            var label = shipping.labels && shipping.labels[0] ? shipping.labels[0].textContent.replace(/\s+/g, ' ').trim() : shipping.value;
            send('add_shipping_info', Object.assign({}, checkout, { shipping_tier: label }));
        }
        var payment = document.querySelector('input[name="payment_method"]:checked');
        if (payment && lastChoice.payment !== payment.value) {
            lastChoice.payment = payment.value;
            send('add_payment_info', Object.assign({}, checkout, { payment_type: payment.value }));
        }
    }
    document.addEventListener('change', checkoutChoices);
    document.addEventListener('htmx:afterSettle', checkoutChoices);

    // Events from POSTs (add_to_cart…): only when the server says there are some.
    var asking = false;
    function fetchQueued() {
        if (asking || !cookie('zero_ga_events')) {
            return;
        }
        asking = true;
        fetch(settings.eventsUrl, { credentials: 'same-origin', cache: 'no-store', headers: { 'HX-Request': 'true', Accept: 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : { events: [] }; })
            .then(function (data) {
                (data.events || []).forEach(function (event) { send(event.name, event.params); });
            })
            .catch(function () {})
            .finally(function () { asking = false; });
    }
    document.addEventListener('htmx:afterRequest', fetchQueued);

    /* ---------- start ---------- */

    function start() {
        sendPageEvents();
        fetchQueued();
        checkoutChoices();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
