<?php
/**
 * TeacherController — gestion des enseignants (CRUD + cours dont ils sont responsables).
 * Accès : admin (gestion), enseignant (consultation de la liste/ses cours).
 */
class TeacherController extends Controller
{
    /** GET teachers?q=&department= */
    public function index(Request $req, array $params): void
    {
        Auth::requireRole(['admin', 'teacher']);

        $sql = 'SELECT u.id, u.first_name, u.last_name, u.email, u.is_active,
                       tp.employee_number, tp.department, tp.grade, tp.office,
                       (SELECT COUNT(*) FROM courses c WHERE c.teacher_id = u.id) AS course_count
                FROM users u
                JOIN teacher_profiles tp ON tp.user_id = u.id
                WHERE u.role = \'teacher\'';
        $args = [];
        if (!empty($req->query['q'])) {
            $sql .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
            $like = '%' . $req->query['q'] . '%';
            array_push($args, $like, $like, $like);
        }
        if (!empty($req->query['department'])) {
            $sql .= ' AND tp.department = ?';
            $args[] = $req->query['department'];
        }
        $sql .= ' ORDER BY u.last_name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);
        Response::json(['data' => $stmt->fetchAll()]);
    }

    /** GET teachers/{id} */
    public function show(Request $req, array $params): void
    {
        Auth::requireRole(['admin', 'teacher']);
        $id = (int)$params['id'];
        $stmt = $this->db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.gender, u.phone, u.is_active, tp.*
             FROM users u JOIN teacher_profiles tp ON tp.user_id = u.id
             WHERE u.id = ? AND u.role = \'teacher\''
        );
        $stmt->execute([$id]);
        $teacher = $stmt->fetch();
        if (!$teacher) {
            Response::error('Enseignant introuvable.', 404);
        }
        Response::json(['data' => $teacher]);
    }

    /** GET teachers/{id}/courses — cours dont l'enseignant est responsable. */
    public function courses(Request $req, array $params): void
    {
        Auth::requireRole(['admin', 'teacher']);
        $id = (int)$params['id'];
        $stmt = $this->db->prepare(
            'SELECT c.*, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status=\'active\') AS enrolled
             FROM courses c WHERE c.teacher_id = ? ORDER BY c.semester, c.code'
        );
        $stmt->execute([$id]);
        Response::json(['data' => $stmt->fetchAll()]);
    }

    /** POST teachers (admin) */
    public function store(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        Auth::verifyCsrf();

        $v = new Validator($req->body);
        $v->required('first_name')->required('last_name')
          ->required('email')->email('email')
          ->required('password')->minLength('password', 8)
          ->required('department')
          ->in('grade', ['Professeur','Maître de conférences','Maître assistant','Vacataire'])
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
                 VALUES (\'teacher\', ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                trim($req->input('email')),
                password_hash($req->input('password'), PASSWORD_BCRYPT),
                trim($req->input('first_name')), trim($req->input('last_name')),
                $req->input('gender'), $req->input('phone'),
            ]);
            $userId = (int)$this->db->lastInsertId();
            $employeeNumber = 'ENS' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);

            $p = $this->db->prepare(
                'INSERT INTO teacher_profiles (user_id, employee_number, department, grade, office, hire_date)
                 VALUES (?,?,?,?,?,?)'
            );
            $p->execute([
                $userId, $employeeNumber, trim($req->input('department')),
                $req->input('grade') ?: 'Maître assistant', $req->input('office'),
                $req->input('hire_date') ?: null,
            ]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        Response::json(['message' => 'Enseignant créé.', 'id' => $userId, 'employee_number' => $employeeNumber], 201);
    }

    /** PUT teachers/{id} (admin) */
    public function update(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        Auth::verifyCsrf();
        $id = (int)$params['id'];

        $exists = $this->db->prepare('SELECT 1 FROM teacher_profiles WHERE user_id = ?');
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            Response::error('Enseignant introuvable.', 404);
        }

        $v = new Validator($req->body);
        $v->required('first_name')->required('last_name')
          ->required('email')->email('email')
          ->required('department')
          ->validateOrFail();

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
            $p = $this->db->prepare('UPDATE teacher_profiles SET department=?, grade=?, office=? WHERE user_id=?');
            $p->execute([trim($req->input('department')), $req->input('grade') ?: 'Maître assistant', $req->input('office'), $id]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        Response::json(['message' => 'Enseignant mis à jour.']);
    }

    /** DELETE teachers/{id} (admin) */
    public function destroy(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        Auth::verifyCsrf();
        $id = (int)$params['id'];
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ? AND role = \'teacher\'');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            Response::error('Enseignant introuvable.', 404);
        }
        Response::json(['message' => 'Enseignant supprimé.']);
    }
}
