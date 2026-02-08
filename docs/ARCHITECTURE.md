# Architecture — dion.coach

## 1. Vue d'ensemble

Application web de coaching sportif (ultimate frisbee) hebergee sur **Google Cloud Run**. Stack PHP classique avec framework leger.

```
Navigateur  -->  Cloud Run (Apache + PHP)  -->  Cloud SQL (MySQL)
                        |
                   Auth0 (OAuth)
```

---

## 2. Stack technique

| Composant       | Technologie                          |
|-----------------|--------------------------------------|
| Langage         | PHP 8.2                              |
| Framework       | Slim 4 (micro-framework)             |
| Serveur web     | Apache (mod_rewrite)                 |
| Base de donnees | MySQL (Cloud SQL) — instance `dbcoach` |
| Auth            | Auth0 SDK PHP 8 (OAuth2 / OpenID)    |
| CSS             | Bootstrap 5.3 (CDN)                  |
| Email           | PHPMailer (SMTP Gmail)               |
| Conteneur       | Docker (PHP 8.2-apache)              |
| Hebergement     | Google Cloud Run                     |
| DNS             | Cloudflare -> Cloud Run domain mapping |
| Analytics       | Google Analytics (G-X60DDFMY6C)      |

---

## 3. Structure du projet

```
dion.coach/
├── Dockerfile                  # Image Docker (PHP 8.2 + Apache)
├── composer.json                # Dependances PHP
├── docs/                        # Documentation
│   ├── ARCHITECTURE.md          # Ce fichier
│   ├── howto_deploy.txt         # Commandes de deploiement
│   └── camps-selection/         # Specs du module Camps
│       ├── REQUIREMENTS.md
│       ├── PLAN.md
│       └── schema.sql
├── public/                      # Document root Apache
│   ├── index.php                # Point d'entree (bootstrap Slim)
│   ├── .htaccess                # Rewrite rules -> index.php
│   ├── css/
│   │   ├── theme.css            # Styles globaux
│   │   └── camps.css            # Styles module camps
│   ├── assets/
│   │   ├── logo_mark.png
│   │   └── logo_profil.png
│   ├── includes/                # Fragments HTML reutilisables
│   │   ├── head.php             # <head> (meta, Bootstrap CDN, GA)
│   │   ├── navbar.php           # Navigation principale
│   │   └── footer.php           # Pied de page + Bootstrap JS
│   └── pages/                   # Vues PHP (une par route)
│       ├── home.php
│       ├── nouvelles.php
│       ├── apropos.php
│       ├── contact.php
│       ├── callback.php         # Callback Auth0
│       ├── dashboard.php
│       ├── register.php
│       ├── privacy.php
│       ├── data-deletion.php
│       ├── terms.php
│       └── camps/
│           └── evaluate.php     # Prototype evaluation camps
└── src/                         # Code applicatif (autoload PSR-4: App\)
    ├── routes.php               # Definition de toutes les routes Slim
    ├── SendMail.php             # Envoi de courriels (PHPMailer)
    ├── Auth/
    │   ├── getAuth.php          # Factory Auth0 (config + instance)
    │   └── AuthService.php      # Logique find-or-create utilisateur
    └── Database/
        ├── Database.php         # Singleton PDO + helpers (fetch, execute)
        └── UserRepository.php   # CRUD utilisateurs + providers
```

---

## 4. Flux de requete

```
1. Requete HTTP
2. Apache recoit -> .htaccess rewrite vers index.php
3. index.php cree l'app Slim, charge routes.php
4. Slim match la route -> execute le handler
5. Le handler:
   a. Charge Auth0 (getAuth()) pour verifier la session
   b. Inclut la page PHP correspondante (public/pages/...)
   c. La page inclut head.php, navbar.php, footer.php
   d. Retourne le HTML via ob_start/ob_get_clean
```

---

## 5. Routes

| Methode    | URL                      | Auth requise | Description              |
|------------|--------------------------|--------------|--------------------------|
| GET        | `/`                      | Non          | Page d'accueil           |
| GET        | `/nouvelles`             | Non          | Page nouvelles           |
| GET        | `/a-propos`              | Non          | Page a propos            |
| GET, POST  | `/contact`               | Non          | Formulaire de contact    |
| GET        | `/login`                 | Non          | Redirect vers Auth0      |
| GET, POST  | `/callback`              | Non          | Callback Auth0           |
| GET, POST  | `/register`              | Non          | Inscription              |
| GET        | `/dashboard`             | Oui          | Tableau de bord          |
| GET        | `/logout`                | Oui          | Deconnexion Auth0        |
| GET        | `/privacy-policy`        | Non          | Politique vie privee     |
| GET        | `/data-deletion`         | Non          | Suppression de donnees   |
| GET        | `/terms`                 | Non          | Conditions utilisation   |
| GET, POST  | `/verify-email-required` | Non          | Verification courriel    |
| GET        | `/camps/evaluate`        | Oui          | Evaluation camps (proto) |

---

## 6. Authentification

- **Fournisseur** : Auth0 (tenant `ionultimate.auth0.com`)
- **Flux** : Authorization Code (OpenID Connect)
- **Session** : Geree par Auth0 SDK via cookies
- **Verification navbar** : `$_SESSION['user_email']`
- **Verification routes** : `getAuth()->getUser()` — redirige vers `/login` si null

---

## 7. Base de donnees

- **Instance** : Cloud SQL MySQL `dioncoach:us-east1:dbcoach`
- **Connexion** : Unix socket `/cloudsql/...` (Cloud Run natif)
- **Pattern** : Classe statique `Database` (singleton PDO) + Repository
- **Tables connues** : `users`, `user_providers`, `roles` (+ tables camps en cours)

---

## 8. Deploiement

### Prerequis
- Google Cloud SDK (`gcloud`) installe et authentifie
- Projet GCP : `dioncoach`

### Commandes

```bash
# 1. Selectionner le projet
gcloud config set project dioncoach

# 2. Builder et pousser l'image Docker
gcloud builds submit --tag us-east1-docker.pkg.dev/dioncoach/web/dion-coach:latest

# 3. Deployer sur Cloud Run
gcloud run deploy dion-coach \
  --image us-east1-docker.pkg.dev/dioncoach/web/dion-coach:latest \
  --region us-east1 \
  --allow-unauthenticated \
  --port=8080
```

### Setup initial (une seule fois)

```bash
gcloud services enable run.googleapis.com artifactregistry.googleapis.com cloudbuild.googleapis.com
gcloud artifacts repositories create web --repository-format=docker --location=us-east1
```

### Variables d'environnement Cloud Run

Pour ajouter des variables d'env au deploy, ajouter le flag :
```
--set-env-vars=CLE=VALEUR
```

---

## 9. Developpement local

```bash
# Avec Docker
docker build -t dion-coach .
docker run -p 8080:8080 dion-coach

# Avec PHP built-in server (sans Docker)
composer install
php -S localhost:8080 -t public
```

Note : la connexion BD utilise un socket Cloud SQL. En local, il faut soit utiliser le Cloud SQL Proxy, soit modifier temporairement `Database.php` pour une connexion TCP.

---

## 10. Module Camps de selection

Prototype en cours de developpement. Voir `docs/camps-selection/REQUIREMENTS.md` pour les specs completes.

**Etat actuel** : ecran d'evaluation avec donnees de test (localStorage), pas encore connecte a la BD.

**Phases prevues** :
1. MVP — CRUD camps, joueurs, groupes, competences, evaluation, resultats
2. Evaluateurs et collaboration
3. Mode offline / PWA
4. Ameliorations avancees (export, graphiques, stats)
