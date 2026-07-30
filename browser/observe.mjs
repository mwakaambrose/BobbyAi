import { formatElements } from './elements.mjs';

const CANDIDATE_SELECTOR = [
    'a[href]',
    'button',
    'input:not([type=hidden])',
    'textarea',
    '[contenteditable="true"]',
    '[role=button]',
    '[role=textbox]',
    '[role=link]',
    '[role=menuitem]',
].join(',');

/**
 * Tag every visible, actionable element with a stable ref and return its record.
 * Runs inside the page; must be self contained.
 */
function collect(selector) {
    const roleOf = (node) => {
        const explicit = node.getAttribute('role');
        if (explicit) return explicit;
        const tag = node.tagName.toLowerCase();
        if (tag === 'a') return 'link';
        if (tag === 'button') return 'button';
        if (tag === 'textarea') return 'textbox';
        if (tag === 'input') return node.type === 'checkbox' ? 'checkbox' : 'textbox';
        if (node.isContentEditable) return 'textbox';
        return 'generic';
    };

    const nameOf = (node) =>
        node.getAttribute('aria-label') ||
        node.getAttribute('placeholder') ||
        node.getAttribute('title') ||
        (node.innerText || '').trim();

    const visible = (node) => {
        const rect = node.getBoundingClientRect();
        const style = window.getComputedStyle(node);
        return (
            rect.width > 4 &&
            rect.height > 4 &&
            rect.bottom > 0 &&
            rect.top < window.innerHeight &&
            style.visibility !== 'hidden' &&
            style.display !== 'none'
        );
    };

    document.querySelectorAll('[data-agent-ref]').forEach((node) => node.removeAttribute('data-agent-ref'));

    const records = [];
    let ref = 0;

    for (const node of document.querySelectorAll(selector)) {
        if (!visible(node)) continue;

        ref += 1;
        node.setAttribute('data-agent-ref', String(ref));

        const rect = node.getBoundingClientRect();

        records.push({
            ref,
            role: roleOf(node),
            name: nameOf(node),
            disabled: node.disabled === true || node.getAttribute('aria-disabled') === 'true',
            box: { x: rect.x, y: rect.y, width: rect.width, height: rect.height },
        });
    }

    return records;
}

/**
 * Draw numbered overlay boxes for the collected elements.
 */
function drawMarks(records) {
    const layer = document.createElement('div');
    layer.id = '__agent_marks__';
    layer.style.cssText = 'position:fixed;inset:0;z-index:2147483647;pointer-events:none;';

    for (const record of records) {
        const box = document.createElement('div');
        box.style.cssText = `position:absolute;left:${record.box.x}px;top:${record.box.y}px;width:${record.box.width}px;height:${record.box.height}px;border:2px solid #e11d48;box-sizing:border-box;`;

        const label = document.createElement('span');
        label.textContent = String(record.ref);
        label.style.cssText =
            'position:absolute;top:-2px;left:-2px;background:#e11d48;color:#fff;font:600 11px/1.2 monospace;padding:1px 3px;';

        box.appendChild(label);
        layer.appendChild(box);
    }

    document.body.appendChild(layer);
}

/**
 * Remove the overlay layer.
 */
function clearMarks() {
    document.getElementById('__agent_marks__')?.remove();
}

/**
 * Produce a full observation: annotated screenshot plus the element list.
 */
export async function observe(page, { marks = true } = {}) {
    const records = await page.evaluate(collect, CANDIDATE_SELECTOR);

    if (marks) {
        await page.evaluate(drawMarks, records);
    }

    const screenshot = await page.screenshot({ type: 'png' });

    if (marks) {
        await page.evaluate(clearMarks);
    }

    return {
        url: page.url(),
        title: await page.title(),
        screenshot: screenshot.toString('base64'),
        elements: formatElements(records),
    };
}
