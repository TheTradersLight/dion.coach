<?php
declare(strict_types=1);

use App\Database\CampRepository;
use App\Database\EvaluatorRepository;

$campId = (int)($_GET['camp_id'] ?? 0);
$camp = $campId > 0 ? CampRepository::findById($campId) : null;
$userId = (int)($_SESSION['user_id'] ?? 0);
$isOwner = $camp && (int)$camp['created_by'] === $userId;
$isEvaluator = $camp && !$isOwner && EvaluatorRepository::isEvaluator($campId, $userId);
$isAuth = $camp && ($isOwner || $isEvaluator);

if (!$camp) {
    header('Location: /camps');
    exit;
}
?>
<!doctype html>
<html lang="fr">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<link href="/css/camps.css" rel="stylesheet">
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<main class="container-fluid py-3 flex-grow-1" id="app">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <a href="/camps/<?= $campId ?>" class="text-muted small text-decoration-none">&larr; Retour au camp</a>
            <h5 class="mb-0 mt-1" id="campName"></h5>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span id="offlineBanner" class="badge bg-warning text-dark d-none">Mode hors-ligne (auth expirée)</span>
            <span id="syncStatus" class="badge bg-secondary">Chargement...</span>
            <button class="btn btn-outline-light btn-sm d-none" id="btnResults" onclick="showResults()">Résultats</button>
            <button class="btn btn-outline-light btn-sm" id="btnSync" onclick="syncToServer()">Synchroniser</button>
        </div>
    </div>

    <div class="offline-panel mb-3" id="offlinePanel">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <div class="fw-semibold">Mode hors-ligne</div>
                <div class="small text-muted" id="offlineStatus">Préparez ce camp avant d'aller au gymnase.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary" id="offlineAge">Jamais préparé</span>
                <button class="btn btn-outline-secondary btn-sm" id="btnOffline" onclick="prepareOffline(<?= $campId ?>)">
                    Préparer pour hors-ligne
                </button>
            </div>
        </div>
    </div>

    <!-- Station selection -->
    <div id="stationSelect" class="mb-4">
        <h6 class="text-muted mb-2">Choisir la station</h6>
        <div id="stationCards" class="row g-3"></div>
    </div>

    <div id="codeGate" class="offline-panel mb-3 d-none">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <div class="fw-semibold">Accès par code</div>
                <div class="small text-muted">Entrez le code fourni par courriel pour accéder aux tests.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <input id="accessCodeInput" class="form-control form-control-sm" placeholder="Code (ex: A7K3)" style="max-width: 160px;">
                <button class="btn btn-outline-light btn-sm" onclick="submitAccessCode()">Valider</button>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-auto">
            <select id="selSession" class="form-select form-select-sm" onchange="onSessionChange()"></select>
        </div>
        <div class="col-auto">
            <select id="selGroup" class="form-select form-select-sm" onchange="onGroupChange()">
                <option value="">Tous les joueurs</option>
            </select>
        </div>
        <div class="col-auto">
            <input id="playerSearch" class="form-control form-control-sm" placeholder="Nom ou # joueur" oninput="onPlayerSearch()" list="playerList">
            <datalist id="playerList"></datalist>
        </div>
        <div class="col-auto ms-auto d-flex align-items-center gap-2">
            <button class="btn btn-outline-light btn-sm" onclick="nextPlayerMissing()">Joueur suivant (incomplet)</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="resetStation()">Changer de station</button>
            <span class="text-muted small" id="progressText"></span>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 mb-3" id="playerNav">
        <button class="btn btn-outline-secondary btn-sm" onclick="prevPlayer()" id="btnPrev">&laquo; PrÃ©c.</button>
        <div class="flex-grow-1">
            <select id="selPlayer" class="form-select form-select-sm" onchange="onPlayerChange()"></select>
        </div>
        <button class="btn btn-outline-secondary btn-sm" onclick="nextPlayer()" id="btnNext">Suiv. &raquo;</button>
    </div>

    <div class="card bg-dark border-secondary mb-3" id="playerCard" style="display:none;">
        <div class="card-body py-2">
            <div class="d-flex align-items-center gap-3">
                <span class="fs-3 fw-bold text-gold" id="playerJersey"></span>
                <div>
                    <div class="fw-semibold" id="playerName"></div>
                    <small class="text-muted" id="playerPosition"></small>
                </div>
                <div class="ms-auto text-end">
                    <div class="fs-6 fw-semibold text-gold" id="playerStatus">Profil physique</div>
                </div>
            </div>
        </div>
    </div>

    <div id="testsGrid"></div>

    <div id="resultsView" style="display:none;">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="mb-0">Résultats (admin)</h5>
            <button class="btn btn-outline-light btn-sm" onclick="hideResults()">Retour</button>
        </div>
        <div id="resultsLoading" class="text-muted">Chargement...</div>
        <div class="table-responsive" id="resultsTableWrap" style="display:none;">
            <table class="table table-dark table-sm table-hover" id="resultsTable">
                <thead id="resultsHead"></thead>
                <tbody id="resultsBody"></tbody>
            </table>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script>
