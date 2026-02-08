# Module Camps de Selection - Plan d'implementation

## Architecture globale

```
src/
  Database/
    Database.php              (existant)
    UserRepository.php        (existant)
    CampRepository.php        (nouveau)
    SessionRepository.php     (nouveau)
    PlayerRepository.php      (nouveau)
    GroupRepository.php        (nouveau)
    SkillRepository.php        (nouveau)
    EvaluationRepository.php   (nouveau)
  routes.php                  (existant - ajouter include du nouveau fichier routes)
  routes-camps.php            (nouveau - toutes les routes /camps/*)

public/
  pages/
    camps/
      index.php               # Liste des camps
      create.php              # Creer un camp
      edit.php                # Modifier un camp
      view.php                # Vue d'ensemble d'un camp (hub)
      players.php             # Gestion joueurs du camp
      groups.php              # Gestion groupes du camp
      skills.php              # Gestion grille de competences
      sessions.php            # Gestion seances
      evaluate.php            # Ecran d'evaluation (saisie notes)
      results.php             # Resultats et classement
  css/
    camps.css                 # Styles specifiques au module
  js/
    camps.js                  # JavaScript pour interactions (evaluation AJAX, etc.)
```

---

## Etapes d'implementation

### Etape 1 : Base de donnees
**Fichier** : `docs/camps-selection/schema.sql`

Creer toutes les tables dans l'ordre (contraintes FK) :
1. `camps`
2. `camp_links`
3. `camp_evaluators` (evaluateurs invites par camp)
4. `sessions` (seances)
5. `players`
6. `camp_players`
7. `groups`
8. `group_players`
9. `skill_categories`
10. `skills`
11. `evaluations`

> **Note** : La table `camp_evaluators` est creee des la phase 1 pour que le schema
> soit pret, mais la fonctionnalite d'invitation sera implementee en phase 2.

### Etape 2 : Routes et navigation
- Ajouter un fichier `src/routes-camps.php` avec toutes les routes
- Modifier `src/routes.php` pour inclure les routes camps
- Ajouter un lien "Camps de selection" dans le navbar (section connectee)
- Middleware d'authentification : verifier session sur toutes les routes /camps/*

**Routes prevues :**
```
GET    /camps                          # Liste des camps
GET    /camps/create                   # Formulaire creation
POST   /camps                          # Sauvegarder nouveau camp
GET    /camps/{id}                     # Vue d'ensemble du camp
GET    /camps/{id}/edit                # Formulaire edition
POST   /camps/{id}                     # Sauvegarder modifications
POST   /camps/{id}/delete              # Supprimer camp

GET    /camps/{id}/sessions            # Liste seances
POST   /camps/{id}/sessions            # Ajouter seance
POST   /camps/{id}/sessions/{sid}      # Modifier seance
POST   /camps/{id}/sessions/{sid}/delete  # Supprimer seance

GET    /camps/{id}/players             # Liste joueurs
POST   /camps/{id}/players             # Ajouter joueur
POST   /camps/{id}/players/generate    # Generer cas d'essai
POST   /camps/{id}/players/{pid}/cut   # Couper joueur
POST   /camps/{id}/players/{pid}/transfer  # Transferer joueur

GET    /camps/{id}/groups              # Gestion groupes
POST   /camps/{id}/groups              # Creer groupe
POST   /camps/{id}/groups/{gid}        # Modifier groupe
POST   /camps/{id}/groups/{gid}/delete # Supprimer groupe
POST   /camps/{id}/groups/{gid}/players  # Assigner joueurs au groupe

GET    /camps/{id}/skills              # Grille de competences
POST   /camps/{id}/skills/categories   # Ajouter categorie
POST   /camps/{id}/skills/categories/{cid}  # Modifier categorie
POST   /camps/{id}/skills/categories/{cid}/delete  # Supprimer categorie
POST   /camps/{id}/skills              # Ajouter competence
POST   /camps/{id}/skills/{sid}        # Modifier competence
POST   /camps/{id}/skills/{sid}/delete # Supprimer competence

GET    /camps/{id}/evaluate            # Ecran d'evaluation
GET    /camps/{id}/evaluate/{session}  # Evaluation pour une seance
POST   /camps/{id}/evaluate/save       # Sauvegarder note (AJAX)

GET    /camps/{id}/results             # Tableau de resultats
GET    /camps/{id}/results/player/{pid}  # Fiche joueur
```

### Etape 3 : Repositories (couche donnees)
Creer les repositories suivant le pattern existant (`UserRepository.php`).

**CampRepository** :
- `findAll(int $userId)` - camps de l'utilisateur
- `findById(int $id)` - detail d'un camp
- `create(array $data)` - creer camp
- `update(int $id, array $data)` - modifier camp
- `delete(int $id)` - supprimer camp
- `linkCamps(int $sourceId, int $targetId)` - lier deux camps
- `getLinkedCamps(int $campId)` - camps lies

**SessionRepository** :
- `findByCamp(int $campId)` - seances du camp
- `create(array $data)` / `update()` / `delete()`

**PlayerRepository** :
- `findByCamp(int $campId)` - joueurs du camp
- `findById(int $id)` - detail joueur
- `create(array $data)` - creer joueur
- `addToCamp(int $playerId, int $campId)` - inscrire au camp
- `cutFromCamp(int $campPlayerId)` - couper
- `transferPlayer(int $campPlayerId, int $targetCampId)` - transferer
- `generateTestPlayers(int $campId, int $count)` - generer fictifs

