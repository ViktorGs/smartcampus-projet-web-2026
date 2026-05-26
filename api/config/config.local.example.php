<?php
/**
 * Exemple de configuration locale.
 *
 * Copiez ce fichier en "config.local.php" (dans le même dossier) et adaptez
 * les valeurs à votre installation MySQL. Le fichier config.local.php est
 * ignoré par Git (voir .gitignore) afin de ne jamais publier vos identifiants.
 *
 * Seules les constantes que vous souhaitez surcharger sont nécessaires.
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'smartcampus');
define('DB_USER', 'root');
define('DB_PASS', 'VOTRE_MOT_DE_PASSE_MYSQL');   // laissez '' si root n'a pas de mot de passe
