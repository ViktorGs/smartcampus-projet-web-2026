<?php
/**
 * MessageController — messagerie interne (bonus).
 * Tout utilisateur connecté peut écrire à un autre selon des règles de rôle :
 *  - étudiant -> enseignants de ses cours + administration ;
 *  - enseignant -> ses étudiants + admin + autres enseignants ;
 *  - admin -> tout le monde.
 */
class MessageController extends Controller
{
    /** GET messages — boîte de réception. */
    public function inbox(Request $req, array $params): void
    {
        Auth::requireAuth();
        $stmt = $this->db->prepare(
            'SELECT m.id, m.subject, m.body, m.is_read, m.sent_at,
                    u.id AS sender_id, CONCAT(u.first_name, \' \', u.last_name) AS sender_name, u.role AS sender_role
             FROM messages m JOIN users u ON u.id = m.sender_id
             WHERE m.recipient_id = ? ORDER BY m.sent_at DESC'
        );
        $stmt->execute([Auth::id()]);
        Response::json(['data' => $stmt->fetchAll()]);
    }

    /** GET messages/sent — messages envoyés. */
    public function sent(Request $req, array $params): void
    {
        Auth::requireAuth();
        $stmt = $this->db->prepare(
            'SELECT m.id, m.subject, m.body, m.sent_at,
                    u.id AS recipient_id, CONCAT(u.first_name, \' \', u.last_name) AS recipient_name
             FROM messages m JOIN users u ON u.id = m.recipient_id
             WHERE m.sender_id = ? ORDER BY m.sent_at DESC'
        );
        $stmt->execute([Auth::id()]);
        Response::json(['data' => $stmt->fetchAll()]);
    }

    /** GET messages/{id} — lecture d'un message (marque comme lu si destinataire). */
    public function show(Request $req, array $params): void
    {
        Auth::requireAuth();
        $id = (int)$params['id'];
        $stmt = $this->db->prepare(
            'SELECT m.*, CONCAT(s.first_name, \' \', s.last_name) AS sender_name
             FROM messages m JOIN users s ON s.id = m.sender_id WHERE m.id = ?'
        );
        $stmt->execute([$id]);
        $msg = $stmt->fetch();
        if (!$msg || ((int)$msg['recipient_id'] !== Auth::id() && (int)$msg['sender_id'] !== Auth::id())) {
            Response::error('Message introuvable.', 404);
        }
        // Marque lu si on est le destinataire
        if ((int)$msg['recipient_id'] === Auth::id() && (int)$msg['is_read'] === 0) {
            $this->db->prepare('UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ?')->execute([$id]);
        }
        Response::json(['data' => $msg]);
    }

    /** POST messages — envoi. { recipient_id, subject, body } */
    public function store(Request $req, array $params): void
    {
        Auth::requireAuth();
        Auth::verifyCsrf();

        $v = new Validator($req->body);
        $v->required('recipient_id')->required('subject')->required('body')->validateOrFail();

        $recipientId = (int)$req->input('recipient_id');
        // Le destinataire doit faire partie des contacts autorisés (contrôle de rôle)
        if (!in_array($recipientId, $this->allowedRecipientIds(), true)) {
            Response::error('Destinataire non autorisé.', 403);
        }

        $stmt = $this->db->prepare('INSERT INTO messages (sender_id, recipient_id, subject, body) VALUES (?,?,?,?)');
        $stmt->execute([Auth::id(), $recipientId, trim($req->input('subject')), trim($req->input('body'))]);

        $this->notify($recipientId, 'message', 'Nouveau message',
            'De ' . ($_SESSION['name'] ?? 'un utilisateur') . ' : ' . trim($req->input('subject')), 'messages');

        Response::json(['message' => 'Message envoyé.', 'id' => (int)$this->db->lastInsertId()], 201);
    }

    /** GET messages/recipients/list — contacts autorisés pour le formulaire. */
    public function recipients(Request $req, array $params): void
    {
        Auth::requireAuth();
        $ids = $this->allowedRecipientIds();
        if (empty($ids)) {
            Response::json(['data' => []]);
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, role, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE id IN ($in) ORDER BY role, last_name"
        );
        $stmt->execute($ids);
        Response::json(['data' => $stmt->fetchAll()]);
    }

    /**
     * Calcule la liste des IDs d'utilisateurs auxquels l'utilisateur connecté
     * a le droit d'écrire, selon son rôle.
     */
    private function allowedRecipientIds(): array
    {
        $uid = Auth::id();
        $role = Auth::role();
        $ids = [];

        if ($role === 'admin') {
            $stmt = $this->db->query('SELECT id FROM users WHERE is_active = 1');
            $ids = array_column($stmt->fetchAll(), 'id');
        } elseif ($role === 'teacher') {
            // Étudiants inscrits à ses cours + admins + autres enseignants
            $stmt = $this->db->prepare(
                'SELECT DISTINCT e.student_id AS id FROM enrollments e
                 JOIN courses c ON c.id = e.course_id WHERE c.teacher_id = ? AND e.status = \'active\''
            );
            $stmt->execute([$uid]);
            $ids = array_column($stmt->fetchAll(), 'id');
            $staff = $this->db->query('SELECT id FROM users WHERE role IN (\'admin\',\'teacher\')')->fetchAll();
            $ids = array_merge($ids, array_column($staff, 'id'));
        } else { // student
            // Enseignants de ses cours + admins
            $stmt = $this->db->prepare(
                'SELECT DISTINCT c.teacher_id AS id FROM enrollments e
                 JOIN courses c ON c.id = e.course_id
                 WHERE e.student_id = ? AND e.status = \'active\' AND c.teacher_id IS NOT NULL'
            );
            $stmt->execute([$uid]);
            $ids = array_column($stmt->fetchAll(), 'id');
            $admins = $this->db->query('SELECT id FROM users WHERE role = \'admin\'')->fetchAll();
            $ids = array_merge($ids, array_column($admins, 'id'));
        }

        // Nettoyage : entiers, sans soi-même, uniques
        $ids = array_values(array_unique(array_map('intval', $ids)));
        return array_values(array_filter($ids, fn($id) => $id !== $uid));
    }
}
