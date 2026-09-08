'use strict';

const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const { runInNewContext } = require('node:vm');

const source = readFileSync(require.resolve('../../assets/js/checkout-tracker.js'), 'utf8');
const settle = () => new Promise(setImmediate);

function form(fields = {}, { blocks = false, signature, orderForm = false } = {}) {
    const listeners = new Map();
    const inputs = new Map(Object.entries(fields).map(([key, value]) => [key, {
        name: key.includes('-') ? '' : key,
        id: key.includes('-') ? key : '',
        type: 'text',
        value
    }]));
    return {
        inputs,
        isConnected: true,
        dataset: orderForm ? {
            adoologyOrderForm: '1',
            adoologyCaptureContext: 'context-' + signature,
            adoologyCaptureSignature: signature
        } : {},
        matches: () => blocks,
        querySelector(selector) {
            for (const part of selector.split(',')) {
                const name = part.match(/^\[name="([^"]+)"\]$/);
                const input = Array.from(inputs.values()).find(input => name ? input.name === name[1] : input.id === part.slice(1));
                if (input) return input;
            }
            return null;
        },
        appendChild(input) {
            inputs.set(input.name, input);
            this.mutate();
        },
        addEventListener(type, listener) {
            listeners.set(type, listener);
        },
        edit(key, value, type = 'input') {
            const target = inputs.get(key);
            target.value = value;
            listeners.get(type)({ type, target });
        }
    };
}

function identity(signature) {
    return {
        checkout_id: 'checkout-' + signature,
        anonymous_id: 'anonymous-' + signature,
        session_id: 'session-' + signature,
        token: 'token-' + signature,
        expires: Math.floor(Date.now() / 1000) + 7200,
        capture_context: 'context-' + signature
    };
}

function browser(options = {}) {
    const forms = options.forms || [form()];
    const storage = options.storage || new Map();
    const requests = [];
    const cookies = [];
    const timers = new Map();
    const listeners = new Map();
    const subscribers = [];
    const extensions = [];
    const observers = [];
    let cart = options.cart;
    let sequence = 0;
    let mutationQueued = false;
    let mutations = 0;
    const mutate = () => {
        if (mutationQueued) return;
        mutationQueued = true;
        queueMicrotask(() => {
            mutationQueued = false;
            mutations++;
            assert.ok(mutations < 100, 'MutationObserver must not loop on hidden inputs');
            observers.forEach(observer => observer());
        });
    };
    forms.forEach(form => { form.mutate = mutate; });
    const document = {
        readyState: 'complete',
        body: {},
        querySelectorAll: () => forms,
        createElement: () => ({ name: '', id: '', value: '' }),
        set cookie(value) { cookies.push(value); }
    };
    const window = {
        adoologyCheckout: {
            trackingEnabled: options.enabled !== false,
            endpoint: '/checkout',
            tokenEndpoint: '/checkout-token',
            captureContext: 'context-native',
            captureSignature: 'native',
            restNonce: options.restNonce
        },
        location: { protocol: 'https:' },
        sessionStorage: options.sessionStorage || {
            getItem: key => storage.get(key) || null,
            setItem: (key, value) => storage.set(key, value)
        },
        setTimeout(callback) {
            timers.set(++sequence, callback);
            return sequence;
        },
        clearTimeout: id => timers.delete(id),
        addEventListener(type, listener) {
            if (!listeners.has(type)) listeners.set(type, []);
            listeners.get(type).push(listener);
        },
        fetch(url, init) {
            const request = { url, ...init, body: JSON.parse(init.body) };
            requests.push(request);
            if (url === '/checkout-token') {
                return options.tokenFetch ? options.tokenFetch(request) : Promise.resolve({
                    ok: true,
                    json: async () => identity(request.body.capture_signature)
                });
            }
            return options.captureFetch ? options.captureFetch(request) : Promise.resolve({ ok: true });
        }
    };
    function installBlocks(nextCart) {
        cart = nextCart;
        window.wc = { wcBlocksData: { CART_STORE_KEY: 'wc/store/cart', CHECKOUT_STORE_KEY: 'wc/store/checkout' } };
        window.wp = { data: {
            select(key) {
                assert.equal(key, 'wc/store/cart');
                return { getCartData: () => cart };
            },
            subscribe(callback, key) {
                assert.equal(key, 'wc/store/cart');
                subscribers.push(callback);
                return () => subscribers.splice(subscribers.indexOf(callback), 1);
            },
            dispatch(key) {
                assert.equal(key, 'wc/store/checkout');
                return {
                    setExtensionData(...args) {
                        extensions.push(JSON.parse(JSON.stringify(args)));
                        subscribers.slice().forEach(callback => callback());
                    }
                };
            }
        } };
    }
    if (cart !== undefined) installBlocks(cart);
    runInNewContext(source, {
        window,
        document,
        MutationObserver: class {
            constructor(callback) { this.callback = callback; }
            observe() { observers.push(this.callback); }
        }
    }, { filename: 'checkout-tracker.js' });

    return {
        forms, storage, cookies, extensions, subscribers, timers, mutate, installBlocks,
        get captures() { return requests.filter(request => request.url === '/checkout'); },
        get tokens() { return requests.filter(request => request.url === '/checkout-token'); },
        async flush() {
            await settle();
            const pending = Array.from(timers.values());
            timers.clear();
            pending.forEach(callback => callback());
            await settle();
        },
        updateCart(nextCart) {
            cart = nextCart;
            subscribers.slice().forEach(callback => callback());
        },
        pagehide() { (listeners.get('pagehide') || []).forEach(callback => callback({})); }
    };
}

test('classic checkout captures full name, both address lines, and explicit empty optional fields', async () => {
    const checkout = form({
        billing_first_name: ' Ada ', billing_last_name: ' Lovelace ', billing_phone: ' +441234 ',
        billing_address_1: '12 Main St', billing_address_2: 'Apt 4', billing_email: ''
    });
    const page = browser({ forms: [checkout] });
    await page.flush();

    assert.equal(page.captures.length, 1);
    assert.deepEqual(page.captures[0].body.customer, {
        anonymous_id: 'anonymous-native', name: 'Ada Lovelace', phone: '+441234', email: '', address: '12 Main St Apt 4'
    });
    assert.equal(page.captures[0].body.form_stage, 'started');
    assert.equal(page.captures[0].body.billing_contact, true);
    assert.equal(checkout.inputs.get('_adoology_checkout_id').value, 'checkout-native');
});