const CAMP_ID = <?= $campId ?>;
const IS_AUTH = <?= $isAuth ? 'true' : 'false' ?>;

const CACHE_DATA_KEY = "dion_test_data_" + CAMP_ID;
const CACHE_DEFS_KEY = "dion_test_defs_" + CAMP_ID;
const CACHE_RESULTS_KEY = "dion_test_results_" + CAMP_ID;
const PENDING_KEY = "dion_test_pending_" + CAMP_ID;
const ACCESS_TOKEN_KEY = "dion_access_token_" + CAMP_ID;
const ACCESS_SCOPE_KEY = "dion_access_scope_" + CAMP_ID;

let state = {
    camp: null,
    sessions: [],
    groups: [],
    players: [],
    testTypes: [],
    selectedTestTypeId: null,
    lockedTestTypeId: null,
    currentSessionId: null,
    currentGroupId: null,
    currentPlayerIndex: 0,
    results: {},
    search: ""
};

let isSyncing = false;
let syncTimer = null;

// ============================================================
// Cache helpers
// ============================================================
function loadCache() {
    const data = localStorage.getItem(CACHE_DATA_KEY);
    if (data) {
        const parsed = JSON.parse(data);
        state.camp = parsed.camp;
        state.sessions = parsed.sessions || [];
        state.groups = parsed.groups || [];
        state.players = parsed.players || [];
    }
    const defs = localStorage.getItem(CACHE_DEFS_KEY);
    if (defs) {
        state.testTypes = JSON.parse(defs) || [];
    }
    const results = localStorage.getItem(CACHE_RESULTS_KEY);
    if (results) {
        state.results = JSON.parse(results) || {};
    }

    const pending = localStorage.getItem(PENDING_KEY);
    if (pending) {
        const pendingMap = JSON.parse(pending);
        for (const [key, val] of Object.entries(pendingMap)) {
            if (val.pendingDelete) {
                delete state.results[key];
            } else {
                state.results[key] = { ...val, synced: false };
            }
        }
    }

    if (!IS_AUTH) {
        const scope = getAccessScope();
        if (scope && scope.test_type_id) {
            state.lockedTestTypeId = scope.test_type_id;
            state.selectedTestTypeId = scope.test_type_id;
        }
    }
}

function saveCache() {
    localStorage.setItem(CACHE_DATA_KEY, JSON.stringify({
        camp: state.camp,
        sessions: state.sessions,
        groups: state.groups,
        players: state.players
    }));
    localStorage.setItem(CACHE_DEFS_KEY, JSON.stringify(state.testTypes));
    localStorage.setItem(CACHE_RESULTS_KEY, JSON.stringify(state.results));
}

function savePending() {
    const pending = {};
    for (const [key, val] of Object.entries(state.results)) {
        if (!val.synced) pending[key] = val;
    }

    const local = localStorage.getItem(PENDING_KEY);
    if (local) {
        const oldPending = JSON.parse(local);
        for (const [key, val] of Object.entries(oldPending)) {
            if (val.pendingDelete && !(key in state.results)) {
                pending[key] = val;
            }
        }
    }

    if (Object.keys(pending).length > 0) {
        localStorage.setItem(PENDING_KEY, JSON.stringify(pending));
    } else {
        localStorage.removeItem(PENDING_KEY);
    }
    saveCache();
    updateSyncStatus();
    scheduleSyncDebounce();
}

// ============================================================
// Key / accessors
// ============================================================
function resultKey(playerId, sessionId, metricId) {
    const sid = sessionId ? sessionId : 0;
    return `${playerId}-${sid}-${metricId}`;
}

function getResult(playerId, sessionId, metricId) {
    return state.results[resultKey(playerId, sessionId, metricId)] || null;
}

function setResult(playerId, sessionId, metricId, valueNumber, valueText) {
    const key = resultKey(playerId, sessionId, metricId);
    state.results[key] = {
        value_number: valueNumber,
        value_text: valueText || null,
        timestamp: new Date().toISOString(),
        synced: false
    };
    savePending();
}

function removeResult(playerId, sessionId, metricId) {
    const key = resultKey(playerId, sessionId, metricId);
    const existing = state.results[key];
    delete state.results[key];

    if (existing && existing.synced) {
        const local = localStorage.getItem(PENDING_KEY);
        const pending = local ? JSON.parse(local) : {};
        pending[key] = { pendingDelete: true, timestamp: new Date().toISOString() };
        localStorage.setItem(PENDING_KEY, JSON.stringify(pending));
    }
    savePending();
}

// ============================================================
// Sync
// ============================================================
function scheduleSyncDebounce() {
    if (syncTimer) clearTimeout(syncTimer);
    syncTimer = setTimeout(syncToServer, 1500);
}

