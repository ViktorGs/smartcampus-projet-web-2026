<?php
/**
 * UserController — vue transversale sur tous les comptes (réservé admin).
 * Permet la modération : activer/désactiver ou supprimer un compte abusif.
 */
class UserController extends Controller
{
    /** GET users?role=&q= */
    public function index(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);

        $sql = 'SELECT id, role, email, first_name, last_name, is_active, created_at FROM users WHERE 1=1';
        $args = [];
        if (!empty($req->query['role'])) {
            $sql .= ' AND role = ?';
            $args[] = $req->query['role'];
        }
        if (!empty($req->query['q'])) {
            $sql .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)';
            $like = '%' . $req->query['q'] . '%';
            array_push($args, $like, $like, $like);
        }
        $sql .= ' ORDER BY role, last_name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);
        Response::json(['data' => $stmt->fetchAll()]);
    }

    /** GET users/{id} */
    public function show(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        $id = (int)$params['id'];
        $stmt = $this->db->prepare('SELECT id, role, email, first_name, last_name, gender, phone, is_active, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            Response::error('Utilisateur introuvable.', 404);
        }
        Response::json(['data' => $user]);
    }

    /** PATCH users/{id}/status — active/désactive un compte (modération). */
    public function toggleStatus(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        Auth::verifyCsrf();
        $id = (int)$params['id'];

        if ($id === Auth::id()) {
            Response::error('Vous ne pouvez pas désactiver votre propre compte.', 422);
        }
        $active = $req->input('is_active') ? 1 : 0;
        $stmt = $this->db->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $stmt->execute([$active, $id]);
        if ($stmt->rowCount() === 0) {
            // rowCount peut être 0 si valeur identique ; on vérifie l'existence
            $c = $this->db->prepare('SELECT 1 FROM users WHERE id = ?');
            $c->execute([$id]);
            if (!$c->fetch()) {
                Response::error('Utilisateur introuvable.', 404);
            }
        }
        Response::json(['message' => $active ? 'Compte activé.' : 'Compte désactivé.']);
    }

    /** DELETE users/{id} */
    public function destroy(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);
        Auth::verifyCsrf();
        $id = (int)$params['id'];
        if ($id === Auth::id()) {
            Response::error('Vous ne pouvez pas supprimer votre propre compte.', 422);
        }
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            Response::error('Utilisateur introuvable.', 404);
        }
        Response::json(['message' => 'Compte supprimé.']);
    }
}
