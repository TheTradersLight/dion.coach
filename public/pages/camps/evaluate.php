<?php
$user = $GLOBALS['user'] ?? null;
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
        <h5 class="mb-0" id="campName"></h5>
        <div class="d-flex align-items-center gap-2">
            <span id="syncStatus" class="badge bg-success">Sauvegarde locale</span>
            <button class="btn btn-outline-light btn-sm" onclick="showResults()">Resultats</button>
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
    <div class="d-flex align-items-center gap-2 mb-3">
        <button class="btn btn-outline-secondary btn-sm" onclick="prevPlayer()" id="btnPrev">&laquo; Prec.</button>
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
            <h5 class="mb-0">Resultats</h5>
            <button class="btn btn-outline-light btn-sm" onclick="hideResults()">Retour a l'evaluation</button>
        </div>
        <div class="table-responsive">
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
// DONNEES DE TEST
// ============================================================
const TEST_DATA = {
    camp: {
        id: 1,
        name: "Camp de selection U15 - Saison 2025-2026",
        sport: "Hockey",
        rating_min: 1,
        rating_max: 5,
        eval_mode: "cumulative"
    },
    sessions: [
        { id: 1, name: "Seance 1 - Evaluation initiale", session_order: 1 },
        { id: 2, name: "Seance 2 - Deuxieme evaluation", session_order: 2 }
    ],
    groups: [
        { id: 1, name: "Groupe A - 9h00", color: "#3b82f6" },
        { id: 2, name: "Groupe B - 11h00", color: "#22c55e" },
        { id: 3, name: "Groupe C - 13h00", color: "#f59e0b" }
    ],
    skillCategories: [
        {
            id: 1, name: "Physique", parent_id: null, sort_order: 1,
            skills: [
                { id: 1, name: "Vitesse" },
                { id: 2, name: "Acceleration" },
                { id: 3, name: "Changement de direction" },
                { id: 4, name: "Endurance" },
                { id: 5, name: "Hauteur" }
            ]
        },
        {
            id: 2, name: "Lancer", parent_id: null, sort_order: 2,
            skills: [
                { id: 6, name: "Coup droit" },
                { id: 7, name: "Revers" }
            ]
        },
        {
            id: 3, name: "Defensive", parent_id: null, sort_order: 3,
            skills: [
                { id: 8, name: "Positionnement" },
                { id: 9, name: "Repositionnement" },
                { id: 10, name: "Prise d'information" },
                { id: 11, name: "Anticipation" },
                { id: 12, name: "Prise de decision" }
            ]
        },
        {
            id: 4, name: "Offensive", parent_id: null, sort_order: 4,
            skills: [
                { id: 13, name: "Position" },
                { id: 14, name: "Ajustement au deplacement de jeu" },
                { id: 15, name: "Synchronisme" },
                { id: 16, name: "Creation d'espace" },
                { id: 17, name: "Prise d'espace" }
            ]
        },
        {
            id: 5, name: "Psychologique", parent_id: null, sort_order: 5,
            skills: [
                { id: 18, name: "Ecoute" },
                { id: 19, name: "Applique les consignes" },
                { id: 20, name: "Adaptabilite" }
            ]
        }
    ],
    players: []
};

// Generer 30 joueurs test
(function generatePlayers() {
    const prenoms = [
        "Alexis","Samuel","Nathan","Gabriel","William","Olivier","Thomas","Felix",
        "Raphael","Mathis","Emile","Antoine","Jacob","Xavier","Edouard","Julien",
        "Zachary","Hugo","Simon","Louis","Maxime","Etienne","Vincent","Cedric",
        "Philippe","Benoit","Tristan","Marc-Antoine","Charles","Sebastien"
    ];
    const noms = [
        "Tremblay","Gagnon","Bouchard","Cote","Fortin","Gauthier","Morin","Lavoie",
        "Roy","Pelletier","Belanger","Levesque","Bergeron","Leblanc","Girard","Simard",
        "Boucher","Ouellet","Poirier","Beaulieu","Cloutier","Dubois","Deschenes","Plante",
        "Demers","Lachance","Martel","Savard","Therrien","Leclerc"
    ];
    const positions = ["Attaquant","Defenseur","Gardien","Centre","Ailier gauche","Ailier droit"];
    const groupAssign = [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3];

    for (let i = 0; i < 30; i++) {
        TEST_DATA.players.push({
            id: i + 1,
            camp_player_id: i + 1,
            first_name: prenoms[i],
            last_name: noms[i],
            jersey_number: String(i + 1),
            position: positions[i % positions.length],
            group_id: groupAssign[i],
            status: "active"
        });
    }
})();

// ============================================================
// ETAT DE L'APPLICATION
// ============================================================
let state = {
    currentSessionId: 1,
    currentGroupId: null,
    currentPlayerIndex: 0,
    filteredPlayers: [],
    evaluations: {} // key: "sessionId-campPlayerId-skillId" -> { rating, comment, timestamp }
};

const STORAGE_KEY = "dion_camp_evals_" + TEST_DATA.camp.id;

