<?php
/**
 * StatsController — statistiques académiques agrégées (bonus).
 * Fournit des séries de données prêtes à tracer (graphiques Chart.js côté front).
 * Réservé à l'administration.
 */
class StatsController extends Controller
{
    public function index(Request $req, array $params): void
    {
        Auth::requireRole(['admin']);

        // Étudiants par filière
        $byFiliere = $this->db->query(
            'SELECT filiere AS label, COUNT(*) AS value FROM student_profiles GROUP BY filiere ORDER BY value DESC'
        )->fetchAll();

        // Étudiants par niveau
        $byNiveau = $this->db->query(
            'SELECT niveau AS label, COUNT(*) AS value FROM student_profiles GROUP BY niveau ORDER BY niveau'
        )->fetchAll();

        // Inscriptions par cours (top 10)
        $byCourse = $this->db->query(
            'SELECT c.code AS label, COUNT(e.id) AS value
             FROM courses c LEFT JOIN enrollments e ON e.course_id = c.id AND e.status = \'active\'
             GROUP BY c.id ORDER BY value DESC LIMIT 10'
        )->fetchAll();

        // Cours par département
        $byDept = $this->db->query(
            'SELECT department AS label, COUNT(*) AS value FROM courses WHERE is_archived = 0 GROUP BY department'
        )->fetchAll();

        // Taux de réussite global (moyenne >= 10) calculé en PHP pour rester lisible
        $pairs = $this->db->query('SELECT DISTINCT student_id, course_id FROM grades')->fetchAll();
        $passed = 0; $total = 0;
        foreach ($pairs as $p) {
            $avg = Academic::courseAverage($this->db, (int)$p['student_id'], (int)$p['course_id']);
            if ($avg !== null) {
                $total++;
                if ($avg >= Academic::PASS_THRESHOLD) { $passed++; }
            }
        }
        $passRate = $total > 0 ? round($passed * 100 / $total, 1) : null;

        Response::json(['data' => [
            'students_by_filiere' => $byFiliere,
            'students_by_niveau'  => $byNiveau,
            'enrollments_by_course' => $byCourse,
            'courses_by_department' => $byDept,
            'pass_rate'           => $passRate,
            'evaluated_results'   => $total,
        ]]);
    }
}
