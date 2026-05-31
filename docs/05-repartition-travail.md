# Répartition du travail — SmartCampus

## 1. Membres de l'équipe

| # | Nom / Prénom | Rôle principal |
|---|--------------|----------------|
| 1 | Viktor GOUSSOT | Chef de projet · Backend (noyau API, authentification, sécurité CSRF/RBAC) |
| 2 | Nicolas CARMINATI | Backend (modèle de données, règles métier, contrôleurs CRUD) |
| 3 | Louis PEZE | Frontend (coquille SPA, design Bootstrap, pages gestion) |
| 4 | Alexis OSORIO | Frontend (tableaux de bord, emploi du temps, statistiques) + tests |

## 2. Répartition par module

| Module / Tâche | Responsable principal | Contributeurs |
|----------------|-----------------------|---------------|
| Conception modèle de données (MCD, schéma SQL) | Nicolas CARMINATI | Viktor GOUSSOT |
| Noyau backend (Router, Auth, sécurité CSRF/RBAC) | Viktor GOUSSOT | — |
| Règles métier (double inscription, capacités, prérequis, conflits, verrouillage notes) | Nicolas CARMINATI | Viktor GOUSSOT |
| API CRUD (utilisateurs, étudiants, enseignants, cours) | Viktor GOUSSOT | Nicolas CARMINATI |
| API inscriptions et notes | Nicolas CARMINATI | Viktor GOUSSOT |
| Authentification, sessions, jeton CSRF | Viktor GOUSSOT | — |
| Frontend — noyau (api.js, routeur SPA, UI, auth) | Louis PEZE | Alexis OSORIO |
| Frontend — pages gestion (étudiants, enseignants, cours) | Louis PEZE | — |
| Frontend — espace étudiant (mes cours, notes, profil) | Alexis OSORIO | Louis PEZE |
| Tableaux de bord par rôle | Alexis OSORIO | — |
| Emploi du temps (UI + API) | Alexis OSORIO | Nicolas CARMINATI |
| Filtrage / tri des cours | Louis PEZE | Viktor GOUSSOT |
| Bonus — relevé de notes PDF (FPDF) | Nicolas CARMINATI | — |
| Bonus — messagerie & notifications | Louis PEZE | Alexis OSORIO |
| Bonus — statistiques (Chart.js) | Alexis OSORIO | — |
| Documentation (specs, MCD, archi, wireframes, compromis) | Tous | — |
| Tests fonctionnels & préparation démonstration | Alexis OSORIO | Tous |

## 3. Organisation du travail

- **Versioning** : dépôt Git/GitHub `smartcampus-projet-web-2026`, commits réguliers
  préfixés par convention (`feat:`, `fix:`, `docs:`, `chore:`). Voir
  `docs/GUIDE-SOUTENANCE.md` pour la présentation Git.
- **Branches** : `main` stable ; branches courtes par fonctionnalité, fusionnées par
  *pull request* après relecture croisée.
- **Communication** : points d'avancement réguliers en présentiel et via messagerie
  d'équipe ; chaque membre relit le code des autres pour pouvoir l'expliquer en
  soutenance (objectif pédagogique du module).
- **Outils** : VS Code, WAMP local, MySQL Workbench, Figma (wireframes), Canva
  (PowerPoint).

## 4. Bilan individuel

### Viktor GOUSSOT (chef de projet, backend)
- **Réalisé** : noyau backend (routeur REST, connexion PDO, validateur, réponses
  JSON), authentification par session + cookie HttpOnly, jeton CSRF, contrôle d'accès
  par rôle (RBAC), CRUD principal (utilisateurs, étudiants, enseignants, cours).
- **Appris** : architecture MVC sans framework, sécurité applicative (CSRF, XSS, SQL
  injection), gestion d'équipe et revue de code.
- **Difficultés** : routage des segments dynamiques (`{id}`) en compatibilité Apache
  `.htaccess` et serveur PHP intégré (mode test).
- **Contribution collective** : pilotage du projet, revues de code, intégration.

### Nicolas CARMINATI (backend, base de données, règles métier)
- **Réalisé** : MCD + modèle relationnel, schéma SQL avec contraintes et clés
  étrangères, jeu de données de démonstration, règles métier centralisées dans
  `core/Academic.php` (calcul des moyennes pondérées par ECTS, détection de conflits
  horaires en SQL, vérification des prérequis), génération PDF FPDF du relevé.
- **Appris** : modélisation académique réaliste, écriture de règles SQL non triviales
  (chevauchement d'intervalles), gestion d'erreurs côté serveur.
- **Difficultés** : génération PDF cassée par un warning PHP avant le flux binaire
  (résolu en désactivant les erreurs sur cet endpoint et ajoutant le dossier `font/`).
- **Contribution collective** : référent base de données et règles métier.

### Louis PEZE (frontend — noyau et pages de gestion)
- **Réalisé** : coquille SPA (`index.html`, routeur JS, gestion d'état, client REST
  avec CSRF), pages de gestion (étudiants, enseignants, cours), recherche et
  filtrage des cours, messagerie interne, design Bootstrap responsive.
- **Appris** : modules ES natifs, fetch + sessions, design system Bootstrap.
- **Difficultés** : cohérence entre les états (cache local) et la base après une
  modification simultanée par plusieurs utilisateurs ; résolu par rafraîchissement
  ciblé après chaque mutation.
- **Contribution collective** : référent ergonomie et accessibilité.

### Alexis OSORIO (frontend — dashboards, EDT, stats, tests)
- **Réalisé** : tableaux de bord adaptés aux 3 rôles, page emploi du temps avec
  détection visuelle des conflits, page « mes cours » et notes côté étudiant,
  statistiques avec Chart.js, scénarios de tests fonctionnels manuels.
- **Appris** : visualisation de données (Chart.js), conception d'interfaces
  contextuelles par rôle.
- **Difficultés** : présentation d'un emploi du temps lisible sur petit écran ;
  résolu par une vue listée en mobile et une grille en desktop.
- **Contribution collective** : préparation du scénario de démonstration et des
  jeux de données de présentation.

## 5. Bilan collectif

- **Ce qui a bien fonctionné** : séparation nette front/back, conventions de commit
  partagées, mini-noyau MVC clair que chaque membre peut expliquer, choix d'un
  contexte d'établissement précis (école d'ingénieurs) qui a guidé les décisions
  fonctionnelles.
- **Ce qui a été difficile** : modélisation des règles métier transverses
  (notamment les conflits horaires étudiant *et* ressource), gestion fine des
  permissions (3 rôles × N actions), choix d'écarter certaines fonctionnalités
  séduisantes mais hors-périmètre (QR code, WebSocket temps réel).
- **Ce que l'on referait différemment** : mettre en place plus tôt un suivi des
  régressions (tests automatisés), commencer par la maquette des dashboards (point
  de convergence de toutes les fonctionnalités), prévoir la pagination dès le départ.
