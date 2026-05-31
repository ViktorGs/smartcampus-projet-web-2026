# Répartition du travail — SmartCampus

> ⚠️ **À COMPLÉTER PAR L'ÉQUIPE** : remplacez les `[Membre X]` par les vrais prénoms/noms
> des membres (3 à 4 étudiants). Cette répartition doit refléter la réalité du dépôt Git
> (commits, branches) — les enseignants croisent les deux.

## 1. Membres de l'équipe

| # | Nom / Prénom | Rôle principal |
|---|--------------|----------------|
| 1 | [Membre 1] | Chef de projet · Backend (API, sécurité) |
| 2 | [Membre 2] | Backend (règles métier, base de données) |
| 3 | [Membre 3] | Frontend (UI Bootstrap, pages) |
| 4 | [Membre 4] | Frontend (dashboards, intégration) + tests |

*(Si l'équipe est de 3 personnes, fusionnez les rôles 3 et 4.)*

## 2. Répartition prévisionnelle par module

| Module / Tâche | Responsable principal | Contributeurs |
|----------------|------------------------|---------------|
| Modèle de données (schéma SQL, MCD) | [Membre 2] | [Membre 1] |
| Noyau backend (Router, Auth, sécurité CSRF/RBAC) | [Membre 1] | — |
| Règles métier (inscriptions, notes, conflits) | [Membre 2] | [Membre 1] |
| API CRUD (étudiants, enseignants, cours) | [Membre 1] | [Membre 2] |
| Authentification & gestion de session | [Membre 1] | — |
| Frontend — noyau (api.js, routeur, UI) | [Membre 3] | [Membre 4] |
| Frontend — pages gestion (étudiants/profs/cours) | [Membre 3] | — |
| Frontend — espace étudiant (notes, inscriptions) | [Membre 4] | [Membre 3] |
| Tableaux de bord par rôle | [Membre 4] | — |
| Emploi du temps (UI + API) | [Membre 4] | [Membre 2] |
| Bonus — relevé PDF (FPDF) | [Membre 2] | — |
| Bonus — messagerie & notifications | [Membre 3] | [Membre 4] |
| Bonus — statistiques (Chart.js) | [Membre 4] | — |
| Documentation & rapport | Tous | — |
| Tests & démonstration | [Membre 4] | Tous |

## 3. Organisation du travail

- **Versioning** : dépôt Git/GitHub, commits réguliers et nommés clairement
  (préfixes `feat:`, `fix:`, `docs:`…). Voir `docs/GUIDE-SOUTENANCE.md` pour la
  présentation du versioning.
- **Branches** : `main` (stable) + branches par fonctionnalité fusionnées par
  *pull request* après relecture croisée.
- **Communication** : points d'avancement réguliers ; chaque membre doit pouvoir
  expliquer le code des autres (objectif pédagogique du module).

## 4. Bilan individuel (gabarit pour le PowerPoint)

Chaque membre rédige 4–6 lignes : ce que j'ai réalisé, ce que j'ai appris, mes
difficultés, ma contribution au collectif.

## 5. Bilan collectif (gabarit pour le PowerPoint)

- Ce qui a bien fonctionné (organisation, séparation front/back…).
- Ce qui a été difficile (règles métier, conflits horaires, sécurité…).
- Ce que l'on referait différemment.
