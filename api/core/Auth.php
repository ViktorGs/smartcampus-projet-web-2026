<?php
/**
 * Authentification & autorisation.
 *
 * Choix de conception : sessions PHP (et non JWT).
 *  - Application servie en même origine (Apache) -> les cookies de session
 *    sont simples, sûrs (HttpOnly) et suffisants pour le contexte.
 *  - Évite la complexité de stockage/expiration des tokens côté client.
 *
 * Sécurité mise en place :
 *  - mots de passe hashés avec password_hash() / vérifiés avec password_verify() ;
 *  - cookie de session HttpOnly + SameSite=Lax (limite le CSRF & le vol XSS) ;
 *  - jeton CSRF exigé sur toutes les requêtes qui modifient des données ;
 *  - contrôle des rôles (RBAC) avant chaque action sensible.
 */
class Auth
{
    /** Démarre la session avec des paramètres de cookie durcis. */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'httponly' => true,       // inaccessible au JavaScript -> anti-XSS
            'samesite' => 'Lax',      // le cookie n'est pas envoyé en cross-site -> anti-CSRF
        ]);
        session_start();
    }

    /** Connecte un utilisateur : vérifie email + mot de passe. */
    public static function attempt(PDO $db, string $email, string $password): ?array
    {
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;   // identifiants invalides (message volontairement générique)
        }

        // Régénère l'ID de session après login -> protège contre la fixation de session.
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['csrf']    = bin2hex(random_bytes(32));   // jeton CSRF de la session

        unset($user['password_hash']);
        return $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['role'] ?? null;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    // --- Gardes (à appeler en début de contrôleur) -------------------

    /** Exige une session active, sinon 401. */
    public static function requireAuth(): void
    {
        if (!self::check()) {
            Response::error('Authentification requise.', 401);
        }
    }

    /** Exige un rôle parmi la liste, sinon 403. */
    public static function requireRole(array $roles): void
    {
        self::requireAuth();
        if (!in_array(self::role(), $roles, true)) {
            Response::error('Accès refusé : permissions insuffisantes.', 403);
        }
    }

    /**
     * Vérifie le jeton CSRF pour les requêtes modifiant l'état.
     * Le client doit renvoyer le jeton dans l'en-tête X-CSRF-Token.
     */
    public static function verifyCsrf(): void
    {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $real = $_SESSION['csrf'] ?? '';
        if ($real === '' || !hash_equals($real, $sent)) {
            Response::error('Jeton CSRF invalide ou manquant.', 419);
        }
    }
}
