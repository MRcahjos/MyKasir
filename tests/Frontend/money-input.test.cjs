const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const context = { window: {} };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/scripts/shared/format.js'), 'utf8'), context);
const App = context.window.App;

function field(value = '', options) {
    const listeners = {};
    const input = {
        value,
        selectionStart: value.length,
        selectionEnd: value.length,
        addEventListener(name, listener) { listeners[name] = listener; },
        setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end; },
        setCustomValidity(message) { this.validationMessage = message; },
        edit(next, caret = next.length, inputType = 'insertText') {
            this.value = next;
            this.setSelectionRange(caret, caret);
            listeners.input({ inputType });
        },
    };
    input.refresh = App.bindMoneyInput(input, options);
    return input;
}

test('typing a rupiah amount adds grouping and submits the original amount', () => {
    const input = field();
    for (const character of '11000') input.edit(input.value + character);
    assert.equal(input.value, '11.000');
    assert.equal(input.selectionStart, 6);
    assert.equal(App.moneyInputRaw(input.value), '11000');
    assert.equal(Number(App.moneyInputRaw(input.value)), 11000);
    assert.equal(input.validationMessage, '');
});

test('API prices and cashier shortcuts render with Indonesian separators', () => {
    assert.equal(App.moneyInputValue('11000.00'), '11.000');
    assert.equal(App.moneyInputValue('11000.50'), '11.000,50');
    assert.equal(App.moneyInputValue(100000), '100.000');
    assert.equal(App.moneyInputValue('0.01'), '0,01');
    assert.equal(App.moneyInputValue(''), '');
    assert.equal(App.moneyInputValue(null), '');
});

test('decimal comma and pasted grouped prices retain their cents', () => {
    const input = field('', { min: 0.01 });
    input.edit('1.234.567,89');
    assert.equal(input.value, '1.234.567,89');
    assert.equal(App.moneyInputRaw(input.value), '1234567.89');
    assert.equal(input.validationMessage, '');
    input.edit('11000,');
    assert.equal(input.value, '11.000,');
    input.edit(input.value + '50');
    assert.equal(input.value, '11.000,50');
    assert.equal(App.moneyInputRaw(input.value), '11000.50');
});

test('editing in the middle preserves the cursor beside the edited digit', () => {
    const input = field('11.000');
    input.edit('119.000', 3);
    assert.equal(input.value, '119.000');
    assert.equal(input.selectionStart, 3);
    input.edit('1199.000', 4);
    assert.equal(input.value, '1.199.000');
    assert.equal(input.selectionStart, 5);
    input.edit('1.19.000', 4, 'deleteContentBackward');
    assert.equal(input.value, '119.000');
    assert.equal(input.selectionStart, 3);
});

test('deleting a grouping separator leaves the cursor ready to delete a digit', () => {
    const input = field('11.000');
    input.edit('11000', 2, 'deleteContentBackward');
    assert.equal(input.value, '11.000');
    assert.equal(input.selectionStart, 2);
    input.edit('11000', 2, 'deleteContentForward');
    assert.equal(input.value, '11.000');
    assert.equal(input.selectionStart, 3);
});

test('clearing an input preserves empty so native required validation can run', () => {
    const input = field('11.000', { min: 0.01 });
    input.edit('');
    assert.equal(input.value, '');
    assert.equal(App.moneyInputRaw(input.value), '');
    assert.equal(input.validationMessage, '');
    assert.equal(input.selectionStart, 0);
});

test('extra precision and malformed amounts are rejected without truncation', () => {
    const input = field();
    for (const invalid of ['11000,123', '11,00,0', '11abc000', '1e3', '.', '...', '11,0.5']) {
        input.edit(invalid);
        assert.ok(input.validationMessage);
        assert.equal(App.moneyInputRaw(input.value), 'NaN');
    }
    input.edit('11000,123');
    assert.equal(input.value, '11.000,123');
});

test('negative values remain negative and range validation blocks them', () => {
    const input = field();
    input.edit('-11000');
    assert.equal(input.value, '-11.000');
    assert.equal(App.moneyInputRaw(input.value), '-11000');
    assert.match(input.validationMessage, /minimal/);
    input.edit('1000000000000');
    assert.match(input.validationMessage, /maksimal/);
    input.edit('999999999999,99');
    assert.equal(input.validationMessage, '');
});

test('positive-price inputs reject zero while received cash allows it', () => {
    assert.equal(field('0').validationMessage, '');
    assert.match(field('0', { min: 0.01 }).validationMessage, /minimal/);
    assert.equal(field('0,01', { min: 0.01 }).validationMessage, '');
});

test('a programmatic shortcut can refresh stale validity and keep its whole amount', () => {
    const input = field('12,345');
    assert.ok(input.validationMessage);
    input.value = App.moneyInputValue(50000);
    input.refresh();
    assert.equal(input.value, '50.000');
    assert.equal(input.validationMessage, '');
    assert.equal(App.moneyInputRaw(input.value), '50000');
});

test('localized initialization does not reinterpret a decimal comma or grouping', () => {
    assert.equal(field('11.000,50').value, '11.000,50');
    assert.equal(field('11.000,50').validationMessage, '');
    assert.equal(App.moneyInputRaw(',50'), '0.50');
    assert.equal(App.moneyInputRaw('-,50'), '-0.50');
});

test('typing a leading decimal comma keeps subsequent digits in the fractional part', () => {
    for (const [typed, expected] of [[',50', '0,50'], ['-,50', '-0,50']]) {
        const input = field();
        for (const character of typed) {
            const caret = input.selectionStart;
            input.edit(input.value.slice(0, caret) + character + input.value.slice(caret), caret + 1);
        }
        assert.equal(input.value, expected);
        assert.equal(input.selectionStart, expected.length);
        assert.equal(App.moneyInputRaw(input.value), expected.replace(',', '.'));
    }
});
