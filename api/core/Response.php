<?php
/**
 * Helpers de réponse HTTP au format JSON.
 * Centralise l'envoi des réponses pour garder une API cohérente.
 */
class Response
{
    /** Réponse de succès avec données. */
    public static function json($data = null, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Réponse d'erreur normalisée : { "error": "...", "fields": {...} }. */
    public static function error(string $message, int $status = 400, array $fields = []): void
    {
        $payload = ['error' => $message];
        if (!empty($fields)) {
            $payload['fields'] = $fields;  // erreurs de validation champ par champ
        }
        self::json($payload, $status);
    }
}
