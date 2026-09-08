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
    var captureSequences = {};
    var headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
    if (config.restNonce) {
        headers['X-WP-Nonce'] = config.restNonce;
    }

    function connected(form) {
        if (form.isConnected) {
            return true;
        }
        window.clearTimeout(timers.get(form));
        var state = states.get(form);
        if (state && typeof state.unsubscribe === 'function') {
            state.unsubscribe();
            state.unsubscribe = null;
        }
        return false;
    }

    function field(form, names) {
        for (var i = 0; i < names.length; i++) {
            var input = form.querySelector('[name="' + names[i] + '"],#' + names[i]);
            if (input) {
                return input.value.trim().slice(0, 500);
            }
        }
    }

    function cartData(state) {
        if (!state.blocks || !window.wp || !window.wp.data || !window.wc || !window.wc.wcBlocksData) {
            return;
        }
        try {
            var store = window.wp.data.select(window.wc.wcBlocksData.CART_STORE_KEY);
            return store && store.getCartData();
        } catch (error) {}
    }

    function customerFor(form, state) {
        var customer = {};
        var fields = { name: ['first_name', 'last_name'], phone: ['phone'], email: ['email'], address: ['address_1', 'address_2'], city: ['city'], postcode: ['postcode'], country: ['country'] };
        if (state.context.orderForm) {
            Object.keys(fields).forEach(function (key) {
                var value = field(form, ['adoology_' + key]);
                if (value !== undefined) {
                    customer[key] = value;
                }
            });
            return customer;
        }

        var cart = cartData(state) || {};
        var addresses = {};
        ['billing', 'shipping'].forEach(function (type) {
            var address = cart[type + 'Address'] || {};
            var previous = state.addresses[type];
            addresses[type] = {};
            Object.keys(fields).forEach(function (key) {
                var present = false;
                var edited = false;
                var populated = fields[key].some(function (part) { return !!previous[part]; });
                var parts = fields[key].map(function (part) {
                    var value = typeof address[part] === 'string' ? address[part].trim().slice(0, 500) : undefined;
                    var input = form.querySelector('[name="' + type + '_' + part + '"],#' + type + '_' + part + ',[name="' + type + '-' + part + '"],#' + type + '-' + part);
                    var edit = input && state.edits.get(input);
                    edited = edited || !!(edit && edit.value === input.value);
                    if (edit && (edit.value !== input.value || (edit.cart[type + 'Address'] || {})[part] !== address[part])) {
                        state.edits.delete(input);
                        edit = null;
                    }
                    if (input && (value === undefined || edit)) {
                        value = input.value.trim().slice(0, 500);
                        edited = edited || !!edit;
                    }
                    if (value !== undefined) {
                        present = true;
                        previous[part] = value;
                    }
                    // Keep the other half of a name/address when Blocks unmounts a field.
                    return previous[part] || '';
                });
                if (present) {
                    var value = parts.join(' ').trim().slice(0, 500);
                    if (value) {
                        delete state.clears[type][key];
                    } else if (edited || populated) {
                        state.clears[type][key] = true;
                    }
                    addresses[type][key] = { value: value, edited: edited || state.clears[type][key] };
                }
            });
        });
        Object.keys(fields).forEach(function (key) {
            var billing = addresses.billing[key];
            var shipping = addresses.shipping[key];
            // Acknowledged clears survive DOM edits being discarded, but missing fields stay omitted.
            if (!billing && state.clears.billing[key]) {
                return;
            }
            // Empty default billing data must not hide a populated shipping address.
            var selected = billing && (billing.value || billing.edited) ? billing : shipping || billing;
            if (selected) {
                customer[key] = selected.value;
            }
        });
        return customer;
    }

    function contextFor(form) {
        return {
            context: form.dataset.adoologyCaptureContext || config.captureContext || '',
            signature: form.dataset.adoologyCaptureSignature || config.captureSignature || '',
            orderForm: !!form.dataset.adoologyOrderForm
        };
    }

    function persistIdentity(value, context) {
        if (context.orderForm) {
            try {
                window.sessionStorage.setItem('adoologyCheckoutIdentity:' + context.signature, JSON.stringify(value));
            } catch (error) {}
        } else {
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
        // Native checkout identity belongs to the live WC session, not a cached page.
        if (context.orderForm) {
            try {
                var stored = JSON.parse(window.sessionStorage.getItem('adoologyCheckoutIdentity:' + context.signature) || 'null');
                if (stored && stored.expires > Math.floor(Date.now() / 1000) + 60 && stored.token && stored.capture_context === context.context) {
                    return Promise.resolve(stored);
                }
            } catch (error) {}
        }

        identityPromises[context.signature] = window.fetch(config.tokenEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
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
            if (!context.orderForm) {
                delete identityPromises[context.signature];
            }
            return value;
        }).catch(function (error) {
            delete identityPromises[context.signature];
            throw error;
        });

        return identityPromises[context.signature];
    }

    function snapshot(form, stage) {
        var state = states.get(form);
        if (!connected(form) || !state || !config.trackingEnabled) {
            return;
        }
        var customer = customerFor(form, state);
        state.billingContact = state.billingContact || ['first_name', 'last_name', 'phone', 'address_1', 'address_2'].some(function (part) {
            return !!state.addresses.billing[part];
        });
        var merged = Object.assign({}, state.customer, customer);
        var cart = cartData(state);
        if ((!Object.keys(customer).length && !cart) || (!Object.keys(state.customer).length && (!merged.name || !merged.phone))) {
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
            customer: Object.assign({ anonymous_id: identity.anonymous_id }, customer)
        };
        if (!state.context.orderForm) {
            payload.billing_contact = state.billingContact;
        }
        var contents = cart && {
            items: (cart.items || []).map(function (item) {
                return [item.key, item.id, item.quantity, item.variation, item.totals];
            }),
            totals: cart.totals
        };
        var fingerprint = JSON.stringify([merged, payload.variation_id, payload.quantity, contents, payload.billing_contact]);
        if (fingerprint === state.lastSnapshot && (stage !== 'leaving' || state.lastLeaving || state.acknowledgedSequence === state.lastSequence)) {
            return;
        }
        var sequenceKey = 'adoologyCheckoutSequence:' + identity.checkout_id + ':' + state.context.signature;
        var sequence = captureSequences[sequenceKey] || 0;
        try {
            var stored = window.sessionStorage.getItem(sequenceKey);
            var storedSequence = Number(stored);
            if (Number.isSafeInteger(storedSequence) && storedSequence > 0 && String(storedSequence) === stored) {
                sequence = Math.max(sequence, storedSequence);
            }
        } catch (error) {}
        if (!Number.isSafeInteger(sequence + 1)) {
            return;
        }
        payload.capture_sequence = sequence + 1;
        captureSequences[sequenceKey] = payload.capture_sequence;
        // Reserve queued and keepalive sequences before a cached document can reload.
        try {
            window.sessionStorage.setItem(sequenceKey, String(payload.capture_sequence));
        } catch (error) {}
        state.customer = merged;
        state.lastSnapshot = fingerprint;
        state.lastLeaving = stage === 'leaving';
        state.lastSequence = payload.capture_sequence;
        state.pending[payload.capture_sequence] = customer;

        function send() {
            if (!connected(form)) {
                return Promise.resolve();
            }
            // Include unacknowledged edits that this capture can overtake, not omitted saved fields.
            var pending = Object.assign({}, state.captured ? {} : merged);
            Object.keys(state.pending).forEach(function (sequence) {
                if (Number(sequence) <= payload.capture_sequence) {
                    Object.assign(pending, state.pending[sequence]);
                }
            });
            payload.customer = Object.assign(pending, payload.customer);
            var request;
            try {
                request = window.fetch(config.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    keepalive: stage === 'leaving',
                    headers: headers,
                    body: JSON.stringify(payload)
                });
            } catch (error) {
                request = Promise.reject(error);
            }
            return request.then(function (response) {
                if (!response.ok) {
                    throw new Error('Checkout capture rejected');
                }
                if (payload.capture_sequence > state.acknowledgedSequence) {
                    state.acknowledgedSequence = payload.capture_sequence;
                    state.captured = state.captured || !!(payload.customer.name && payload.customer.phone);
                    Object.keys(state.pending).forEach(function (sequence) {
                        if (Number(sequence) <= payload.capture_sequence) {
                            delete state.pending[sequence];
                        }
                    });
                }
            }).catch(function () {
                if (state.lastSequence === payload.capture_sequence) {
                    state.lastSnapshot = null;
                }
            });
        }

        // Unload cannot wait on promises; the server rejects late, lower-sequence captures.
        if (stage === 'leaving') {
            send();
        } else {
            state.request = state.request.then(send);
        }
    }

    function queueSnapshot(form, delay) {
        if (!connected(form)) {
            return;
        }
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
        if (!form || !connected(form)) {
            return;
        }
        if (form.dataset.adoologyTrackerBound) {
            if (states.has(form)) {
                bindBlocks(form);
                queueSnapshot(form, 300);
            }
            return;
        }
        form.dataset.adoologyTrackerBound = 'pending';
        var context = contextFor(form);
        loadIdentity(context).then(function (identity) {
            if (!connected(form)) {
                delete form.dataset.adoologyTrackerBound;
                return;
            }
            states.set(form, {
                identity: identity,
                context: context,
                blocks: !context.orderForm && form.matches('form.wc-block-checkout__form,.wc-block-checkout form'),
                addresses: { billing: {}, shipping: {} },
                billingContact: false,
                clears: { billing: {}, shipping: {} },
                edits: new WeakMap(),
                customer: {},
                captured: false,
                pending: {},
                acknowledgedSequence: 0,
                request: Promise.resolve()
            });
            form.dataset.adoologyTrackerBound = '1';
            setHidden(form, '_adoology_checkout_id', identity.checkout_id);
            if (context.orderForm) {
                setHidden(form, '_adoology_anonymous_id', identity.anonymous_id);
                setHidden(form, '_adoology_session_id', identity.session_id);
                setHidden(form, '_adoology_capture_token', identity.token);
                setHidden(form, '_adoology_capture_context', context.context);
                setHidden(form, '_adoology_capture_signature', context.signature);
            }
            function changed(event) {
                var state = states.get(form);
                if (event.target && typeof event.target.value === 'string') {
                    state.edits.set(event.target, { value: event.target.value, cart: cartData(state) || {} });
                }
                queueSnapshot(form, event.type === 'input' ? 1200 : 300);
            }
            form.addEventListener('input', changed);
            form.addEventListener('change', changed);
            bindBlocks(form);
            snapshot(form, 'started');
            window.addEventListener('pagehide', function () {
                window.clearTimeout(timers.get(form));
                snapshot(form, 'leaving');
            });
        }).catch(function () {
            delete form.dataset.adoologyTrackerBound;
        });
    }

    function bindForms() {
        var selectors = 'form.checkout,form.wc-block-checkout__form,.wc-block-checkout form,form[data-adoology-order-form]';
        document.querySelectorAll(selectors).forEach(bind);
    }

    function bindBlocks(form) {
        var state = states.get(form);
        if (!state.blocks || state.unsubscribe || !cartData(state) || typeof window.wp.data.subscribe !== 'function') {
            return;
        }
        try {
            var lastCart = JSON.stringify(cartData(state));
            state.unsubscribe = window.wp.data.subscribe(function () {
                if (!connected(form)) {
                    return;
                }
                var currentCart = JSON.stringify(cartData(state));
                if (currentCart !== lastCart) {
                    lastCart = currentCart;
                    queueSnapshot(form, 300);
                }
            }, window.wc.wcBlocksData.CART_STORE_KEY);
            var checkoutStore = window.wp.data.dispatch(window.wc.wcBlocksData.CHECKOUT_STORE_KEY);
            if (checkoutStore && typeof checkoutStore.setExtensionData === 'function') {
                checkoutStore.setExtensionData('adoology', { checkout_id: state.identity.checkout_id }, true);
            }
        } catch (error) {}
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
