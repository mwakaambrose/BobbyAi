const MAX_NAME_LENGTH = 120;

/**
 * Collapse whitespace and truncate an accessible name for prompt use.
 */
export function normalizeName(name) {
    return String(name ?? '')
        .replace(/\s+/g, ' ')
        .trim()
        .slice(0, MAX_NAME_LENGTH);
}

/**
 * Turn raw in-page element records into the API payload. Refs are assigned in
 * the page itself, so they survive this filtering and stay addressable.
 */
export function formatElements(raw, { max = 60 } = {}) {
    return raw
        .map((element) => ({
            ref: element.ref,
            role: element.role,
            name: normalizeName(element.name),
            state: element.disabled ? { disabled: true } : {},
        }))
        .filter((element) => element.name.length > 0)
        .slice(0, max);
}
