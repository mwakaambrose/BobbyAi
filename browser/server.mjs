import { existsSync } from 'node:fs';
import http from 'node:http';
import { chromium } from 'playwright';
import { act } from './act.mjs';
import { observe } from './observe.mjs';
import { closeSession, openSession, requireSession } from './session.mjs';

const PORT = Number(process.env.SIDECAR_PORT ?? 4571);

/**
 * Check that Playwright's browser executable is installed for this runtime.
 */
function chromiumIsInstalled() {
    return existsSync(chromium.executablePath());
}

/**
 * Read and parse a JSON request body.
 */
async function body(request) {
    const chunks = [];
    for await (const chunk of request) chunks.push(chunk);
    return chunks.length ? JSON.parse(Buffer.concat(chunks).toString()) : {};
}

/**
 * Write a JSON response.
 */
function json(response, status, payload) {
    response.writeHead(status, { 'content-type': 'application/json' });
    response.end(payload === undefined ? '' : JSON.stringify(payload));
}

const server = http.createServer(async (request, response) => {
    const url = new URL(request.url, `http://localhost:${PORT}`);
    const segments = url.pathname.split('/').filter(Boolean);

    try {
        if (request.method === 'GET' && url.pathname === '/health') {
            const chromium = chromiumIsInstalled();

            return json(response, chromium ? 200 : 503, { ok: chromium, chromium });
        }

        if (request.method === 'POST' && url.pathname === '/sessions') {
            return json(response, 201, await openSession(await body(request)));
        }

        if (segments[0] === 'sessions' && segments[1]) {
            const sessionId = segments[1];

            if (request.method === 'DELETE' && !segments[2]) {
                await closeSession(sessionId);
                return json(response, 204);
            }

            const { page } = requireSession(sessionId);

            if (request.method === 'GET' && segments[2] === 'observation') {
                return json(response, 200, await observe(page, { marks: url.searchParams.get('marks') !== '0' }));
            }

            if (request.method === 'POST' && segments[2] === 'actions') {
                return json(response, 200, await act(page, await body(request)));
            }

            if (request.method === 'POST' && segments[2] === 'navigate') {
                const { url: target } = await body(request);
                await page.goto(target, { waitUntil: 'domcontentloaded', timeout: 30_000 });
                return json(response, 200, { ok: true, url: page.url(), title: await page.title() });
            }
        }

        return json(response, 404, { ok: false, error: 'Not found.' });
    } catch (error) {
        return json(response, error.status ?? 500, { ok: false, error: error.message });
    }
});

server.listen(PORT, '127.0.0.1', () => {
    console.log(`Browser sidecar listening on http://127.0.0.1:${PORT}`);
});