function getPendingCount() {
    const local = localStorage.getItem(PENDING_KEY);
    if (!local) return 0;
    return Object.keys(JSON.parse(local)).length;
}

function updateSyncStatus() {
    const el = document.getElementById("syncStatus");
    const banner = document.getElementById("offlineBanner");
    const pending = getPendingCount();

    if (isSyncing) {
        el.textContent = "Synchronisation...";
        el.className = "badge bg-info";
    } else if (!navigator.onLine) {
        el.textContent = `Hors ligne (${pending} en attente)`;
        el.className = "badge bg-secondary";
        if (banner) banner.classList.remove("d-none");
    } else if (pending > 0) {
        el.textContent = `${pending} en attente`;
        el.className = "badge bg-warning text-dark";
        if (banner) banner.classList.add("d-none");
    } else {
        el.textContent = "Tout synchronisÃ©";
        el.className = "badge bg-success";
        if (banner) banner.classList.add("d-none");
    }
}

async function syncToServer() {
    if (isSyncing || !navigator.onLine) return;

    const local = localStorage.getItem(PENDING_KEY);
    if (!local) {
        updateSyncStatus();
        return;
    }
    const pending = JSON.parse(local);
    const keys = Object.keys(pending);
    if (keys.length === 0) return;

    isSyncing = true;
    updateSyncStatus();

    const bySession = {};
    for (const [key, val] of Object.entries(pending)) {
        const parts = key.split("-");
        const playerId = parseInt(parts[0]);
        const sessionId = parseInt(parts[1]) || null;
        const metricId = parseInt(parts[2]);
        const payload = { player_id: playerId, metric_id: metricId };
        const sKey = sessionId ? String(sessionId) : "null";
        if (!bySession[sKey]) bySession[sKey] = { session_id: sessionId, results: [], deletes: [] };
        if (val.pendingDelete) {
            bySession[sKey].deletes.push(payload);
        } else {
            payload.value_number = val.value_number;
            payload.value_text = val.value_text;
            bySession[sKey].results.push(payload);
        }
    }

    try {
        let allOk = true;
        const token = getAccessToken();
        const url = IS_AUTH ? `/api/camps/${CAMP_ID}/tests/results` : `/api/public/camps/${CAMP_ID}/tests/results`;
        for (const group of Object.values(bySession)) {
            const headers = { "Content-Type": "application/json" };
            if (!IS_AUTH && token) headers["X-Access-Token"] = token;
            const resp = await fetch(url, {
                method: "POST",
                headers,
                body: JSON.stringify({
                    session_id: group.session_id,
                    results: group.results,
                    deletes: group.deletes
                }),
                credentials: "same-origin"
            });
            if (!resp.ok) {
                allOk = false;
                break;
            }
        }

        if (allOk) {
            for (const key of keys) {
                if (state.results[key]) state.results[key].synced = true;
            }
            localStorage.removeItem(PENDING_KEY);
        }
    } catch (e) {
        console.warn("Sync failed:", e);
    }

    isSyncing = false;
    updateSyncStatus();
}

window.addEventListener("online", () => { updateSyncStatus(); syncToServer(); });
window.addEventListener("offline", () => updateSyncStatus());

// ============================================================
// Loaders
// ============================================================
async function fetchCampData() {
    if (!IS_AUTH) throw new Error("not-auth");
    const resp = await fetch(`/api/camps/${CAMP_ID}/data`, { credentials: "same-origin" });
    if (!resp.ok) throw new Error("data");
    const data = await resp.json();
    state.camp = data.camp;
    state.sessions = data.sessions || [];
    state.groups = data.groups || [];
    state.players = data.players || [];
}

async function fetchDefinitions() {
    if (!IS_AUTH) throw new Error("not-auth");
    const resp = await fetch(`/api/camps/${CAMP_ID}/tests/definitions`, { credentials: "same-origin" });
    if (!resp.ok) throw new Error("defs");
    const data = await resp.json();
    state.testTypes = data.test_types || [];
}

async function fetchResultsForGroup() {
    if (!IS_AUTH) return;
    const groupId = state.currentGroupId;
    const params = new URLSearchParams();
    if (groupId) params.set("group_id", String(groupId));
    if (state.currentSessionId) params.set("session_id", String(state.currentSessionId));
    const resp = await fetch(`/api/camps/${CAMP_ID}/tests/results?` + params.toString(), { credentials: "same-origin" });
    if (!resp.ok) return;
    const data = await resp.json();
    const pending = localStorage.getItem(PENDING_KEY);
    const pendingMap = pending ? JSON.parse(pending) : {};

    (data.results || []).forEach(r => {
        const key = resultKey(r.player_id, r.session_id, r.metric_id);
        if (pendingMap[key]) return;
        state.results[key] = {
            value_number: r.value_number !== null ? parseFloat(r.value_number) : null,
            value_text: r.value_text || null,
            timestamp: r.updated_at,
            synced: true
        };
    });
    saveCache();
}

