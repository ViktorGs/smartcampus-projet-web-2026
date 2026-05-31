# Rapport de compromis techniques et décisions de conception — SmartCampus

> Objectif (sujet) : montrer une démarche d'ingénieur — analyser des contraintes, prendre
> des décisions, identifier les limites. Il ne s'agit pas de présenter un projet « parfait ».

## 1. Décisions de conception majeures

### 1.1 Sessions PHP plutôt que JWT
**Choix** : authentification par session PHP + cookie `HttpOnly`/`SameSite=Lax`.
**Justification** : l'application est servie en **même origine** (Apache sert le front et
l'API). Les sessions évitent la gestion d'expiration/stockage des tokens côté client et
réduisent la surface d'attaque (cookie inaccessible au JS). Le JWT aurait ajouté de la
complexité sans bénéfice ici.
**Compromis** : moins adapté à une API multi-clients (mobile natif) — acceptable dans notre
périmètre.

### 1.2 Mini-noyau MVC plutôt qu'un framework
**Choix** : routeur + contrôleurs maison (≈ 6 classes de cœur).
**Justification** : le sujet déconseille les générateurs produisant l'essentiel de
l'architecture, et valorise la **compréhension**. Chaque mécanisme est court et explicable.
**Compromis** : on réécrit des briques fournies par les frameworks (routage, validation),
mais on garde la maîtrise totale du code.

### 1.3 Héritage `users` + tables profil (1-1)
**Choix** : une table `users` commune + `student_profiles` / `teacher_profiles`.
**Justification** : authentification unifiée, e-mails uniques sur tous les comptes,
messagerie qui référence simplement `users`.
**Alternative écartée** : trois tables totalement séparées → duplication de la logique
d'authentification et complications pour les relations transverses.

### 1.4 Règles métier centralisées dans `core/Academic.php`
**Choix** : moyennes, prérequis et conflits horaires dans une classe dédiée.
**Justification** : éviter la duplication (utilisées par plusieurs contrôleurs) et avoir
**un seul endroit** où la règle est définie → plus facile à tester et à expliquer.

### 1.5 Frontend en modules ES sans build
**Choix** : JavaScript natif (ES modules), pas de bundler (Webpack/Vite).
**Justification** : pas d'étape de compilation → projet **exécutable directement** sous
WAMP, plus simple à corriger et à comprendre.
**Compromis** : pas d'optimisation de bundle ; acceptable pour la taille du projet.

### 1.6 Vendors en local plutôt que CDN
**Choix** : Bootstrap, Bootstrap Icons et Chart.js téléchargés dans `assets/vendor/`.
**Justification** : la **démonstration fonctionne hors-ligne** et reste reproductible.
**Compromis** : ~900 Ko de fichiers versionnés — négligeable.

## 2. Fonctionnalités envisagées puis simplifiées / abandonnées

- **Suivi des présences (QR code)** : prévu en option, non retenu pour se concentrer sur
  les fonctionnalités obligatoires et trois bonus solides (PDF, messagerie, stats).
- **Notifications en temps réel (WebSocket)** : remplacées par un **polling** simple
  (rafraîchissement périodique) — suffisant et bien plus simple à déployer sous WAMP.
- **Upload de photos de profil** : champ prévu dans le schéma (`photo`) mais non câblé à
  une UI d'upload, faute de priorité.

## 3. Difficultés techniques rencontrées

- **Génération PDF (FPDF)** : PDF corrompu car le dossier de polices manquait et une alerte
  PHP s'insérait avant le flux binaire. Résolu en ajoutant `api/lib/font/` et en
  désactivant l'affichage des erreurs sur cet endpoint.
- **Détection des conflits horaires** : exprimer le chevauchement en SQL
  (`a.start < b.end AND a.end > b.start`) et distinguer conflit *ressource* (salle/prof) et
  conflit *étudiant* (deux cours suivis).
- **Routage sans framework** : gérer les segments dynamiques (`{id}`) et la compatibilité
  `.htaccess` (Apache) vs serveur intégré PHP (mode test).

## 4. Limites actuelles du système

- **Pas de pagination** sur les listes (suffisant pour le volume de démonstration, à
  ajouter pour de grands effectifs).
- **Sécurité « pédagogique »** : pas de limitation du nombre de tentatives de connexion
  (rate limiting), pas de HTTPS en local, pas de politique de mot de passe avancée.
- **Moyenne générale** pondérée par les crédits ECTS, mais sans gestion fine des
  compensations/rattrapages propres à chaque cursus.
- **Emploi du temps** hebdomadaire générique (pas de gestion de dates calendaires ni de
  semaines A/B).

## 5. Pistes d'évolution

Pagination et recherche serveur avancée, présences/QR code, export Excel, calendrier
interactif, notifications temps réel, tests automatisés (PHPUnit / Playwright).
