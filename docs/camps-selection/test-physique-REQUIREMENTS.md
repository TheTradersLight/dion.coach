# Module Camps de Selection - Document d'exigences

## 1. Vue d'ensemble

Application web integree au site dion.coach (section privee/dashboard) permettant la gestion complete de camps de selection sportifs. Le module doit etre **generique** pour supporter plusieurs camps simultanement, avec la possibilite de lier des camps entre eux (transferts de joueurs).

---

## 2. Concepts cles et glossaire

| Terme | Definition |
|-------|-----------|
| **Camp** | Un camp de selection avec ses parametres, dates, joueurs et evaluations |
| **Seance** | Une session d'evaluation au sein d'un camp (un camp a plusieurs seances) |
| **Joueur** | Un participant inscrit a un camp |
| **Groupe** | Un sous-ensemble de joueurs dans un camp (par horaire, atelier, etc.) |
| **Competence** | Un critere d'evaluation (ex: vitesse, endurance, lancer) |
| **Categorie** | Un regroupement de competences (ex: Physique, Technique) - 2 niveaux max |
| **Grille d'evaluation** | L'ensemble des categories/competences definies pour un camp |
| **Evaluation** | La note attribuee a un joueur pour une competence lors d'une seance |
| **Evaluateur** | La personne qui attribue les notes (gestionnaire ou evaluateur assigne) |
| **Transfert** | Deplacement d'un joueur d'un camp vers un autre camp lie |

---

## 3. Entites et modele de donnees

### 3.1 Camp (`camps`)

| Champ | Type | Description |
|-------|------|-------------|
| id | INT PK AUTO | Identifiant unique |
| name | VARCHAR(255) | Nom du camp |
| description | TEXT NULL | Description du camp |
| sport | VARCHAR(100) | Sport concerne |
| season | VARCHAR(50) | Saison (ex: "2025-2026") |
| status | ENUM | 'draft', 'active', 'completed', 'archived' |
| eval_mode | ENUM | 'cumulative' (bonifiee seance apres seance) |
| rating_min | INT | Note minimale (ex: 1) |
| rating_max | INT | Note maximale (ex: 5, 10, etc.) |
| created_by | INT FK | Utilisateur createur (users.id) |
| created_at | DATETIME | Date de creation |
| updated_at | DATETIME | Date de modification |

> **Note** : Le mode `'independent'` (evaluations independantes par seance avec moyennes) est prevu pour une phase ulterieure. Le champ `eval_mode` est present pour le supporter plus tard.

### 3.2 Liens entre camps (`camp_links`)

| Champ | Type | Description |
|-------|------|-------------|
| id | INT PK AUTO | Identifiant unique |
| camp_source_id | INT FK | Camp d'origine |
| camp_target_id | INT FK | Camp de destination |
| description | VARCHAR(255) NULL | Description du lien (ex: "Joueurs coupes") |

Permet de definir qu'un camp peut transferer des joueurs vers un autre camp.

### 3.3 Seance (`sessions`)

| Champ | Type | Description |
|-------|------|-------------|
| id | INT PK AUTO | Identifiant unique |
| camp_id | INT FK | Camp associe |
| name | VARCHAR(255) | Nom de la seance (ex: "Seance 1 - 15 janvier") |
| session_date | DATE | Date de la seance |
| session_order | INT | Ordre de la seance dans le camp |
| status | ENUM | 'planned', 'in_progress', 'completed' |
| created_at | DATETIME | Date de creation |

### 3.4 Joueur (`players`)

| Champ | Type | Description |
|-------|------|-------------|
| id | INT PK AUTO | Identifiant unique |
| first_name | VARCHAR(100) | Prenom |
| last_name | VARCHAR(100) | Nom de famille |
| date_of_birth | DATE NULL | Date de naissance |
| jersey_number | VARCHAR(10) NULL | Numero de chandail |
| position | VARCHAR(50) NULL | Position |
| notes | TEXT NULL | Notes supplementaires |
| created_at | DATETIME | Date de creation |

### 3.5 Inscription joueur-camp (`camp_players`)

| Champ | Type | Description |
|-------|------|-------------|
| id | INT PK AUTO | Identifiant unique |
| camp_id | INT FK | Camp |
| player_id | INT FK | Joueur |
| status | ENUM | 'active', 'cut', 'transferred' |
| transferred_to_camp_id | INT FK NULL | Si transfere, vers quel camp |
| registered_at | DATETIME | Date d'inscription |