async function fetchPreloadWithToken(token) {
    const resp = await fetch(`/api/public/camps/${CAMP_ID}/test-physique/preload`, {
        headers: { "X-Access-Token": token },
        credentials: "same-origin"
    });
    if (!resp.ok) throw new Error("preload");
    const data = await resp.json();
    state.camp = data.camp;
    state.sessions = data.sessions || [];
    state.groups = data.groups || [];
    state.players = data.players || [];
    state.testTypes = data.test_types || [];
    const scope = { test_type_id: data.test_type_id || null, role: data.role || 'station' };
    localStorage.setItem(ACCESS_SCOPE_KEY, JSON.stringify(scope));
    applyAccessScope();

    const pending = localStorage.getItem(PENDING_KEY);
    const pendingMap = pending ? JSON.parse(pending) : {};
    (data.results || []).forEach(r => {
        const key = resultKey(r.player_id, r.session_id, r.metric_id);
        if (pendingMap[key]) return;
        state.results[key] = {
            value_number: r.value_number !== null ? parseFloat(r.value_number) : null,
            value_text: r.value_text || null,
            timestamp: r.updated_at,
            synced: true
        };
    });
}

async function submitAccessCode() {
    const input = document.getElementById("accessCodeInput");
    const code = (input ? input.value : "").trim().toUpperCase();
    if (code === "") return;

    try {
        const resp = await fetch(`/api/public/camps/${CAMP_ID}/access-code`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ code }),
            credentials: "same-origin"
        });
        if (!resp.ok) throw new Error("invalid");
        const data = await resp.json();
        localStorage.setItem(ACCESS_TOKEN_KEY, data.token);
        localStorage.setItem(ACCESS_SCOPE_KEY, JSON.stringify({ test_type_id: data.test_type_id || null, role: data.role || 'station' }));
        document.getElementById("codeGate").classList.add("d-none");
        await fetchPreloadWithToken(data.token);
        saveCache();
        initUI();
    } catch (e) {
        const status = document.getElementById("offlineStatus");
        if (status) {
            status.textContent = "Code invalide ou expirÃ©.";
            status.className = "small text-danger";
        }
    }
}

// ============================================================
// UI
// ============================================================
function initUI() {
    document.getElementById("campName").textContent = state.camp ? state.camp.name : "Camp";

    renderStationCards();

    const selSession = document.getElementById("selSession");
    selSession.innerHTML = "";
    const optNone = document.createElement("option");
    optNone.value = "";
    optNone.textContent = "Aucune sÃ©ance";
    selSession.appendChild(optNone);
    state.sessions.forEach(s => {
        const opt = document.createElement("option");
        opt.value = s.id;
        opt.textContent = s.name;
        selSession.appendChild(opt);
    });
    const urlSession = new URLSearchParams(window.location.search).get("session");
    if (urlSession) {
        state.currentSessionId = parseInt(urlSession);
    } else if (state.sessions.length > 0) {
        state.currentSessionId = parseInt(state.sessions[0].id);
    } else {
        state.currentSessionId = null;
    }
    selSession.value = state.currentSessionId || "";

    const selGroup = document.getElementById("selGroup");
    selGroup.innerHTML = '<option value="">Tous les joueurs</option>';
    state.groups.forEach(g => {
        const opt = document.createElement("option");
        opt.value = g.id;
        opt.textContent = g.name;
        selGroup.appendChild(opt);
    });

    filterPlayers();
    renderTests();
    updateSyncStatus();
}

function filterPlayers() {
    const groupId = state.currentGroupId;
    const players = state.players.filter(p => {
        if (p.status !== "active") return false;
        if (groupId && p.group_id !== groupId) return false;
        if (state.search) {
            const needle = state.search.toLowerCase();
            const full = `${p.first_name} ${p.last_name}`.toLowerCase();
            const jersey = String(p.jersey_number || "").toLowerCase();
            if (!full.includes(needle) && !jersey.includes(needle)) return false;
        }
        return true;
    });
    state.filteredPlayers = players;

    const selPlayer = document.getElementById("selPlayer");
    selPlayer.innerHTML = "";
    players.forEach((p, idx) => {
        const opt = document.createElement("option");
        opt.value = idx;
        opt.textContent = `#${p.jersey_number || '?'} ${p.first_name} ${p.last_name}`;
        selPlayer.appendChild(opt);
    });
    if (state.currentPlayerIndex >= players.length) state.currentPlayerIndex = 0;
    selPlayer.value = state.currentPlayerIndex;
    showCurrentPlayer();
    updateProgress();
    updatePlayerDatalist();
}

