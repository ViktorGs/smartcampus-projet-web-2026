<?php
/**
 * Représente la requête HTTP entrante : méthode, segments d'URL, corps JSON.
 * Le routeur s'appuie dessus pour dispatcher vers le bon contrôleur.
 */
class Request
{
    public string $method;
    /** @var string[] Segments de l'URL après /api/  (ex: ['students','12']) */
    public array $segments;
    /** @var array Corps de la requête décodé (JSON ou form) */
    public array $body;
    /** @var array Paramètres de query string ($_GET) */
    public array $query;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->query  = $_GET;
        $this->segments = self::parsePath();
        $this->body   = self::parseBody();
    }

    /**
     * Récupère le chemin après "/api/".
     * Supporte deux modes :
     *  - réécriture .htaccess  -> variable ?route=students/12
     *  - PATH_INFO direct      -> /api/index.php/students/12
     */
    private static function parsePath(): array
    {
        $route = $_GET['route'] ?? ($_SERVER['PATH_INFO'] ?? '');
        $route = trim($route, '/');
        if ($route === '') {
            return [];
        }
        return array_values(array_filter(explode('/', $route), fn($s) => $s !== ''));
    }

    /** Décode le corps : JSON en priorité, sinon données de formulaire. */
    private static function parseBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST ?? [];
    }

    /** Segment d'URL à l'index donné (ex: l'ID), ou null. */
    public function segment(int $index): ?string
    {
        return $this->segments[$index] ?? null;
    }

    /** Valeur du corps, avec valeur par défaut. */
    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }
}
