import assert from 'node:assert/strict';
import test from 'node:test';
import { formatElements, normalizeName } from './elements.mjs';

test('it drops elements without an accessible name', () => {
    const elements = formatElements([
        { ref: 1, role: 'button', name: 'Start a post', disabled: false },
        { ref: 2, role: 'button', name: '   ', disabled: false },
    ]);

    assert.deepEqual(elements, [{ ref: 1, role: 'button', name: 'Start a post', state: {} }]);
});

test('it preserves the page assigned ref so actions stay addressable', () => {
    const elements = formatElements([
        { ref: 42, role: 'textbox', name: 'What do you want to talk about?', disabled: false },
    ]);

    assert.equal(elements[0].ref, 42);
});

test('it reports disabled state', () => {
    const [element] = formatElements([{ ref: 3, role: 'button', name: 'Post', disabled: true }]);

    assert.deepEqual(element.state, { disabled: true });
});

test('it caps the element list', () => {
    const raw = Array.from({ length: 200 }, (_, index) => ({
        ref: index + 1,
        role: 'button',
        name: `Item ${index}`,
        disabled: false,
    }));

    assert.equal(formatElements(raw, { max: 60 }).length, 60);
});

test('it collapses whitespace and truncates long names', () => {
    assert.equal(normalizeName('  Start   a\n post '), 'Start a post');
    assert.equal(normalizeName('x'.repeat(300)).length, 120);
});
