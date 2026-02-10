<?php
declare(strict_types=1);

use App\Database\CampRepository;
use App\Database\EvaluatorRepository;
use App\Database\SkillRepository;
use App\Database\Database;

$campId = (int)($_GET['camp_id'] ?? 0);
$camp = $campId > 0 ? CampRepository::findById($campId) : null;
$userId = (int)($_SESSION['user_id'] ?? 0);
$isOwner = $camp && (int)$camp['created_by'] === $userId;
$isEvaluator = $camp && !$isOwner && EvaluatorRepository::isEvaluator($campId, $userId);

if (!$camp || (!$isOwner && !$isEvaluator)) {
    header('Location: /camps');
    exit;
}

// Build CAMP_DATA payload (injected into JS)
$sessions = Database::fetchAll(
    "SELECT id, name, session_date, session_order FROM camp_sessions WHERE camp_id = ? ORDER BY session_order ASC, id ASC",
    [$campId]
);

$groups = Database::fetchAll(
    "SELECT id, name, color, sort_order FROM camp_groups WHERE camp_id = ? ORDER BY sort_order ASC, id ASC",
    [$campId]
);

$players = Database::fetchAll(
    "SELECT cp.id AS camp_player_id, p.id AS player_id, p.first_name, p.last_name,
            p.jersey_number, p.position, cp.status
     FROM camp_players cp
     JOIN players p ON cp.player_id = p.id
     WHERE cp.camp_id = ?
     ORDER BY p.last_name ASC, p.first_name ASC",
    [$campId]
);

$groupPlayers = Database::fetchAll(
    "SELECT gp.camp_player_id, gp.group_id
     FROM group_players gp
     JOIN camp_groups cg ON gp.group_id = cg.id
     WHERE cg.camp_id = ?",
    [$campId]
);
$gpMap = [];
foreach ($groupPlayers as $gp) {
    $gpMap[(int)$gp['camp_player_id']] = (int)$gp['group_id'];
}
foreach ($players as &$p) {
    $p['group_id'] = $gpMap[(int)$p['camp_player_id']] ?? null;
}
unset($p);

$skillCategories = SkillRepository::getCategoriesWithSkills($campId);

// Load existing evaluations for this user
$evals = Database::fetchAll(
    "SELECT e.session_id, e.camp_player_id, e.skill_id, e.rating, e.comment, e.evaluated_at
     FROM evaluations e
     JOIN camp_sessions cs ON e.session_id = cs.id
     WHERE cs.camp_id = ? AND e.evaluated_by = ?",
    [$campId, $userId]
);
$evalMap = [];
foreach ($evals as $e) {
    $key = $e['session_id'] . '-' . $e['camp_player_id'] . '-' . $e['skill_id'];
    $evalMap[$key] = [
        'rating' => (int)$e['rating'],
        'comment' => $e['comment'] ?? '',
        'timestamp' => $e['evaluated_at'],
        'synced' => true,
    ];
}