function showCurrentPlayer() {
    const player = state.filteredPlayers[state.currentPlayerIndex];
    const card = document.getElementById("playerCard");
    if (!player) {
        card.style.display = "none";
        document.getElementById("testsGrid").innerHTML = '<p class="text-muted">Aucun joueur dans ce groupe.</p>';
        return;
    }
    card.style.display = "block";
    document.getElementById("playerJersey").textContent = "#" + (player.jersey_number || '?');
    document.getElementById("playerName").textContent = player.first_name + " " + player.last_name;
    document.getElementById("playerPosition").textContent = player.position || '';

    document.getElementById("btnPrev").disabled = state.currentPlayerIndex === 0;
    document.getElementById("btnNext").disabled = state.currentPlayerIndex === state.filteredPlayers.length - 1;

    renderTests();
}

function onSessionChange() {
    const val = document.getElementById("selSession").value;
    state.currentSessionId = val ? parseInt(val) : null;
    fetchResultsForGroup();
    renderTests();
}

function onGroupChange() {
    const val = document.getElementById("selGroup").value;
    state.currentGroupId = val ? parseInt(val) : null;
    state.currentPlayerIndex = 0;
    filterPlayers();
    fetchResultsForGroup();
}

function onPlayerChange() {
    state.currentPlayerIndex = parseInt(document.getElementById("selPlayer").value);
    showCurrentPlayer();
}

function prevPlayer() {
    if (state.currentPlayerIndex > 0) {
        state.currentPlayerIndex--;
        document.getElementById("selPlayer").value = state.currentPlayerIndex;
        showCurrentPlayer();
    }
}

function nextPlayer() {
    if (state.currentPlayerIndex < state.filteredPlayers.length - 1) {
        state.currentPlayerIndex++;
        document.getElementById("selPlayer").value = state.currentPlayerIndex;
        showCurrentPlayer();
    }
}

function updateProgress() {
    const total = state.filteredPlayers.length;
    const station = state.testTypes.find(t => t.id === state.selectedTestTypeId);
    const stationName = station ? station.name : "Aucune station";
    const completed = station ? countCompletedForStation(station.id) : 0;
    document.getElementById("progressText").textContent = `${stationName} â€¢ ${completed}/${total} complétés`;
}

function getAccessToken() {
    return localStorage.getItem(ACCESS_TOKEN_KEY) || "";
}

function getAccessScope() {
    const raw = localStorage.getItem(ACCESS_SCOPE_KEY);
    try { return raw ? JSON.parse(raw) : null; } catch { return null; }
}

function isAdminAccess() {
    const scope = getAccessScope();
    return !!(scope && scope.role === 'admin');
}

function onPlayerSearch() {
    state.search = (document.getElementById("playerSearch").value || "").trim();
    state.currentPlayerIndex = 0;
    filterPlayers();
    if (state.filteredPlayers.length === 1) {
        state.currentPlayerIndex = 0;
        document.getElementById("selPlayer").value = 0;
        showCurrentPlayer();
    }
}

function updatePlayerDatalist() {
    const list = document.getElementById("playerList");
    if (!list) return;
    list.innerHTML = "";
    state.filteredPlayers.forEach(p => {
        const opt = document.createElement("option");
        const jersey = p.jersey_number ? `#${p.jersey_number} ` : "";
        opt.value = `${jersey}${p.first_name} ${p.last_name}`.trim();
        list.appendChild(opt);
    });
}

function renderStationCards() {
    const wrap = document.getElementById("stationCards");
    const types = state.testTypes.slice().sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
    wrap.innerHTML = "";
    types.forEach(tt => {
        const col = document.createElement("div");
        col.className = "col-12 col-sm-6 col-lg-3";
        col.innerHTML = `
            <div class="card station-card h-100" onclick="selectStation(${tt.id})">
                <div class="card-body">
                    <div class="fw-semibold">${escHtml(tt.name)}</div>
                    <div class="text-muted small mt-1">${escHtml(tt.description || "")}</div>
                </div>
            </div>
        `;
        wrap.appendChild(col);
    });
}

function applyAccessScope() {
    const scope = getAccessScope();
    if (scope && scope.test_type_id) {
        state.lockedTestTypeId = scope.test_type_id;
        state.selectedTestTypeId = scope.test_type_id;
        document.getElementById("stationSelect").style.display = "none";
    }
    const btnResults = document.getElementById("btnResults");
    const btnSync = document.getElementById("btnSync");
    if (btnResults) {
        btnResults.classList.toggle("d-none", !isAdminAccess());
    }
    if (btnSync) {
        btnSync.classList.toggle("d-none", isAdminAccess());
    }
}

function selectStation(testTypeId) {
    state.selectedTestTypeId = testTypeId;
    document.getElementById("stationSelect").style.display = "none";
    renderTests();
    updateProgress();
}

function resetStation() {
    if (state.lockedTestTypeId) return;
    state.selectedTestTypeId = null;
    document.getElementById("stationSelect").style.display = "block";
    renderTests();
    updateProgress();
}

