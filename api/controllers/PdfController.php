<?php
require_once __DIR__ . '/../lib/fpdf.php';

/**
 * PdfController — génération d'un relevé de notes au format PDF (bonus).
 * Utilise la bibliothèque FPDF (légère, sans dépendance externe).
 *
 * Sortie : un vrai fichier PDF (Content-Type application/pdf) affiché dans
 * l'onglet du navigateur. La session/cookie sert à l'autorisation.
 */
class PdfController extends Controller
{
    /** GET transcript/{id} — relevé de notes d'un étudiant. */
    public function transcript(Request $req, array $params): void
    {
        Auth::requireAuth();
        // Sortie binaire : on évite qu'une éventuelle alerte PHP ne corrompe le PDF.
        ini_set('display_errors', '0');
        $sid = (int)$params['id'];

        // Autorisation : l'étudiant lui-même, un enseignant ou un admin.
        if (Auth::role() === 'student' && Auth::id() !== $sid) {
            Response::error('Accès refusé.', 403);
        }

        // Données étudiant
        $stmt = $this->db->prepare(
            'SELECT u.first_name, u.last_name, u.email, sp.student_number, sp.filiere, sp.niveau
             FROM users u JOIN student_profiles sp ON sp.user_id = u.id
             WHERE u.id = ? AND u.role = \'student\''
        );
        $stmt->execute([$sid]);
        $student = $stmt->fetch();
        if (!$student) {
            Response::error('Étudiant introuvable.', 404);
        }

        // Cours + notes
        $courses = $this->db->prepare(
            'SELECT c.id, c.code, c.name, c.credits FROM enrollments e
             JOIN courses c ON c.id = e.course_id
             WHERE e.student_id = ? AND e.status = \'active\' ORDER BY c.semester, c.code'
        );
        $courses->execute([$sid]);
        $courseList = $courses->fetchAll();

        // --- Construction du PDF ---
        $pdf = new FPDF();
        $pdf->AddPage();

        // En-tête établissement
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->Cell(0, 10, $this->t('SmartCampus'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 6, $this->t('École d\'ingénieurs — Relevé de notes officiel'), 0, 1, 'C');
        $pdf->Ln(4);
        $pdf->SetDrawColor(13, 110, 253);
        $pdf->SetLineWidth(0.6);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(6);

        // Identité étudiant
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 7, $this->t($student['first_name'] . ' ' . $student['last_name']), 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $this->t('N° étudiant : ' . $student['student_number']), 0, 1);
        $pdf->Cell(0, 6, $this->t('Filière : ' . $student['filiere'] . '   —   Niveau : ' . $student['niveau']), 0, 1);
        $pdf->Cell(0, 6, $this->t('Email : ' . $student['email']), 0, 1);
        $pdf->Ln(4);

        // Tableau des cours
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(13, 110, 253);
        $pdf->SetTextColor(255);
        $pdf->Cell(30, 8, 'Code', 1, 0, 'L', true);
        $pdf->Cell(90, 8, $this->t('Intitulé'), 1, 0, 'L', true);
        $pdf->Cell(20, 8, 'ECTS', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Moyenne', 1, 0, 'C', true);
        $pdf->Cell(25, 8, $this->t('Résultat'), 1, 1, 'C', true);
        $pdf->SetTextColor(0);
        $pdf->SetFont('Arial', '', 10);

        $totalWeighted = 0.0; $totalCredits = 0;
        foreach ($courseList as $c) {
            $avg = Academic::courseAverage($this->db, $sid, (int)$c['id']);
            $result = $avg === null ? '—' : ($avg >= Academic::PASS_THRESHOLD ? 'Admis' : 'Ajourné');
            if ($avg !== null) {
                $totalWeighted += $avg * (int)$c['credits'];
                $totalCredits  += (int)$c['credits'];
            }
            $pdf->Cell(30, 7, $this->t($c['code']), 1);
            $pdf->Cell(90, 7, $this->t($c['name']), 1);
            $pdf->Cell(20, 7, (string)$c['credits'], 1, 0, 'C');
            $pdf->Cell(25, 7, $avg === null ? '—' : number_format($avg, 2) . '/20', 1, 0, 'C');
            $pdf->Cell(25, 7, $this->t($result), 1, 1, 'C');
        }

        // Moyenne générale
        $general = $totalCredits > 0 ? round($totalWeighted / $totalCredits, 2) : null;
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetFillColor(233, 236, 239);
        $pdf->Cell(140, 8, $this->t('Moyenne générale (pondérée par les crédits ECTS)'), 1, 0, 'R', true);
        $pdf->Cell(50, 8, $general === null ? '—' : number_format($general, 2) . '/20', 1, 1, 'C', true);

        // Pied de page
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(120);
        $pdf->Cell(0, 5, $this->t('Document généré le ' . date('d/m/Y à H:i') . ' — SmartCampus'), 0, 1, 'C');

        // Envoi du PDF au navigateur (inline)
        $filename = 'releve_' . $student['student_number'] . '.pdf';
        $pdf->Output('I', $filename);
        exit;
    }

    /** Convertit l'UTF-8 (DB) vers l'encodage attendu par FPDF (cp1252). */
    private function t(string $s): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
    }
}
