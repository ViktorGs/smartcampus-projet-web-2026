<?php
/**
 * =====================================================================
 *  Point d'entrée unique de l'API REST SmartCampus (front controller).
 * =====================================================================
 *  Toutes les requêtes /api/... sont réécrites vers ce fichier (.htaccess)
 *  puis dispatchées vers le bon contrôleur par le routeur.
 *
 *  Format des routes : voir la table $router plus bas.
 *  Toutes les réponses sont en JSON.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';

// --- Autoload simple du cœur et des contrôleurs ---
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Request.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Validator.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Academic.php';
require_once __DIR__ . '/core/Controller.php';
foreach (glob(__DIR__ . '/controllers/*.php') as $file) {
    require_once $file;
}

// --- En-têtes communs ---
header('X-Content-Type-Options: nosniff');   // empêche le MIME sniffing
header('X-Frame-Options: DENY');             // anti-clickjacking

// Réponse aux pré-vols CORS (utile si front servi sur un autre port en dev)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

Auth::start();
$request = new Request();
$router  = new Router();

// =====================================================================
//  TABLE DES ROUTES
// =====================================================================

// --- Authentification ---
$router->post('auth/login',  [AuthController::class, 'login']);
$router->post('auth/logout', [AuthController::class, 'logout']);
$router->get('auth/me',      [AuthController::class, 'me']);
$router->post('auth/register',[AuthController::class, 'register']);  // auto-inscription étudiant

// --- Utilisateurs (admin) ---
$router->get('users',         [UserController::class, 'index']);
$router->get('users/{id}',    [UserController::class, 'show']);
$router->patch('users/{id}/status', [UserController::class, 'toggleStatus']);
$router->delete('users/{id}', [UserController::class, 'destroy']);

// --- Étudiants ---
$router->get('students',        [StudentController::class, 'index']);
$router->get('students/{id}',   [StudentController::class, 'show']);
$router->post('students',       [StudentController::class, 'store']);
$router->put('students/{id}',   [StudentController::class, 'update']);
$router->delete('students/{id}',[StudentController::class, 'destroy']);

// --- Enseignants ---
$router->get('teachers',        [TeacherController::class, 'index']);
$router->get('teachers/{id}',   [TeacherController::class, 'show']);
$router->post('teachers',       [TeacherController::class, 'store']);
$router->put('teachers/{id}',   [TeacherController::class, 'update']);
$router->delete('teachers/{id}',[TeacherController::class, 'destroy']);
$router->get('teachers/{id}/courses', [TeacherController::class, 'courses']);

// --- Cours ---
$router->get('courses',           [CourseController::class, 'index']);
$router->get('courses/{id}',      [CourseController::class, 'show']);
$router->post('courses',          [CourseController::class, 'store']);
$router->put('courses/{id}',      [CourseController::class, 'update']);
$router->delete('courses/{id}',   [CourseController::class, 'destroy']);
$router->patch('courses/{id}/archive', [CourseController::class, 'archive']);
$router->get('courses/{id}/students',  [CourseController::class, 'students']);

// --- Inscriptions ---
$router->get('enrollments/mine',   [EnrollmentController::class, 'mine']);   // étudiant connecté
$router->post('enrollments',       [EnrollmentController::class, 'store']);
$router->delete('enrollments/{id}',[EnrollmentController::class, 'destroy']);

// --- Notes ---
$router->get('grades/mine',          [GradeController::class, 'mine']);      // étudiant connecté
$router->get('grades/course/{id}',   [GradeController::class, 'byCourse']);  // enseignant
$router->post('grades',              [GradeController::class, 'store']);
$router->put('grades/{id}',          [GradeController::class, 'update']);
$router->delete('grades/{id}',       [GradeController::class, 'destroy']);
$router->post('grades/course/{id}/validate', [GradeController::class, 'validateCourse']);

// --- Emploi du temps ---
$router->get('schedule',        [ScheduleController::class, 'index']);    // selon le rôle
$router->post('schedule',       [ScheduleController::class, 'store']);
$router->delete('schedule/{id}',[ScheduleController::class, 'destroy']);

// --- Messagerie (bonus) ---
$router->get('messages',          [MessageController::class, 'inbox']);
$router->get('messages/sent',     [MessageController::class, 'sent']);
$router->get('messages/{id}',     [MessageController::class, 'show']);
$router->post('messages',         [MessageController::class, 'store']);
$router->get('messages/recipients/list', [MessageController::class, 'recipients']);

// --- Notifications (bonus) ---
$router->get('notifications',         [NotificationController::class, 'index']);
$router->patch('notifications/{id}/read', [NotificationController::class, 'markRead']);
$router->post('notifications/read-all',  [NotificationController::class, 'markAllRead']);

// --- Tableaux de bord & statistiques (bonus) ---
$router->get('dashboard',  [DashboardController::class, 'index']);
$router->get('stats',      [StatsController::class, 'index']);

// --- Relevé de notes PDF (bonus) ---
$router->get('transcript/{id}', [PdfController::class, 'transcript']);

// =====================================================================
//  DISPATCH
// =====================================================================
try {
    $router->dispatch($request);
} catch (Throwable $e) {
    // Filet de sécurité : aucune fuite de stacktrace en production.
    Response::error(
        DEBUG ? ('Erreur serveur : ' . $e->getMessage()) : 'Erreur interne du serveur.',
        500
    );
}
