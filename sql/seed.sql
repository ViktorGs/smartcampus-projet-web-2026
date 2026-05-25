-- =====================================================================
--  SmartCampus — Données de démonstration
--  À exécuter APRÈS schema.sql.
--  Mot de passe de TOUS les comptes de démo : Password123!
--  (le hash bcrypt ci-dessous correspond à ce mot de passe)
-- =====================================================================
USE smartcampus;

SET @PWD := '$2y$10$Xr7Z29.uxLCTGAKUmoz26ONQ.PIMh.UQ92P3q0OCy6FDkhdQSckKy';

-- ---------------------------------------------------------------------
-- UTILISATEURS
-- ---------------------------------------------------------------------
-- id 1 : administrateur
INSERT INTO users (role, email, password_hash, first_name, last_name, gender, phone) VALUES
('admin', 'admin@smartcampus.fr', @PWD, 'Alice', 'Admin', 'F', '0600000001');

-- id 2..5 : enseignants
INSERT INTO users (role, email, password_hash, first_name, last_name, gender, phone) VALUES
('teacher', 'm.ali@smartcampus.fr',     @PWD, 'Mohamed', 'Ali',    'M', '0600000002'),
('teacher', 'l.bensalem@smartcampus.fr',@PWD, 'Leila',   'Bensalem','F', '0600000003'),
('teacher', 'k.haddad@smartcampus.fr',  @PWD, 'Karim',   'Haddad',  'M', '0600000004'),
('teacher', 's.hamdi@smartcampus.fr',   @PWD, 'Sarah',   'Hamdi',   'F', '0600000005');

-- id 6..15 : étudiants
INSERT INTO users (role, email, password_hash, first_name, last_name, gender, phone) VALUES
('student', 'emma.martin@edu.smartcampus.fr',  @PWD, 'Emma',   'Martin',  'F', '0600000006'),
('student', 'lucas.dubois@edu.smartcampus.fr', @PWD, 'Lucas',  'Dubois',  'M', '0600000007'),
('student', 'chloe.bernard@edu.smartcampus.fr',@PWD, 'Chloé',  'Bernard', 'F', '0600000008'),
('student', 'hugo.petit@edu.smartcampus.fr',   @PWD, 'Hugo',   'Petit',   'M', '0600000009'),
('student', 'lea.robert@edu.smartcampus.fr',   @PWD, 'Léa',    'Robert',  'F', '0600000010'),
('student', 'nathan.richard@edu.smartcampus.fr',@PWD,'Nathan', 'Richard', 'M', '0600000011'),
('student', 'manon.durand@edu.smartcampus.fr', @PWD, 'Manon',  'Durand',  'F', '0600000012'),
('student', 'tom.moreau@edu.smartcampus.fr',   @PWD, 'Tom',    'Moreau',  'M', '0600000013'),
('student', 'jade.simon@edu.smartcampus.fr',   @PWD, 'Jade',   'Simon',   'F', '0600000014'),
('student', 'enzo.laurent@edu.smartcampus.fr', @PWD, 'Enzo',   'Laurent', 'M', '0600000015');

-- ---------------------------------------------------------------------
-- PROFILS ENSEIGNANTS
-- ---------------------------------------------------------------------
INSERT INTO teacher_profiles (user_id, employee_number, department, grade, office, hire_date) VALUES
(2, 'ENS0001', 'Informatique',   'Professeur',              'A201', '2015-09-01'),
(3, 'ENS0002', 'Mathématiques',  'Maître de conférences',   'B105', '2018-09-01'),
(4, 'ENS0003', 'Physique',       'Maître de conférences',   'C012', '2017-09-01'),
(5, 'ENS0004', 'Informatique',   'Maître assistant',        'A210', '2020-09-01');

