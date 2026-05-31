# Guide de soutenance & démonstration — SmartCampus

> Ce guide vous aide à **démontrer** chaque point de la grille d'évaluation et à
> **répondre** aux questions techniques. Le projet est noté sur votre **compréhension** :
> entraînez-vous à expliquer le code, pas seulement à le montrer.

## 1. Scénario de démonstration recommandé (≈ 8 min)

### Espace administrateur (`admin@smartcampus.fr`)
1. **Connexion** → tableau de bord (statistiques globales). *(Navigation, F01, F09)*
2. **Étudiants** : montrer recherche + filtre niveau + tri ; **Ajouter** un étudiant ;
   le **Modifier** ; ouvrir son **profil** ; le **Supprimer**. *(F03 + filtres = 4 pts)*
3. **Enseignants** : ajouter / modifier. *(F04)*
4. **Cours** : créer un cours, l'affecter à un enseignant. *(F05)*
5. **Emploi du temps** : ajouter un créneau **en provoquant un conflit de salle** →
   refus automatique. *(Règle métier conflit)*
6. **Statistiques** : montrer les graphiques. *(Bonus)*
7. **Utilisateurs** : désactiver puis réactiver un compte. *(Modération)*

### Espace enseignant (`m.ali@smartcampus.fr`)
8. **Mes cours** → ouvrir « Algorithmique avancée ».
9. **Saisir** une note, **modifier**-la, voir la **moyenne** se recalculer. *(F07)*
10. **Valider les notes** → montrer qu'une note validée 🔒 n'est **plus modifiable**.
    *(Règle métier verrouillage)*

### Espace étudiant (`emma.martin@edu.smartcampus.fr`)
11. **Catalogue** → tenter de **s'inscrire** à un cours **complet** → refus. *(Capacité)*
12. Tenter une inscription créant un **conflit horaire** → refus. *(Conflit étudiant)*
13. **Mes notes** → **Télécharger le relevé PDF**. *(Bonus PDF)*
14. **Messagerie** : envoyer un message à un enseignant ; montrer la **notification** 🔔.

## 2. Démonstration ciblée des 6 règles métier

| Règle | Comment la montrer | Message attendu |
|-------|--------------------|-----------------|
| Double inscription | Se réinscrire à un cours déjà suivi | « Vous êtes déjà inscrit à ce cours. » |
| Capacité max | S'inscrire à un cours plein (ex. INFO-201, capacité 3) | « Ce cours est complet… » |
| Prérequis | Étudiant sans INFO-203 s'inscrit à INFO-301 | « Prérequis non validés : INFO-203. » |
| Conflit (étudiant) | Deux cours au même créneau | « Conflit d'emploi du temps avec… » |
| Conflit (salle/prof) | Admin place 2 créneaux même salle/heure | « Conflit : la salle ou l'enseignant… » |
| Verrouillage notes | Modifier une note validée | « Cette note est validée et ne peut plus être modifiée. » |

## 3. Questions probables & réponses

**« Comment sécurisez-vous l'application ? »**
> Mots de passe hashés (bcrypt), requêtes préparées PDO (anti-injection SQL), sessions à
> cookie HttpOnly + SameSite, jeton CSRF sur les requêtes mutantes, contrôle des rôles
> côté serveur (RBAC), validation des entrées client **et** serveur, échappement HTML au
> rendu (anti-XSS). Code dans `api/core/Auth.php` et `Validator.php`.

**« Où se trouve la logique métier ? »**
> Centralisée dans `api/core/Academic.php` (moyennes, prérequis, conflits) et appliquée
> dans les contrôleurs `EnrollmentController`, `GradeController`, `ScheduleController`,
> toujours **avant** l'écriture en base, dans des transactions si nécessaire.

**« Comment fonctionne le routage de l'API ? »**
> `.htaccess` réécrit `/api/<route>` vers `index.php`, qui instancie un `Router` maison.
> Le routeur compare la méthode HTTP et les segments d'URL (avec paramètres `{id}`) à une
> table de routes et appelle `[Controller, méthode]`.

**« Comment la moyenne est-elle calculée ? »**
> Moyenne pondérée par les coefficients : `Σ(note × coef) / Σ(coef)` par cours
> (`Academic::courseAverage`). La moyenne générale est pondérée par les crédits ECTS
> (`DashboardController`). Résultat « Admis » si ≥ 10.

**« Pourquoi pas de framework / pourquoi des sessions ? »**
> Voir `RAPPORT-compromis-techniques.md` §1.1 et §1.2.

**« Que se passe-t-il si on supprime un étudiant / un enseignant ? »**
> Clés étrangères : suppression d'un étudiant → `CASCADE` sur ses inscriptions/notes ;
> suppression d'un enseignant → `SET NULL` sur ses cours (le cours subsiste sans responsable).

## 4. Versioning Git (à présenter)

- Montrer l'historique : `git log --oneline --graph`.
- Mettre en avant des **commits réguliers** par fonctionnalité et la **participation de
  chaque membre** (`git shortlog -sn`).
- Expliquer le workflow (branches par fonctionnalité, fusion sur `main`).

## 5. Checklist avant la démo

- [ ] WAMP démarré (Apache + MySQL au vert).
- [ ] Base `smartcampus` importée (schema + seed).
- [ ] `config.local.php` créé si MySQL a un mot de passe.
- [ ] Page `http://localhost/smartcampus/` accessible.
- [ ] Tester les 3 connexions de démo à l'avance.
- [ ] Avoir ce guide ouvert pour suivre le scénario.