**GroupRepository** :
- `findByCamp(int $campId)` - groupes du camp
- `create()` / `update()` / `delete()`
- `assignPlayers(int $groupId, array $campPlayerIds)` - assigner joueurs
- `getPlayersInGroup(int $groupId)` - joueurs du groupe

**SkillRepository** :
- `getCategoriesWithSkills(int $campId)` - arborescence complete
- `createCategory()` / `updateCategory()` / `deleteCategory()`
- `createSkill()` / `updateSkill()` / `deleteSkill()`
- `reorder()` - reordonner

**EvaluationRepository** :
- `getBySession(int $sessionId, ?int $groupId)` - evaluations d'une seance
- `getByPlayer(int $campPlayerId)` - evaluations d'un joueur
- `save(array $data)` - sauvegarder/modifier note (upsert)
- `getResults(int $campId)` - resultats agreges

### Etape 4 : Pages et UI

Chaque page suit le pattern existant :
- Include head.php, navbar.php, footer.php
- Bootstrap 5 pour le layout
- Formulaires standard avec POST
- AJAX uniquement pour l'ecran d'evaluation (sauvegarde en temps reel)

**Ordre de developpement des ecrans :**

1. **Liste des camps** (`camps/index.php`)
   - Tableau Bootstrap avec statut, sport, saison
   - Bouton "Nouveau camp"

2. **Creation/Edition camp** (`camps/create.php`, `camps/edit.php`)
   - Formulaire : nom, description, sport, saison, echelle notation
   - Section liens vers autres camps

3. **Vue d'ensemble camp** (`camps/view.php`)
   - Dashboard du camp avec statistiques
   - Navigation rapide : Joueurs, Groupes, Competences, Seances, Evaluer, Resultats
   - Nombre de joueurs, seances, evaluations completees

4. **Gestion joueurs** (`camps/players.php`)
   - Tableau avec nom, prenom, numero, position, statut, groupe
   - Formulaire modal d'ajout
   - Bouton "Generer X joueurs test"
   - Actions : couper, transferer

5. **Gestion groupes** (`camps/groups.php`)
   - Cards par groupe avec liste de joueurs
   - Formulaire modal creation groupe
   - Drag & drop ou checkboxes pour assigner joueurs

6. **Grille de competences** (`camps/skills.php`)
   - Vue arborescente (accordeon Bootstrap)
   - Ajout categorie niveau 1
   - Ajout sous-categorie niveau 2
   - Ajout competence sous une categorie
   - Inline editing ou modals

7. **Gestion seances** (`camps/sessions.php`)
   - Liste ordonnee des seances
   - Ajout/modification rapide (inline)

8. **Ecran d'evaluation** (`camps/evaluate.php`)
   - Selecteurs : seance, groupe
   - Un joueur a la fois avec toutes ses competences
   - Boutons radio ou slider pour la note (1 a N)
   - Champ commentaire
   - Sauvegarde AJAX immediate
   - Navigation precedent/suivant
   - Badge indicant evalues/non-evalues

9. **Resultats** (`camps/results.php`)
   - Tableau croise : joueurs x competences
   - Colonnes triables
   - Moyennes par categorie
   - Score total
   - Filtre par groupe

10. **Fiche joueur** (modale ou page)
    - Toutes les notes du joueur
    - Evolution si plusieurs seances

### Etape 5 : JavaScript et interactions
- `public/js/camps.js`
  - Sauvegarde AJAX des evaluations
  - Navigation entre joueurs
  - Confirmation suppression
  - Reordonnancement (drag & drop - optionnel)

---

## Ordre de travail recommande

| # | Tache | Dependance | Complexite |
|---|-------|------------|------------|
| 1 | Schema SQL + creation tables | Aucune | Faible |
| 2 | CampRepository + routes de base | #1 | Faible |
| 3 | Liste des camps + creation camp | #2 | Faible |
| 4 | Vue d'ensemble camp + edition | #3 | Faible |
| 5 | PlayerRepository + gestion joueurs | #1 | Moyenne |
| 6 | Generation de joueurs test | #5 | Faible |
| 7 | GroupRepository + gestion groupes | #5 | Moyenne |
| 8 | SkillRepository + grille competences | #1 | Moyenne |
| 9 | SessionRepository + gestion seances | #1 | Faible |
| 10 | EvaluationRepository + ecran evaluation | #5, #8, #9 | Elevee |
| 11 | Ecran resultats | #10 | Moyenne |
| 12 | Transferts de joueurs | #3, #5 | Moyenne |
| 13 | Lien navbar + integration dashboard | #3 | Faible |

**Estimation** : ~13 etapes, livrable progressivement.

---

## Considerations techniques

### Sauvegarde AJAX des evaluations
```javascript
// Exemple de sauvegarde automatique
function saveRating(sessionId, campPlayerId, skillId, rating) {
    fetch('/camps/{id}/evaluate/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: sessionId, camp_player_id: campPlayerId, skill_id: skillId, rating: rating })
    })
    .then(r => r.json())
    .then(data => { /* feedback visuel */ });
}
```

### Generation de joueurs test
Utiliser des listes de prenoms/noms quebecois pour generer des joueurs realistes.

### Mode cumulative
En mode cumulative, l'ecran d'evaluation charge les notes de la seance precedente comme valeurs par defaut. L'evaluateur peut les bonifier (augmenter ou diminuer).

### Pattern URL
Toutes les URLs suivent le pattern RESTful : `/camps/{campId}/resource/{resourceId}/action`

---

## Prochaine action

A la validation de ce plan, on commence par **l'etape 1** : creation du schema SQL complet.
