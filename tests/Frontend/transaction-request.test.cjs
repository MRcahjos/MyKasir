const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/scripts/shared/transaction-request.js'), 'utf8');
const storageKey = 'mykasir.pending.1.stock-adjustments';
const payload = () => ({ product_id: 7, quantity: -2, date: '2026-10-19', notes: 'Barang rusak', unit_cost: null });

function browser({ storage = new Map(), http, userId = '1' } = {}) {
    const calls = [], alerts = [], banners = [];
    let sequence = 0, reloads = 0;
    const context = {
        URL,
        document: {
            querySelector: () => ({ content: userId }),
            createElement: () => ({
                setAttribute() {},
                remove() { this.removed = true; },
                querySelector: () => ({ addEventListener() {} }),
            }),
        },
        window: { location: { href: 'https://kasir.test/stok', origin: 'https://kasir.test', reload: () => { reloads++; } } },
        sessionStorage: {
            getItem: (key) => storage.get(key) || null,
            setItem: (key, value) => storage.set(key, value),
            removeItem: (key) => storage.delete(key),
        },
        Swal: { fire: async (options) => { alerts.push(options); } },
        App: {
            url: (value) => value,
            escape: (value) => value,
            uuid: () => `00000000-0000-4000-8000-${String(++sequence).padStart(12, '0')}`,
            http: async (...args) => {
                calls.push(JSON.parse(JSON.stringify(args)));
                return http ? http(...args) : { data: { reference: 'ADJ-123' } };
            },
        },
    };
    vm.runInNewContext(source, context);
    const request = context.App.transactionRequest('stock-adjustments', { prepend: (banner) => banners.push(banner) });
    return { request, calls, alerts, banners, storage, get reloads() { return reloads; } };
}

test('an initial validation rejection allows corrections and a new submission', async () => {
    let attempts = 0;
    const page = browser({ http: async () => { if (++attempts === 1) throw { status: 422 }; return { data: {} }; } });
    await assert.rejects(page.request.send('/ajax/stock-adjustments', payload()), (error) => error.status === 422);
    assert.equal(page.request.pending, false);
    assert.equal(page.storage.has(storageKey), false);
    await page.request.send('/ajax/stock-adjustments', { ...payload(), quantity: -1 });
    assert.notEqual(page.calls[0][2].request_id, page.calls[1][2].request_id);
});

test('a lost response followed by validation rejection retains the original request', async () => {
    let attempts = 0;
    const page = browser({ http: async () => { throw { status: ++attempts === 1 ? 0 : 422 }; } });
    await assert.rejects(page.request.send('/ajax/stock-adjustments', payload()));
    const original = page.storage.get(storageKey);
    await page.request.retry();
    assert.equal(page.request.pending, true);
    assert.equal(page.storage.get(storageKey), original);
    assert.deepEqual(page.calls[0][2], page.calls[1][2]);
    await assert.rejects(page.request.send('/ajax/stock-adjustments', { ...payload(), quantity: -3 }), (error) => error.status === 409);
    assert.equal(page.calls.length, 2, 'changed data must not reach the server');
});

test('a pending request survives reload, expired session, and eventual successful replay', async () => {
    const storage = new Map();
    const first = browser({ storage, http: async () => { throw { status: 500 }; } });
    await assert.rejects(first.request.send('/ajax/stock-adjustments', payload()));
    const expired = browser({ storage, http: async () => { throw { status: 419 }; } });
    await expired.request.retry();
    assert.equal(expired.request.pending, true);
    assert.equal(expired.reloads, 0);
    const recovered = browser({ storage });
    assert.equal(recovered.request.pending, true);
    await recovered.request.retry();
    assert.deepEqual(recovered.calls[0][2], first.calls[0][2]);
    assert.equal(recovered.request.pending, false);
    assert.equal(storage.has(storageKey), false);
    assert.equal(recovered.reloads, 1);
    assert.match(recovered.alerts[0].text, /ADJ-123/);
});

test('an older saved request without metadata is treated as unconfirmed', async () => {
    const saved = { url: '/ajax/stock-adjustments', payload: { ...payload(), request_id: 'existing-key' } };
    const storage = new Map([[storageKey, JSON.stringify(saved)]]);
    const page = browser({ storage, http: async () => { throw { status: 401 }; } });
    await page.request.retry();
    assert.equal(page.request.pending, true);
    assert.equal(JSON.parse(storage.get(storageKey)).payload.request_id, 'existing-key');
});

test('a new request rejected by authentication does not leave a pending mutation', async () => {
    const page = browser({ http: async () => { throw { status: 401 }; } });
    await assert.rejects(page.request.send('/ajax/stock-adjustments', payload()));
    assert.equal(page.request.pending, false);
    assert.equal(page.storage.has(storageKey), false);
});

test('only one request is dispatched while sending and the submitted payload is immutable', async () => {
    let finish;
    const page = browser({ http: () => new Promise((resolve) => { finish = resolve; }) });
    const data = payload();
    const first = page.request.send('/ajax/stock-adjustments', data);
    data.quantity = -100;
    assert.equal(page.request.pending, true);
    assert.equal(JSON.parse(page.storage.get(storageKey)).attempted, true, 'persist before transport starts');
    await assert.rejects(page.request.send('/ajax/stock-adjustments', payload()), (error) => error.status === 429);
    assert.equal(page.calls.length, 1);
    assert.equal(page.calls[0][2].quantity, -2);
    finish({ data: {} });
    await first;
    assert.equal(page.request.pending, false);
});

test('pending storage is isolated by authenticated user and ignores external URLs', () => {
    const storage = new Map([[storageKey, JSON.stringify({ url: '/ajax/stock-adjustments', payload: { ...payload(), request_id: 'existing-key' } })]]);
    assert.equal(browser({ storage, userId: '2' }).request.pending, false);
    storage.set(storageKey, JSON.stringify({ url: 'https://outside.test/submit', payload: { request_id: 'unsafe' } }));
    assert.equal(browser({ storage }).request.pending, false);
});