test('REST nonce authenticates token, normal capture, and immediate pagehide requests', async () => {
    const checkout = form({ billing_first_name: 'Ada', billing_phone: '123' });
    const page = browser({ forms: [checkout], restNonce: 'wp-rest-nonce' });
    await page.flush();
    checkout.edit('billing_phone', '456');
    page.pagehide();
    assert.equal(page.captures.length, 2);
    for (const request of [...page.tokens, ...page.captures]) {
        assert.equal(request.headers['X-WP-Nonce'], 'wp-rest-nonce');
        assert.equal(request.credentials, 'same-origin');
    }
    await settle();
});

test('absent or empty REST nonce is omitted from both endpoints', async t => {
    for (const restNonce of [undefined, '']) {
        await t.test(String(restNonce) || 'empty', async () => {
            const page = browser({ forms: [form({ billing_first_name: 'Ada', billing_phone: '123' })], restNonce });
            await page.flush();
            assert.equal(page.tokens.length, 1);
            assert.equal(page.captures.length, 1);
            for (const request of [...page.tokens, ...page.captures]) {
                assert.equal('X-WP-Nonce' in request.headers, false);
            }
        });
    }
});

test('DOM shipping fields fall back when billing is unavailable', async () => {
    const page = browser({ forms: [form({
        'shipping-first_name': 'Grace', 'shipping-last_name': 'Hopper', 'shipping-phone': '5551234',
        'shipping-address_1': '1 Navy Rd', 'shipping-address_2': 'Suite 2', 'shipping-city': 'Arlington'
    }, { blocks: true })] });
    await page.flush();

    assert.deepEqual(page.captures[0].body.customer, {
        anonymous_id: 'anonymous-native', name: 'Grace Hopper', phone: '5551234', address: '1 Navy Rd Suite 2', city: 'Arlington'
    });
});

test('native checkout supports hyphenated field names without IDs', async () => {
    const checkout = form({ 'billing-first_name': 'Ada', 'billing-last_name': 'Lovelace', 'billing-phone': '123' });
    for (const input of checkout.inputs.values()) {
        input.name = input.id;
        input.id = '';
    }
    const page = browser({ forms: [checkout] });
    await page.flush();
    assert.equal(page.captures[0].body.customer.name, 'Ada Lovelace');
    assert.equal(page.captures[0].body.customer.phone, '123');
});

test('anonymous and whitespace-only details never capture, including pagehide', async t => {
    for (const fields of [{}, { billing_first_name: 'Ada' }, { billing_phone: '123' }, {
        billing_first_name: '  ', billing_last_name: ' ', billing_phone: '123'
    }, { billing_first_name: 'Ada', billing_phone: '   ' }]) {
        await t.test(JSON.stringify(fields), async () => {
            const page = browser({ forms: [form(fields)] });
            await page.flush();
            page.pagehide();
            await page.flush();
            assert.equal(page.captures.length, 0);
        });
    }
});

test('Blocks cart store overrides stale or hidden DOM and captures unmounted customer fields', async () => {
    const checkout = form({
        'billing-first_name': 'Stale', 'billing-last_name': '', 'billing-phone': '', 'billing-city': ''
    }, { blocks: true });
    checkout.inputs.get('billing-first_name').type = 'hidden';
    const page = browser({ forms: [checkout], cart: {
        billingAddress: {
            first_name: 'Ada', last_name: 'Lovelace', phone: '123', email: 'ada@example.test',
            address_1: '1 Main St', address_2: 'Unit 2', city: 'London', postcode: 'N1', country: 'GB'
        },
        shippingAddress: { first_name: 'Other', last_name: 'Recipient', phone: '999' }
    } });
    await page.flush();

    assert.deepEqual(page.captures[0].body.customer, {
        anonymous_id: 'anonymous-native', name: 'Ada Lovelace', phone: '123', email: 'ada@example.test',
        address: '1 Main St Unit 2', city: 'London', postcode: 'N1', country: 'GB'
    });
    assert.deepEqual(page.extensions, [['adoology', { checkout_id: 'checkout-native' }, true]]);
    assert.equal(page.subscribers.length, 1);
});

test('Blocks shipping fallback handles absent and empty default billing data', async t => {
    for (const billingAddress of [undefined, {}, { first_name: '', last_name: '', phone: '', address_1: '', address_2: '' }]) {
        await t.test(JSON.stringify(billingAddress) || 'absent', async () => {
            const page = browser({ forms: [form({}, { blocks: true })], cart: {
                billingAddress,
                shippingAddress: { first_name: 'Grace', last_name: 'Hopper', phone: '555', address_1: 'Navy Rd', address_2: 'Unit 3' }
            } });
            await page.flush();
            assert.deepEqual(page.captures[0].body.customer, {
                anonymous_id: 'anonymous-native', name: 'Grace Hopper', phone: '555', address: 'Navy Rd Unit 3'
            });
            assert.equal(page.captures[0].body.billing_contact, false);
        });
    }
});

test('billing provenance ignores country-only defaults and captures a source-only change', async () => {
    const shippingAddress = { first_name: 'Ada', last_name: 'Lovelace', phone: '123', country: 'GB' };
    const billingAddress = { first_name: '', last_name: '', phone: '', address_1: '', address_2: '', country: 'GB' };
    const page = browser({ forms: [form({}, { blocks: true })], cart: { billingAddress, shippingAddress } });
    await page.flush();
    assert.equal(page.captures[0].body.billing_contact, false);

    page.updateCart({ billingAddress: { ...billingAddress, first_name: 'Ada', last_name: 'Lovelace', phone: '123' }, shippingAddress });
    await page.flush();
    assert.equal(page.captures.length, 2, 'billing provenance changes must not dedupe away');
    assert.deepEqual(page.captures[1].body.customer, page.captures[0].body.customer);
    assert.equal(page.captures[1].body.billing_contact, true);

    page.updateCart({ billingAddress, shippingAddress });
    await page.flush();
    assert.equal(page.captures[2].body.billing_contact, true);
    assert.equal(page.captures[2].body.customer.name, '');
    assert.equal(page.captures[2].body.customer.phone, '');
    for (const request of page.captures) {
        assert.equal('billing_contact' in request.body.customer, false);
        assert.equal('_billing_contact' in request.body.customer, false);
        assert.equal('capture_generation' in request.body, false);
    }
});

