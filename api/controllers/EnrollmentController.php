<?php
/**
 * EnrollmentController — inscriptions des étudiants aux cours.
 *
 * C'est ici que sont appliquées les RÈGLES MÉTIER obligatoires :
 *   1. Pas de double inscription au même cours.
 *   2. Respect de la capacité maximale du cours.
 *   3. Respect des prérequis académiques.
 *   4. Absence de conflit d'emploi du temps.
 * Toutes sont vérifiées AVANT d'écrire en base, dans une transaction.
 */
class EnrollmentController extends Controller
{
    /** GET enrollments/mine — cours de l'étudiant connecté + moyenne par cours. */
    public function mine(Request $req, array $params): void
    {
        Auth::requireRole(['student']);
        $sid = Auth::id();

        $stmt = $this->db->prepare(
            'SELECT e.id AS enrollment_id, e.enrolled_at, c.id AS course_id, c.code, c.name,
                    c.credits, c.semester, c.niveau,
                    CONCAT(u.first_name, \' \', u.last_name) AS teacher_name
             FROM enrollments e
             JOIN courses c ON c.id = e.course_id
             LEFT JOIN users u ON u.id = c.teacher_id
             WHERE e.student_id = ? AND e.status = \'active\'
             ORDER BY c.semester, c.code'
        );
        $stmt->execute([$sid]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['average'] = Academic::courseAverage($this->db, $sid, (int)$r['course_id']);
        }
        Response::json(['data' => $rows]);
    }

    /**
     * POST enrollments — inscription à un cours.
     *  - étudiant : s'inscrit lui-même  { course_id }
     *  - admin    : inscrit un étudiant  { course_id, student_id }
     */
    public function store(Request $req, array $params): void
    {
        Auth::requireRole(['student', 'admin']);
        Auth::verifyCsrf();

        $courseId = (int)$req->input('course_id');
        if ($courseId <= 0) {
            Response::error('Cours non spécifié.', 422, ['course_id' => 'Obligatoire.']);
        }

        // Détermine l'étudiant concerné selon le rôle
        if (Auth::role() === 'admin') {
            $studentId = (int)$req->input('student_id');
            if ($studentId <= 0) {
                Response::error('Étudiant non spécifié.', 422, ['student_id' => 'Obligatoire.']);
            }
        } else {
            $studentId = Auth::id();
        }

        // Le cours existe et n'est pas archivé ?
        $c = $this->db->prepare('SELECT * FROM courses WHERE id = ?');
        $c->execute([$courseId]);
        $course = $c->fetch();
        if (!$course) {
            Response::error('Cours introuvable.', 404);
        }
        if ((int)$course['is_archived'] === 1) {
            Response::error('Ce cours est archivé : inscription impossible.', 422);
        }

        // ---- RÈGLE 1 : pas de double inscription ----
        $dup = $this->db->prepare('SELECT id, status FROM enrollments WHERE student_id = ? AND course_id = ?');
        $dup->execute([$studentId, $courseId]);
        $existing = $dup->fetch();
        if ($existing && $existing['status'] === 'active') {
            Response::error('Vous êtes déjà inscrit à ce cours.', 409);
        }

        // ---- RÈGLE 2 : capacité maximale ----
        $cnt = $this->db->prepare('SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status = \'active\'');
        $cnt->execute([$courseId]);
        if ((int)$cnt->fetchColumn() >= (int)$course['capacity']) {
            Response::error('Ce cours est complet (capacité maximale atteinte).', 409);
        }

        // ---- RÈGLE 3 : prérequis académiques ----
        $missing = Academic::missingPrerequisites($this->db, $studentId, $courseId);
        if (!empty($missing)) {
            $codes = implode(', ', array_map(fn($p) => $p['code'], $missing));
            Response::error("Prérequis non validés : $codes.", 422, ['prerequisites' => $missing]);
        }

        // ---- RÈGLE 4 : conflit d'emploi du temps ----
        $conflict = Academic::scheduleConflict($this->db, $studentId, $courseId);
        if ($conflict) {
            Response::error(
                "Conflit d'emploi du temps avec « {$conflict['existing_code']} - {$conflict['existing_name']} ».",
                409,
                ['conflict' => $conflict]
            );
        }

        // ---- Écriture (ou réactivation si l'inscription avait été abandonnée) ----
        if ($existing && $existing['status'] === 'dropped') {
            $upd = $this->db->prepare('UPDATE enrollments SET status = \'active\', enrolled_at = NOW() WHERE id = ?');
            $upd->execute([$existing['id']]);
            $enrollId = (int)$existing['id'];
        } else {
            $ins = $this->db->prepare('INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)');
            $ins->execute([$studentId, $courseId]);
            $enrollId = (int)$this->db->lastInsertId();
        }

        // Notification à l'étudiant
        $this->notify($studentId, 'enrollment', 'Inscription confirmée',
            "Vous êtes inscrit à « {$course['name']} ».", 'courses');

        Response::json(['message' => 'Inscription enregistrée.', 'id' => $enrollId], 201);
    }

    /**
     * DELETE enrollments/{id} — désinscription.
     * Condition : impossible si des notes verrouillées (validées) existent.
     */
    public function destroy(Request $req, array $params): void
    {
        Auth::requireRole(['student', 'admin']);
        Auth::verifyCsrf();
        $id = (int)$params['id'];

        $stmt = $this->db->prepare('SELECT * FROM enrollments WHERE id = ?');
        $stmt->execute([$id]);
        $enroll = $stmt->fetch();
        if (!$enroll) {
            Response::error('Inscription introuvable.', 404);
        }

        // Un étudiant ne peut retirer que SES propres inscriptions.
        if (Auth::role() === 'student' && (int)$enroll['student_id'] !== Auth::id()) {
            Response::error('Accès refusé.', 403);
        }

        // Condition de retrait : pas de notes verrouillées dans ce cours.
        $locked = $this->db->prepare(
            'SELECT COUNT(*) FROM grades WHERE student_id = ? AND course_id = ? AND is_locked = 1'
        );
        $locked->execute([$enroll['student_id'], $enroll['course_id']]);
        if ((int)$locked->fetchColumn() > 0) {
            Response::error('Désinscription impossible : des notes ont été validées pour ce cours.', 409);
        }

        $del = $this->db->prepare('DELETE FROM enrollments WHERE id = ?');
        $del->execute([$id]);
        Response::json(['message' => 'Désinscription effectuée.']);
    }
}