-- ---------------------------------------------------------------------
-- PROFILS ÉTUDIANTS
-- ---------------------------------------------------------------------
INSERT INTO student_profiles (user_id, student_number, filiere, niveau, group_td, date_naissance, address) VALUES
(6,  'E2026001', 'Informatique', 'L2', 'GR-A', '2004-03-12', '15 rue des Universités, 75007 Paris'),
(7,  'E2026002', 'Informatique', 'L2', 'GR-A', '2004-07-22', '8 av. de la Gare, 75012 Paris'),
(8,  'E2026003', 'Informatique', 'L2', 'GR-B', '2003-11-05', '3 rue Lafayette, 75009 Paris'),
(9,  'E2026004', 'Énergie',      'L2', 'GR-A', '2004-01-30', '21 bd Voltaire, 75011 Paris'),
(10, 'E2026005', 'Énergie',      'L2', 'GR-B', '2004-05-18', '5 rue de Rivoli, 75004 Paris'),
(11, 'E2026006', 'Informatique', 'L3', 'GR-A', '2003-09-09', '44 rue du Temple, 75003 Paris'),
(12, 'E2026007', 'Informatique', 'L3', 'GR-A', '2003-12-25', '12 rue Oberkampf, 75011 Paris'),
(13, 'E2026008', 'Mathématiques','L3', 'GR-B', '2003-02-14', '7 rue de Vaugirard, 75006 Paris'),
(14, 'E2026009', 'Informatique', 'M1', 'GR-A', '2002-06-01', '30 av. des Champs, 75008 Paris'),
(15, 'E2026010', 'Informatique', 'M1', 'GR-A', '2002-08-19', '2 rue Mouffetard, 75005 Paris');

-- ---------------------------------------------------------------------
-- COURS  (id 1..8)
-- ---------------------------------------------------------------------
INSERT INTO courses (code, name, description, credits, semester, niveau, department, teacher_id, capacity) VALUES
('INFO-201', 'Algorithmique avancée',      'Structures de données, complexité, algorithmes de graphes.', 5, 'S3', 'L2', 'Informatique',  2, 3),
('INFO-203', 'Bases de données',           'Modèle relationnel, SQL, normalisation, transactions.',       4, 'S3', 'L2', 'Informatique',  5, 30),
('MATH-202', 'Mathématiques pour l''ingénieur', 'Algèbre linéaire, probabilités, statistiques.',          4, 'S3', 'L2', 'Mathématiques', 3, 30),
('PHYS-202', 'Physique des ondes',         'Ondes mécaniques et électromagnétiques, optique.',            3, 'S3', 'L2', 'Physique',      4, 30),
('INFO-301', 'Programmation Web dynamique','HTML/CSS/JS, PHP, MySQL, architecture client-serveur.',        5, 'S5', 'L3', 'Informatique',  2, 30),
('INFO-305', 'Réseaux et télécoms',        'Modèle OSI, TCP/IP, routage, sécurité réseau.',               4, 'S5', 'L3', 'Informatique',  5, 30),
('MATH-301', 'Optimisation',               'Programmation linéaire, recherche opérationnelle.',           3, 'S5', 'L3', 'Mathématiques', 3, 30),
('INFO-501', 'Machine Learning',           'Apprentissage supervisé/non supervisé, réseaux de neurones.', 6, 'S9', 'M1', 'Informatique',  2, 25);

-- ---------------------------------------------------------------------
-- PRÉREQUIS  (Web dynamique exige Bases de données ; ML exige Algo + Maths)
-- ---------------------------------------------------------------------
INSERT INTO prerequisites (course_id, prerequisite_course_id) VALUES
(5, 2),   -- INFO-301 nécessite INFO-203
(8, 1),   -- INFO-501 nécessite INFO-201
(8, 3);   -- INFO-501 nécessite MATH-202