test('each qualifying billing field establishes provenance, unlike other optional fields', async t => {
    for (const part of ['first_name', 'last_name', 'phone', 'address_1', 'address_2', 'country', 'city', 'email', 'postcode']) {
        await t.test(part, async () => {
            const page = browser({ forms: [form({}, { blocks: true })], cart: {
                billingAddress: { [part]: 'populated' },
                shippingAddress: { first_name: 'Ada', last_name: 'Lovelace', phone: '123' }
            } });
            await page.flush();
            assert.equal(page.captures[0].body.billing_contact, ['first_name', 'last_name', 'phone', 'address_1', 'address_2'].includes(part));
            assert.equal('billing_contact' in page.captures[0].body.customer, false);
        });
    }
});

test('Blocks programmatic customer updates trigger capture and unchanged notifications dedupe', async () => {
    const page = browser({ forms: [form({}, { blocks: true })], cart: {
        billingAddress: { first_name: '', last_name: '', phone: '' }
    } });
    await page.flush();
    assert.equal(page.captures.length, 0);

    const cart = { billingAddress: { first_name: 'Ada', last_name: 'Lovelace', phone: '123' } };
    page.updateCart(cart);
    await page.flush();
    assert.equal(page.captures.length, 1);
    page.updateCart(JSON.parse(JSON.stringify(cart)));
    page.mutate();
    await page.flush();
    assert.equal(page.captures.length, 1);

    page.updateCart({ billingAddress: { ...cart.billingAddress, last_name: 'Byron', phone: '456' } });
    await page.flush();
    assert.equal(page.captures.length, 2);
    assert.equal(page.captures[1].body.customer.name, 'Ada Byron');
    assert.equal(page.captures[1].body.customer.phone, '456');
    assert.equal(page.captures[1].body.checkout_id, page.captures[0].body.checkout_id);
});

test('Blocks cart-only item and total changes capture while transient store data dedupes', async () => {
    const checkout = form({}, { blocks: true });
    let cart = {
        billingAddress: { first_name: 'Ada', last_name: 'Lovelace', phone: '123' },
        items: [{ key: 'item-1', id: 10, quantity: 1, variation: [], totals: { line_total: '1000' } }],
        totals: { total_price: '1000', currency_code: 'GBP' }
    };
    const page = browser({ forms: [checkout], cart });
    await page.flush();

    cart = { ...cart, errors: ['temporary'], shippingRates: [{ loading: true }], needsPayment: true };
    page.updateCart(cart);
    await page.flush();
    assert.equal(page.captures.length, 1);

    cart = { ...cart, items: [{ ...cart.items[0], quantity: 2 }] };
    page.updateCart(cart);
    await page.flush();
    assert.equal(page.captures.length, 2);
    cart = { ...cart, totals: { ...cart.totals, total_price: '2000' } };
    page.updateCart(cart);
    await page.flush();
    assert.equal(page.captures.length, 3);

    cart = { ...cart, items: [{ ...cart.items[0], id: 11, variation: [{ attribute: 'Size', value: 'Large' }] }] };
    page.updateCart(cart);
    await page.flush();
    assert.equal(page.captures.length, 4);
    cart = { ...cart, items: [{ ...cart.items[0], totals: { line_total: '2000' } }] };
    page.updateCart(cart);
    await page.flush();
    assert.equal(page.captures.length, 5);

    cart = { ...cart, billingAddress: {}, items: [] };
    page.updateCart(cart);
    await page.flush();
    assert.equal(page.captures.length, 6);
    assert.deepEqual(page.captures[5].body.customer, { anonymous_id: 'anonymous-native' });
    for (const request of page.captures) {
        assert.equal(request.body.checkout_id, 'checkout-native');
        assert.equal('items' in request.body, false, 'cart values remain server-owned');
        assert.equal('totals' in request.body, false, 'cart values remain server-owned');
    }
    page.updateCart(JSON.parse(JSON.stringify(cart)));
    await page.flush();
    assert.equal(page.captures.length, 6);
});

test('cart-only changes do not qualify an anonymous Blocks checkout', async () => {
    const page = browser({ forms: [form({}, { blocks: true })], cart: {
        billingAddress: { first_name: 'Ada', phone: '' }, items: [], totals: { total_price: '0' }
    } });
    await page.flush();
    page.updateCart({ billingAddress: { first_name: 'Ada', phone: '' },
        items: [{ key: 'item-1', id: 10, quantity: 1 }], totals: { total_price: '1000' }
    });
    await page.flush();
    assert.equal(page.captures.length, 0);
});

test('fresh DOM edits override lagging Blocks data without losing unavailable last name', async () => {
    const checkout = form({ 'billing-first_name': 'Ada', 'billing-city': 'London' }, { blocks: true });
    const billingAddress = { first_name: 'Ada', last_name: 'Lovelace', phone: '123', city: 'London' };
    const page = browser({ forms: [checkout], cart: { billingAddress } });
    await page.flush();
    checkout.edit('billing-first_name', 'Augusta');
    checkout.edit('billing-city', '', 'change');
    await page.flush();

    assert.equal(page.captures[1].body.customer.name, 'Augusta Lovelace');
    assert.equal(page.captures[1].body.customer.city, '');
    page.updateCart({ billingAddress: { ...billingAddress, first_name: 'Augusta', city: '' } });
    await page.flush();
    assert.equal(page.captures.length, 2);

    page.updateCart({ billingAddress: { ...billingAddress, first_name: 'Grace' } });
    await page.flush();
    assert.equal(page.captures[2].body.customer.name, 'Grace Lovelace');
    assert.equal(page.captures[2].body.customer.city, 'London');
});

