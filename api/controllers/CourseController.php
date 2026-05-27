<?php
/**
 * CourseController — gestion des cours (CRUD, filtres/tri, archivage, liste des inscrits).
 */
class CourseController extends Controller
{
    /**
     * GET courses?q=&niveau=&semester=&department=&teacher_id=&sort=&dir=&available=
     * Liste filtrable et triable. Tout utilisateur connecté peut consulter le catalogue.
     */
    public function index(Request $req, array $params): void
    {
        Auth::requireAuth();

        $sql = 'SELECT c.*,
                       CONCAT(u.first_name, \' \', u.last_name) AS teacher_name,
                       (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = \'active\') AS enrolled
                FROM courses c
                LEFT JOIN users u ON u.id = c.teacher_id
                WHERE 1=1';
        $args = [];

        if (!isset($req->query['include_archived'])) {
            $sql .= ' AND c.is_archived = 0';
        }
        if (!empty($req->query['q'])) {
            $sql .= ' AND (c.name LIKE ? OR c.code LIKE ? OR c.description LIKE ?)';
            $like = '%' . $req->query['q'] . '%';
            array_push($args, $like, $like, $like);
        }
        foreach (['niveau' => 'c.niveau', 'semester' => 'c.semester', 'department' => 'c.department'] as $q => $col) {
            if (!empty($req->query[$q])) {
                $sql .= " AND $col = ?";
                $args[] = $req->query[$q];
            }
        }
        if (!empty($req->query['teacher_id'])) {
            $sql .= ' AND c.teacher_id = ?';
            $args[] = (int)$req->query['teacher_id'];
        }

        $sortable = ['code'=>'c.code','name'=>'c.name','credits'=>'c.credits','semester'=>'c.semester','niveau'=>'c.niveau'];
        $sortCol = $sortable[$req->query['sort'] ?? 'code'] ?? 'c.code';
        $dir = (strtolower($req->query['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
        $sql .= " ORDER BY $sortCol $dir";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);
        $courses = $stmt->fetchAll();

        // "available" : ne garder que les cours non pleins (filtre côté liste)
        if (!empty($req->query['available'])) {
            $courses = array_values(array_filter($courses, fn($c) => (int)$c['enrolled'] < (int)$c['capacity']));
        }

        Response::json(['data' => $courses]);
    }

    /** GET courses/{id} — détail + enseignant + prérequis + places restantes. */
    public function show(Request $req, array $params): void
    {
        Auth::requireAuth();
        $id = (int)$params['id'];

        $stmt = $this->db->prepare(
            'SELECT c.*, CONCAT(u.first_name, \' \', u.last_name) AS teacher_name,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status=\'active\') AS enrolled
             FROM courses c LEFT JOIN users u ON u.id = c.teacher_id WHERE c.id = ?'
        );
        $stmt->execute([$id]);
        $course = $stmt->fetch();
        if (!$course) {
            Response::error('Cours introuvable.', 404);
        }

        // Prérequis
        $pre = $this->db->prepare(
            'SELECT c.id, c.code, c.name FROM prerequisites p JOIN courses c ON c.id = p.prerequisite_course_id WHERE p.course_id = ?'
        );
        $pre->execute([$id]);
        $course['prerequisites'] = $pre->fetchAll();

        // Créneaux d'emploi du temps
        $slots = $this->db->prepare('SELECT * FROM schedule_slots WHERE course_id = ? ORDER BY day_of_week, start_time');
        $slots->execute([$id]);
        $course['slots'] = $slots->fetchAll();

        Response::json(['data' => $course]);
    }

    /** GET courses/{id}/students — étudiants inscrits (admin/enseignant responsable). */
    public function students(Request $req, array $params): void
    {
        Auth::requireRole(['admin', 'teacher']);
        $id = (int)$params['id'];

        // Un enseignant ne voit que les inscrits de SES cours.
        if (Auth::role() === 'teacher') {
            $own = $this->db->prepare('SELECT 1 FROM courses WHERE id = ? AND teacher_id = ?');
            $own->execute([$id, Auth::id()]);
            if (!$own->fetch()) {
                Response::error('Ce cours n\'est pas sous votre responsabilité.', 403);
            }
        }

        $stmt = $this->db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, sp.student_number, sp.filiere, sp.niveau, sp.group_td, e.enrolled_at
             FROM enrollments e
             JOIN users u ON u.id = e.student_id
             JOIN student_profiles sp ON sp.user_id = u.id
             WHERE e.course_id = ? AND e.status = \'active\'
             ORDER BY u.last_name'
        );
        $stmt->execute([$id]);
        Response::json(['data' => $stmt->fetchAll()]);
    }

    /** POST courses (admin) */
    public function store(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        Auth::verifyCsrf();

        $v = new Validator($req->body);
        $v->required('code')->required('name')
          ->required('semester')->in('semester', ['S1','S2','S3','S4','S5','S6','S7','S8','S9','S10'])
          ->required('niveau')->in('niveau', ['L1','L2','L3','M1','M2'])
          ->required('department')
          ->numericRange('credits', 1, 30)
          ->numericRange('capacity', 1, 500)
          ->validateOrFail();

        $check = $this->db->prepare('SELECT 1 FROM courses WHERE code = ?');
        $check->execute([trim($req->input('code'))]);
        if ($check->fetch()) {
            Response::error('Ce code de cours existe déjà.', 409, ['code' => 'Code déjà utilisé.']);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO courses (code, name, description, credits, semester, niveau, department, teacher_id, capacity)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            trim($req->input('code')), trim($req->input('name')), $req->input('description'),
            (int)$req->input('credits', 3), $req->input('semester'), $req->input('niveau'),
            trim($req->input('department')), $req->input('teacher_id') ?: null,
            (int)$req->input('capacity', 30),
        ]);
        Response::json(['message' => 'Cours créé.', 'id' => (int)$this->db->lastInsertId()], 201);
    }

    /** PUT courses/{id} (admin) */
    public function update(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        Auth::verifyCsrf();
        $id = (int)$params['id'];

        $exists = $this->db->prepare('SELECT 1 FROM courses WHERE id = ?');
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            Response::error('Cours introuvable.', 404);
        }

        $v = new Validator($req->body);
        $v->required('code')->required('name')
          ->required('semester')->in('semester', ['S1','S2','S3','S4','S5','S6','S7','S8','S9','S10'])
          ->required('niveau')->in('niveau', ['L1','L2','L3','M1','M2'])
          ->required('department')
          ->numericRange('credits', 1, 30)
          ->numericRange('capacity', 1, 500)
          ->validateOrFail();

        $check = $this->db->prepare('SELECT 1 FROM courses WHERE code = ? AND id <> ?');
        $check->execute([trim($req->input('code')), $id]);
        if ($check->fetch()) {
            Response::error('Ce code de cours existe déjà.', 409, ['code' => 'Code déjà utilisé.']);
        }

        $stmt = $this->db->prepare(
            'UPDATE courses SET code=?, name=?, description=?, credits=?, semester=?, niveau=?, department=?, teacher_id=?, capacity=? WHERE id=?'
        );
        $stmt->execute([
            trim($req->input('code')), trim($req->input('name')), $req->input('description'),
            (int)$req->input('credits', 3), $req->input('semester'), $req->input('niveau'),
            trim($req->input('department')), $req->input('teacher_id') ?: null,
            (int)$req->input('capacity', 30), $id,
        ]);
        Response::json(['message' => 'Cours mis à jour.']);
    }

    /** PATCH courses/{id}/archive — archive un cours (règle : archivage semestre passé). */
    public function archive(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        Auth::verifyCsrf();
        $id = (int)$params['id'];
        $archived = $req->input('archived', true) ? 1 : 0;
        $stmt = $this->db->prepare('UPDATE courses SET is_archived = ? WHERE id = ?');
        $stmt->execute([$archived, $id]);
        Response::json(['message' => $archived ? 'Cours archivé.' : 'Cours désarchivé.']);
    }

    /** DELETE courses/{id} (admin) */
    public function destroy(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        Auth::verifyCsrf();
        $id = (int)$params['id'];
        $stmt = $this->db->prepare('DELETE FROM courses WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            Response::error('Cours introuvable.', 404);
        }
        Response::json(['message' => 'Cours supprimé.']);
    }
}
