<?php
/**
 * Connexion à la base de données via PDO (patron Singleton).
 *
 * Pourquoi PDO ?
 *  - interface unique et portable (MySQL, etc.) ;
 *  - requêtes préparées => protection contre les injections SQL ;
 *  - gestion des erreurs par exceptions.
 *
 * Pourquoi un Singleton ?
 *  - on ne veut qu'UNE seule connexion réutilisée pendant toute la requête HTTP.
 */
class Database
{
    private static ?PDO $instance = null;

    /** Empêche l'instanciation directe (on passe par getConnection). */
    private function __construct() {}

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );

            $options = [
                // Lève une exception en cas d'erreur SQL (au lieu d'un retour silencieux)
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Récupère les lignes sous forme de tableaux associatifs
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Vraies requêtes préparées côté serveur (sécurité)
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // On ne renvoie jamais le détail SQL au client en clair.
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'error' => 'Connexion à la base de données impossible.',
                    'detail' => DEBUG ? $e->getMessage() : null,
                ]);
                exit;
            }
        }

        return self::$instance;
    }
}