$campDataJson = json_encode([
    'camp' => [
        'id' => (int)$camp['id'],
        'name' => $camp['name'],
        'sport' => $camp['sport'],
        'rating_min' => (int)$camp['rating_min'],
        'rating_max' => (int)$camp['rating_max'],
        'eval_mode' => $camp['eval_mode'],
    ],
    'sessions' => $sessions,
    'groups' => $groups,
    'players' => $players,
    'skillCategories' => $skillCategories,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$evalJson = json_encode($evalMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<?php include __DIR__ . '/../../includes/head.php'; ?>
<link href="/css/camps.css" rel="stylesheet">
<body>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<div class="container-fluid py-3" id="app">
    <!-- Header bar -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <a href="/camps/<?= $campId ?>" class="text-muted small text-decoration-none">&larr; Retour au camp</a>
            <h5 class="mb-0 mt-1" id="campName"></h5>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span id="syncStatus" class="badge bg-secondary">Chargement...</span>
            <button class="btn btn-outline-light btn-sm" onclick="showResults()">Résultats</button>
        </div>
    </div>

    <!-- Filters -->
    <div class="row g-2 mb-3">
        <div class="col-auto">
            <select id="selSession" class="form-select form-select-sm" onchange="onSessionChange()">
            </select>
        </div>
        <div class="col-auto">
            <select id="selGroup" class="form-select form-select-sm" onchange="onGroupChange()">
                <option value="">Tous les joueurs</option>
            </select>
        </div>
        <div class="col-auto ms-auto">
            <span class="text-muted small" id="progressText"></span>
        </div>
    </div>

    <!-- Player navigation -->
    <div class="d-flex align-items-center gap-2 mb-3" id="playerNav">
        <button class="btn btn-outline-secondary btn-sm" onclick="prevPlayer()" id="btnPrev">&laquo; Préc.</button>
        <div class="flex-grow-1">
            <select id="selPlayer" class="form-select form-select-sm" onchange="onPlayerChange()">
            </select>
        </div>
        <button class="btn btn-outline-secondary btn-sm" onclick="nextPlayer()" id="btnNext">Suiv. &raquo;</button>
    </div>

    <!-- Player info card -->
    <div class="card bg-dark border-secondary mb-3" id="playerCard" style="display:none;">
        <div class="card-body py-2">
            <div class="d-flex align-items-center gap-3">
                <span class="fs-3 fw-bold text-gold" id="playerJersey"></span>
                <div>
                    <div class="fw-semibold" id="playerName"></div>
                    <small class="text-muted" id="playerPosition"></small>
                </div>
                <div class="ms-auto text-end">
                    <div class="fs-5 fw-bold text-gold" id="playerTotal">-</div>
                    <small class="text-muted">Score moyen</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Evaluation grid -->
    <div id="evalGrid"></div>

    <!-- Results view (hidden by default) -->
    <div id="resultsView" style="display:none;">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="mb-0">Résultats</h5>
            <button class="btn btn-outline-light btn-sm" onclick="hideResults()">Retour à l'évaluation</button>
        </div>
        <div id="resultsLoading" class="text-muted">Chargement des résultats...</div>
        <div class="table-responsive" id="resultsTableWrap" style="display:none;">
            <table class="table table-dark table-sm table-hover" id="resultsTable">
                <thead id="resultsHead"></thead>
                <tbody id="resultsBody"></tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
// ============================================================
// SERVER DATA (injected by PHP)
// ============================================================
const CAMP_DATA = <?= $campDataJson ?>;
const SERVER_EVALS = <?= $evalJson ?>;
const CAMP_ID = <?= $campId ?>;

// ============================================================
// APPLICATION STATE
// ============================================================
let state = {
    currentSessionId: null,
    currentGroupId: null,
    currentPlayerIndex: 0,
    filteredPlayers: [],
    evaluations: {}  // key: "sessionId-campPlayerId-skillId" -> { rating, comment, timestamp, synced, pendingDelete }
};

const STORAGE_KEY = "dion_camp_evals_" + CAMP_ID;
const PENDING_KEY = "dion_camp_pending_" + CAMP_ID;
let syncTimer = null;
let isSyncing = false;

// ============================================================
// LOCAL STORAGE (write-through buffer)
// ============================================================
function loadEvaluations() {
    // Start with server data
    state.evaluations = {};
    for (const [key, val] of Object.entries(SERVER_EVALS)) {
        state.evaluations[key] = { ...val, synced: true };
    }

    // Overlay local pending changes
    const local = localStorage.getItem(PENDING_KEY);
    if (local) {
        const pending = JSON.parse(local);
        for (const [key, val] of Object.entries(pending)) {
            if (val.pendingDelete) {
                // Pending deletion — remove from evaluations but keep in pending
                delete state.evaluations[key];
            } else {
                state.evaluations[key] = { ...val, synced: false };
            }
        }
    }
}

function savePending() {
    const pending = {};
    for (const [key, val] of Object.entries(state.evaluations)) {
        if (!val.synced) {
            pending[key] = val;
        }
    }

    // Also keep pending deletes from localStorage
    const local = localStorage.getItem(PENDING_KEY);
    if (local) {
        const oldPending = JSON.parse(local);
        for (const [key, val] of Object.entries(oldPending)) {
            if (val.pendingDelete && !(key in state.evaluations)) {
                pending[key] = val;
            }
        }
    }

    if (Object.keys(pending).length > 0) {
        localStorage.setItem(PENDING_KEY, JSON.stringify(pending));
    } else {
        localStorage.removeItem(PENDING_KEY);
    }
    updateSyncStatus();
    scheduleSyncDebounce();
}

function evalKey(sessionId, campPlayerId, skillId) {
    return `${sessionId}-${campPlayerId}-${skillId}`;
}

function getEval(sessionId, campPlayerId, skillId) {
    return state.evaluations[evalKey(sessionId, campPlayerId, skillId)] || null;
}

function setEval(sessionId, campPlayerId, skillId, rating, comment) {
    const key = evalKey(sessionId, campPlayerId, skillId);
    state.evaluations[key] = {
        rating: rating,
        comment: comment || "",
        timestamp: new Date().toISOString(),
        synced: false
    };
    savePending();
}

function removeEval(sessionId, campPlayerId, skillId) {
    const key = evalKey(sessionId, campPlayerId, skillId);
    const existing = state.evaluations[key];
    delete state.evaluations[key];

    // If it was synced, we need to mark it as pending delete
    if (existing && existing.synced) {
        const local = localStorage.getItem(PENDING_KEY);
        const pending = local ? JSON.parse(local) : {};
        pending[key] = { pendingDelete: true, timestamp: new Date().toISOString() };
        localStorage.setItem(PENDING_KEY, JSON.stringify(pending));
    }
    savePending();
}

// ============================================================
// SYNC LOGIC
// ============================================================
function scheduleSyncDebounce() {
    if (syncTimer) clearTimeout(syncTimer);
    syncTimer = setTimeout(syncToServer, 2000);
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

    // Split into upserts and deletes
    const evaluations = [];
    const deletes = [];
    for (const [key, val] of Object.entries(pending)) {
        const parts = key.split('-');
        const payload = {
            session_id: parseInt(parts[0]),
            camp_player_id: parseInt(parts[1]),
            skill_id: parseInt(parts[2]),
        };
        if (val.pendingDelete) {
            deletes.push(payload);
        } else {
            payload.rating = val.rating;
            payload.comment = val.comment || "";
            evaluations.push(payload);
        }
    }

    try {
        const resp = await fetch(`/api/camps/${CAMP_ID}/evaluations`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ evaluations, deletes }),
            credentials: 'same-origin',
        });

        if (resp.ok) {
            const data = await resp.json();
            // Mark all synced
            for (const key of keys) {
                if (state.evaluations[key]) {
                    state.evaluations[key].synced = true;
                }
            }
            localStorage.removeItem(PENDING_KEY);
        }
    } catch (e) {
        // Network error — keep pending, will retry
        console.warn('Sync failed:', e);
    }

    isSyncing = false;
    updateSyncStatus();
}

