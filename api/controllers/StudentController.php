<?php
/**
 * StudentController — gestion des étudiants (CRUD + consultation profil).
 * Accès : admin (gestion complète), enseignant (consultation), étudiant (son propre profil).
 */
class StudentController extends Controller
{
    /**
     * GET students?q=&filiere=&niveau=&sort=&dir=
     * Liste avec recherche, filtres et tri (admin & enseignants).
     */
    public function index(Request $req, array $params): void
    {
        Auth::requireRole(['admin', 'teacher']);

        $sql = 'SELECT u.id, u.first_name, u.last_name, u.email, u.is_active,
                       sp.student_number, sp.filiere, sp.niveau, sp.group_td, sp.date_inscription
                FROM users u
                JOIN student_profiles sp ON sp.user_id = u.id
                WHERE u.role = \'student\'';
        $args = [];

        // Recherche plein texte simple (nom, prénom, n° étudiant, email)
        if (!empty($req->query['q'])) {
            $sql .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR sp.student_number LIKE ? OR u.email LIKE ?)';
            $like = '%' . $req->query['q'] . '%';
            array_push($args, $like, $like, $like, $like);
        }
        if (!empty($req->query['filiere'])) {
            $sql .= ' AND sp.filiere = ?';
            $args[] = $req->query['filiere'];
        }
        if (!empty($req->query['niveau'])) {
            $sql .= ' AND sp.niveau = ?';
            $args[] = $req->query['niveau'];
        }

        // Tri : on utilise une liste blanche pour éviter toute injection dans ORDER BY
        $sortable = [
            'name'   => 'u.last_name',
            'number' => 'sp.student_number',
            'niveau' => 'sp.niveau',
            'filiere'=> 'sp.filiere',
            'date'   => 'sp.date_inscription',
        ];
        $sortCol = $sortable[$req->query['sort'] ?? 'name'] ?? 'u.last_name';
        $dir = (strtolower($req->query['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
        $sql .= " ORDER BY $sortCol $dir";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);
        Response::json(['data' => $stmt->fetchAll()]);
    }

    /** GET students/{id} — profil détaillé + cours suivis + relevé. */
    public function show(Request $req, array $params): void
    {
        Auth::requireAuth();
        $id = (int)$params['id'];

        // Un étudiant ne peut consulter que SON profil ; admin/teacher : tous.
        if (Auth::role() === 'student' && Auth::id() !== $id) {
            Response::error('Accès refusé.', 403);
        }

        $stmt = $this->db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.gender, u.phone, u.is_active,
                    sp.*
             FROM users u JOIN student_profiles sp ON sp.user_id = u.id
             WHERE u.id = ? AND u.role = \'student\''
        );
        $stmt->execute([$id]);
        $student = $stmt->fetch();
        if (!$student) {
            Response::error('Étudiant introuvable.', 404);
        }

        // Cours suivis (parcours académique)
        $courses = $this->db->prepare(
            'SELECT c.id, c.code, c.name, c.credits, c.semester, c.niveau, e.enrolled_at
             FROM enrollments e JOIN courses c ON c.id = e.course_id
             WHERE e.student_id = ? AND e.status = \'active\'
             ORDER BY c.semester, c.code'
        );
        $courses->execute([$id]);
        $student['courses'] = $courses->fetchAll();

        Response::json(['data' => $student]);
    }

    /**
     * POST students — création d'un étudiant par l'admin.
     * { first_name, last_name, email, password, filiere, niveau, group_td?, date_naissance?, phone?, address? }
     */
    public function store(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        Auth::verifyCsrf();

        $v = new Validator($req->body);
        $v->required('first_name')->required('last_name')
          ->required('email')->email('email')
          ->required('password')->minLength('password', 8)
          ->required('filiere')
          ->required('niveau')->in('niveau', ['L1','L2','L3','M1','M2'])
          ->validateOrFail();

        $check = $this->db->prepare('SELECT 1 FROM users WHERE email = ?');
        $check->execute([trim($req->input('email'))]);
        if ($check->fetch()) {
            Response::error('E-mail déjà utilisé.', 409, ['email' => 'E-mail déjà enregistré.']);
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO users (role, email, password_hash, first_name, last_name, gender, phone)
                 VALUES (\'student\', ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                trim($req->input('email')),
                password_hash($req->input('password'), PASSWORD_BCRYPT),
                trim($req->input('first_name')),
                trim($req->input('last_name')),
                $req->input('gender'),
                $req->input('phone'),
            ]);
            $userId = (int)$this->db->lastInsertId();
            $studentNumber = 'E' . date('Y') . str_pad((string)$userId, 3, '0', STR_PAD_LEFT);

            $p = $this->db->prepare(
                'INSERT INTO student_profiles (user_id, student_number, filiere, niveau, group_td, date_naissance, address)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $p->execute([
                $userId, $studentNumber,
                trim($req->input('filiere')), $req->input('niveau'),
                $req->input('group_td'), $req->input('date_naissance') ?: null,
                $req->input('address'),
            ]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        Response::json(['message' => 'Étudiant créé.', 'id' => $userId, 'student_number' => $studentNumber], 201);
    }

    /** PUT students/{id} — mise à jour (admin). */
    public function update(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        Auth::verifyCsrf();
        $id = (int)$params['id'];

        $exists = $this->db->prepare('SELECT 1 FROM student_profiles WHERE user_id = ?');
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            Response::error('Étudiant introuvable.', 404);
        }

        $v = new Validator($req->body);
        $v->required('first_name')->required('last_name')
          ->required('email')->email('email')
          ->required('filiere')
          ->required('niveau')->in('niveau', ['L1','L2','L3','M1','M2'])
          ->validateOrFail();

        // Email unique (sauf lui-même)
        $check = $this->db->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
        $check->execute([trim($req->input('email')), $id]);
        if ($check->fetch()) {
            Response::error('E-mail déjà utilisé.', 409, ['email' => 'E-mail déjà enregistré.']);
        }

        $this->db->beginTransaction();
        try {
            $u = $this->db->prepare('UPDATE users SET first_name=?, last_name=?, email=?, gender=?, phone=? WHERE id=?');
            $u->execute([trim($req->input('first_name')), trim($req->input('last_name')), trim($req->input('email')),
                         $req->input('gender'), $req->input('phone'), $id]);

            $p = $this->db->prepare('UPDATE student_profiles SET filiere=?, niveau=?, group_td=?, date_naissance=?, address=? WHERE user_id=?');
            $p->execute([trim($req->input('filiere')), $req->input('niveau'), $req->input('group_td'),
                         $req->input('date_naissance') ?: null, $req->input('address'), $id]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        Response::json(['message' => 'Étudiant mis à jour.']);
    }

    /** DELETE students/{id} (admin). La suppression cascade sur inscriptions/notes (FK). */
    public function destroy(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        Auth::verifyCsrf();
        $id = (int)$params['id'];

        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ? AND role = \'student\'');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            Response::error('Étudiant introuvable.', 404);
        }
        Response::json(['message' => 'Étudiant supprimé.']);
    }
}