test('billing clear intent survives Blocks acknowledgment and shipping fallback until replaced', async () => {
    const checkout = form({ 'billing-first_name': 'Ada', 'billing-last_name': 'Lovelace', 'billing-phone': '123' }, { blocks: true });
    const shippingAddress = { first_name: 'Grace', last_name: 'Hopper', phone: '999' };
    const billingAddress = { first_name: 'Ada', last_name: 'Lovelace', phone: '123' };
    const page = browser({ forms: [checkout], cart: { billingAddress, shippingAddress } });
    await page.flush();
    checkout.edit('billing-first_name', '');
    checkout.edit('billing-last_name', '');
    checkout.edit('billing-phone', '');
    await page.flush();
    assert.deepEqual(page.captures[1].body.customer, { anonymous_id: 'anonymous-native', name: '', phone: '' });

    const cleared = { first_name: '', last_name: '', phone: '' };
    page.updateCart({ billingAddress: cleared, shippingAddress });
    await page.flush();
    page.mutate();
    await page.flush();
    assert.equal(page.captures.length, 2, 'acknowledgment must not restore populated shipping details');

    checkout.inputs.delete('billing-first_name');
    checkout.inputs.delete('billing-last_name');
    checkout.inputs.delete('billing-phone');
    page.updateCart({ billingAddress: { ...cleared, city: 'Paris' }, shippingAddress });
    await page.flush();
    assert.deepEqual(page.captures[2].body.customer, { anonymous_id: 'anonymous-native', name: '', phone: '', city: 'Paris' });
    page.updateCart({ billingAddress: { city: 'London' }, shippingAddress });
    await page.flush();
    assert.deepEqual(page.captures[3].body.customer, { anonymous_id: 'anonymous-native', city: 'London' });

    page.updateCart({ billingAddress: { first_name: 'Augusta', last_name: 'Byron', phone: '456' }, shippingAddress });
    await page.flush();
    assert.equal(page.captures[4].body.customer.name, 'Augusta Byron');
    assert.equal(page.captures[4].body.customer.phone, '456');
    page.updateCart({ billingAddress: {}, shippingAddress });
    await page.flush();
    assert.equal(page.captures[5].body.customer.name, 'Grace Hopper');
    assert.equal(page.captures[5].body.customer.phone, '999');
});

test('billing clears acknowledged before debounce persist, and nonempty DOM edits replace them', async () => {
    const checkout = form({ 'billing-first_name': 'Ada', 'billing-last_name': 'Lovelace', 'billing-phone': '123' }, { blocks: true });
    const shippingAddress = { first_name: 'Grace', last_name: 'Hopper', phone: '999' };
    const page = browser({ forms: [checkout], cart: {
        billingAddress: { first_name: 'Ada', last_name: 'Lovelace', phone: '123' }, shippingAddress
    } });
    await page.flush();
    checkout.edit('billing-first_name', '');
    checkout.edit('billing-last_name', '');
    checkout.edit('billing-phone', '');
    page.updateCart({ billingAddress: { first_name: '', last_name: '', phone: '' }, shippingAddress });
    await page.flush();
    assert.deepEqual(page.captures[1].body.customer, { anonymous_id: 'anonymous-native', name: '', phone: '' });
    page.mutate();
    await page.flush();
    assert.equal(page.captures.length, 2);

    checkout.edit('billing-first_name', 'Augusta');
    checkout.edit('billing-last_name', 'Byron');
    checkout.edit('billing-phone', '456');
    await page.flush();
    assert.equal(page.captures[2].body.customer.name, 'Augusta Byron');
    assert.equal(page.captures[2].body.customer.phone, '456');
    page.updateCart({ billingAddress: { first_name: 'Augusta', last_name: 'Byron', phone: '456' }, shippingAddress });
    await page.flush();
    assert.equal(page.captures.length, 3);
});

test('partial DOM removal preserves compound fields and omits unavailable optional keys', async () => {
    const checkout = form({
        billing_first_name: 'Ada', billing_last_name: 'Lovelace', billing_phone: '123', billing_city: 'London',
        billing_address_1: '1 Main St', billing_address_2: 'Unit 2'
    });
    const page = browser({ forms: [checkout] });
    await page.flush();
    checkout.inputs.delete('billing_last_name');
    checkout.inputs.delete('billing_address_2');
    checkout.inputs.delete('billing_city');
    checkout.edit('billing_first_name', 'Augusta');
    checkout.edit('billing_address_1', '2 Main St');
    await page.flush();

    assert.equal(page.captures[1].body.customer.name, 'Augusta Lovelace');
    assert.equal(page.captures[1].body.customer.address, '2 Main St Unit 2');
    assert.equal('city' in page.captures[1].body.customer, false);
    checkout.inputs.delete('billing_first_name');
    checkout.edit('billing_phone', '456');
    await page.flush();
    assert.equal('name' in page.captures[2].body.customer, false);
    assert.equal(page.captures[2].body.customer.phone, '456');
});

test('qualified checkout propagates explicit clears and further edits, but DOM teardown omits fields', async () => {
    const checkout = form({ billing_first_name: 'Ada', billing_last_name: 'Lovelace', billing_phone: '123' });
    const page = browser({ forms: [checkout] });
    await page.flush();
    checkout.edit('billing_phone', '  ');
    await page.flush();
    page.pagehide();
    await page.flush();
    assert.equal(page.captures.length, 2);
    assert.equal(page.captures[1].body.customer.phone, '');

    checkout.edit('billing_first_name', ' ');
    checkout.edit('billing_last_name', ' ');
    await page.flush();
    assert.equal(page.captures.length, 3);
    assert.equal(page.captures[2].body.customer.name, '');
    assert.equal(page.captures[2].body.customer.phone, '');
    checkout.edit('billing_phone', '456');
    await page.flush();
    assert.equal(page.captures.length, 4);
    assert.equal(page.captures[3].body.customer.name, '');
    assert.equal(page.captures[3].body.customer.phone, '456');

    checkout.inputs.clear();
    page.mutate();
    await page.flush();
    page.pagehide();
    await page.flush();
    assert.equal(page.captures.length, 4);
});

test('qualified Blocks checkout propagates programmatic name and phone clears', async () => {
    const page = browser({ forms: [form({}, { blocks: true })], cart: {
        billingAddress: { first_name: 'Ada', last_name: 'Lovelace', phone: '123' }
    } });
    await page.flush();
    page.updateCart({ billingAddress: { first_name: '', last_name: '', phone: '' } });
    await page.flush();
    assert.equal(page.captures.length, 2);
    assert.deepEqual(page.captures[1].body.customer, { anonymous_id: 'anonymous-native', name: '', phone: '' });

    page.updateCart({ billingAddress: { city: 'Paris' } });
    await page.flush();
    assert.equal(page.captures.length, 3);
    assert.deepEqual(page.captures[2].body.customer, { anonymous_id: 'anonymous-native', city: 'Paris' });
});

