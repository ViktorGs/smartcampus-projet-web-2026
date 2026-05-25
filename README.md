# SmartCampus — Plateforme de gestion académique

> Projet Web dynamique 2026 (ING2) — Sujet 2 « SmartCampus »
> Contexte retenu : **école d'ingénieurs** (filières, semestres, crédits ECTS, groupes de TD).

SmartCampus est une application web dynamique permettant à trois types d'utilisateurs
(**administrateur**, **enseignant**, **étudiant**) de collaborer autour des activités
académiques : gestion des étudiants/enseignants/cours, inscriptions, notes, emploi du
temps, messagerie et notifications.

L'application repose sur une **architecture client–serveur** avec une séparation nette
frontend / backend :

| Couche      | Technologies |
|-------------|--------------|
| Frontend    | HTML5, CSS3, JavaScript (ES modules), **Bootstrap 5**, Chart.js |
| Backend     | **PHP 8** (API REST, sans framework), architecture MVC légère |
| Base de données | **MySQL 8 / MariaDB** (PDO, requêtes préparées) |
| Génération PDF | FPDF (relevé de notes) |

---

## 1. Prérequis

- **WAMP** (ou XAMPP) avec **PHP ≥ 8.0** et **MySQL ≥ 8** (ou MariaDB ≥ 10.4).
- Un navigateur moderne (Chrome, Edge, Firefox).
- Aucune connexion internet n'est nécessaire : Bootstrap, Bootstrap Icons et Chart.js
  sont fournis en local dans `assets/vendor/`.

## 2. Installation (WAMP, Windows)

### Étape 1 — Copier le projet
Copiez le dossier `smartcampus/` dans le répertoire web de WAMP :
```
C:\wamp64\www\smartcampus
```
(Pour XAMPP : `C:\xampp\htdocs\smartcampus`.)

### Étape 2 — Créer la base de données
Deux méthodes au choix.

**a) Via phpMyAdmin** (le plus simple)
1. Démarrez WAMP, ouvrez `http://localhost/phpmyadmin`.
2. Onglet **Importer** → choisissez `sql/schema.sql` → Exécuter.
3. Onglet **Importer** → choisissez `sql/seed.sql` → Exécuter.

**b) En ligne de commande**
```bash
mysql -u root -p < sql/schema.sql
mysql -u root -p < sql/seed.sql
```

### Étape 3 — Configurer l'accès MySQL
Si votre compte `root` MySQL a un **mot de passe** (par défaut WAMP : vide) :
1. Copiez `api/config/config.local.example.php` en `api/config/config.local.php`.
2. Renseignez votre mot de passe dans `DB_PASS`.

Ce fichier `config.local.php` n'est **pas** versionné (voir `.gitignore`) afin de ne
jamais publier d'identifiants. Si `root` n'a pas de mot de passe, aucune action n'est
nécessaire (les valeurs par défaut conviennent).

### Étape 4 — Lancer
Ouvrez : **`http://localhost/smartcampus/`**

> Le projet utilise `mod_rewrite` (activé par défaut sous WAMP/XAMPP) pour router
> l'API via `api/.htaccess`. Si l'API renvoie des 404, activez le module `rewrite`
> d'Apache (menu WAMP → Apache → Modules Apache → `rewrite_module`).

## 3. Comptes de démonstration

Tous les comptes ont le mot de passe : **`Password123!`**

| Rôle | E-mail | Description |
|------|--------|-------------|
| Administrateur | `admin@smartcampus.fr` | Gère tout le système |
| Enseignant | `m.ali@smartcampus.fr` | Responsable de 3 cours (Algo, Web, ML) |
| Enseignant | `l.bensalem@smartcampus.fr` | Mathématiques |
| Étudiant | `emma.martin@edu.smartcampus.fr` | L2 Informatique, a des notes |
| Étudiant | `nathan.richard@edu.smartcampus.fr` | L3 Informatique |

Vous pouvez aussi créer un nouveau compte étudiant via l'onglet **Inscription**.

## 4. Structure du projet

```
smartcampus/
├── index.html              # Page unique (SPA) : auth + application
├── assets/
│   ├── css/style.css        # Styles personnalisés
│   ├── js/                  # Frontend (modules ES)
│   │   ├── api.js           # Client REST (fetch + CSRF)
│   │   ├── auth.js, store.js, router.js, ui.js, app.js
│   │   └── pages/           # Une page = un module (dashboard, students, courses…)
│   └── vendor/              # Bootstrap, Bootstrap Icons, Chart.js (locaux)
├── api/                     # Backend PHP (API REST)
│   ├── index.php            # Front controller (table des routes)
│   ├── .htaccess            # Réécriture des URL
│   ├── config/              # Configuration + connexion PDO
│   ├── core/                # Auth, Router, Validator, Response, Academic…
│   ├── controllers/         # Un contrôleur par ressource
│   └── lib/fpdf.php         # Génération PDF
├── sql/
│   ├── schema.sql           # Création des tables + contraintes
│   └── seed.sql             # Données de démonstration
├── docs/                    # Conception : specs, MCD, architecture, wireframes…
└── README.md
```

## 5. Documentation

Tous les documents de conception et le rapport technique sont dans `docs/` :

- `01-specifications-fonctionnelles.md` — rôles, fonctionnalités, cas d'usage
- `02-modele-donnees.md` — modèle entité-association + modèle relationnel
- `03-architecture.md` — architecture client-serveur, flux des requêtes
- `04-wireframes-storyboard.md` — maquettes et parcours utilisateurs
- `05-repartition-travail.md` — organisation de l'équipe
- `RAPPORT-compromis-techniques.md` — décisions de conception, compromis, limites
- `GUIDE-SOUTENANCE.md` — points clés pour la démonstration et l'oral

## 6. Sécurité (résumé)

- Mots de passe **hashés** (bcrypt via `password_hash`).
- **Requêtes préparées** PDO partout → anti-injection SQL.
- **Sessions** PHP avec cookie `HttpOnly` + `SameSite=Lax`.
- **Jeton CSRF** exigé sur toutes les requêtes modifiant des données.
- **Contrôle des rôles (RBAC)** côté serveur avant chaque action sensible.
- **Validation** systématique des entrées côté serveur (et côté client).
- Échappement HTML au rendu côté client (anti-XSS).
