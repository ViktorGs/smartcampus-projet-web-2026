<?php
/**
 * Configuration globale de l'application SmartCampus.
 *
 * ⚠️ Les identifiants de base de données ne doivent PAS être versionnés en
 * clair. Mécanisme retenu :
 *   - ce fichier définit des valeurs PAR DÉFAUT (root / mot de passe vide,
 *     configuration WAMP/XAMPP la plus courante) ;
 *   - s'il existe un fichier config.local.php (NON versionné, voir .gitignore),
 *     il est chargé en premier et ses constantes prennent le dessus.
 *
 * Pour adapter à votre machine : copiez config.local.example.php en
 * config.local.php et renseignez votre mot de passe MySQL.
 */

// Surcharge locale éventuelle (définit les constantes avant les valeurs par défaut)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// --- Base de données (valeurs par défaut si non surchargées) ---
defined('DB_HOST')    || define('DB_HOST', '127.0.0.1');
defined('DB_PORT')    || define('DB_PORT', '3306');
defined('DB_NAME')    || define('DB_NAME', 'smartcampus');
defined('DB_USER')    || define('DB_USER', 'root');
defined('DB_PASS')    || define('DB_PASS', '');        // WAMP/XAMPP : root sans mot de passe par défaut
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

// --- Application ---
define('APP_NAME', 'SmartCampus');
define('SESSION_NAME', 'SMARTCAMPUS_SESSID');
define('SESSION_LIFETIME', 60 * 60 * 2);   // 2 heures

// --- Affichage des erreurs (mettre à false en "production") ---
defined('DEBUG') || define('DEBUG', true);

error_reporting(DEBUG ? E_ALL : 0);
ini_set('display_errors', DEBUG ? '1' : '0');

date_default_timezone_set('Europe/Paris');