function getPendingCount() {
    const local = localStorage.getItem(PENDING_KEY);
    if (!local) return 0;
    return Object.keys(JSON.parse(local)).length;
}

function updateSyncStatus() {
    const el = document.getElementById("syncStatus");
    const pending = getPendingCount();

    if (isSyncing) {
        el.textContent = "Synchronisation...";
        el.className = "badge bg-info";
    } else if (!navigator.onLine) {
        el.textContent = `Hors ligne (${pending} en attente)`;
        el.className = "badge bg-secondary";
    } else if (pending > 0) {
        el.textContent = `${pending} notes en attente`;
        el.className = "badge bg-warning text-dark";
    } else {
        el.textContent = "Tout synchronisé";
        el.className = "badge bg-success";
    }
}

// Listen for online/offline
window.addEventListener('online', () => { updateSyncStatus(); syncToServer(); });
window.addEventListener('offline', () => { updateSyncStatus(); });

// ============================================================
// INITIALIZATION
// ============================================================
function init() {
    loadEvaluations();
    document.getElementById("campName").textContent = CAMP_DATA.camp.name;

    // Populate sessions
    const selSession = document.getElementById("selSession");
    if (CAMP_DATA.sessions.length === 0) {
        const opt = document.createElement("option");
        opt.value = "";
        opt.textContent = "Aucune séance";
        selSession.appendChild(opt);
    } else {
        CAMP_DATA.sessions.forEach(s => {
            const opt = document.createElement("option");
            opt.value = s.id;
            opt.textContent = s.name;
            selSession.appendChild(opt);
        });
        // Pre-select session from URL ?session=
        const urlSession = new URLSearchParams(window.location.search).get('session');
        const match = urlSession ? CAMP_DATA.sessions.find(s => String(s.id) === urlSession) : null;
        state.currentSessionId = match ? parseInt(match.id) : parseInt(CAMP_DATA.sessions[0].id);
        selSession.value = state.currentSessionId;
    }

    // Populate groups
    const selGroup = document.getElementById("selGroup");
    CAMP_DATA.groups.forEach(g => {
        const opt = document.createElement("option");
        opt.value = g.id;
        opt.textContent = g.name;
        selGroup.appendChild(opt);
    });

    filterPlayers();
    renderEvalGrid();
    updateProgress();
    updateSyncStatus();
}