// ============================================================
// STOCKAGE LOCAL
// ============================================================
function loadEvaluations() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
        state.evaluations = JSON.parse(saved);
    }
}

function saveEvaluations() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state.evaluations));
    updateSyncStatus();
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
        timestamp: new Date().toISOString()
    };
    saveEvaluations();
}

function removeEval(sessionId, campPlayerId, skillId) {
    const key = evalKey(sessionId, campPlayerId, skillId);
    delete state.evaluations[key];
    saveEvaluations();
}

// ============================================================
// INITIALISATION
// ============================================================
function init() {
    loadEvaluations();
    document.getElementById("campName").textContent = TEST_DATA.camp.name;

    // Populate sessions
    const selSession = document.getElementById("selSession");
    TEST_DATA.sessions.forEach(s => {
        const opt = document.createElement("option");
        opt.value = s.id;
        opt.textContent = s.name;
        selSession.appendChild(opt);
    });

    // Populate groups
    const selGroup = document.getElementById("selGroup");
    TEST_DATA.groups.forEach(g => {
        const opt = document.createElement("option");
        opt.value = g.id;
        opt.textContent = g.name;
        selGroup.appendChild(opt);
    });

    filterPlayers();
    renderEvalGrid();
    updateProgress();
}

// ============================================================
// FILTRAGE ET NAVIGATION
// ============================================================
function filterPlayers() {
    const groupId = state.currentGroupId;
    state.filteredPlayers = TEST_DATA.players.filter(p => {
        if (p.status !== "active") return false;
        if (groupId && p.group_id !== groupId) return false;
        return true;
    });

    // Populate player select
    const selPlayer = document.getElementById("selPlayer");
    selPlayer.innerHTML = "";
    state.filteredPlayers.forEach((p, idx) => {
        const opt = document.createElement("option");
        opt.value = idx;
        const evaluated = isPlayerEvaluated(p.camp_player_id);
        opt.textContent = `#${p.jersey_number} ${p.first_name} ${p.last_name}${evaluated ? " \u2713" : ""}`;
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
    TEST_DATA.skillCategories.forEach(cat => {
        cat.skills.forEach(s => skills.push(s));
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
// AFFICHAGE JOUEUR
// ============================================================
function showCurrentPlayer() {
    const player = state.filteredPlayers[state.currentPlayerIndex];
    if (!player) {
        document.getElementById("playerCard").style.display = "none";
        document.getElementById("evalGrid").innerHTML = '<p class="text-muted">Aucun joueur dans ce groupe.</p>';
        return;
    }

    document.getElementById("playerCard").style.display = "block";
    document.getElementById("playerJersey").textContent = "#" + player.jersey_number;
    document.getElementById("playerName").textContent = player.first_name + " " + player.last_name;
    document.getElementById("playerPosition").textContent = player.position;

    // Update nav buttons
    document.getElementById("btnPrev").disabled = state.currentPlayerIndex === 0;
    document.getElementById("btnNext").disabled = state.currentPlayerIndex === state.filteredPlayers.length - 1;

    // Load ratings into grid
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
        // Comment
        const commentEl = document.querySelector(`[data-skill="${skill.id}"] .skill-comment`);
        if (commentEl) {
            commentEl.value = ev ? ev.comment : "";
        }
    });
}

// ============================================================
// GRILLE D'EVALUATION
// ============================================================
function renderEvalGrid() {
    const grid = document.getElementById("evalGrid");
    const min = TEST_DATA.camp.rating_min;
    const max = TEST_DATA.camp.rating_max;
    let html = "";

    TEST_DATA.skillCategories.forEach(cat => {
        const collapseId = `cat-collapse-${cat.id}`;
        html += `<div class="eval-category mb-3">`;
        html += `<div class="eval-category-header" data-bs-toggle="collapse" data-bs-target="#${collapseId}" role="button" aria-expanded="false" aria-controls="${collapseId}">`;
        html += `<span class="cat-chevron me-2">&#9654;</span>`;
        html += `<span class="cat-title">${cat.name}</span>`;
        html += `<span class="ms-auto d-flex align-items-center gap-2">`;
        html += `<span class="cat-avg text-muted small" id="cat-avg-${cat.id}">-</span>`;
        html += `<span class="cat-status badge" id="cat-status-${cat.id}"></span>`;
        html += `</span>`;
        html += `</div>`;

        html += `<div class="collapse" id="${collapseId}">`;
        cat.skills.forEach(skill => {
            html += `<div class="eval-skill-row" data-skill="${skill.id}">`;
            html += `<div class="eval-skill-name">${skill.name}</div>`;
            html += `<div class="eval-skill-buttons">`;
            for (let v = min; v <= max; v++) {
                html += `<button type="button" class="rating-btn" data-value="${v}" onclick="rate(${skill.id}, ${v})">${v}</button>`;
            }
            html += `<button type="button" class="rating-btn rating-clear" onclick="clearRating(${skill.id})" title="Effacer">&times;</button>`;
            html += `</div>`;
            html += `<input type="text" class="form-control form-control-sm skill-comment" placeholder="Note..." onchange="saveComment(${skill.id}, this.value)">`;
            html += `</div>`;
        });
        html += `</div>`;

        html += `</div>`;
    });

    grid.innerHTML = html;
    updateCategoryHeaders();
}

function updateCategoryHeaders() {
    const player = state.filteredPlayers[state.currentPlayerIndex];
    if (!player) return;

    TEST_DATA.skillCategories.forEach(cat => {
        let sum = 0, count = 0;
        cat.skills.forEach(skill => {
            const ev = getEval(state.currentSessionId, player.camp_player_id, skill.id);
            if (ev) { sum += ev.rating; count++; }
        });

        const avgEl = document.getElementById(`cat-avg-${cat.id}`);
        const statusEl = document.getElementById(`cat-status-${cat.id}`);
        if (!avgEl || !statusEl) return;

        const total = cat.skills.length;
        const isComplete = count === total;

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

    // Update UI
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
        opt.textContent = `#${player.jersey_number} ${player.first_name} ${player.last_name}${suffix}`;
    }
}

// ============================================================
// PROGRESSION
// ============================================================
function updateProgress() {
    const allSkills = getAllSkills();
    const totalPlayers = state.filteredPlayers.length;
    let evaluated = 0;
    state.filteredPlayers.forEach(p => {
        if (isPlayerFullyEvaluated(p.camp_player_id)) evaluated++;
    });
    document.getElementById("progressText").textContent =
        `${evaluated} / ${totalPlayers} joueurs completes`;
}

function updateSyncStatus() {
    const count = Object.keys(state.evaluations).length;
    const el = document.getElementById("syncStatus");
    el.textContent = `${count} notes sauvees localement`;
    el.className = "badge bg-success";
}

// ============================================================
// RESULTATS
// ============================================================
function showResults() {
    document.getElementById("evalGrid").style.display = "none";
    document.getElementById("playerCard").style.display = "none";
    document.querySelector(".d-flex.align-items-center.gap-2.mb-3").style.display = "none";
    document.getElementById("resultsView").style.display = "block";
    renderResults();
}

function hideResults() {
    document.getElementById("evalGrid").style.display = "block";
    document.getElementById("playerCard").style.display = "block";
    document.querySelector(".d-flex.align-items-center.gap-2.mb-3").style.display = "flex";
    document.getElementById("resultsView").style.display = "none";
}

function renderResults() {
    const allSkills = getAllSkills();
    const categories = TEST_DATA.skillCategories;

    // Header
    let headHtml = "<tr><th>Rang</th><th>#</th><th>Joueur</th><th>Pos.</th>";
    categories.forEach(cat => {
        headHtml += `<th class="text-center cat-header" title="${cat.skills.map(s=>s.name).join(', ')}">${cat.name}</th>`;
    });
    headHtml += "<th class='text-center'>Moy.</th></tr>";
    document.getElementById("resultsHead").innerHTML = headHtml;

    // Build player scores
    const playerScores = [];
    const players = state.currentGroupId
        ? TEST_DATA.players.filter(p => p.group_id === state.currentGroupId && p.status === "active")
        : TEST_DATA.players.filter(p => p.status === "active");

    players.forEach(player => {
        const catAverages = [];
        let totalSum = 0, totalCount = 0;

        categories.forEach(cat => {
            let catSum = 0, catCount = 0;
            cat.skills.forEach(skill => {
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
            player: player,
            catAverages: catAverages,
            totalAvg: totalCount > 0 ? totalSum / totalCount : null
        });
    });

    // Sort by total average descending
    playerScores.sort((a, b) => {
        if (a.totalAvg === null && b.totalAvg === null) return 0;
        if (a.totalAvg === null) return 1;
        if (b.totalAvg === null) return -1;
        return b.totalAvg - a.totalAvg;
    });

    // Render rows
    let bodyHtml = "";
    playerScores.forEach((ps, idx) => {
        const p = ps.player;
        bodyHtml += `<tr>`;
        bodyHtml += `<td class="text-center fw-bold">${ps.totalAvg !== null ? idx + 1 : "-"}</td>`;
        bodyHtml += `<td>${p.jersey_number}</td>`;
        bodyHtml += `<td>${p.first_name} ${p.last_name}</td>`;
        bodyHtml += `<td><small class="text-muted">${p.position}</small></td>`;
        ps.catAverages.forEach(avg => {
            if (avg !== null) {
                const pct = ((avg - TEST_DATA.camp.rating_min) / (TEST_DATA.camp.rating_max - TEST_DATA.camp.rating_min)) * 100;
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

// Keyboard nav
document.addEventListener("keydown", function(e) {
    if (e.target.tagName === "INPUT" || e.target.tagName === "TEXTAREA") return;
    if (e.key === "ArrowLeft") prevPlayer();
    if (e.key === "ArrowRight") nextPlayer();
});

// Init
document.addEventListener("DOMContentLoaded", init);
</script>
</body>
</html>
