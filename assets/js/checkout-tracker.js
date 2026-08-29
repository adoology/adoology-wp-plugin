(function () {
    'use strict';

    if (!window.adoologyCheckout || !window.fetch) {
        return;
    }

    var config = window.adoologyCheckout;
    var identity = null;
    var timers = new WeakMap();

    function field(form, names) {
        for (var i = 0; i < names.length; i++) {
            var input = form.querySelector('[name="' + names[i] + '"],#' + names[i]);
            if (input && input.value) {
                return input.value.slice(0, 500);
            }
        }
        return '';
    }

    function persistIdentity(value) {
        identity = value;
        try {
            window.sessionStorage.setItem('adoologyCheckoutIdentity', JSON.stringify(value));
        } catch (error) {}
        document.cookie = 'adoology_anonymous_id=' + encodeURIComponent(value.anonymous_id) + ';path=/;SameSite=Lax';
        document.cookie = 'adoology_checkout_id=' + encodeURIComponent(value.checkout_id) + ';path=/;SameSite=Lax';
    }

    function loadIdentity() {
        try {
            var stored = JSON.parse(window.sessionStorage.getItem('adoologyCheckoutIdentity') || 'null');
            if (stored && stored.expires > Math.floor(Date.now() / 1000) + 60 && stored.token) {
                persistIdentity(stored);
                return Promise.resolve(stored);
            }
        } catch (error) {}

        return window.fetch(config.tokenEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Checkout token rejected');
            }
            return response.json();
        }).then(function (value) {
            persistIdentity(value);
            return value;
        });
    }

    function snapshot(form, stage) {
        if (!identity || !config.trackingEnabled) {
            return;
        }
        var flowData = form.dataset.adoologyOrderForm ? form.dataset : {};
        var data = config.data || {};
        var payload = {
            checkout_id: identity.checkout_id,
            anonymous_id: identity.anonymous_id,
            session_id: identity.session_id,
            capture_token: identity.token,
            flow: form.dataset.adoologyOrderForm ? 'order_form' : config.flow,
            product_id: parseInt(flowData.productId || 0, 10),
            variation_id: parseInt(field(form, ['variation_id']) || 0, 10),
            quantity: parseInt(field(form, ['quantity']) || 1, 10),
            value_minor: parseInt(flowData.valueMinor || data.value_minor || 0, 10),
            currency: flowData.currency || data.currency || '',
            items: data.items || [],
            landing_page: config.landingPage,
            form_stage: stage,
            customer: {
                anonymous_id: identity.anonymous_id,
                name: field(form, ['adoology_name', 'billing_first_name', 'billing-first_name']),
                phone: field(form, ['adoology_phone', 'billing_phone', 'billing-phone']),
                email: field(form, ['adoology_email', 'billing_email', 'billing-email']),
                address: field(form, ['adoology_address', 'billing_address_1', 'billing-address_1']),
                city: field(form, ['adoology_city', 'billing_city', 'billing-city']),
                postcode: field(form, ['adoology_postcode', 'billing_postcode', 'billing-postcode']),
                country: field(form, ['adoology_country', 'billing_country', 'billing-country'])
            }
        };

        window.fetch(config.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: stage === 'leaving',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).catch(function () {});
    }

    function queueSnapshot(form, delay) {
        window.clearTimeout(timers.get(form));
        timers.set(form, window.setTimeout(function () { snapshot(form, 'details'); }, delay));
    }

    function bind(form) {
        if (!form || form.dataset.adoologyTrackerBound) {
            return;
        }
        form.dataset.adoologyTrackerBound = '1';
        var checkoutInput = form.querySelector('[name="_adoology_checkout_id"]');
        if (!checkoutInput) {
            checkoutInput = document.createElement('input');
            checkoutInput.type = 'hidden';
            checkoutInput.name = '_adoology_checkout_id';
            form.appendChild(checkoutInput);
        }
        checkoutInput.value = identity.checkout_id;
        form.addEventListener('input', function () { queueSnapshot(form, 1200); });
        form.addEventListener('change', function () { queueSnapshot(form, 300); });
        snapshot(form, 'started');
        window.addEventListener('pagehide', function () { snapshot(form, 'leaving'); });
    }

    function bindForms() {
        var selectors = 'form.checkout,form.wc-block-checkout__form,.wc-block-checkout form,form[data-adoology-order-form]';
        document.querySelectorAll(selectors).forEach(bind);
    }

    function setBlocksExtensionData() {
        if (!identity || !window.wp || !window.wp.data || !window.wc || !window.wc.wcBlocksData) {
            return;
        }
        var checkoutStore = window.wp.data.dispatch(window.wc.wcBlocksData.CHECKOUT_STORE_KEY);
        if (checkoutStore && typeof checkoutStore.setExtensionData === 'function') {
            checkoutStore.setExtensionData('adoology', { checkout_id: identity.checkout_id }, true);
        }
    }

    function initialize() {
        loadIdentity().then(function () {
            setBlocksExtensionData();
            bindForms();
            new MutationObserver(bindForms).observe(document.body, { childList: true, subtree: true });
        }).catch(function () {});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
}());
