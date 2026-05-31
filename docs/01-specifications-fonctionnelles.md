# Spécifications fonctionnelles — SmartCampus

## 1. Introduction

### Objectif du projet
Concevoir et développer une plateforme web dynamique de **gestion académique** pour une
**école d'ingénieurs**. SmartCampus centralise les interactions entre l'administration,
les enseignants et les étudiants autour des cours, inscriptions, notes et emplois du temps.

### Contexte et public cible
- **Type d'établissement** : école d'ingénieurs (organisation en filières, niveaux L1→M2,
  semestres S1→S10, crédits ECTS, groupes de TD).
- **Public prioritaire** : étudiants et enseignants de l'établissement, pilotés par une
  administration pédagogique.
- **Contexte d'utilisation** : usage quotidien sur poste et mobile (interface responsive),
  consultation rapide (notes, emploi du temps) et gestion administrative (CRUD, validation).

## 2. Rôles utilisateurs et permissions

| Rôle | Accès autorisé |
|------|----------------|
| **Visiteur** | Consulter la page de connexion, créer un compte étudiant |
| **Étudiant** | Consulter ses cours/notes/emploi du temps, s'inscrire/se désinscrire, télécharger son relevé PDF, messagerie |
| **Enseignant** | Consulter ses cours et étudiants inscrits, saisir/modifier/valider les notes, emploi du temps, messagerie |
| **Administrateur** | Gérer étudiants, enseignants, cours, inscriptions, emploi du temps, utilisateurs (modération), statistiques |

**Justification des choix de rôles** : trois rôles suffisent à couvrir les interactions
réelles d'un établissement. L'administrateur porte la responsabilité de la cohérence des
données (référentiels étudiants/enseignants/cours) ; l'enseignant est cantonné à **ses**
cours (il ne peut noter que les étudiants qu'il encadre) ; l'étudiant n'agit que sur **ses**
propres données. Ce cloisonnement est imposé **côté serveur** (RBAC), pas seulement par
l'interface.

## 3. Fonctionnalités principales

| ID | Fonctionnalité | Description | Rôles |
|----|----------------|-------------|-------|
| F01 | Authentification | Connexion / déconnexion / inscription, sessions sécurisées | Tous |
| F02 | Gestion des utilisateurs | Création de comptes, gestion des rôles, modération (activer/désactiver/supprimer) | Admin |
| F03 | Gestion des étudiants | CRUD étudiant, profil, parcours académique, recherche/filtre/tri | Admin (gestion), Enseignant (consultation) |
| F04 | Gestion des enseignants | CRUD enseignant, association aux cours | Admin |
| F05 | Gestion des cours | CRUD cours, affectation enseignant, archivage, filtres/tri | Admin |
| F06 | Inscriptions aux cours | Inscription / désinscription, listes des inscrits | Étudiant, Admin |
| F07 | Gestion des notes | Saisie, modification, calcul de moyenne, validation finale (verrouillage) | Enseignant, Admin |
| F08 | Emploi du temps | Création de créneaux, consultation hebdomadaire selon le rôle | Admin (gestion), tous (consultation) |
| F09 | Tableaux de bord | Vue synthétique adaptée au rôle | Tous |
| F10 | Relevé de notes PDF *(bonus)* | Génération PDF du relevé d'un étudiant | Étudiant, Admin/Enseignant |
| F11 | Messagerie interne *(bonus)* | Échange de messages entre utilisateurs selon les rôles | Tous |
| F12 | Notifications *(bonus)* | Alertes (note publiée, inscription, validation, message) | Tous |
| F13 | Statistiques *(bonus)* | Graphiques académiques (réussite, répartitions) | Admin |

## 4. Règles métier implémentées

Le sujet exige « au minimum plusieurs règles métier réalistes ». **Six** sont implémentées
et vérifiées **côté serveur** (cf. `api/core/Academic.php` et `EnrollmentController`,
`GradeController`, `ScheduleController`) :

1. **Pas de double inscription** — un étudiant ne peut s'inscrire deux fois au même cours
   (contrainte d'unicité SQL + vérification applicative).
2. **Contrôle de capacité** — l'inscription est refusée si la capacité maximale du cours
   est atteinte.
3. **Prérequis académiques** — l'inscription à un cours exige d'avoir validé (moyenne ≥ 10)
   les cours prérequis.
4. **Détection des conflits d'emploi du temps** :
   - *côté inscription* : un étudiant ne peut suivre deux cours qui se chevauchent ;
   - *côté planification* : on ne peut pas placer deux créneaux dans la même salle ou pour
     le même enseignant au même moment.
5. **Verrouillage des notes après validation** — une note validée devient non modifiable
   et non supprimable.
6. **Restriction des actions selon les rôles** — chaque action sensible vérifie le rôle et,
   pour l'enseignant, la propriété du cours.

## 5. Cas d'utilisation principal — « S'inscrire à un cours »

- **Acteur principal** : étudiant connecté.
- **Préconditions** : l'étudiant est authentifié ; le cours existe et n'est pas archivé.
- **Scénario nominal** :
  1. L'étudiant recherche/filtre un cours dans le catalogue.
  2. Il clique sur « S'inscrire ».
  3. Le serveur vérifie successivement : double inscription, capacité, prérequis, conflit
     d'emploi du temps.
  4. Si toutes les règles passent, l'inscription est enregistrée et une notification est créée.
- **Scénarios alternatifs** : à chaque règle non respectée, l'inscription est refusée avec
  un message explicite (ex : « Ce cours est complet », « Prérequis non validés : INFO-203 »,
  « Conflit d'emploi du temps avec MATH-202 »).

## 6. Interfaces attendues

| Page | Contenu |
|------|---------|
| Connexion / Inscription | Formulaires d'authentification |
| Tableau de bord | Synthèse adaptée au rôle (stats, prochaines séances, notes récentes) |
| Étudiants / Enseignants | Listes filtrables + formulaires CRUD (modales) |
| Cours (catalogue) | Cartes filtrables/triables, inscription, gestion |
| Détail d'un cours | Infos, créneaux, grille de notes (enseignant), inscription (étudiant) |
| Mes notes | Notes par cours, moyennes, résultat, relevé PDF |
| Emploi du temps | Vue hebdomadaire Lundi→Vendredi |
| Messagerie | Reçus / envoyés / composition |
| Statistiques | Graphiques (Chart.js) |
| Utilisateurs | Modération des comptes |

## 7. Contraintes techniques

- Base de données relationnelle **MySQL**.
- Backend **PHP** organisé en API REST, séparé du frontend.
- Frontend **HTML/CSS/JavaScript + Bootstrap**.
- Validation des entrées **côté client ET serveur**.
- Sécurité : sessions, CSRF, RBAC, requêtes préparées, hashage des mots de passe.
- Interface **responsive** (testée mobile et desktop).

## 8. Critères de validation

- [x] Inscription et authentification fonctionnelles
- [x] Processus d'inscription aux cours complet (avec règles métier)
- [x] Ajout/retrait de cours et de données académiques
- [x] Modération de la plateforme par l'administrateur
- [x] Interface claire et responsive
- [x] Calcul automatique des moyennes et résultats
