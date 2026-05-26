<?php
/**
 * Classe de base des contrôleurs : fournit l'accès à la base de données.
 * Tous les contrôleurs en héritent pour disposer de $this->db (PDO).
 */
abstract class Controller
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Crée une notification pour un utilisateur (utilisé par plusieurs modules).
     */
    protected function notify(int $userId, string $type, string $title, ?string $content = null, ?string $link = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (user_id, type, title, content, link) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$userId, $type, $title, $content, $link]);
    }
}
