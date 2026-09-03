(function () {
    'use strict';

    if (!window.adoologyCheckout || !window.fetch) {
        return;
    }

    var config = window.adoologyCheckout;
    if (!config.trackingEnabled) {
        return;
    }
    var timers = new WeakMap();
    var states = new WeakMap();
    var identityPromises = {};

    function field(form, names) {
        for (var i = 0; i < names.length; i++) {
            var input = form.querySelector('[name="' + names[i] + '"],#' + names[i]);
            if (input && input.value) {
                return input.value.slice(0, 500);
            }
        }
        return '';
    }

    function contextFor(form) {
        return {
            context: form.dataset.adoologyCaptureContext || config.captureContext || '',
            signature: form.dataset.adoologyCaptureSignature || config.captureSignature || '',
            orderForm: !!form.dataset.adoologyOrderForm
        };
    }

    function persistIdentity(value, context) {
        try {
            window.sessionStorage.setItem('adoologyCheckoutIdentity:' + context.signature, JSON.stringify(value));
        } catch (error) {}
        if (!context.orderForm) {
            var secure = window.location.protocol === 'https:' ? ';Secure' : '';
            document.cookie = 'adoology_anonymous_id=' + encodeURIComponent(value.anonymous_id) + ';path=/;SameSite=Lax' + secure;
            document.cookie = 'adoology_checkout_id=' + encodeURIComponent(value.checkout_id) + ';path=/;SameSite=Lax' + secure;
        }
    }

    function loadIdentity(context) {
        if (!context.context || !context.signature) {
            return Promise.reject(new Error('Checkout context missing'));
        }
        if (identityPromises[context.signature]) {
            return identityPromises[context.signature];
        }
        try {
            var stored = JSON.parse(window.sessionStorage.getItem('adoologyCheckoutIdentity:' + context.signature) || 'null');
            if (stored && stored.expires > Math.floor(Date.now() / 1000) + 60 && stored.token && stored.capture_context === context.context) {
                persistIdentity(stored, context);
                return Promise.resolve(stored);
            }
        } catch (error) {}

        identityPromises[context.signature] = window.fetch(config.tokenEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({
                capture_context: context.context,
                capture_signature: context.signature
            })
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Checkout token rejected');
            }
            return response.json();
        }).then(function (value) {
            value.capture_context = context.context;
            persistIdentity(value, context);
            return value;
        }).catch(function (error) {
            delete identityPromises[context.signature];
            throw error;
        });

        return identityPromises[context.signature];
    }

    function snapshot(form, stage) {
        var state = states.get(form);
        if (!state || !config.trackingEnabled) {
            return;
        }
        var identity = state.identity;
        var payload = {
            checkout_id: identity.checkout_id,
            anonymous_id: identity.anonymous_id,
            session_id: identity.session_id,
            capture_token: identity.token,
            capture_context: state.context.context,
            capture_signature: state.context.signature,
            variation_id: parseInt(field(form, ['variation_id']) || 0, 10),
            quantity: parseInt(field(form, ['quantity']) || 1, 10),
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

    function setHidden(form, name, value) {
        var input = form.querySelector('[name="' + name + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        input.value = value;
    }

    function bind(form) {
        if (!form || form.dataset.adoologyTrackerBound) {
            return;
        }
        form.dataset.adoologyTrackerBound = 'pending';
        var context = contextFor(form);
        loadIdentity(context).then(function (identity) {
            states.set(form, { identity: identity, context: context });
            form.dataset.adoologyTrackerBound = '1';
            setHidden(form, '_adoology_checkout_id', identity.checkout_id);
            if (context.orderForm) {
                setHidden(form, '_adoology_anonymous_id', identity.anonymous_id);
                setHidden(form, '_adoology_session_id', identity.session_id);
                setHidden(form, '_adoology_capture_token', identity.token);
                setHidden(form, '_adoology_capture_context', context.context);
                setHidden(form, '_adoology_capture_signature', context.signature);
            }
            form.addEventListener('input', function () { queueSnapshot(form, 1200); });
            form.addEventListener('change', function () { queueSnapshot(form, 300); });
            snapshot(form, 'started');
            window.addEventListener('pagehide', function () { snapshot(form, 'leaving'); });
            if (!form.dataset.adoologyOrderForm) {
                setBlocksExtensionData(identity);
            }
        }).catch(function () {
            delete form.dataset.adoologyTrackerBound;
        });
    }

    function bindForms() {
        var selectors = 'form.checkout,form.wc-block-checkout__form,.wc-block-checkout form,form[data-adoology-order-form]';
        document.querySelectorAll(selectors).forEach(bind);
    }

    function setBlocksExtensionData(identity) {
        if (!window.wp || !window.wp.data || !window.wc || !window.wc.wcBlocksData) {
            return;
        }
        var checkoutStore = window.wp.data.dispatch(window.wc.wcBlocksData.CHECKOUT_STORE_KEY);
        if (checkoutStore && typeof checkoutStore.setExtensionData === 'function') {
            checkoutStore.setExtensionData('adoology', { checkout_id: identity.checkout_id }, true);
        }
    }

    function initialize() {
        bindForms();
        new MutationObserver(bindForms).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
}());
