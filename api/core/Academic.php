<?php
/**
 * Academic — logique métier académique réutilisable.
 *
 * Centralise les calculs et vérifications partagés par plusieurs contrôleurs :
 *  - moyenne pondérée d'un étudiant dans un cours ;
 *  - vérification des prérequis ;
 *  - détection des conflits d'emploi du temps.
 *
 * Regrouper ces règles ici évite la duplication et facilite leur explication
 * (un seul endroit où la "règle métier" est définie).
 */
class Academic
{
    /** Seuil de validation d'un cours (moyenne >= 10/20). */
    public const PASS_THRESHOLD = 10.0;

    /**
     * Moyenne pondérée d'un étudiant dans un cours, ou null si aucune note.
     * moyenne = Σ(note × coef) / Σ(coef)
     */
    public static function courseAverage(PDO $db, int $studentId, int $courseId): ?float
    {
        $stmt = $db->prepare('SELECT value, coefficient FROM grades WHERE student_id = ? AND course_id = ?');
        $stmt->execute([$studentId, $courseId]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return null;
        }
        $sum = 0.0; $coefSum = 0.0;
        foreach ($rows as $r) {
            $sum += (float)$r['value'] * (float)$r['coefficient'];
            $coefSum += (float)$r['coefficient'];
        }
        return $coefSum > 0 ? round($sum / $coefSum, 2) : null;
    }

    /**
     * Vérifie que l'étudiant a validé tous les prérequis du cours.
     * Retourne la liste des cours prérequis NON validés (vide => OK).
     */
    public static function missingPrerequisites(PDO $db, int $studentId, int $courseId): array
    {
        $stmt = $db->prepare(
            'SELECT c.id, c.code, c.name
             FROM prerequisites p JOIN courses c ON c.id = p.prerequisite_course_id
             WHERE p.course_id = ?'
        );
        $stmt->execute([$courseId]);
        $missing = [];
        foreach ($stmt->fetchAll() as $prereq) {
            $avg = self::courseAverage($db, $studentId, (int)$prereq['id']);
            if ($avg === null || $avg < self::PASS_THRESHOLD) {
                $missing[] = $prereq;   // prérequis non suivi ou non validé
            }
        }
        return $missing;
    }

    /**
     * Détecte un conflit d'emploi du temps pour un étudiant qui voudrait
     * s'inscrire à $courseId : un de ses cours actuels chevauche-t-il un
     * créneau du nouveau cours (même jour + plages horaires qui se croisent) ?
     *
     * Retourne le 1er conflit trouvé (tableau descriptif), ou null.
     */
    public static function scheduleConflict(PDO $db, int $studentId, int $courseId): ?array
    {
        $sql = 'SELECT ns.day_of_week, ns.start_time, ns.end_time, ns.room,
                       c.code AS existing_code, c.name AS existing_name
                FROM schedule_slots ns                       -- créneaux du NOUVEAU cours
                JOIN schedule_slots es                        -- créneaux des cours EXISTANTS
                     ON es.day_of_week = ns.day_of_week
                    AND es.start_time < ns.end_time           -- condition de chevauchement
                    AND es.end_time   > ns.start_time
                JOIN enrollments e ON e.course_id = es.course_id
                     AND e.student_id = ? AND e.status = \'active\'
                JOIN courses c ON c.id = es.course_id
                WHERE ns.course_id = ?
                LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute([$studentId, $courseId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Détecte un conflit de ressource lors de l'AJOUT d'un créneau à
     * l'emploi du temps : même jour + chevauchement horaire sur la même
     * salle OU le même enseignant.
     * Retourne le conflit, ou null.
     */
    public static function slotResourceConflict(PDO $db, int $dayOfWeek, string $start, string $end, string $room, ?int $teacherId): ?array
    {
        $sql = 'SELECT s.id, s.room, c.code, c.name,
                       CONCAT(u.first_name, \' \', u.last_name) AS teacher_name
                FROM schedule_slots s
                JOIN courses c ON c.id = s.course_id
                LEFT JOIN users u ON u.id = c.teacher_id
                WHERE s.day_of_week = ?
                  AND s.start_time < ?      -- chevauchement
                  AND s.end_time   > ?
                  AND ( s.room = ? OR (c.teacher_id IS NOT NULL AND c.teacher_id = ?) )
                LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute([$dayOfWeek, $end, $start, $room, $teacherId]);
        return $stmt->fetch() ?: null;
    }
}
