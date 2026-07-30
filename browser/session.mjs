import { chromium } from 'playwright';

const sessions = new Map();

let counter = 0;

/**
 * Launch a persistent browser context for an account.
 */
export async function openSession({ accountId, profileDir, headed = false }) {
    const context = await chromium.launchPersistentContext(profileDir, {
        headless: !headed,
        viewport: { width: 1440, height: 900 },
        args: ['--disable-blink-features=AutomationControlled'],
    });

    const page = context.pages()[0] ?? (await context.newPage());
    const sessionId = `s${++counter}-${accountId}`;

    sessions.set(sessionId, { context, page });

    return { sessionId, url: page.url(), title: await page.title() };
}

/**
 * Get a live session or throw a 404-shaped error.
 */
export function requireSession(sessionId) {
    const session = sessions.get(sessionId);

    if (!session) {
        const error = new Error(`Unknown session [${sessionId}].`);
        error.status = 404;
        throw error;
    }

    return session;
}

/**
 * Close a session, leaving the persistent profile on disk.
 */
export async function closeSession(sessionId) {
    const session = sessions.get(sessionId);

    if (session) {
        await session.context.close();
        sessions.delete(sessionId);
    }
}
