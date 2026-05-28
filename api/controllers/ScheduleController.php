<?php
/**
 * ScheduleController — emploi du temps.
 *  - consultation filtrée selon le rôle ;
 *  - ajout de créneaux par l'admin, avec détection de conflit (salle/enseignant).
 */
class ScheduleController extends Controller
{
    /** GET schedule — créneaux visibles selon le rôle de l'utilisateur. */
    public function index(Request $req, array $params): void
    {
        Auth::requireAuth();
        $role = Auth::role();
        $uid  = Auth::id();

        $base = 'SELECT s.id, s.day_of_week, s.start_time, s.end_time, s.room, s.group_td,
                        c.id AS course_id, c.code, c.name, c.niveau,
                        CONCAT(u.first_name, \' \', u.last_name) AS teacher_name
                 FROM schedule_slots s
                 JOIN courses c ON c.id = s.course_id
                 LEFT JOIN users u ON u.id = c.teacher_id ';

        if ($role === 'student') {
            // Seulement les cours auxquels l'étudiant est inscrit
            $sql = $base . 'JOIN enrollments e ON e.course_id = c.id
                            WHERE e.student_id = ? AND e.status = \'active\'
                            ORDER BY s.day_of_week, s.start_time';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$uid]);
        } elseif ($role === 'teacher') {
            // Seulement les cours dont l'enseignant est responsable
            $sql = $base . 'WHERE c.teacher_id = ? ORDER BY s.day_of_week, s.start_time';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$uid]);
        } else {
            // Admin : tout l'emploi du temps (filtrable par niveau)
            $sql = $base . 'WHERE 1=1';
            $args = [];
            if (!empty($req->query['niveau'])) {
                $sql .= ' AND c.niveau = ?';
                $args[] = $req->query['niveau'];
            }
            $sql .= ' ORDER BY s.day_of_week, s.start_time';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($args);
        }

        Response::json(['data' => $stmt->fetchAll()]);
    }

    /** POST schedule — ajout d'un créneau (admin) avec détection de conflit. */
    public function store(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        Auth::verifyCsrf();

        $v = new Validator($req->body);
        $v->required('course_id')
          ->required('day_of_week')->numericRange('day_of_week', 1, 7)
          ->required('start_time')->required('end_time')
          ->required('room')
          ->validateOrFail();

        $courseId = (int)$req->input('course_id');
        $day = (int)$req->input('day_of_week');
        $start = $req->input('start_time');
        $end = $req->input('end_time');
        $room = trim($req->input('room'));

        if ($end <= $start) {
            Response::error('L\'heure de fin doit être après l\'heure de début.', 422, ['end_time' => 'Incohérent.']);
        }

        // Enseignant du cours (pour détecter un conflit d'enseignant)
        $c = $this->db->prepare('SELECT teacher_id FROM courses WHERE id = ?');
        $c->execute([$courseId]);
        $course = $c->fetch();
        if (!$course) {
            Response::error('Cours introuvable.', 404);
        }
        $teacherId = $course['teacher_id'] !== null ? (int)$course['teacher_id'] : null;

        // ---- Détection de conflit (même créneau : salle OU enseignant occupé) ----
        $conflict = Academic::slotResourceConflict($this->db, $day, $start, $end, $room, $teacherId);
        if ($conflict) {
            Response::error(
                "Conflit : la salle ou l'enseignant est déjà occupé sur ce créneau par « {$conflict['code']} - {$conflict['name']} ».",
                409, ['conflict' => $conflict]
            );
        }

        $stmt = $this->db->prepare(
            'INSERT INTO schedule_slots (course_id, day_of_week, start_time, end_time, room, group_td)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([$courseId, $day, $start, $end, $room, $req->input('group_td')]);
        Response::json(['message' => 'Créneau ajouté.', 'id' => (int)$this->db->lastInsertId()], 201);
    }

    /** DELETE schedule/{id} (admin) */
    public function destroy(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        Auth::verifyCsrf();
        $id = (int)$params['id'];
        $stmt = $this->db->prepare('DELETE FROM schedule_slots WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            Response::error('Créneau introuvable.', 404);
        }
        Response::json(['message' => 'Créneau supprimé.']);
    }
}
