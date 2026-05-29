<?php
/**
 * DashboardController — données du tableau de bord, adaptées au rôle.
 * Une seule route (GET dashboard) renvoie un contenu différent par rôle.
 */
class DashboardController extends Controller
{
    public function index(Request $req, array $params): void
    {
        Auth::requireAuth();
        switch (Auth::role()) {
            case 'student': $this->studentDashboard(); break;
            case 'teacher': $this->teacherDashboard(); break;
            case 'admin':   $this->adminDashboard();   break;
            default: Response::error('Rôle inconnu.', 400);
        }
    }

    private function studentDashboard(): void
    {
        $sid = Auth::id();

        // Cours suivis
        $courses = $this->db->prepare(
            'SELECT c.id, c.code, c.name, c.credits FROM enrollments e
             JOIN courses c ON c.id = e.course_id WHERE e.student_id = ? AND e.status = \'active\''
        );
        $courses->execute([$sid]);
        $courseList = $courses->fetchAll();

        // Moyenne générale pondérée par les crédits ECTS
        $totalWeighted = 0.0; $totalCredits = 0;
        foreach ($courseList as $c) {
            $avg = Academic::courseAverage($this->db, $sid, (int)$c['id']);
            if ($avg !== null) {
                $totalWeighted += $avg * (int)$c['credits'];
                $totalCredits  += (int)$c['credits'];
            }
        }
        $generalAverage = $totalCredits > 0 ? round($totalWeighted / $totalCredits, 2) : null;

        // Prochaines séances (créneaux des cours suivis, classés par jour/heure)
        $slots = $this->db->prepare(
            'SELECT s.day_of_week, s.start_time, s.end_time, s.room, c.code, c.name
             FROM schedule_slots s JOIN courses c ON c.id = s.course_id
             JOIN enrollments e ON e.course_id = c.id
             WHERE e.student_id = ? AND e.status = \'active\'
             ORDER BY s.day_of_week, s.start_time LIMIT 5'
        );
        $slots->execute([$sid]);

        // Notes récentes
        $recentGrades = $this->db->prepare(
            'SELECT g.eval_type, g.value, c.code, c.name, g.graded_at
             FROM grades g JOIN courses c ON c.id = g.course_id
             WHERE g.student_id = ? ORDER BY g.graded_at DESC LIMIT 5'
        );
        $recentGrades->execute([$sid]);

        $unread = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $unread->execute([$sid]);

        Response::json(['data' => [
            'role'            => 'student',
            'course_count'    => count($courseList),
            'general_average' => $generalAverage,
            'total_credits'   => $totalCredits,
            'upcoming'        => $slots->fetchAll(),
            'recent_grades'   => $recentGrades->fetchAll(),
            'unread_notifs'   => (int)$unread->fetchColumn(),
        ]]);
    }

    private function teacherDashboard(): void
    {
        $tid = Auth::id();

        $courses = $this->db->prepare(
            'SELECT c.id, c.code, c.name,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status=\'active\') AS students,
                    (SELECT COUNT(*) FROM grades g WHERE g.course_id = c.id) AS grades_count
             FROM courses c WHERE c.teacher_id = ? ORDER BY c.code'
        );
        $courses->execute([$tid]);
        $courseList = $courses->fetchAll();

        $totalStudents = 0;
        foreach ($courseList as $c) { $totalStudents += (int)$c['students']; }

        // Prochaines séances de l'enseignant
        $slots = $this->db->prepare(
            'SELECT s.day_of_week, s.start_time, s.end_time, s.room, c.code, c.name
             FROM schedule_slots s JOIN courses c ON c.id = s.course_id
             WHERE c.teacher_id = ? ORDER BY s.day_of_week, s.start_time LIMIT 5'
        );
        $slots->execute([$tid]);

        Response::json(['data' => [
            'role'          => 'teacher',
            'course_count'  => count($courseList),
            'total_students'=> $totalStudents,
            'courses'       => $courseList,
            'upcoming'      => $slots->fetchAll(),
        ]]);
    }

    private function adminDashboard(): void
    {
        $count = fn(string $sql) => (int)$this->db->query($sql)->fetchColumn();

        Response::json(['data' => [
            'role'         => 'admin',
            'students'     => $count('SELECT COUNT(*) FROM users WHERE role = \'student\''),
            'teachers'     => $count('SELECT COUNT(*) FROM users WHERE role = \'teacher\''),
            'courses'      => $count('SELECT COUNT(*) FROM courses WHERE is_archived = 0'),
            'enrollments'  => $count('SELECT COUNT(*) FROM enrollments WHERE status = \'active\''),
            'recent_users' => $this->db->query(
                'SELECT id, role, CONCAT(first_name, \' \', last_name) AS name, created_at
                 FROM users ORDER BY created_at DESC LIMIT 5'
            )->fetchAll(),
        ]]);
    }
}
