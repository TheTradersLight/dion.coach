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
            <span id="offlineBanner" class="badge bg-warning text-dark d-none">Mode hors-ligne</span>
        </div>
    </div>

    <div id="codeGate" class="offline-panel mb-3 d-none">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <div class="fw-semibold">Accès par code admin</div>
                <div class="small text-muted">Entrez le code admin pour voir les résultats.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <input id="accessCodeInput" class="form-control form-control-sm" placeholder="Code (ex: A7K3)" style="max-width: 160px;">
                <button class="btn btn-outline-light btn-sm" onclick="submitAccessCode()">Valider</button>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-auto">
            <select id="selSession" class="form-select form-select-sm" onchange="onFiltersChange()"></select>
        </div>
        <div class="col-auto">
            <select id="selGroup" class="form-select form-select-sm" onchange="onFiltersChange()">
                <option value="">Tous les joueurs</option>
            </select>
        </div>
        <div class="col-auto">
            <select id="selStation" class="form-select form-select-sm" onchange="onFiltersChange()"></select>
        </div>
    </div>

    <div id="resultsView">
        <div class="d-flex align-items-center gap-2 mb-2">
            <button class="btn btn-outline-light btn-sm" id="btnViewRaw" onclick="setViewMode('raw')">Détail</button>
            <button class="btn btn-outline-light btn-sm" id="btnViewAvg" onclick="setViewMode('avg')">Moyennes</button>
            <span class="text-muted small" id="viewHint"></span>
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
const ACCESS_TOKEN_KEY = "dion_access_token_" + CAMP_ID;
const ACCESS_SCOPE_KEY = "dion_access_scope_" + CAMP_ID;

let state = {
    camp: null,
    sessions: [],
    groups: [],
    players: [],
    testTypes: [],
    selectedTestTypeId: null,
    currentSessionId: null,
    currentGroupId: null,
    viewMode: 'raw',
};

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

function updateOfflineBanner() {
    const banner = document.getElementById("offlineBanner");
    if (!banner) return;
    if (!navigator.onLine) banner.classList.remove("d-none");
    else banner.classList.add("d-none");
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
    if (data.test_type_id) state.selectedTestTypeId = data.test_type_id;
    localStorage.setItem(ACCESS_SCOPE_KEY, JSON.stringify({ test_type_id: data.test_type_id || null, role: data.role || 'station' }));
}

async function submitAccessCode() {
    const input = document.getElementById("accessCodeInput");
    const code = (input ? input.value : "").trim().toUpperCase();
    if (code === "") return;

    const resp = await fetch(`/api/public/camps/${CAMP_ID}/access-code`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ code }),
        credentials: "same-origin"
    });
    if (!resp.ok) {
        input.value = "";
        return;
    }
    const data = await resp.json();
    localStorage.setItem(ACCESS_TOKEN_KEY, data.token);
    localStorage.setItem(ACCESS_SCOPE_KEY, JSON.stringify({ test_type_id: data.test_type_id || null, role: data.role || 'station' }));
    document.getElementById("codeGate").classList.add("d-none");
    await fetchPreloadWithToken(data.token);
    initUI();
    await loadResults();
}

function initUI() {
    document.getElementById("campName").textContent = state.camp ? state.camp.name : "Camp";

    const selSession = document.getElementById("selSession");
    selSession.innerHTML = "";
    const optNone = document.createElement("option");
    optNone.value = "";
    optNone.textContent = "Aucune séance";
    selSession.appendChild(optNone);
    state.sessions.forEach(s => {
        const opt = document.createElement("option");
        opt.value = s.id;
        opt.textContent = s.name;
        selSession.appendChild(opt);
    });

    const selGroup = document.getElementById("selGroup");
    selGroup.innerHTML = '<option value="">Tous les joueurs</option>';
    state.groups.forEach(g => {
        const opt = document.createElement("option");
        opt.value = g.id;
        opt.textContent = g.name;
        selGroup.appendChild(opt);
    });

    const selStation = document.getElementById("selStation");
    selStation.innerHTML = "";
    state.testTypes.forEach(t => {
        const opt = document.createElement("option");
        opt.value = t.id;
        opt.textContent = t.name;
        selStation.appendChild(opt);
    });
    if (!state.selectedTestTypeId && state.testTypes.length > 0) {
        state.selectedTestTypeId = state.testTypes[0].id;
    }
    selStation.value = state.selectedTestTypeId || "";
}

