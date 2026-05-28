<?php
/**
 * GradeController — saisie, modification, consultation et validation des notes.
 *
 * Règle métier : une note VALIDÉE (is_locked = 1) ne peut plus être modifiée
 * ni supprimée. La validation finale est déclenchée par l'enseignant.
 */
class GradeController extends Controller
{
    /** GET grades/mine — notes de l'étudiant connecté, regroupées par cours. */
    public function mine(Request $req, array $params): void
    {
        Auth::requireRole(['student']);
        $sid = Auth::id();

        $stmt = $this->db->prepare(
            'SELECT g.id, g.eval_type, g.value, g.coefficient, g.comment, g.is_locked, g.graded_at,
                    c.id AS course_id, c.code, c.name, c.credits
             FROM grades g JOIN courses c ON c.id = g.course_id
             WHERE g.student_id = ?
             ORDER BY c.code, g.eval_type'
        );
        $stmt->execute([$sid]);
        $grades = $stmt->fetchAll();

        // Regroupe par cours et calcule la moyenne + résultat
        $byCourse = [];
        foreach ($grades as $g) {
            $cid = $g['course_id'];
            if (!isset($byCourse[$cid])) {
                $byCourse[$cid] = [
                    'course_id' => (int)$cid, 'code' => $g['code'], 'name' => $g['name'],
                    'credits' => (int)$g['credits'], 'grades' => [],
                ];
            }
            $byCourse[$cid]['grades'][] = $g;
        }
        foreach ($byCourse as $cid => &$course) {
            $avg = Academic::courseAverage($this->db, $sid, (int)$cid);
            $course['average'] = $avg;
            $course['result']  = $avg === null ? null : ($avg >= Academic::PASS_THRESHOLD ? 'Admis' : 'Ajourné');
        }
        Response::json(['data' => array_values($byCourse)]);
    }

    /** GET grades/course/{id} — notes de tous les inscrits d'un cours (enseignant/admin). */
    public function byCourse(Request $req, array $params): void
    {
        Auth::requireRole(['admin', 'teacher']);
        $courseId = (int)$params['id'];
        $this->assertCourseOwnership($courseId);

        // Tous les étudiants inscrits
        $students = $this->db->prepare(
            'SELECT u.id, u.first_name, u.last_name, sp.student_number
             FROM enrollments e
             JOIN users u ON u.id = e.student_id
             JOIN student_profiles sp ON sp.user_id = u.id
             WHERE e.course_id = ? AND e.status = \'active\'
             ORDER BY u.last_name'
        );
        $students->execute([$courseId]);
        $rows = $students->fetchAll();

        $gradeStmt = $this->db->prepare('SELECT id, eval_type, value, coefficient, is_locked FROM grades WHERE student_id = ? AND course_id = ?');
        foreach ($rows as &$s) {
            $gradeStmt->execute([$s['id'], $courseId]);
            $s['grades'] = $gradeStmt->fetchAll();
            $avg = Academic::courseAverage($this->db, (int)$s['id'], $courseId);
            $s['average'] = $avg;
            $s['result']  = $avg === null ? null : ($avg >= Academic::PASS_THRESHOLD ? 'Admis' : 'Ajourné');
        }
        Response::json(['data' => $rows]);
    }

    /** POST grades — saisie d'une note (enseignant/admin). */
    public function store(Request $req, array $params): void
    {
        Auth::requireRole(['admin', 'teacher']);
        Auth::verifyCsrf();

        $v = new Validator($req->body);
        $v->required('student_id')->required('course_id')
          ->required('eval_type')->in('eval_type', ['CC1','CC2','DS','Projet','Examen'])
          ->required('value')->numericRange('value', 0, 20)
          ->numericRange('coefficient', 0.1, 10)
          ->validateOrFail();

        $studentId = (int)$req->input('student_id');
        $courseId  = (int)$req->input('course_id');
        $this->assertCourseOwnership($courseId);

        // L'étudiant doit être inscrit au cours
        $enr = $this->db->prepare('SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ? AND status = \'active\'');
        $enr->execute([$studentId, $courseId]);
        if (!$enr->fetch()) {
            Response::error('Cet étudiant n\'est pas inscrit à ce cours.', 422);
        }

        // Une note de ce type existe déjà ? (contrainte uq_grade) -> message clair
        $exists = $this->db->prepare('SELECT id, is_locked FROM grades WHERE student_id=? AND course_id=? AND eval_type=?');
        $exists->execute([$studentId, $courseId, $req->input('eval_type')]);
        if ($exists->fetch()) {
            Response::error('Une note de ce type existe déjà pour cet étudiant (modifiez-la).', 409);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO grades (student_id, course_id, eval_type, value, coefficient, comment, graded_by)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $studentId, $courseId, $req->input('eval_type'),
            (float)$req->input('value'), (float)$req->input('coefficient', 1),
            $req->input('comment'), Auth::id(),
        ]);