test('qualified order forms propagate explicit full-name and phone clears', async () => {
    const landing = form({ adoology_name: 'Ada Lovelace', adoology_phone: '123' }, { orderForm: true, signature: 'landing' });
    const page = browser({ forms: [landing] });
    await page.flush();
    landing.edit('adoology_name', ' ');
    landing.edit('adoology_phone', ' ');
    page.pagehide();
    await page.flush();
    assert.equal(page.captures.length, 2);
    assert.equal(page.captures[1].body.customer.name, '');
    assert.equal(page.captures[1].body.customer.phone, '');
    landing.inputs.delete('adoology_name');
    landing.edit('adoology_phone', '456');
    await page.flush();
    assert.equal(page.captures.length, 3);
    assert.equal('name' in page.captures[2].body.customer, false);
});

test('order-form DOM teardown cannot reset quantity or variation through an empty snapshot', async () => {
    const landing = form({ adoology_name: 'Ada Lovelace', adoology_phone: '123', quantity: '2', variation_id: '9' },
        { orderForm: true, signature: 'landing' });
    const page = browser({ forms: [landing] });
    await page.flush();
    landing.inputs.clear();
    page.mutate();
    await page.flush();
    page.pagehide();
    await page.flush();
    assert.equal(page.captures.length, 1);
});

test('pagehide flushes fresh valid edits with keepalive without duplicate snapshots', async () => {
    const checkout = form({ billing_first_name: 'Ada', billing_phone: '123' });
    const page = browser({ forms: [checkout] });
    await page.flush();
    checkout.edit('billing_phone', '456');
    page.pagehide();
    assert.equal(page.captures.length, 2);
    assert.equal(page.captures[1].body.customer.phone, '456');
    assert.equal(page.captures[1].body.form_stage, 'leaving');
    assert.equal(page.captures[1].keepalive, true);
    page.pagehide();
    await page.flush();
    assert.equal(page.captures.length, 2);
});

test('normal captures serialize per form with increasing sequences', async () => {
    const checkout = form({ billing_first_name: 'Ada', billing_phone: '123' });
    const responses = [];
    const page = browser({ forms: [checkout], captureFetch: () => new Promise(resolve => responses.push(resolve)) });
    await page.flush();
    checkout.edit('billing_phone', '456');
    await page.flush();
    checkout.edit('billing_phone', '789');
    await page.flush();
    assert.equal(page.captures.length, 1);

    responses.shift()({ ok: true });
    await settle();
    assert.equal(page.captures.length, 2);
    assert.equal(page.captures[1].body.customer.phone, '456');
    responses.shift()({ ok: true });
    await settle();
    assert.equal(page.captures.length, 3);
    assert.equal(page.captures[2].body.customer.phone, '789');
    assert.equal(page.captures[2].keepalive, false);
    assert.deepEqual(page.captures.map(request => request.body.capture_sequence), [1, 2, 3]);
    assert.equal(new Set(page.captures.map(request => request.body.checkout_id)).size, 1);
    responses.shift()({ ok: true });
    await settle();
});

test('cached signed HTML reload continues stored sequences beyond pending pagehide', async () => {
    const key = 'adoologyCheckoutSequence:checkout-native:native';
    const storage = new Map([[key, '5']]);
    const checkout = form({ billing_first_name: 'Ada', billing_phone: '123' });
    const old = browser({ forms: [checkout], storage, captureFetch: () => new Promise(() => {}) });
    await old.flush();
    assert.equal(old.captures[0].body.capture_sequence, 6);
    checkout.edit('billing_phone', '456');
    await old.flush();
    assert.equal(storage.get(key), '7', 'queued sequence must already be persisted');
    old.pagehide();
    assert.equal(old.captures[1].body.capture_sequence, 8);
    assert.equal(storage.get(key), '8', 'pending keepalive must reserve its sequence synchronously');

    const reloaded = browser({ forms: [form({ billing_first_name: 'Ada', billing_phone: '456' })], storage });
    await reloaded.flush();
    assert.equal(reloaded.tokens.length, 1, 'native identity must still be revalidated');
    assert.equal(reloaded.captures[0].body.checkout_id, old.captures[0].body.checkout_id);
    assert.equal(reloaded.captures[0].body.capture_signature, old.captures[0].body.capture_signature);
    assert.equal(reloaded.captures[0].body.capture_sequence, 9);
    assert.equal(storage.get(key), '9');
});

test('malformed stored sequences cannot seed or regress the in-memory counter', async t => {
    const key = 'adoologyCheckoutSequence:checkout-native:native';
    for (const value of ['garbage', '-5', 'NaN', 'Infinity', '1.5', '5junk', '5\n', ' 5 ', '05', '1e2', '0x10', '9007199254740992', 'null', '{}', '']) {
        await t.test(JSON.stringify(value), async () => {
            const storage = new Map([[key, value]]);
            const checkout = form({ billing_first_name: 'Ada', billing_phone: '123' });
            const page = browser({ forms: [checkout], storage });
            await page.flush();
            assert.equal(page.captures[0].body.capture_sequence, 1);
            storage.set(key, value);
            checkout.edit('billing_phone', '456');
            await page.flush();
            assert.equal(page.captures[1].body.capture_sequence, 2);
            assert.equal(storage.get(key), '2');
        });
    }
});

test('sequence counter retains in-memory progress when storage reads or writes fail', async t => {
    for (const readsFail of [true, false]) {
        await t.test(readsFail ? 'storage inaccessible' : 'writes blocked', async () => {
            const checkout = form({ billing_first_name: 'Ada', billing_phone: '123' });
            const page = browser({ forms: [checkout], sessionStorage: {
                getItem() {
                    if (readsFail) throw new Error('Storage blocked');
                    return '5';
                },
                setItem() { throw new Error('Storage blocked'); }
            } });
            await page.flush();
            checkout.edit('billing_phone', '456');
            await page.flush();
            checkout.edit('billing_phone', '789');
            page.pagehide();
            assert.deepEqual(page.captures.map(request => request.body.capture_sequence), readsFail ? [1, 2, 3] : [6, 7, 8]);
            await settle();
        });
    }
});