function onFiltersChange() {
    const sess = document.getElementById("selSession").value;
    const group = document.getElementById("selGroup").value;
    const station = document.getElementById("selStation").value;
    state.currentSessionId = sess ? parseInt(sess) : null;
    state.currentGroupId = group ? parseInt(group) : null;
    state.selectedTestTypeId = station ? parseInt(station) : null;
    loadResults();
}

function setViewMode(mode) {
    state.viewMode = mode;
    const btnRaw = document.getElementById("btnViewRaw");
    const btnAvg = document.getElementById("btnViewAvg");
    const hint = document.getElementById("viewHint");
    if (btnRaw && btnAvg) {
        btnRaw.classList.toggle("btn-primary", mode === "raw");
        btnRaw.classList.toggle("btn-outline-light", mode !== "raw");
        btnAvg.classList.toggle("btn-primary", mode === "avg");
        btnAvg.classList.toggle("btn-outline-light", mode !== "avg");
    }
    if (hint) {
        hint.textContent = mode === "avg" ? "Moyenne des métriques numériques par joueur" : "Valeurs détaillées par joueur";
    }
    loadResults();
}

async function loadResults() {
    const loading = document.getElementById("resultsLoading");
    const wrap = document.getElementById("resultsTableWrap");
    loading.style.display = "block";
    wrap.style.display = "none";

    try {
        const token = getAccessToken();
        const params = new URLSearchParams();
        if (state.selectedTestTypeId) params.set("test_type_id", String(state.selectedTestTypeId));
        if (state.currentGroupId) params.set("group_id", String(state.currentGroupId));
        if (state.currentSessionId) params.set("session_id", String(state.currentSessionId));
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

    if (state.viewMode === "avg") {
        const numericMetrics = metrics.filter(m => m.value_type !== "text");
        let headHtml = "<tr><th>#</th><th>Joueur</th><th class='text-center'>Moyenne</th></tr>";
        document.getElementById("resultsHead").innerHTML = headHtml;

        let bodyHtml = "";
        players.forEach(p => {
            let sum = 0, count = 0;
            numericMetrics.forEach(m => {
                const r = map[`${p.player_id}-${m.id}`];
                if (r && r.value_number !== null && r.value_number !== undefined) {
                    sum += parseFloat(r.value_number);
                    count++;
                }
            });
            const avg = count > 0 ? (sum / count).toFixed(2) : "";
            bodyHtml += "<tr>";
            bodyHtml += `<td>${escHtml(p.jersey_number || '')}</td>`;
            bodyHtml += `<td>${escHtml(p.first_name + ' ' + p.last_name)}</td>`;
            bodyHtml += `<td class="text-center">${escHtml(avg)}</td>`;
            bodyHtml += "</tr>";
        });
        document.getElementById("resultsBody").innerHTML = bodyHtml;
        return;
    }

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

function escHtml(str) {
    const div = document.createElement("div");
    div.textContent = str || "";
    return div.innerHTML;
}

async function init() {
    updateOfflineBanner();
    if (!IS_AUTH && getAccessToken()) {
        document.getElementById("codeGate").classList.add("d-none");
    } else if (!IS_AUTH) {
        document.getElementById("codeGate").classList.remove("d-none");
    }

    try {
        const token = getAccessToken();
        if (!IS_AUTH && token) {
            await fetchPreloadWithToken(token);
        } else if (IS_AUTH) {
            // For now, admin results should be accessed via code. Keep UI basic.
            return;
        }
        if (isAdminAccess()) {
            initUI();
            setViewMode('raw');
            await loadResults();
        }
    } catch (e) {
        document.getElementById("resultsBody").innerHTML = "<tr><td class='text-muted'>Accès refusé.</td></tr>";
    }
}

document.addEventListener("DOMContentLoaded", init);
window.addEventListener("online", updateOfflineBanner);
window.addEventListener("offline", updateOfflineBanner);
</script>
</body>
</html>
