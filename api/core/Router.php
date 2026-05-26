<?php
/**
 * Mini-routeur REST.
 *
 * On enregistre des routes (méthode HTTP + motif) associées à un couple
 * [Contrôleur, méthode]. Les motifs acceptent des paramètres {param}, par
 * ex. "students/{id}". Les paramètres capturés sont passés au contrôleur.
 *
 * Ce routeur léger remplace un framework : il rend l'API REST lisible et
 * explicite, ce que l'on peut justifier en soutenance.
 */
class Router
{
    /** @var array<int,array{method:string,parts:array,handler:array}> */
    private array $routes = [];

    public function add(string $method, string $pattern, array $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'parts'   => array_values(array_filter(explode('/', trim($pattern, '/')), fn($s) => $s !== '')),
            'handler' => $handler,   // [NomController::class, 'methode']
        ];
    }

    // Raccourcis
    public function get(string $p, array $h): void    { $this->add('GET', $p, $h); }
    public function post(string $p, array $h): void   { $this->add('POST', $p, $h); }
    public function put(string $p, array $h): void    { $this->add('PUT', $p, $h); }
    public function patch(string $p, array $h): void  { $this->add('PATCH', $p, $h); }
    public function delete(string $p, array $h): void { $this->add('DELETE', $p, $h); }

    /**
     * Trouve la route correspondant à la requête et exécute le contrôleur.
     */
    public function dispatch(Request $req): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $req->method) {
                continue;
            }
            $params = $this->match($route['parts'], $req->segments);
            if ($params === null) {
                continue;
            }
            [$class, $action] = $route['handler'];
            $controller = new $class();
            $controller->$action($req, $params);
            return;
        }
        Response::error('Ressource introuvable (route non définie).', 404);
    }

    /**
     * Compare un motif de route aux segments de l'URL.
     * Retourne le tableau des paramètres capturés, ou null si pas de match.
     */
    private function match(array $parts, array $segments): ?array
    {
        if (count($parts) !== count($segments)) {
            return null;
        }
        $params = [];
        foreach ($parts as $i => $part) {
            if (preg_match('/^\{(\w+)\}$/', $part, $m)) {
                $params[$m[1]] = $segments[$i];        // segment dynamique {id}
            } elseif ($part !== $segments[$i]) {
                return null;                            // segment fixe ne correspond pas
            }
        }
        return $params;
    }
}
