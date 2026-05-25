-- =====================================================================
--  SmartCampus — Schéma de la base de données
--  École d'ingénieurs : filières, semestres, crédits ECTS, groupes de TD
--  SGBD : MySQL 8 / MariaDB (XAMPP)  —  Moteur : InnoDB (clés étrangères)
-- =====================================================================
--  Ce script crée la base, toutes les tables, les contraintes (clés
--  primaires/étrangères, unicité) et les index. Il est ré-exécutable :
--  on supprime puis recrée la base à chaque lancement.
-- =====================================================================

DROP DATABASE IF EXISTS smartcampus;
CREATE DATABASE smartcampus
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE smartcampus;

-- ---------------------------------------------------------------------
-- 1. UTILISATEURS  (table mère commune aux 3 rôles)
--    Un seul compte = une seule ligne ici. Les informations propres
--    aux étudiants / enseignants sont dans des tables "profil" liées.
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    role            ENUM('admin','teacher','student') NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,            -- mot de passe hashé (bcrypt)
    first_name      VARCHAR(80)  NOT NULL,
    last_name       VARCHAR(80)  NOT NULL,
    gender          ENUM('M','F','Autre') DEFAULT NULL,
    phone           VARCHAR(30)  DEFAULT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,   -- compte désactivable (modération)
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. PROFIL ÉTUDIANT  (extension 1-1 de users)
-- ---------------------------------------------------------------------
CREATE TABLE student_profiles (
    user_id         INT PRIMARY KEY,
    student_number  VARCHAR(20)  NOT NULL UNIQUE,      -- ex: E2026045
    filiere         VARCHAR(80)  NOT NULL,             -- ex: Informatique, Énergie...
    niveau          ENUM('L1','L2','L3','M1','M2') NOT NULL,
    group_td        VARCHAR(20)  DEFAULT NULL,         -- ex: GR-A, GR-B
    date_naissance  DATE         DEFAULT NULL,
    date_inscription DATE        NOT NULL DEFAULT (CURRENT_DATE),
    address         VARCHAR(255) DEFAULT NULL,
    photo           VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_student_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. PROFIL ENSEIGNANT  (extension 1-1 de users)
-- ---------------------------------------------------------------------
CREATE TABLE teacher_profiles (
    user_id         INT PRIMARY KEY,
    employee_number VARCHAR(20)  NOT NULL UNIQUE,      -- ex: ENS0007
    department      VARCHAR(80)  NOT NULL,             -- ex: Informatique
    grade           ENUM('Professeur','Maître de conférences','Maître assistant','Vacataire')
                    NOT NULL DEFAULT 'Maître assistant',
    office          VARCHAR(40)  DEFAULT NULL,         -- ex: Bureau A201
    hire_date       DATE         DEFAULT NULL,
    CONSTRAINT fk_teacher_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. COURS  (matière enseignée, rattachée à un enseignant responsable)
-- ---------------------------------------------------------------------
CREATE TABLE courses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(20)  NOT NULL UNIQUE,      -- ex: INFO-301
    name            VARCHAR(120) NOT NULL,
    description     TEXT         DEFAULT NULL,
    credits         INT          NOT NULL DEFAULT 3,   -- crédits ECTS
    semester        ENUM('S1','S2','S3','S4','S5','S6','S7','S8','S9','S10') NOT NULL,
    niveau          ENUM('L1','L2','L3','M1','M2') NOT NULL,
    department      VARCHAR(80)  NOT NULL,
    teacher_id      INT          DEFAULT NULL,         -- enseignant responsable
    capacity        INT          NOT NULL DEFAULT 30,  -- capacité max (règle métier)
    is_archived     TINYINT(1)   NOT NULL DEFAULT 0,   -- archivage semestre passé
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_course_teacher FOREIGN KEY (teacher_id)
        REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_course_filter (niveau, semester, department)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. PRÉREQUIS  (un cours peut exiger d'autres cours validés au préalable)
--    Règle métier : gestion des prérequis académiques.
-- ---------------------------------------------------------------------
CREATE TABLE prerequisites (
    course_id              INT NOT NULL,
    prerequisite_course_id INT NOT NULL,
    PRIMARY KEY (course_id, prerequisite_course_id),
    CONSTRAINT fk_prereq_course FOREIGN KEY (course_id)
        REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_prereq_required FOREIGN KEY (prerequisite_course_id)
        REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT chk_prereq_not_self CHECK (course_id <> prerequisite_course_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. INSCRIPTIONS  (association étudiant <-> cours)
--    UNIQUE(student_id, course_id) => règle "pas de double inscription".
-- ---------------------------------------------------------------------
CREATE TABLE enrollments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    course_id   INT NOT NULL,
    enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status      ENUM('active','dropped') NOT NULL DEFAULT 'active',
    CONSTRAINT fk_enroll_student FOREIGN KEY (student_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_enroll_course FOREIGN KEY (course_id)
        REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT uq_enroll UNIQUE (student_id, course_id)   -- blocage double inscription
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. NOTES  (évaluations d'un étudiant dans un cours)
--    is_locked = TRUE après validation finale => non modifiable.
-- ---------------------------------------------------------------------
CREATE TABLE grades (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT NOT NULL,
    course_id    INT NOT NULL,
    eval_type    ENUM('CC1','CC2','DS','Projet','Examen') NOT NULL,
    value        DECIMAL(4,2) NOT NULL,                 -- note /20
    coefficient  DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    comment      VARCHAR(255) DEFAULT NULL,
    is_locked    TINYINT(1)   NOT NULL DEFAULT 0,        -- verrouillage après validation
    graded_by    INT          DEFAULT NULL,             -- enseignant ayant saisi
    graded_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_grade_student FOREIGN KEY (student_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_grade_course FOREIGN KEY (course_id)
        REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_grade_teacher FOREIGN KEY (graded_by)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_grade_value CHECK (value >= 0 AND value <= 20),
    CONSTRAINT uq_grade UNIQUE (student_id, course_id, eval_type),  -- une note par type
    INDEX idx_grade_course (course_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. EMPLOI DU TEMPS  (créneaux d'un cours dans la semaine)
--    Sert à la détection de conflits (salle, enseignant, groupe).
-- ---------------------------------------------------------------------
CREATE TABLE schedule_slots (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    course_id   INT NOT NULL,
    day_of_week TINYINT NOT NULL,                       -- 1=Lundi ... 6=Samedi
    start_time  TIME NOT NULL,
    end_time    TIME NOT NULL,
    room        VARCHAR(40) NOT NULL,
    group_td    VARCHAR(20) DEFAULT NULL,               -- créneau pour un groupe précis
    CONSTRAINT fk_slot_course FOREIGN KEY (course_id)
        REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT chk_slot_day CHECK (day_of_week BETWEEN 1 AND 7),
    CONSTRAINT chk_slot_time CHECK (end_time > start_time),
    INDEX idx_slot_day (day_of_week)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. MESSAGES  (messagerie interne — bonus)
-- ---------------------------------------------------------------------
CREATE TABLE messages (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    sender_id    INT NOT NULL,
    recipient_id INT NOT NULL,
    subject      VARCHAR(150) NOT NULL,
    body         TEXT NOT NULL,
    is_read      TINYINT(1) NOT NULL DEFAULT 0,
    sent_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at      DATETIME DEFAULT NULL,
    CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_recipient FOREIGN KEY (recipient_id)
        REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_msg_recipient (recipient_id, is_read)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. NOTIFICATIONS  (évènements académiques — bonus)
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    type        VARCHAR(40) NOT NULL,                   -- grade, enrollment, schedule, message...
    title       VARCHAR(150) NOT NULL,
    content     VARCHAR(255) DEFAULT NULL,
    link        VARCHAR(150) DEFAULT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user (user_id, is_read)
) ENGINE=InnoDB;