### 3.6 Groupe (`groups`)

| Champ | Type | Description |
|-------|------|-------------|
| id | INT PK AUTO | Identifiant unique |
| camp_id | INT FK | Camp associe |
| name | VARCHAR(100) | Nom du groupe (ex: "Groupe A - 9h00") |
| color | VARCHAR(7) NULL | Couleur pour identification visuelle (hex) |
| sort_order | INT | Ordre d'affichage |

### 3.7 Joueur-Groupe (`group_players`)

| Champ | Type | Description |
|-------|------|-------------|
| id | INT PK AUTO | Identifiant unique |
| group_id | INT FK | Groupe |
| camp_player_id | INT FK | Inscription joueur-camp |

Un joueur peut etre dans un seul groupe par camp.

### 3.8 Categorie de competences (`skill_categories`)

| Champ | Type | Description |
|-------|------|-------------|
| id | INT PK AUTO | Identifiant unique |
| camp_id | INT FK | Camp associe |
| parent_id | INT FK NULL | Categorie parente (NULL = niveau 1) |
| name | VARCHAR(100) | Nom (ex: "Physique", "Technique") |
| sort_order | INT | Ordre d'affichage |

Hierarchie limitee a **2 niveaux** :
- Niveau 1 : Categorie principale (parent_id = NULL)
- Niveau 2 : Sous-categorie (parent_id = id d'un niveau 1)

### 3.9 Competence (`skills`)

| Champ | Type | Description |
|-------|------|-------------|
| id | INT PK AUTO | Identifiant unique |
| category_id | INT FK | Categorie associee |
| name | VARCHAR(100) | Nom (ex: "Vitesse", "Endurance") |
| description | TEXT NULL | Description / consigne pour l'evaluateur |
| sort_order | INT | Ordre d'affichage |

### 3.10 Evaluation (`evaluations`)

| Champ | Type | Description |
|-------|------|-------------|
| id | INT PK AUTO | Identifiant unique |
| session_id | INT FK | Seance |
| camp_player_id | INT FK | Inscription joueur-camp |
| skill_id | INT FK | Competence evaluee |
| rating | INT | Note attribuee (entre rating_min et rating_max du camp) |
| comment | TEXT NULL | Commentaire optionnel |
| evaluated_by | INT FK | Utilisateur evaluateur (users.id) |
| evaluated_at | DATETIME | Date/heure de l'evaluation |
| updated_at | DATETIME | Derniere modification |

**Contrainte unique** : (session_id, camp_player_id, skill_id) - une seule note par competence par joueur par seance.

---

## 4. Fonctionnalites detaillees

### 4.1 Gestion des camps

**Ecran : Liste des camps**
- Liste de tous les camps avec filtre par statut (actif, brouillon, complete, archive)
- Bouton de creation d'un nouveau camp
- Acces rapide au camp pour gerer seances, joueurs, groupes, competences

**Ecran : Creation / Edition d'un camp**
- Formulaire : nom, description, sport, saison, statut
- Configuration de l'echelle de notation (min/max, ex: 1-5, 1-10)
- Mode d'evaluation : cumulative (phase 1)
- Liaison avec d'autres camps (pour les transferts)

### 4.2 Gestion des seances

**Ecran : Seances d'un camp**
- Liste des seances ordonnees
- Ajout/modification/suppression de seances
- Statut de chaque seance (planifiee, en cours, terminee)

### 4.3 Gestion des joueurs

**Ecran : Joueurs d'un camp**
- Liste des joueurs inscrits avec leur statut (actif, coupe, transfere)
- Ajout manuel de joueurs (formulaire)
- Import de joueurs (CSV) - **phase ulterieure**
- Generation de joueurs fictifs pour tests (bouton "Generer des cas d'essai")
- Action : Couper un joueur
- Action : Transferer un joueur vers un camp lie

**Generation de cas d'essai**
- Genere N joueurs fictifs avec des noms realistes
- Les inscrit automatiquement au camp
- Utile pour tester le systeme avant utilisation reelle

### 4.4 Gestion des groupes

**Ecran : Groupes d'un camp**
- Creation de groupes (nom, couleur)
- Attribution de joueurs aux groupes (drag & drop ou selection)
- Visualisation des joueurs par groupe
- Les groupes servent a organiser les ateliers et les rotations d'evaluateurs

### 4.5 Gestion des competences (Grille d'evaluation)

**Ecran : Grille d'evaluation d'un camp**
- Gestion des categories (niveau 1)
  - Ajout, modification, suppression, reordonnancement
- Gestion des sous-categories (niveau 2, optionnel)
  - Ajout sous une categorie parente
- Gestion des competences
  - Ajout sous une categorie (niveau 1 ou 2)
  - Nom, description, ordre
- Apercu visuel de la grille complete en arborescence

**Exemple de grille :**
```
Physique (categorie niveau 1)
  ├── Vitesse
  ├── Endurance
  ├── Changement de direction
  └── Grandeur
Technique (categorie niveau 1)
  ├── Maniement de rondelle (sous-categorie niveau 2)
  │   ├── Controle
  │   └── Protection
  ├── Tir
  └── Passe
Mental (categorie niveau 1)
  ├── Attitude
  └── Effort
```

### 4.6 Evaluation des joueurs

**Ecran : Evaluation (ecran principal de saisie)**
- Selection de : Seance, Groupe (optionnel)
- Affichage d'un joueur a la fois ou mode liste
- Pour chaque joueur : grille de toutes les competences avec curseur/boutons de 1 a N
- Possibilite d'ajouter un commentaire par note
- **Mode cumulative** : si une note existe deja d'une seance precedente, elle est affichee et peut etre modifiee (bonifiee)
- Sauvegarde automatique (AJAX) a chaque changement de note
- Navigation rapide entre joueurs du groupe (precedent/suivant)
- Indicateur visuel des joueurs deja evalues vs non evalues

### 4.7 Consultation des resultats

**Ecran : Resultats d'un camp**
- Tableau recapitulatif : joueurs en lignes, competences en colonnes
- Tri par note totale, par categorie, par competence
- Filtre par groupe, par statut
- Moyenne par categorie pour chaque joueur
- Score total par joueur
- Export (phase ulterieure) : CSV, PDF

**Ecran : Fiche d'un joueur**
- Toutes les evaluations du joueur pour le camp
- Detail par seance (mode cumulative : evolution des notes)
- Commentaires des evaluateurs
- Graphique radar des categories (phase ulterieure)

---

## 5. Roles et permissions

- **Gestionnaire de camp** : le createur du camp, peut tout faire (CRUD camp, joueurs, groupes, competences, evaluations, resultats, inviter des evaluateurs)
- **Evaluateur** : invite par courriel par le gestionnaire. Peut saisir des evaluations et consulter les resultats pour les camps auxquels il est assigne. Son compte peut ne pas exister sur la plateforme au moment de l'invitation (invitation par courriel, creation de compte au premier acces).

### 5.1 Invitation d'evaluateurs

- Le gestionnaire invite un evaluateur en saisissant son courriel
- Si le courriel correspond a un utilisateur existant : acces immediat
- Si le courriel ne correspond a aucun utilisateur : invitation envoyee, compte cree lors du premier acces via Auth0
- Un evaluateur peut etre assigne a un ou plusieurs camps
- Un evaluateur peut etre assigne a des groupes specifiques (optionnel)

---

## 6. Regles metier

1. Un joueur peut etre inscrit a **plusieurs camps** simultanement
2. Un joueur ne peut etre dans qu'**un seul groupe** par camp
3. L'echelle de notation est definie **par camp** (min/max)
4. Une competence ne peut recevoir qu'**une seule note** par joueur par seance **par evaluateur**
5. En mode cumulative, la note de la derniere seance est la note finale
6. Un joueur "coupe" ne peut plus etre evalue mais ses evaluations sont conservees
7. Un joueur "transfere" est marque dans le camp source et inscrit dans le camp cible
8. Les categories de competences sont definies **par camp** (chaque camp a sa propre grille)
9. La suppression d'une categorie supprime ses sous-categories et competences associees (cascade)
10. La suppression d'une competence supprime ses evaluations associees (avec confirmation)

---

## 7. Contraintes techniques

- **Stack** : PHP 8.2 / Slim 4 / MySQL / Bootstrap 5 (coherent avec l'existant)
- **Pattern** : Repository pattern pour la couche donnees
- **Frontend** : Pages PHP avec Bootstrap 5, JavaScript vanilla pour AJAX
- **Authentification** : Auth0 existant, verification de session
- **Base de donnees** : Meme instance MySQL Cloud SQL (`dbcoach`)
- **URLs** : Sous le prefixe `/camps` (ex: `/camps`, `/camps/1/players`, etc.)

---

## 8. Phases de livraison

### Phase 1 - MVP (prioritaire)
- [x] Document d'exigences
- [ ] Schema de base de donnees (SQL)
- [ ] Gestion des camps (CRUD)
- [ ] Gestion des joueurs (CRUD + generation de cas d'essai)
- [ ] Gestion des groupes (CRUD + attribution joueurs)
- [ ] Gestion de la grille de competences (categories 2 niveaux + competences)
- [ ] Gestion des seances
- [ ] Ecran d'evaluation (saisie des notes, mode cumulative)
- [ ] Ecran de resultats (tableau recapitulatif)
- [ ] Transfert de joueurs entre camps lies

### Phase 2 - Evaluateurs et collaboration
- [ ] Invitation d'evaluateurs par courriel
- [ ] Gestion des permissions evaluateurs (camps, groupes)
- [ ] Evaluations multi-evaluateurs (moyenne ou consolidation)
- [ ] Import CSV de joueurs
- [ ] Mode d'evaluation independant (evaluations separees par seance + moyennes)

### Phase 3 - Mode offline / PWA
- [ ] Service Worker pour cache de l'application
- [ ] Stockage local IndexedDB des donnees de seance
- [ ] File d'attente de synchronisation (sync queue)
- [ ] Indicateur visuel de statut de connexion et sync
- [ ] Install as app (manifest PWA)

### Phase 4 - Ameliorations avancees
- [ ] Export resultats (CSV, PDF)
- [ ] Graphique radar par joueur
- [ ] Templates de grilles de competences reutilisables entre camps
- [ ] Statistiques avancees et comparaisons entre camps/saisons
- [ ] Ponderation des competences dans le score total
- [ ] Historique et audit des modifications

---

## 9. Mode offline / PWA - Analyse technique

### Objectif
Permettre aux evaluateurs de saisir des notes dans des arenas/stades ou le reseau est mauvais ou inexistant.

### Technologie : PWA avec IndexedDB + Service Workers
Pas besoin d'app native. Le navigateur web supporte le stockage local et le fonctionnement hors-ligne.

### Fonctionnement
1. **Pre-chargement** : A l'ouverture d'une seance, toutes les donnees requises (joueurs, groupes, competences, notes existantes) sont telechargees et stockees dans IndexedDB
2. **Evaluation offline** : Les notes sont sauvegardees localement immediatement + tentative de sync serveur
3. **Sync automatique** : Quand le reseau revient, les notes en attente sont envoyees au serveur
4. **Indicateur visuel** : "X notes en attente de sync" / "Tout synchronise"

### Limitations connues
| Plateforme | Stockage max | Eviction | Notes |
|-----------|-------------|----------|-------|
| Android/Chrome | ~60% du disque | Rare | Tres fiable |
| iOS/Safari | ~50 MB | Apres ~7 jours sans utilisation | Suffisant pour nos donnees (quelques Ko), risque faible pendant un camp actif |
| Desktop | ~60% du disque | Rare | Tres fiable |

### Risques et mitigations
- **iOS IndexedDB instabilite** : Sauvegarder aussi en localStorage comme backup
- **Conflits de sync** : Chaque evaluation est unique (evaluateur + joueur + competence + seance), conflits improbables
- **Donnees legeres** : Un camp de 200 joueurs x 20 competences = ~4000 evaluations = quelques Ko

### Impact architectural
- Le backend PHP reste identique (endpoints JSON supplementaires pour la sync)
- Le travail est principalement frontend (Service Worker, IndexedDB, sync queue)
- Peut etre ajoute apres la phase 1 sans refactoring du backend

---

## 10. Questions ouvertes

1. **Sport specifique** : Faut-il des templates de competences pre-definis par sport?
2. **Nombre de joueurs** : Quel est le volume attendu par camp? (impacte la pagination)
3. **Impression** : Besoin d'imprimer des feuilles d'evaluation papier?
4. **Evaluateurs multiples sur meme competence** : Moyenne des notes de tous les evaluateurs, ou chaque evaluateur voit seulement ses propres notes?