        // Notifie l'étudiant de la nouvelle note
        $cn = $this->db->prepare('SELECT name FROM courses WHERE id = ?');
        $cn->execute([$courseId]);
        $courseName = $cn->fetchColumn();
        $this->notify($studentId, 'grade', 'Nouvelle note publiée',
            "$courseName — {$req->input('eval_type')} : {$req->input('value')}/20", 'grades');

        Response::json(['message' => 'Note enregistrée.', 'id' => (int)$this->db->lastInsertId()], 201);
    }

    /** PUT grades/{id} — modification (interdite si verrouillée). */
    public function update(Request $req, array $params): void
    {
        Auth::requireRole(['admin', 'teacher']);
        Auth::verifyCsrf();
        $id = (int)$params['id'];

        $g = $this->db->prepare('SELECT * FROM grades WHERE id = ?');
        $g->execute([$id]);
        $grade = $g->fetch();
        if (!$grade) {
            Response::error('Note introuvable.', 404);
        }
        $this->assertCourseOwnership((int)$grade['course_id']);

        // ---- Règle : note verrouillée non modifiable ----
        if ((int)$grade['is_locked'] === 1) {
            Response::error('Cette note est validée et ne peut plus être modifiée.', 423);
        }

        $v = new Validator($req->body);
        $v->required('value')->numericRange('value', 0, 20)
          ->numericRange('coefficient', 0.1, 10)
          ->validateOrFail();

        $stmt = $this->db->prepare('UPDATE grades SET value=?, coefficient=?, comment=? WHERE id=?');
        $stmt->execute([(float)$req->input('value'), (float)$req->input('coefficient', $grade['coefficient']), $req->input('comment'), $id]);
        Response::json(['message' => 'Note modifiée.']);
    }

    /** DELETE grades/{id} — suppression (interdite si verrouillée). */
    public function destroy(Request $req, array $params): void
    {
        Auth::requireRole(['admin', 'teacher']);
        Auth::verifyCsrf();
        $id = (int)$params['id'];

        $g = $this->db->prepare('SELECT * FROM grades WHERE id = ?');
        $g->execute([$id]);
        $grade = $g->fetch();
        if (!$grade) {
            Response::error('Note introuvable.', 404);
        }
        $this->assertCourseOwnership((int)$grade['course_id']);
        if ((int)$grade['is_locked'] === 1) {
            Response::error('Cette note est validée et ne peut plus être supprimée.', 423);
        }
        $this->db->prepare('DELETE FROM grades WHERE id = ?')->execute([$id]);
        Response::json(['message' => 'Note supprimée.']);
    }

    /**
     * POST grades/course/{id}/validate — validation finale des notes d'un cours.
     * Verrouille toutes les notes du cours : elles deviennent non modifiables.
     */
    public function validateCourse(Request $req, array $params): void
    {
        Auth::requireRole(['admin', 'teacher']);
        Auth::verifyCsrf();
        $courseId = (int)$params['id'];
        $this->assertCourseOwnership($courseId);

        $stmt = $this->db->prepare('UPDATE grades SET is_locked = 1 WHERE course_id = ?');
        $stmt->execute([$courseId]);

        // Notifie tous les inscrits que leurs notes sont validées
        $students = $this->db->prepare('SELECT student_id FROM enrollments WHERE course_id = ? AND status = \'active\'');
        $students->execute([$courseId]);
        foreach ($students->fetchAll() as $s) {
            $this->notify((int)$s['student_id'], 'grade', 'Notes validées',
                'Les notes d\'un de vos cours ont été validées définitivement.', 'grades');
        }

        Response::json(['message' => 'Notes du cours validées et verrouillées.', 'locked' => $stmt->rowCount()]);
    }

    /**
     * Vérifie que l'enseignant connecté est responsable du cours.
     * L'admin a tous les droits. Lève 403 sinon.
     */
    private function assertCourseOwnership(int $courseId): void
    {
        if (Auth::role() === 'admin') {
            return;
        }
        $stmt = $this->db->prepare('SELECT 1 FROM courses WHERE id = ? AND teacher_id = ?');
        $stmt->execute([$courseId, Auth::id()]);
        if (!$stmt->fetch()) {
            Response::error('Ce cours n\'est pas sous votre responsabilité.', 403);
        }
    }
}