test('same checkout and context remount continues sequences while other signatures stay isolated', async () => {
    const first = form({ adoology_name: 'Ada Lovelace', adoology_phone: '123' }, { orderForm: true, signature: 'first' });
    const second = form({ adoology_name: 'Grace Hopper', adoology_phone: '456' }, { orderForm: true, signature: 'second' });
    const storage = new Map([['adoologyCheckoutSequence:shared-checkout:first', '5']]);
    const page = browser({ forms: [first, second], storage,
        tokenFetch: async request => ({ ok: true, json: async () => ({ ...identity(request.body.capture_signature), checkout_id: 'shared-checkout' }) })
    });
    await page.flush();
    assert.equal(page.captures.find(request => request.body.capture_signature === 'first').body.capture_sequence, 6);
    assert.equal(page.captures.find(request => request.body.capture_signature === 'second').body.capture_sequence, 1);

    first.isConnected = false;
    const remounted = form({ adoology_name: 'Ada Lovelace', adoology_phone: '789' }, { orderForm: true, signature: 'first' });
    remounted.mutate = page.mutate;
    page.forms.splice(0, 1, remounted);
    page.mutate();
    await page.flush();
    assert.equal(page.captures[2].body.capture_sequence, 7);
    assert.equal(storage.get('adoologyCheckoutSequence:shared-checkout:first'), '7');
    assert.equal(storage.get('adoologyCheckoutSequence:shared-checkout:second'), '1');
});

test('pagehide immediately overtakes queued captures with a higher server-guarded sequence', async () => {
    const checkout = form({ billing_first_name: 'Ada', billing_phone: '123' });
    const responses = [];
    let acceptedSequence = 0;
    let saved;
    const page = browser({ forms: [checkout], captureFetch: request => new Promise(resolve => {
        responses.push(() => {
            if (request.body.capture_sequence > acceptedSequence) {
                acceptedSequence = request.body.capture_sequence;
                saved = request.body;
            }
            resolve({ ok: true });
        });
    }) });
    await page.flush();
    checkout.edit('billing_phone', '456');
    await page.flush();
    assert.equal(page.captures.length, 1);
    page.pagehide();
    assert.equal(page.captures.length, 2, 'keepalive fetch must start before pagehide returns');
    assert.equal(page.captures[1].keepalive, true);
    assert.equal(page.captures[1].body.customer.phone, '456');
    assert.deepEqual(page.captures.map(request => request.body.capture_sequence), [1, 3]);
    page.pagehide();
    assert.equal(page.captures.length, 2, 'unchanged in-flight keepalive must dedupe');

    responses[1]();
    await settle();
    responses[0]();
    await settle();
    assert.deepEqual(page.captures.map(request => request.body.capture_sequence), [1, 3, 2]);
    responses[2]();
    await settle();
    assert.equal(saved.capture_sequence, 3);
    assert.equal(saved.customer.phone, '456');
    assert.equal(saved.form_stage, 'leaving');
    page.pagehide();
    assert.equal(page.captures.length, 3);
});

test('immediate pagehide includes pending omitted edits, but not acknowledged unavailable fields', async () => {
    const checkout = form({ billing_first_name: 'Ada', billing_phone: '123', billing_city: 'London', billing_email: 'ada@example.test' });
    const responses = [];
    const page = browser({ forms: [checkout], captureFetch: () => new Promise(resolve => responses.push(resolve)) });
    await page.flush();
    responses[0]({ ok: true });
    await settle();
    checkout.inputs.delete('billing_first_name');
    checkout.inputs.delete('billing_email');
    checkout.edit('billing_city', 'Paris');
    await page.flush();
    checkout.inputs.delete('billing_city');
    checkout.edit('billing_phone', '456');
    await page.flush();
    checkout.edit('billing_phone', '789');
    page.pagehide();
    assert.equal(page.captures.length, 3);
    assert.deepEqual(page.captures[2].body.customer, { anonymous_id: 'anonymous-native', phone: '789', city: 'Paris' });
    assert.equal(page.captures[2].body.capture_sequence, 4);
    assert.equal(page.captures[2].keepalive, true);
    responses[2]({ ok: true });
    await settle();
    responses[1]({ ok: false });
    await settle();
    assert.equal(page.captures[3].body.capture_sequence, 3);
    responses[3]({ ok: true });
    await settle();
    checkout.edit('billing_phone', '000');
    await page.flush();
    assert.deepEqual(page.captures[4].body.customer, { anonymous_id: 'anonymous-native', phone: '000' });
    responses[4]({ ok: true });
    await settle();
});

test('synchronous keepalive fetch failure is contained and retry gets a new sequence', async () => {
    const checkout = form({ billing_first_name: 'Ada', billing_phone: '123' });
    let fail = true;
    const page = browser({ forms: [checkout], captureFetch: request => {
        if (request.keepalive && fail) {
            fail = false;
            throw new Error('Keepalive quota exceeded');
        }
        return Promise.resolve({ ok: true });
    } });
    await page.flush();
    checkout.edit('billing_phone', '456');
    assert.doesNotThrow(() => page.pagehide());
    assert.equal(page.captures.length, 2);
    await settle();
    page.pagehide();
    assert.equal(page.captures.length, 3);
    assert.deepEqual(page.captures.map(request => request.body.capture_sequence), [1, 2, 3]);
    await settle();
});

test('older acknowledgment after failed keepalive preserves only newer unacknowledged edits', async () => {
    const checkout = form({ billing_first_name: 'Ada', billing_phone: '123', billing_city: 'London', billing_country: 'GB' });
    const responses = [];
    const page = browser({ forms: [checkout], captureFetch: () => new Promise(resolve => responses.push(resolve)) });
    await page.flush();
    responses[0]({ ok: true });
    await settle();
    checkout.inputs.delete('billing_first_name');
    checkout.edit('billing_city', 'Paris');
    await page.flush();
    checkout.inputs.delete('billing_city');
    checkout.edit('billing_phone', '456');
    page.pagehide();
    assert.equal(page.captures.length, 3);
    responses[2]({ ok: false });
    await settle();
    responses[1]({ ok: true });
    await settle();

    checkout.inputs.delete('billing_phone');
    checkout.edit('billing_country', 'FR');
    await page.flush();
    assert.deepEqual(page.captures[3].body.customer, { anonymous_id: 'anonymous-native', phone: '456', country: 'FR' });
    assert.equal(page.captures[3].body.capture_sequence, 4);
    responses[3]({ ok: true });
    await settle();
    page.pagehide();
    assert.equal(page.captures.length, 4);
});