function isPlayerCompleteForStation(playerId, testTypeId) {
    const testType = state.testTypes.find(t => t.id === testTypeId);
    if (!testType) return false;
    const required = (testType.metrics || []).filter(m => !m.is_output);
    if (required.length === 0) return false;
    return required.every(m => {
        const res = getResult(playerId, state.currentSessionId, m.id);
        if (!res) return false;
        if (m.value_type === "text") return !!(res.value_text && res.value_text !== "");
        return res.value_number !== null && res.value_number !== undefined;
    });
}

function countCompletedForStation(testTypeId) {
    let count = 0;
    state.filteredPlayers.forEach(p => {
        if (isPlayerCompleteForStation(p.player_id, testTypeId)) count++;
    });
    return count;
}

function nextPlayerMissing() {
    if (!state.selectedTestTypeId) return;
    const total = state.filteredPlayers.length;
    if (total === 0) return;
    const start = state.currentPlayerIndex + 1;
    for (let i = 0; i < total; i++) {
        const idx = (start + i) % total;
        const p = state.filteredPlayers[idx];
        if (!isPlayerCompleteForStation(p.player_id, state.selectedTestTypeId)) {
            state.currentPlayerIndex = idx;
            document.getElementById("selPlayer").value = idx;
            showCurrentPlayer();
            return;
        }
    }
}

// ============================================================
// Rendering
// ============================================================
function renderTests() {
    const grid = document.getElementById("testsGrid");
    const player = state.filteredPlayers[state.currentPlayerIndex];
    if (!player) {
        grid.innerHTML = '<p class="text-muted">Aucun joueur dans ce groupe.</p>';
        return;
    }

    if (!state.selectedTestTypeId) {
        grid.innerHTML = '<p class="text-muted">Choisir une station pour commencer.</p>';
        return;
    }

    let html = "";
    state.testTypes.filter(t => t.id === state.selectedTestTypeId).forEach(tt => {
        html += `<div class="test-card mb-3">`;
        html += `<div class="test-card-header">${escHtml(tt.name)}<span class="text-muted small ms-2">${escHtml(tt.description || "")}</span></div>`;
        html += `<div class="test-card-body">`;

        (tt.metrics || []).forEach(m => {
            const res = getResult(player.player_id, state.currentSessionId, m.id);
            const value = m.value_type === "text" ? (res ? (res.value_text || "") : "") : (res && res.value_number !== null ? res.value_number : "");
            const isOutput = m.is_output;
            const hasOptions = m.options && m.options.length > 0;

            html += `<div class="test-row" data-metric="${m.id}">`;
            html += `<div class="test-label">${escHtml(m.label)}</div>`;
            html += `<div class="test-input">`;

            if (hasOptions) {
                html += `<select class="form-select form-select-sm test-input-field test-input-lg"
                            data-metric-id="${m.id}"
                            data-test-type-id="${tt.id}"
                            data-value-type="${m.value_type}"
                            data-is-output="${isOutput ? 1 : 0}"
                            ${isOutput ? "disabled" : ""}>
                            <option value=""></option>`;
                m.options.forEach(opt => {
                    const sel = value === opt.value ? "selected" : "";
                    html += `<option value="${escHtml(opt.value)}" ${sel}>${escHtml(opt.label)}</option>`;
                });
                html += `</select>`;
            } else {
                const typeAttr = m.value_type === "text" ? "text" : "number";
                const step = m.value_type === "integer" ? "1" : "0.01";
                const inputMode = m.value_type === "integer" ? "numeric" : "decimal";
                html += `<input class="form-control form-control-sm test-input-field test-input-lg" type="${typeAttr}"
                            data-metric-id="${m.id}"
                            data-test-type-id="${tt.id}"
                            data-value-type="${m.value_type}"
                            data-is-output="${isOutput ? 1 : 0}"
                            ${isOutput ? "disabled" : ""}
                            ${typeAttr === "number" ? 'inputmode="' + inputMode + '"' : ""}
                            step="${step}"
                            value="${escAttr(String(value))}">`;
            }

            html += `</div>`;
            html += `<div class="test-unit">${escHtml(m.unit || "")}</div>`;
            html += `<div class="test-actions">`;
            html += isOutput ? "" : `<button class="btn btn-outline-secondary btn-sm" onclick="clearMetric(${m.id})">Effacer</button>`;
            html += `</div>`;
            html += `</div>`;
        });

        html += `</div></div>`;
    });

    grid.innerHTML = html;

    document.querySelectorAll(".test-input-field").forEach(el => {
        el.addEventListener("change", onMetricChange);
    });
}

function showResults() {
    document.getElementById("testsGrid").style.display = "none";
    document.getElementById("playerCard").style.display = "none";
    document.getElementById("playerNav").style.display = "none";
    document.getElementById("resultsView").style.display = "block";
    loadResultsAdmin();
}

