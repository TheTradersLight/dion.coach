// ============================================================
// dion.coach Offline Manager
// IndexedDB backup for camp data + evaluation sync queue
// ============================================================

const DB_NAME = 'dion_coach';
const DB_VERSION = 1;

let db = null;

function openDB() {
    return new Promise((resolve, reject) => {
        if (db) { resolve(db); return; }
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = (e) => {
            const idb = e.target.result;
            if (!idb.objectStoreNames.contains('campData')) {
                idb.createObjectStore('campData', { keyPath: 'campId' });
            }
            if (!idb.objectStoreNames.contains('evalQueue')) {
                idb.createObjectStore('evalQueue', { autoIncrement: true });
            }
        };
        request.onsuccess = (e) => {
            db = e.target.result;
            resolve(db);
        };
        request.onerror = (e) => reject(e.target.error);
    });
}

// ============================================================
// Camp Data Store
// ============================================================
async function saveCampData(campId, data) {
    const idb = await openDB();
    return new Promise((resolve, reject) => {
        const tx = idb.transaction('campData', 'readwrite');
        tx.objectStore('campData').put({
            campId: campId,
            data: data,
            cachedAt: new Date().toISOString()
        });
        tx.oncomplete = () => resolve();
        tx.onerror = (e) => reject(e.target.error);
    });
}

async function getCampData(campId) {
    const idb = await openDB();
    return new Promise((resolve, reject) => {
        const tx = idb.transaction('campData', 'readonly');
        const req = tx.objectStore('campData').get(campId);
        req.onsuccess = () => resolve(req.result || null);
        req.onerror = (e) => reject(e.target.error);
    });
}

// ============================================================
// Eval Queue Store
// ============================================================
async function queueEvaluations(campId, evaluations) {
    const idb = await openDB();
    return new Promise((resolve, reject) => {
        const tx = idb.transaction('evalQueue', 'readwrite');
        tx.objectStore('evalQueue').add({
            campId: campId,
            evaluations: evaluations,
            createdAt: new Date().toISOString()
        });
        tx.oncomplete = () => resolve();
        tx.onerror = (e) => reject(e.target.error);
    });
}

async function getQueuedEvaluations() {
    const idb = await openDB();
    return new Promise((resolve, reject) => {
        const tx = idb.transaction('evalQueue', 'readonly');
        const req = tx.objectStore('evalQueue').getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = (e) => reject(e.target.error);
    });
}

async function clearEvalQueue() {
    const idb = await openDB();
    return new Promise((resolve, reject) => {
        const tx = idb.transaction('evalQueue', 'readwrite');
        tx.objectStore('evalQueue').clear();
        tx.oncomplete = () => resolve();
        tx.onerror = (e) => reject(e.target.error);
    });
}

// ============================================================
// Prepare Camp for Offline
// ============================================================
async function prepareCampOffline(campId) {
    try {
        // 1. Fetch fresh camp data
        const resp = await fetch(`/api/camps/${campId}/data`, { credentials: 'same-origin' });
        if (!resp.ok) throw new Error('Failed to fetch camp data');
        const data = await resp.json();

        // 2. Save to IndexedDB
        await saveCampData(campId, data);

        // 3. Ask service worker to cache the evaluation pages
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            const evalUrl = `/camps/${campId}/evaluate`;
            const testUrl = `/camps/${campId}/test-physique`;
            const resultsUrl = `/camps/${campId}/test-physique-results`;
            navigator.serviceWorker.controller.postMessage({
                type: 'CACHE_PAGE',
                url: evalUrl
            });
            navigator.serviceWorker.controller.postMessage({
                type: 'CACHE_PAGE',
                url: testUrl
            });
            navigator.serviceWorker.controller.postMessage({
                type: 'CACHE_PAGE',
                url: resultsUrl
            });
        }

        return { ok: true };
    } catch (e) {
        console.error('prepareCampOffline error:', e);
        return { ok: false, error: e.message };
    }
}

// ============================================================
// Sync queued evaluations (on reconnect)
// ============================================================
async function syncQueuedEvaluations() {
    const queued = await getQueuedEvaluations();
    if (queued.length === 0) return { synced: 0 };

    let totalSynced = 0;
    const errors = [];

    for (const entry of queued) {
        try {
            const resp = await fetch(`/api/camps/${entry.campId}/evaluations`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ evaluations: entry.evaluations }),
                credentials: 'same-origin',
            });
            if (resp.ok) {
                totalSynced += entry.evaluations.length;
            } else {
                errors.push({ campId: entry.campId, status: resp.status });
            }
        } catch (e) {
            errors.push({ campId: entry.campId, error: e.message });
        }
    }

    if (errors.length === 0) {
        await clearEvalQueue();
    }

    return { synced: totalSynced, errors };
}

// Export for global use
window.OfflineManager = {
    saveCampData,
    getCampData,
    queueEvaluations,
    getQueuedEvaluations,
    clearEvalQueue,
    prepareCampOffline,
    syncQueuedEvaluations
};
