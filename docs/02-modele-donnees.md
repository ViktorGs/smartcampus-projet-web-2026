# Modèle de données — SmartCampus

Ce document présente le **modèle entité-association (MCD)** puis le **modèle relationnel**
qui en découle. Le script de création correspondant est `sql/schema.sql`.

## 1. Modèle entité-association (diagramme)

> Diagramme au format Mermaid (rendu automatiquement sur GitHub).

```mermaid
erDiagram
    USERS ||--o| STUDENT_PROFILES : "est (1-1)"
    USERS ||--o| TEACHER_PROFILES : "est (1-1)"
    USERS ||--o{ COURSES : "enseigne (0-n)"
    USERS ||--o{ ENROLLMENTS : "s'inscrit (0-n)"
    COURSES ||--o{ ENROLLMENTS : "reçoit (0-n)"
    USERS ||--o{ GRADES : "est noté (0-n)"
    COURSES ||--o{ GRADES : "porte (0-n)"
    COURSES ||--o{ SCHEDULE_SLOTS : "planifié sur (0-n)"
    COURSES ||--o{ PREREQUISITES : "exige (0-n)"
    USERS ||--o{ MESSAGES : "envoie/reçoit"
    USERS ||--o{ NOTIFICATIONS : "reçoit (0-n)"

    USERS {
        int id PK
        enum role
        varchar email UK
        varchar password_hash
        varchar first_name
        varchar last_name
        tinyint is_active
    }
    STUDENT_PROFILES {
        int user_id PK_FK
        varchar student_number UK
        varchar filiere
        enum niveau
        varchar group_td
        date date_inscription
    }
    TEACHER_PROFILES {
        int user_id PK_FK
        varchar employee_number UK
        varchar department
        enum grade
        varchar office
    }
    COURSES {
        int id PK
        varchar code UK
        varchar name
        int credits
        enum semester
        enum niveau
        int teacher_id FK
        int capacity
        tinyint is_archived
    }
    ENROLLMENTS {
        int id PK
        int student_id FK
        int course_id FK
        datetime enrolled_at
        enum status
    }
    GRADES {
        int id PK
        int student_id FK
        int course_id FK
        enum eval_type
        decimal value
        decimal coefficient
        tinyint is_locked
        int graded_by FK
    }
    SCHEDULE_SLOTS {
        int id PK
        int course_id FK
        tinyint day_of_week
        time start_time
        time end_time
        varchar room
    }
    PREREQUISITES {
        int course_id PK_FK
        int prerequisite_course_id PK_FK
    }
    MESSAGES {
        int id PK
        int sender_id FK
        int recipient_id FK
        varchar subject
        text body
        tinyint is_read
    }
    NOTIFICATIONS {
        int id PK
        int user_id FK
        varchar type
        varchar title
        tinyint is_read
    }
```

## 2. Description des entités et cardinalités

| Association | Entités | Cardinalités | Sens |
|-------------|---------|--------------|------|
| Est un étudiant | USERS – STUDENT_PROFILES | (0,1) – (1,1) | héritage 1-1 (un compte de rôle `student` possède un profil) |
| Est un enseignant | USERS – TEACHER_PROFILES | (0,1) – (1,1) | héritage 1-1 |
| Enseigne | USERS(enseignant) – COURSES | (0,n) – (0,1) | un enseignant responsable de plusieurs cours ; un cours a au plus un responsable |
| S'inscrit | USERS(étudiant) – COURSES | (0,n) – (0,n) | association porteuse : ENROLLMENTS |
| Est noté | USERS(étudiant) – COURSES | (0,n) – (0,n) | association porteuse : GRADES (avec type d'évaluation) |
| Planifié | COURSES – SCHEDULE_SLOTS | (0,n) – (1,1) | un cours a plusieurs créneaux |
| Exige | COURSES – COURSES | (0,n) – (0,n) | réflexive : PREREQUISITES |
| Échange | USERS – MESSAGES | (0,n) | un message a un émetteur et un destinataire |

**Choix de modélisation important — héritage des utilisateurs.**
Plutôt que trois tables séparées (admins/enseignants/étudiants), on utilise une table mère
`users` (identité + authentification communes) et deux tables « profil » spécialisées
(`student_profiles`, `teacher_profiles`) reliées en 1-1. Avantages : un seul mécanisme
d'authentification, des e-mails uniques sur l'ensemble des comptes, et une messagerie qui
référence simplement `users`. L'administrateur n'a pas de table profil (aucune donnée
spécifique).

## 3. Modèle relationnel

> Convention : <u>clé primaire soulignée</u>, `#` clé étrangère.

1. **users** (<u>id</u>, role, email, password_hash, first_name, last_name, gender, phone, is_active, created_at, updated_at)
2. **student_profiles** (<u>#user_id</u>, student_number, filiere, niveau, group_td, date_naissance, date_inscription, address, photo)
3. **teacher_profiles** (<u>#user_id</u>, employee_number, department, grade, office, hire_date)
4. **courses** (<u>id</u>, code, name, description, credits, semester, niveau, department, #teacher_id, capacity, is_archived, created_at)
5. **prerequisites** (<u>#course_id</u>, <u>#prerequisite_course_id</u>)
6. **enrollments** (<u>id</u>, #student_id, #course_id, enrolled_at, status) — *contrainte UNIQUE (student_id, course_id)*
7. **grades** (<u>id</u>, #student_id, #course_id, eval_type, value, coefficient, comment, is_locked, #graded_by, graded_at) — *UNIQUE (student_id, course_id, eval_type)*
8. **schedule_slots** (<u>id</u>, #course_id, day_of_week, start_time, end_time, room, group_td)
9. **messages** (<u>id</u>, #sender_id, #recipient_id, subject, body, is_read, sent_at, read_at)
10. **notifications** (<u>id</u>, #user_id, type, title, content, link, is_read, created_at)

## 4. Contraintes d'intégrité notables

- **Unicité** : `users.email`, `student_profiles.student_number`,
  `teacher_profiles.employee_number`, `courses.code`,
  `enrollments(student_id, course_id)`, `grades(student_id, course_id, eval_type)`.
- **Clés étrangères** avec `ON DELETE CASCADE` (suppression d'un utilisateur → ses
  inscriptions/notes) ou `ON DELETE SET NULL` (suppression d'un enseignant → ses cours
  restent mais sans responsable).
- **CHECK** : `grades.value` entre 0 et 20 ; `schedule_slots.end_time > start_time` ;
  `day_of_week` entre 1 et 7 ; un cours ne peut pas être son propre prérequis.
- **Moteur InnoDB** : nécessaire pour les clés étrangères et les transactions
  (utilisées lors des créations multi-tables, ex : compte + profil).