// ============================================================
// FILTERING AND NAVIGATION
// ============================================================
function filterPlayers() {
    const groupId = state.currentGroupId;
    state.filteredPlayers = CAMP_DATA.players.filter(p => {
        if (p.status !== "active") return false;
        if (groupId && p.group_id !== groupId) return false;
        return true;
    });

    const selPlayer = document.getElementById("selPlayer");
    selPlayer.innerHTML = "";
    state.filteredPlayers.forEach((p, idx) => {
        const opt = document.createElement("option");
        opt.value = idx;
        const evaluated = isPlayerEvaluated(p.camp_player_id);
        opt.textContent = `#${p.jersey_number || '?'} ${p.first_name} ${p.last_name}${evaluated ? " \u2713" : ""}`;
        selPlayer.appendChild(opt);
    });

    if (state.currentPlayerIndex >= state.filteredPlayers.length) {
        state.currentPlayerIndex = 0;
    }
    selPlayer.value = state.currentPlayerIndex;
    showCurrentPlayer();
}

function isPlayerEvaluated(campPlayerId) {
    const allSkills = getAllSkills();
    return allSkills.some(s => getEval(state.currentSessionId, campPlayerId, s.id));
}

function isPlayerFullyEvaluated(campPlayerId) {
    const allSkills = getAllSkills();
    return allSkills.every(s => getEval(state.currentSessionId, campPlayerId, s.id));
}

function getAllSkills() {
    const skills = [];
    CAMP_DATA.skillCategories.forEach(cat => {
        (cat.skills || []).forEach(s => skills.push(s));
        (cat.children || []).forEach(sub => {
            (sub.skills || []).forEach(s => skills.push(s));
        });
    });
    return skills;
}

function onSessionChange() {
    state.currentSessionId = parseInt(document.getElementById("selSession").value);
    renderEvalGrid();
    filterPlayers();
    updateProgress();
}