function hideResults() {
    document.getElementById("testsGrid").style.display = "block";
    document.getElementById("playerCard").style.display = "block";
    document.getElementById("playerNav").style.display = "flex";
    document.getElementById("resultsView").style.display = "none";
}

async function loadResultsAdmin() {
    const loading = document.getElementById("resultsLoading");
    const wrap = document.getElementById("resultsTableWrap");
    loading.style.display = "block";
    wrap.style.display = "none";

    try {
        const token = getAccessToken();
        const params = new URLSearchParams();
        if (state.selectedTestTypeId) params.set("test_type_id", String(state.selectedTestTypeId));
        const url = `/api/public/camps/${CAMP_ID}/tests/results?` + params.toString();
        const resp = await fetch(url, {
            headers: token ? { "X-Access-Token": token } : {},
            credentials: "same-origin"
        });
        if (!resp.ok) throw new Error("api");
        const data = await resp.json();
        renderResultsTable(data.results || []);
    } catch (e) {
        renderResultsTable([]);
    }

    loading.style.display = "none";
    wrap.style.display = "block";
}

function renderResultsTable(rows) {
    const testType = state.testTypes.find(t => t.id === state.selectedTestTypeId);
    if (!testType) {
        document.getElementById("resultsHead").innerHTML = "";
        document.getElementById("resultsBody").innerHTML = "<tr><td class='text-muted'>Choisir une station.</td></tr>";
        return;
    }
    const metrics = (testType.metrics || []);
    const players = state.players.filter(p => p.status === "active");

    const map = {};
    rows.forEach(r => {
        const key = `${r.player_id}-${r.metric_id}`;
        map[key] = r;
    });

    let headHtml = "<tr><th>#</th><th>Joueur</th>";
    metrics.forEach(m => {
        headHtml += `<th class="text-center">${escHtml(m.label)}</th>`;
    });
    headHtml += "</tr>";
    document.getElementById("resultsHead").innerHTML = headHtml;

    let bodyHtml = "";
    players.forEach(p => {
        bodyHtml += "<tr>";
        bodyHtml += `<td>${escHtml(p.jersey_number || '')}</td>`;
        bodyHtml += `<td>${escHtml(p.first_name + ' ' + p.last_name)}</td>`;
        metrics.forEach(m => {
            const r = map[`${p.player_id}-${m.id}`];
            let v = '';
            if (r) {
                v = r.value_text !== null && r.value_text !== undefined ? r.value_text : r.value_number;
            }
            bodyHtml += `<td class="text-center">${escHtml(String(v || ''))}</td>`;
        });
        bodyHtml += "</tr>";
    });
    document.getElementById("resultsBody").innerHTML = bodyHtml;
}

function onMetricChange(e) {
    const el = e.target;
    if (!el) return;
    if (el.dataset.isOutput === "1") return;

    const player = state.filteredPlayers[state.currentPlayerIndex];
    if (!player) return;

    const metricId = parseInt(el.dataset.metricId);
    const testTypeId = parseInt(el.dataset.testTypeId);
    const valueType = el.dataset.valueType;
    let valueNumber = null;
    let valueText = null;

    if (valueType === "text") {
        valueText = el.value.trim();
        if (valueText === "") {
            removeResult(player.player_id, state.currentSessionId, metricId);
            recomputeOutputs(testTypeId, player.player_id);
            return;
        }
    } else {
        if (el.value === "") {
            removeResult(player.player_id, state.currentSessionId, metricId);
            recomputeOutputs(testTypeId, player.player_id);
            return;
        }
        valueNumber = parseFloat(el.value);
        if (isNaN(valueNumber)) return;
        if (valueType === "integer") valueNumber = Math.round(valueNumber);
    }

    setResult(player.player_id, state.currentSessionId, metricId, valueNumber, valueText);
    recomputeOutputs(testTypeId, player.player_id);
    updateProgress();
}

function clearMetric(metricId) {
    const player = state.filteredPlayers[state.currentPlayerIndex];
    if (!player) return;
    removeResult(player.player_id, state.currentSessionId, metricId);
    renderTests();
    updateProgress();
}

function recomputeOutputs(testTypeId, playerId) {
    const testType = state.testTypes.find(t => t.id === testTypeId);
    if (!testType) return;

    const inputs = (testType.metrics || []).filter(m => !m.is_output);
    const outputs = (testType.metrics || []).filter(m => m.is_output);
    if (outputs.length === 0) return;

    const values = [];
    inputs.forEach(m => {
        if (m.value_type === "text") return;
        const res = getResult(playerId, state.currentSessionId, m.id);
        if (res && res.value_number !== null && !isNaN(res.value_number)) {
            values.push(res.value_number);
        }
    });

    outputs.forEach(out => {
        if (values.length === 0) {
            removeResult(playerId, state.currentSessionId, out.id);
            updateOutputField(out.id, "");
            return;
        }
        let val = values[0];
        if (out.calc_rule === "min") {
            val = Math.min(...values);
        } else if (out.calc_rule === "max") {
            val = Math.max(...values);
        }
        setResult(playerId, state.currentSessionId, out.id, val, null);
        updateOutputField(out.id, String(val));
    });
}

