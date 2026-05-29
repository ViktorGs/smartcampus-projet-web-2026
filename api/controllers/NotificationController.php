<?php
/**
 * NotificationController — notifications de l'utilisateur connecté (bonus).
 * Les notifications sont créées par d'autres modules via Controller::notify().
 */
class NotificationController extends Controller
{
    /** GET notifications — liste + compteur de non-lues. */
    public function index(Request $req, array $params): void
    {
        Auth::requireAuth();
        $stmt = $this->db->prepare(
            'SELECT id, type, title, content, link, is_read, created_at
             FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50'
        );
        $stmt->execute([Auth::id()]);
        $rows = $stmt->fetchAll();
        $unread = count(array_filter($rows, fn($n) => (int)$n['is_read'] === 0));
        Response::json(['data' => $rows, 'unread' => $unread]);
    }

    /** PATCH notifications/{id}/read */
    public function markRead(Request $req, array $params): void
    {
        Auth::requireAuth();
        Auth::verifyCsrf();
        $stmt = $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([(int)$params['id'], Auth::id()]);
        Response::json(['message' => 'Notification marquée comme lue.']);
    }

    /** POST notifications/read-all */
    public function markAllRead(Request $req, array $params): void
    {
        Auth::requireAuth();
        Auth::verifyCsrf();
        $stmt = $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([Auth::id()]);
        Response::json(['message' => 'Toutes les notifications sont lues.']);
    }
}