test('failed capture can retry an unchanged snapshot', async () => {
    let attempts = 0;
    const page = browser({ forms: [form({ billing_first_name: 'Ada', billing_phone: '123' })],
        captureFetch: () => Promise.resolve({ ok: ++attempts > 1 })
    });
    await page.flush();
    page.mutate();
    await page.flush();
    assert.equal(page.captures.length, 2);
    assert.deepEqual(page.captures[0].body.customer, page.captures[1].body.customer);
});

test('failed initial capture supplies known details to queued partial update, then resumes omission', async () => {
    const checkout = form({ billing_first_name: 'Ada', billing_last_name: 'Lovelace', billing_phone: '123', billing_city: 'London' });
    const responses = [];
    const page = browser({ forms: [checkout], captureFetch: () => new Promise(resolve => responses.push(resolve)) });
    await page.flush();
    checkout.inputs.delete('billing_first_name');
    checkout.inputs.delete('billing_last_name');
    checkout.inputs.delete('billing_city');
    checkout.edit('billing_phone', '456');
    await page.flush();
    assert.equal(page.captures.length, 1);
    responses.shift()({ ok: false });
    await settle();

    assert.deepEqual(page.captures[1].body.customer, {
        anonymous_id: 'anonymous-native', name: 'Ada Lovelace', phone: '456', city: 'London'
    });
    responses.shift()({ ok: true });
    await settle();
    checkout.edit('billing_phone', '789');
    await page.flush();
    assert.deepEqual(page.captures[2].body.customer, { anonymous_id: 'anonymous-native', phone: '789' });
    responses.shift()({ ok: true });
    await settle();
});

test('failed updates retry only unacknowledged fields without overwriting omitted server-owned details', async () => {
    const checkout = form({ billing_first_name: 'Ada', billing_phone: '123', billing_city: 'London', billing_email: 'ada@example.test' });
    const responses = [];
    const page = browser({ forms: [checkout], captureFetch: () => new Promise((resolve, reject) => responses.push({ resolve, reject })) });
    await page.flush();
    responses.shift().resolve({ ok: true });
    await settle();
    checkout.inputs.delete('billing_first_name');
    checkout.inputs.delete('billing_email');
    checkout.edit('billing_city', 'Paris');
    await page.flush();
    checkout.inputs.delete('billing_city');
    checkout.edit('billing_phone', '456');
    await page.flush();
    responses.shift().reject(new Error('Network failure'));
    await settle();

    assert.deepEqual(page.captures[2].body.customer, { anonymous_id: 'anonymous-native', phone: '456', city: 'Paris' });
    responses.shift().resolve({ ok: true });
    await settle();
    checkout.edit('billing_phone', '789');
    await page.flush();
    assert.deepEqual(page.captures[3].body.customer, { anonymous_id: 'anonymous-native', phone: '789' });
    responses.shift().resolve({ ok: true });
    await settle();
});

test('initial failure followed by explicit clears does not lose bootstrap details or freeze qualification', async () => {
    const checkout = form({ billing_first_name: 'Ada', billing_phone: '123' });
    const responses = [];
    const page = browser({ forms: [checkout], captureFetch: () => new Promise(resolve => responses.push(resolve)) });
    await page.flush();
    checkout.inputs.delete('billing_first_name');
    checkout.edit('billing_phone', '');
    await page.flush();
    responses.shift()({ ok: false });
    await settle();
    assert.deepEqual(page.captures[1].body.customer, { anonymous_id: 'anonymous-native', name: 'Ada', phone: '' });
    responses.shift()({ ok: true });
    await settle();

    checkout.edit('billing_phone', '456');
    await page.flush();
    assert.deepEqual(page.captures[2].body.customer, { anonymous_id: 'anonymous-native', name: 'Ada', phone: '456' });
    responses.shift()({ ok: true });
    await settle();
});

test('native checkout revalidates old signed context instead of reinstalling cached identity', async () => {
    const old = { ...identity('native'), checkout_id: 'accepted-checkout' };
    const storage = new Map([['adoologyCheckoutIdentity:native', JSON.stringify(old)]]);
    const page = browser({ storage, forms: [form({ billing_first_name: 'Ada', billing_phone: '123' })] });
    await page.flush();

    assert.equal(page.tokens.length, 1);
    assert.deepEqual(page.tokens[0].body, { capture_context: 'context-native', capture_signature: 'native' });
    assert.equal(page.tokens[0].credentials, 'same-origin');
    assert.equal(page.captures[0].body.checkout_id, 'checkout-native');
    assert.ok(page.cookies.some(cookie => cookie.startsWith('adoology_checkout_id=checkout-native;')));
    assert.ok(page.cookies.every(cookie => !cookie.includes('accepted-checkout')));
});

test('rejected native token never falls back to old sessionStorage identity', async () => {
    const storage = new Map([['adoologyCheckoutIdentity:native', JSON.stringify(identity('native'))]]);
    const page = browser({ storage, forms: [form({ billing_first_name: 'Ada', billing_phone: '123' })],
        tokenFetch: async () => ({ ok: false })
    });
    await page.flush();
    assert.equal(page.captures.length, 0);
    assert.equal(page.cookies.length, 0);
    page.mutate();
    await page.flush();
    assert.equal(page.tokens.length, 2);
});

