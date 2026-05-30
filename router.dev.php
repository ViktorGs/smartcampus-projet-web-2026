<?php
/**
 * Routeur pour le serveur intégré de PHP (DÉVELOPPEMENT / TEST UNIQUEMENT).
 * Reproduit la réécriture du .htaccess (Apache) pour le serveur `php -S`.
 *
 * Lancer :  php -S 127.0.0.1:8765 router.dev.php
 * En production, c'est Apache (WAMP) + .htaccess qui gèrent le routage.
 */
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = __DIR__;

// Requêtes API -> front controller
if (preg_match('#^/api/(.*)$#', $uri, $m)) {
    $_GET['route'] = $m[1];
    $_SERVER['PATH_INFO'] = '/' . $m[1];
    require $root . '/api/index.php';
    return true;
}

// Fichier statique existant -> servi tel quel
$file = realpath($root . $uri);
if ($file && is_file($file)) {
    return false;
}

// Sinon, on sert l'application front (index.html)
require $root . '/index.html';
return true;
