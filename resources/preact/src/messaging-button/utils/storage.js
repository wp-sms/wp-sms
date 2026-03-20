const PREFIX = 'wsms_mb_';
const COOLDOWN_MS = 24 * 60 * 60 * 1000; // 24 hours

function hashCode(str) {
    let hash = 5381;
    for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) + hash + str.charCodeAt(i)) | 0;
    }
    return Math.abs(hash).toString(36);
}

function getItem(storage, key) {
    try { return storage.getItem(PREFIX + key); }
    catch { return null; }
}

function setItem(storage, key, value) {
    try { storage.setItem(PREFIX + key, value); }
    catch { /* incognito or full storage — degrade gracefully */ }
}

// --- Greeting bubble (localStorage, hash-based deduplication) ---

export function hasSeenGreeting(messageText) {
    return getItem(localStorage, `greeting_${hashCode(messageText)}`) === '1';
}

export function markGreetingSeen(messageText) {
    setItem(localStorage, `greeting_${hashCode(messageText)}`, '1');
}

// --- Triggers (localStorage, 24-hour cooldown) ---

export function hasTriggerFired(triggerName) {
    const ts = getItem(localStorage, `trigger_${triggerName}`);
    if (!ts) return false;
    return Date.now() - Number(ts) < COOLDOWN_MS;
}

export function markTriggerFired(triggerName) {
    setItem(localStorage, `trigger_${triggerName}`, String(Date.now()));
}

// --- User closed widget (sessionStorage, per-session) ---

export function hasUserClosed() {
    return getItem(sessionStorage, 'user_closed') === '1';
}

export function markUserClosed() {
    setItem(sessionStorage, 'user_closed', '1');
}