test('remounted native form revalidates live session instead of retaining settled identity promise', async () => {
    let checkoutId = 'accepted-checkout';
    const page = browser({ forms: [form({ billing_first_name: 'Ada', billing_phone: '123' })],
        tokenFetch: async () => ({ ok: true, json: async () => ({ ...identity('native'), checkout_id: checkoutId }) })
    });
    await page.flush();
    assert.equal(page.captures[0].body.checkout_id, 'accepted-checkout');

    checkoutId = 'next-checkout';
    const next = form({ billing_first_name: 'Grace', billing_phone: '456' });
    next.mutate = page.mutate;
    page.forms[0].isConnected = false;
    page.forms.splice(0, 1, next);
    page.mutate();
    await page.flush();
    assert.equal(page.tokens.length, 2);
    assert.equal(page.captures[1].body.checkout_id, 'next-checkout');
    assert.deepEqual(page.captures.map(request => request.body.capture_sequence), [1, 1]);
    assert.equal(next.inputs.get('_adoology_checkout_id').value, 'next-checkout');
});

test('removed Blocks forms unsubscribe and discard queued sends after remount', async () => {
    const old = form({}, { blocks: true });
    let checkoutId = 'old-checkout';
    const cart = { billingAddress: { first_name: 'Ada', phone: '123' } };
    const responses = [];
    const page = browser({ forms: [old], cart,
        tokenFetch: async () => ({ ok: true, json: async () => ({ ...identity('native'), checkout_id: checkoutId }) }),
        captureFetch: () => new Promise(resolve => responses.push(resolve))
    });
    await page.flush();
    page.updateCart({ billingAddress: { ...cart.billingAddress, phone: '456' } });
    await page.flush();
    assert.equal(page.captures.length, 1, 'second old-form capture is queued');

    old.isConnected = false;
    checkoutId = 'new-checkout';
    const next = form({}, { blocks: true });
    next.mutate = page.mutate;
    page.forms.splice(0, 1, next);
    page.mutate();
    await page.flush();
    assert.equal(page.captures.length, 2);
    assert.equal(page.captures[1].body.checkout_id, 'new-checkout');

    page.updateCart({ billingAddress: { ...cart.billingAddress, phone: '789' } });
    await page.flush();
    assert.equal(page.subscribers.length, 1);
    responses.shift()({ ok: true });
    await settle();
    assert.equal(page.captures.length, 2, 'disconnected old-form queue must not send');
    responses.shift()({ ok: true });
    await settle();
    assert.equal(page.captures.length, 3);
    assert.equal(page.captures[2].body.checkout_id, 'new-checkout');
    responses.shift()({ ok: true });
    await settle();

    old.inputs.set('billing_phone', { name: 'billing_phone', value: 'stale' });
    page.pagehide();
    await page.flush();
    assert.equal(page.captures.length, 3);
});

test('order forms keep full-name cache and isolated identities, fields, cookies, and request queues', async () => {
    const landing = form({ adoology_name: 'Ada Lovelace', adoology_phone: '123', adoology_city: '', quantity: '2', variation_id: '9' },
        { orderForm: true, signature: 'landing' });
    const other = form({ adoology_name: 'Grace Hopper', adoology_phone: '456' }, { orderForm: true, signature: 'other' });
    const storage = new Map([['adoologyCheckoutIdentity:landing', JSON.stringify(identity('landing'))]]);
    const page = browser({ forms: [landing, other], storage, cart: {
        billingAddress: { first_name: 'Wrong', last_name: 'Customer', phone: '999' }
    }, captureFetch: () => new Promise(() => {}) });
    await page.flush();

    assert.equal(page.tokens.length, 1);
    assert.equal(page.tokens[0].body.capture_signature, 'other');
    assert.equal(page.captures.length, 2);
    const capture = page.captures.find(request => request.body.checkout_id === 'checkout-landing').body;
    assert.deepEqual(page.captures.map(request => request.body.capture_sequence), [1, 1]);
    assert.equal('billing_contact' in capture, false);
    assert.deepEqual(capture.customer, { anonymous_id: 'anonymous-landing', name: 'Ada Lovelace', phone: '123', city: '' });
    assert.equal(capture.quantity, 2);
    assert.equal(capture.variation_id, 9);
    assert.equal(landing.inputs.get('_adoology_capture_token').value, 'token-landing');
    assert.equal(other.inputs.get('_adoology_checkout_id').value, 'checkout-other');
    assert.equal(page.cookies.length, 0);
    assert.equal(page.extensions.length, 0);
    assert.equal(page.subscribers.length, 0);
});

test('expired or mismatched order-form cache requests a new token', async t => {
    for (const stored of [{ ...identity('landing'), expires: 1 }, { ...identity('landing'), capture_context: 'wrong' }]) {
        await t.test(JSON.stringify(stored), async () => {
            const page = browser({ forms: [form({}, { orderForm: true, signature: 'landing' })],
                storage: new Map([['adoologyCheckoutIdentity:landing', JSON.stringify(stored)]])
            });
            await page.flush();
            assert.equal(page.tokens.length, 1);
            assert.equal(page.captures.length, 0);
            assert.equal(JSON.parse(page.storage.get('adoologyCheckoutIdentity:landing')).token, 'token-landing');
        });
    }
});

test('late Blocks registration binds once and hidden inputs do not cause mutation loops', async () => {
    const checkout = form({}, { blocks: true });
    const page = browser({ forms: [checkout] });
    await page.flush();
    page.installBlocks({ billingAddress: { first_name: 'Ada', last_name: 'Lovelace', phone: '123' } });
    page.mutate();
    await page.flush();
    page.mutate();
    await page.flush();
    assert.equal(page.tokens.length, 1);
    assert.equal(page.captures.length, 1);
    assert.equal(page.subscribers.length, 1);
    assert.equal(page.extensions.length, 1);
    assert.equal(checkout.inputs.size, 1);
    assert.equal(page.timers.size, 0);
});

test('classic checkout ignores unrelated Blocks cart store', async () => {
    const page = browser({ forms: [form({ billing_first_name: 'Ada', billing_last_name: 'Lovelace', billing_phone: '123' })],
        cart: { billingAddress: { first_name: 'Wrong', phone: '999' } }
    });
    await page.flush();
    assert.equal(page.captures[0].body.customer.name, 'Ada Lovelace');
    assert.equal(page.captures[0].body.customer.phone, '123');
    assert.equal(page.subscribers.length, 0);
});

test('tracking disabled makes no token or capture requests', async () => {
    const page = browser({ enabled: false, forms: [form({ billing_first_name: 'Ada', billing_phone: '123' })] });
    await page.flush();
    assert.equal(page.tokens.length, 0);
    assert.equal(page.captures.length, 0);
});