function updateOutputField(metricId, value) {
    document.querySelectorAll(`[data-metric-id="${metricId}"]`).forEach(el => {
        if (el.tagName === "SELECT") {
            el.value = value;
        } else {
            el.value = value;
        }
    });
}

// ============================================================
// Utils
// ============================================================
function escHtml(str) {
    const div = document.createElement("div");
    div.textContent = str || "";
    return div.innerHTML;
}

function escAttr(str) {
    return (str || "").replace(/"/g, "&quot;");
}

// ============================================================
// Init
// ============================================================
async function init() {
    loadCache();
    applyAccessScope();
    if (!IS_AUTH && getAccessToken()) {
        document.getElementById("codeGate").classList.add("d-none");
    } else if (!IS_AUTH) {
        document.getElementById("codeGate").classList.remove("d-none");
    }
    if (state.camp && state.testTypes.length > 0) {
        initUI();
    }

    try {
        if (IS_AUTH) {
            await fetchCampData();
            await fetchDefinitions();
            saveCache();
            initUI();
            await fetchResultsForGroup();
        } else {
            const token = getAccessToken();
            if (token) {
                await fetchPreloadWithToken(token);
                saveCache();
                initUI();
            }
        }
    } catch (e) {
        if (!state.camp) {
            document.getElementById("testsGrid").innerHTML = '<p class="text-muted">Impossible de charger les donnÃ©es hors-ligne. Connectez-vous et prÃ©parez le camp.</p>';
        }
    }

    updateSyncStatus();
    updateOfflineAge();

    const urlCode = new URLSearchParams(window.location.search).get("code");
    if (!IS_AUTH && urlCode && !getAccessToken()) {
        const input = document.getElementById("accessCodeInput");
        if (input) {
            input.value = urlCode.toUpperCase();
            submitAccessCode();
        }
    }
}

document.addEventListener("DOMContentLoaded", init);

async function prepareOffline(campId) {
    const btn = document.getElementById('btnOffline');
    const status = document.getElementById('offlineStatus');
    if (!btn || !status) return;
    btn.disabled = true;
    status.textContent = 'Préparation en cours...';
    status.className = 'small text-info';

    try {
        if (!IS_AUTH) {
            const token = getAccessToken();
            if (!token) throw new Error('Code requis');
            await fetchPreloadWithToken(token);
            saveCache();
            if (window.OfflineManager && navigator.serviceWorker && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({
                    type: 'CACHE_PAGE',
                    url: `/camps/${campId}/test-physique`
                });
            }
            status.textContent = 'Camp prêt pour utilisation hors-ligne!';
            status.className = 'small text-success';
            localStorage.setItem("dion_test_offline_prepared_" + campId, new Date().toISOString());
            updateOfflineAge();
        } else {
            const result = await window.OfflineManager.prepareCampOffline(campId);
            if (result.ok) {
                status.textContent = 'Camp prêt pour utilisation hors-ligne!';
                status.className = 'small text-success';
                localStorage.setItem("dion_test_offline_prepared_" + campId, new Date().toISOString());
                updateOfflineAge();
            } else {
                status.textContent = 'Erreur: ' + (result.error || 'Échec de la préparation');
                status.className = 'small text-danger';
            }
        }
    } catch (e) {
        status.textContent = 'Erreur: ' + (e.message || 'Échec de la préparation');
        status.className = 'small text-danger';
    }
    btn.disabled = false;
}

function updateOfflineAge() {
    const badge = document.getElementById('offlineAge');
    if (!badge) return;
    const key = "dion_test_offline_prepared_" + CAMP_ID;
    const iso = localStorage.getItem(key);
    const hasCache = !!(state.camp && state.testTypes && state.testTypes.length > 0);
    if (!hasCache) {
        badge.textContent = 'Cache manquant';
        badge.className = 'badge bg-danger';
        return;
    }
    if (!iso) {
        badge.textContent = 'Jamais préparé';
        badge.className = 'badge bg-secondary';
        return;
    }
    const then = new Date(iso);
    const now = new Date();
    const diffMs = Math.max(0, now - then);
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    const diffDays = Math.floor(diffHours / 24);

    let label = '';
    if (diffDays >= 1) {
        label = `Préparé il y a ${diffDays} j`;
    } else if (diffHours >= 1) {
        label = `Préparé il y a ${diffHours} h`;
    } else {
        label = 'Préparé récemment';
    }
    badge.textContent = label;
    badge.className = diffDays >= 3 ? 'badge bg-warning text-dark' : 'badge bg-success';
}
</script>
</body>
</html>