-- ---------------------------------------------------------------------
-- EMPLOI DU TEMPS  (créneaux ; pensés SANS conflit de salle/enseignant)
--   jours : 1=Lun 2=Mar 3=Mer 4=Jeu 5=Ven
-- ---------------------------------------------------------------------
INSERT INTO schedule_slots (course_id, day_of_week, start_time, end_time, room, group_td) VALUES
(1, 1, '08:00', '10:00', 'B201', NULL),  -- Algo L2  Lundi
(2, 1, '10:15', '12:15', 'B105', NULL),  -- BDD  L2  Lundi
(3, 2, '08:00', '10:00', 'B201', NULL),  -- Maths L2 Mardi
(4, 2, '10:15', '12:15', 'C012', NULL),  -- Physique L2 Mardi
(5, 3, '08:00', '10:00', 'Lab1', NULL),  -- Web L3   Mercredi
(6, 3, '10:15', '12:15', 'A205', NULL),  -- Réseaux L3 Mercredi
(7, 4, '08:00', '10:00', 'B105', NULL),  -- Optim L3 Jeudi
(8, 5, '14:00', '17:00', 'Lab2', NULL);  -- ML M1    Vendredi

-- ---------------------------------------------------------------------
-- INSCRIPTIONS  (étudiants <-> cours, cohérentes avec leur niveau)
-- ---------------------------------------------------------------------
-- L2 Informatique (Emma 6, Lucas 7, Chloé 8) -> Algo, BDD, Maths
INSERT INTO enrollments (student_id, course_id) VALUES
(6,1),(6,2),(6,3),
(7,1),(7,2),(7,3),
(8,2),(8,3),(8,4),
-- L2 Énergie (Hugo 9, Léa 10) -> Maths, Physique
(9,3),(9,4),
(10,3),(10,4),
-- L3 Informatique (Nathan 11, Manon 12) -> Web, Réseaux, Optim
(11,5),(11,6),(11,7),
(12,5),(12,6),
-- L3 Maths (Tom 13) -> Optim
(13,7),
-- M1 Informatique (Jade 14, Enzo 15) -> ML
(14,8),
(15,8);

-- ---------------------------------------------------------------------
-- NOTES  (quelques évaluations ; certaines verrouillées)
-- ---------------------------------------------------------------------
INSERT INTO grades (student_id, course_id, eval_type, value, coefficient, is_locked, graded_by) VALUES
-- Emma (6) en Algo (1) : CC validés (verrouillés)
(6, 1, 'CC1', 15.50, 1.0, 1, 2),
(6, 1, 'CC2', 14.00, 1.0, 1, 2),
(6, 1, 'Examen', 16.00, 2.0, 0, 2),
-- Emma (6) en BDD (2)
(6, 2, 'CC1', 17.00, 1.0, 0, 5),
(6, 2, 'Projet', 18.00, 1.0, 0, 5),
-- Lucas (7) en Algo (1)
(7, 1, 'CC1', 11.00, 1.0, 1, 2),
(7, 1, 'CC2', 12.50, 1.0, 1, 2),
-- Nathan (11) en Web (5)
(11, 5, 'CC1', 14.00, 1.0, 0, 2),
(11, 5, 'Projet', 16.50, 2.0, 0, 2);

-- ---------------------------------------------------------------------
-- MESSAGES  (bonus)
-- ---------------------------------------------------------------------
INSERT INTO messages (sender_id, recipient_id, subject, body) VALUES
(2, 6, 'Rappel : rendu de projet Algo', 'Bonjour Emma, pensez à rendre le projet vendredi avant 18h.'),
(6, 2, 'RE: Rappel rendu projet Algo',  'Bonjour Professeur, c''est noté, merci !'),
(1, 11, 'Bienvenue sur SmartCampus',     'Votre compte étudiant L3 est actif. Bonne rentrée.');

-- ---------------------------------------------------------------------
-- NOTIFICATIONS  (bonus)
-- ---------------------------------------------------------------------
INSERT INTO notifications (user_id, type, title, content, link) VALUES
(6,  'grade',      'Nouvelle note publiée', 'Algorithmique avancée — CC1 : 15.5/20', 'grades'),
(6,  'message',    'Nouveau message',       'De M. Ali : Rappel rendu de projet',   'messages'),
(11, 'enrollment', 'Inscription confirmée', 'Vous êtes inscrit à Programmation Web dynamique', 'courses');
