<?php
/**
 * AuthController — inscription, connexion, déconnexion, session courante.
 */
class AuthController extends Controller
{
    /** POST auth/login  { email, password } */
    public function login(Request $req, array $params): void
    {
        $v = new Validator($req->body);
        $v->required('email')->email('email')->required('password')->validateOrFail();

        $user = Auth::attempt($this->db, trim($req->input('email')), $req->input('password'));
        if (!$user) {
            Response::error('E-mail ou mot de passe incorrect.', 401);
        }

        Response::json([
            'user' => [
                'id'         => (int)$user['id'],
                'role'       => $user['role'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
                'email'      => $user['email'],
            ],
            'csrf' => Auth::csrfToken(),
        ]);
    }

    /** POST auth/logout */
    public function logout(Request $req, array $params): void
    {
        Auth::requireAuth();
        Auth::verifyCsrf();
        Auth::logout();
        Response::json(['message' => 'Déconnexion réussie.']);
    }

    /** GET auth/me — renvoie l'utilisateur connecté + son profil + jeton CSRF. */
    public function me(Request $req, array $params): void
    {
        if (!Auth::check()) {
            Response::error('Non authentifié.', 401);
        }
        $id = Auth::id();
        $stmt = $this->db->prepare('SELECT id, role, email, first_name, last_name, gender, phone FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        // Joint le profil spécifique au rôle
        if ($user['role'] === 'student') {
            $p = $this->db->prepare('SELECT * FROM student_profiles WHERE user_id = ?');
            $p->execute([$id]);
            $user['profile'] = $p->fetch() ?: null;
        } elseif ($user['role'] === 'teacher') {
            $p = $this->db->prepare('SELECT * FROM teacher_profiles WHERE user_id = ?');
            $p->execute([$id]);
            $user['profile'] = $p->fetch() ?: null;
        }

        Response::json(['user' => $user, 'csrf' => Auth::csrfToken()]);
    }

    /**
     * POST auth/register — auto-inscription publique (rôle étudiant uniquement).
     * { first_name, last_name, email, password, filiere, niveau }
     */
    public function register(Request $req, array $params): void
    {
        $v = new Validator($req->body);
        $v->required('first_name')->required('last_name')
          ->required('email')->email('email')
          ->required('password')->minLength('password', 8)
          ->required('filiere')
          ->required('niveau')->in('niveau', ['L1','L2','L3','M1','M2'])
          ->validateOrFail();

        // Unicité de l'email
        $check = $this->db->prepare('SELECT 1 FROM users WHERE email = ?');
        $check->execute([trim($req->input('email'))]);
        if ($check->fetch()) {
            Response::error('Cet e-mail est déjà utilisé.', 409, ['email' => 'E-mail déjà enregistré.']);
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO users (role, email, password_hash, first_name, last_name)
                 VALUES (\'student\', ?, ?, ?, ?)'
            );
            $stmt->execute([
                trim($req->input('email')),
                password_hash($req->input('password'), PASSWORD_BCRYPT),
                trim($req->input('first_name')),
                trim($req->input('last_name')),
            ]);
            $userId = (int)$this->db->lastInsertId();

            // Numéro étudiant généré : E + année + id sur 3 chiffres
            $studentNumber = 'E' . date('Y') . str_pad((string)$userId, 3, '0', STR_PAD_LEFT);
            $p = $this->db->prepare(
                'INSERT INTO student_profiles (user_id, student_number, filiere, niveau)
                 VALUES (?,?,?,?)'
            );
            $p->execute([$userId, $studentNumber, trim($req->input('filiere')), $req->input('niveau')]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        // Connecte automatiquement après inscription
        $user = Auth::attempt($this->db, trim($req->input('email')), $req->input('password'));
        Response::json([
            'message' => 'Compte créé avec succès.',
            'user'    => ['id' => $user['id'], 'role' => $user['role'], 'first_name' => $user['first_name'], 'last_name' => $user['last_name']],
            'csrf'    => Auth::csrfToken(),
        ], 201);
    }
}
