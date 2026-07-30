const STEP_TIMEOUT = 20_000;

/**
 * Execute one action against the element carrying its ref.
 */
export async function act(page, { action, ref, text, key }) {
    const target = ref === undefined || ref === null ? null : page.locator(`[data-agent-ref="${ref}"]`);

    switch (action) {
        case 'click':
            await target.click({ timeout: STEP_TIMEOUT });
            break;

        case 'type':
            await target.click({ timeout: STEP_TIMEOUT });
            await target.fill('', { timeout: STEP_TIMEOUT }).catch(() => {});
            await page.keyboard.type(text, { delay: 12 });
            break;

        case 'press':
            await page.keyboard.press(key);
            break;

        case 'scroll':
            await page.mouse.wheel(0, 600);
            break;

        case 'wait':
            await page.waitForTimeout(1_000);
            break;

        default:
            throw Object.assign(new Error(`Unsupported action [${action}].`), { status: 422 });
    }

    await page.waitForTimeout(400);

    return { ok: true, url: page.url(), title: await page.title() };
}