function onGroupChange() {
    const val = document.getElementById("selGroup").value;
    state.currentGroupId = val ? parseInt(val) : null;
    state.currentPlayerIndex = 0;
    filterPlayers();
    updateProgress();
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

// ============================================================
// PLAYER DISPLAY
// ============================================================
function showCurrentPlayer() {
    const player = state.filteredPlayers[state.currentPlayerIndex];
    if (!player) {
        document.getElementById("playerCard").style.display = "none";
        document.getElementById("evalGrid").innerHTML = '<p class="text-muted">Aucun joueur dans ce groupe.</p>';
        return;
    }

    document.getElementById("playerCard").style.display = "block";
    document.getElementById("playerJersey").textContent = "#" + (player.jersey_number || '?');
    document.getElementById("playerName").textContent = player.first_name + " " + player.last_name;
    document.getElementById("playerPosition").textContent = player.position || '';

    document.getElementById("btnPrev").disabled = state.currentPlayerIndex === 0;
    document.getElementById("btnNext").disabled = state.currentPlayerIndex === state.filteredPlayers.length - 1;

    loadPlayerRatings(player);
    updatePlayerTotal(player);
    updateCategoryHeaders();
}

function updatePlayerTotal(player) {
    const allSkills = getAllSkills();
    let sum = 0, count = 0;
    allSkills.forEach(s => {
        const ev = getEval(state.currentSessionId, player.camp_player_id, s.id);
        if (ev) { sum += ev.rating; count++; }
    });
    const avg = count > 0 ? (sum / count).toFixed(1) : "-";
    document.getElementById("playerTotal").textContent = avg;
}

function loadPlayerRatings(player) {
    const allSkills = getAllSkills();
    allSkills.forEach(skill => {
        const ev = getEval(state.currentSessionId, player.camp_player_id, skill.id);
        const btns = document.querySelectorAll(`[data-skill="${skill.id}"] .rating-btn`);
        btns.forEach(btn => {
            btn.classList.remove("active");
            if (ev && parseInt(btn.dataset.value) === ev.rating) {
                btn.classList.add("active");
            }
        });
        const commentEl = document.querySelector(`[data-skill="${skill.id}"] .skill-comment`);
        if (commentEl) {
            commentEl.value = ev ? ev.comment : "";
        }
    });
}

// ============================================================
// EVALUATION GRID
// ============================================================
function renderSkillsForCategory(skills, min, max) {
    let html = "";
    skills.forEach(skill => {
        html += `<div class="eval-skill-row" data-skill="${skill.id}">`;
        html += `<div class="eval-skill-name">${escHtml(skill.name)}</div>`;
        html += `<div class="eval-skill-buttons">`;
        for (let v = min; v <= max; v++) {
            html += `<button type="button" class="rating-btn" data-value="${v}" onclick="rate(${skill.id}, ${v})">${v}</button>`;
        }
        html += `<button type="button" class="rating-btn rating-clear" onclick="clearRating(${skill.id})" title="Effacer">&times;</button>`;
        html += `</div>`;
        html += `<input type="text" class="form-control form-control-sm skill-comment" placeholder="Note..." onchange="saveComment(${skill.id}, this.value)">`;
        html += `</div>`;
    });
    return html;
}

function renderEvalGrid() {
    const grid = document.getElementById("evalGrid");
    const min = CAMP_DATA.camp.rating_min;
    const max = CAMP_DATA.camp.rating_max;
    let html = "";

    CAMP_DATA.skillCategories.forEach(cat => {
        const collapseId = `cat-collapse-${cat.id}`;
        html += `<div class="eval-category mb-3">`;
        html += `<div class="eval-category-header" data-bs-toggle="collapse" data-bs-target="#${collapseId}" role="button" aria-expanded="false" aria-controls="${collapseId}">`;
        html += `<span class="cat-chevron me-2">&#9654;</span>`;
        html += `<span class="cat-title">${escHtml(cat.name)}</span>`;
        html += `<span class="ms-auto d-flex align-items-center gap-2">`;
        html += `<span class="cat-avg text-muted small" id="cat-avg-${cat.id}">-</span>`;
        html += `<span class="cat-status badge" id="cat-status-${cat.id}"></span>`;
        html += `</span>`;
        html += `</div>`;

        html += `<div class="collapse" id="${collapseId}">`;
        // Skills directly under category
        html += renderSkillsForCategory(cat.skills || [], min, max);

        // Sub-categories
        (cat.children || []).forEach(sub => {
            html += `<div class="ps-3 border-start border-secondary ms-2 mt-2 mb-1">`;
            html += `<div class="text-muted small fw-semibold mb-1">${escHtml(sub.name)}</div>`;
            html += renderSkillsForCategory(sub.skills || [], min, max);
            html += `</div>`;
        });
        html += `</div></div>`;
    });

    grid.innerHTML = html;
    updateCategoryHeaders();
}

function getCategoryAllSkills(cat) {
    const skills = [...(cat.skills || [])];
    (cat.children || []).forEach(sub => {
        (sub.skills || []).forEach(s => skills.push(s));
    });
    return skills;
}

function updateCategoryHeaders() {
    const player = state.filteredPlayers[state.currentPlayerIndex];
    if (!player) return;

    CAMP_DATA.skillCategories.forEach(cat => {
        const catSkills = getCategoryAllSkills(cat);
        let sum = 0, count = 0;
        catSkills.forEach(skill => {
            const ev = getEval(state.currentSessionId, player.camp_player_id, skill.id);
            if (ev) { sum += ev.rating; count++; }
        });

        const avgEl = document.getElementById(`cat-avg-${cat.id}`);
        const statusEl = document.getElementById(`cat-status-${cat.id}`);
        if (!avgEl || !statusEl) return;

        const total = catSkills.length;
        const isComplete = count === total && total > 0;

        avgEl.textContent = count > 0 ? (sum / count).toFixed(1) : "-";
        statusEl.textContent = isComplete ? "Complet" : `${count}/${total}`;
        statusEl.className = `cat-status badge ${isComplete ? "bg-success" : "bg-secondary"}`;
    });
}

function rate(skillId, value) {
    const player = state.filteredPlayers[state.currentPlayerIndex];
    if (!player) return;

    const existing = getEval(state.currentSessionId, player.camp_player_id, skillId);
    const comment = existing ? existing.comment : "";
    setEval(state.currentSessionId, player.camp_player_id, skillId, value, comment);

    const btns = document.querySelectorAll(`[data-skill="${skillId}"] .rating-btn`);
    btns.forEach(btn => {
        btn.classList.remove("active");
        if (parseInt(btn.dataset.value) === value) btn.classList.add("active");
    });

    updatePlayerTotal(player);
    updateProgress();
    updatePlayerSelectLabel(player);
    updateCategoryHeaders();
    flashSaved(skillId);
}

function clearRating(skillId) {
    const player = state.filteredPlayers[state.currentPlayerIndex];
    if (!player) return;

    removeEval(state.currentSessionId, player.camp_player_id, skillId);

    const btns = document.querySelectorAll(`[data-skill="${skillId}"] .rating-btn`);
    btns.forEach(btn => btn.classList.remove("active"));

    updatePlayerTotal(player);
    updateProgress();
    updatePlayerSelectLabel(player);
    updateCategoryHeaders();
}

function saveComment(skillId, comment) {
    const player = state.filteredPlayers[state.currentPlayerIndex];
    if (!player) return;

    const existing = getEval(state.currentSessionId, player.camp_player_id, skillId);
    if (existing) {
        setEval(state.currentSessionId, player.camp_player_id, skillId, existing.rating, comment);
    }
}

function flashSaved(skillId) {
    const row = document.querySelector(`[data-skill="${skillId}"]`);
    if (row) {
        row.classList.add("just-saved");
        setTimeout(() => row.classList.remove("just-saved"), 400);
    }
}

function updatePlayerSelectLabel(player) {
    const selPlayer = document.getElementById("selPlayer");
    const opt = selPlayer.options[state.currentPlayerIndex];
    if (opt) {
        const evaluated = isPlayerEvaluated(player.camp_player_id);
        const full = isPlayerFullyEvaluated(player.camp_player_id);
        let suffix = "";
        if (full) suffix = " \u2713\u2713";
        else if (evaluated) suffix = " \u2713";
        opt.textContent = `#${player.jersey_number || '?'} ${player.first_name} ${player.last_name}${suffix}`;
    }
}

// ============================================================
// PROGRESS
// ============================================================
function updateProgress() {
    const totalPlayers = state.filteredPlayers.length;
    let evaluated = 0;
    state.filteredPlayers.forEach(p => {
        if (isPlayerFullyEvaluated(p.camp_player_id)) evaluated++;
    });
    document.getElementById("progressText").textContent =
        `${evaluated} / ${totalPlayers} joueurs complétés`;
}

// ============================================================
// RESULTS
// ============================================================
function showResults() {
    document.getElementById("evalGrid").style.display = "none";
    document.getElementById("playerCard").style.display = "none";
    document.getElementById("playerNav").style.display = "none";
    document.getElementById("resultsView").style.display = "block";
    loadResultsFromServer();
}

function hideResults() {
    document.getElementById("evalGrid").style.display = "block";
    document.getElementById("playerCard").style.display = "block";
    document.getElementById("playerNav").style.display = "flex";
    document.getElementById("resultsView").style.display = "none";
}

async function loadResultsFromServer() {
    const loading = document.getElementById("resultsLoading");
    const wrap = document.getElementById("resultsTableWrap");
    loading.style.display = "block";
    wrap.style.display = "none";

    try {
        const resp = await fetch(`/api/camps/${CAMP_ID}/results`, { credentials: 'same-origin' });
        if (!resp.ok) throw new Error('API error');
        const data = await resp.json();
        renderResultsFromApi(data);
    } catch (e) {
        // Fallback: use local evaluations
        renderResultsLocal();
    }

    loading.style.display = "none";
    wrap.style.display = "block";
}

function renderResultsFromApi(data) {
    const categories = CAMP_DATA.skillCategories;
    const allSkills = getAllSkills();
    const rMin = data.rating_min;
    const rMax = data.rating_max;

    // Build lookup: key -> avg_rating
    const resultMap = {};
    (data.results || []).forEach(r => {
        const key = `${r.camp_player_id}-${r.skill_id}`;
        if (!resultMap[key]) resultMap[key] = { sum: 0, count: 0 };
        resultMap[key].sum += parseFloat(r.avg_rating);
        resultMap[key].count++;
    });

    // Header
    let headHtml = "<tr><th>Rang</th><th>#</th><th>Joueur</th><th>Pos.</th>";
    categories.forEach(cat => {
        headHtml += `<th class="text-center cat-header">${escHtml(cat.name)}</th>`;
    });
    headHtml += "<th class='text-center'>Moy.</th></tr>";
    document.getElementById("resultsHead").innerHTML = headHtml;

    // Build player scores
    const players = state.currentGroupId
        ? CAMP_DATA.players.filter(p => p.group_id === state.currentGroupId && p.status === "active")
        : CAMP_DATA.players.filter(p => p.status === "active");

    const playerScores = [];
    players.forEach(player => {
        const catAverages = [];
        let totalSum = 0, totalCount = 0;

        categories.forEach(cat => {
            const catSkills = getCategoryAllSkills(cat);
            let catSum = 0, catCount = 0;
            catSkills.forEach(skill => {
                const key = `${player.camp_player_id}-${skill.id}`;
                if (resultMap[key]) {
                    const avg = resultMap[key].sum / resultMap[key].count;
                    catSum += avg;
                    catCount++;
                    totalSum += avg;
                    totalCount++;
                }
            });
            catAverages.push(catCount > 0 ? catSum / catCount : null);
        });

        playerScores.push({
            player,
            catAverages,
            totalAvg: totalCount > 0 ? totalSum / totalCount : null
        });
    });

    renderResultsTable(playerScores, categories, rMin, rMax);
}

function renderResultsLocal() {
    const categories = CAMP_DATA.skillCategories;
    const rMin = CAMP_DATA.camp.rating_min;
    const rMax = CAMP_DATA.camp.rating_max;

    let headHtml = "<tr><th>Rang</th><th>#</th><th>Joueur</th><th>Pos.</th>";
    categories.forEach(cat => {
        headHtml += `<th class="text-center cat-header">${escHtml(cat.name)}</th>`;
    });
    headHtml += "<th class='text-center'>Moy.</th></tr>";
    document.getElementById("resultsHead").innerHTML = headHtml;

    const players = state.currentGroupId
        ? CAMP_DATA.players.filter(p => p.group_id === state.currentGroupId && p.status === "active")
        : CAMP_DATA.players.filter(p => p.status === "active");

    const playerScores = [];
    players.forEach(player => {
        const catAverages = [];
        let totalSum = 0, totalCount = 0;

        categories.forEach(cat => {
            const catSkills = getCategoryAllSkills(cat);
            let catSum = 0, catCount = 0;
            catSkills.forEach(skill => {
                const ev = getEval(state.currentSessionId, player.camp_player_id, skill.id);
                if (ev) {
                    catSum += ev.rating;
                    catCount++;
                    totalSum += ev.rating;
                    totalCount++;
                }
            });
            catAverages.push(catCount > 0 ? catSum / catCount : null);
        });

        playerScores.push({
            player,
            catAverages,
            totalAvg: totalCount > 0 ? totalSum / totalCount : null
        });
    });

    renderResultsTable(playerScores, categories, rMin, rMax);
}

function renderResultsTable(playerScores, categories, rMin, rMax) {
    playerScores.sort((a, b) => {
        if (a.totalAvg === null && b.totalAvg === null) return 0;
        if (a.totalAvg === null) return 1;
        if (b.totalAvg === null) return -1;
        return b.totalAvg - a.totalAvg;
    });

    let bodyHtml = "";
    playerScores.forEach((ps, idx) => {
        const p = ps.player;
        bodyHtml += `<tr>`;
        bodyHtml += `<td class="text-center fw-bold">${ps.totalAvg !== null ? idx + 1 : "-"}</td>`;
        bodyHtml += `<td>${escHtml(p.jersey_number || '?')}</td>`;
        bodyHtml += `<td>${escHtml(p.first_name + ' ' + p.last_name)}</td>`;
        bodyHtml += `<td><small class="text-muted">${escHtml(p.position || '')}</small></td>`;
        ps.catAverages.forEach(avg => {
            if (avg !== null) {
                const pct = ((avg - rMin) / (rMax - rMin)) * 100;
                const color = pct >= 70 ? "text-success" : pct >= 40 ? "text-warning" : "text-danger";
                bodyHtml += `<td class="text-center ${color}">${avg.toFixed(1)}</td>`;
            } else {
                bodyHtml += `<td class="text-center text-muted">-</td>`;
            }
        });
        if (ps.totalAvg !== null) {
            bodyHtml += `<td class="text-center fw-bold text-gold">${ps.totalAvg.toFixed(2)}</td>`;
        } else {
            bodyHtml += `<td class="text-center text-muted">-</td>`;
        }
        bodyHtml += `</tr>`;
    });

    document.getElementById("resultsBody").innerHTML = bodyHtml;
}

// ============================================================
// UTILITY
// ============================================================
function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

// Keyboard nav
document.addEventListener("keydown", function(e) {
    if (e.target.tagName === "INPUT" || e.target.tagName === "TEXTAREA") return;
    if (e.key === "ArrowLeft") prevPlayer();
    if (e.key === "ArrowRight") nextPlayer();
});

// Init
document.addEventListener("DOMContentLoaded", init);

// Backup camp data to IndexedDB for offline fallback
document.addEventListener("DOMContentLoaded", function() {
    if (window.OfflineManager) {
        OfflineManager.saveCampData(CAMP_ID, CAMP_DATA).catch(function() {});
    }
});

// Sync queued evaluations when coming back online
window.addEventListener('online', function() {
    if (window.OfflineManager) {
        OfflineManager.syncQueuedEvaluations().then(function(result) {
            if (result.synced > 0) {
                console.log('Synced', result.synced, 'queued evaluations from IndexedDB');
            }
        }).catch(function() {});
    }
});
</script>
<script src="/js/offline-manager.js"></script>
</body>
</html>
