<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Models\Course;
use App\Services\ReportService;

final class ReportController extends Controller
{
    public function index(): Response
    {
        [$labels, $enrolled, $completed] = ReportService::monthly(6);
        $courses = ReportService::courses();
        $totals = [
            'enrolled' => array_sum(array_column($courses, 'enrolled')) - array_sum(array_column($courses, 'cancelled')),
            'started' => array_sum(array_column($courses, 'started')),
            'completed' => array_sum(array_column($courses, 'completed')),
            'half' => (int) DB::value("SELECT COUNT(*) FROM enrollments WHERE status <> 'cancelled' AND progress_percent >= 50"),
        ];
        return $this->view('admin/reports/index', [
            'title' => 'Relatórios',
            'labels' => $labels,
            'enrolled' => $enrolled,
            'completed' => $completed,
            'courses' => $courses,
            'totals' => $totals,
        ], 'admin');
    }

    public function course(string $id): Response
    {
        $course = Course::find((int) $id) ?? throw HttpException::notFound('Curso não encontrado.');
        $row = array_values(array_filter(ReportService::courses(), fn ($c) => (int) $c['id'] === (int) $id))[0] ?? [];
        return $this->view('admin/reports/course', [
            'title' => 'Relatório: ' . $course['title'],
            'course' => $course,
            'stats' => $row,
            'funnel' => ReportService::lessonFunnel((int) $id),
            'questions' => ReportService::questionStats((int) $id),
            'students' => DB::fetchAll(
                "SELECT e.*, u.name, u.email,
                        (SELECT MAX(qa.score) FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id WHERE q.course_id = e.course_id AND q.module_id IS NULL AND qa.user_id = e.user_id) AS final_score
                   FROM enrollments e JOIN users u ON u.id = e.user_id WHERE e.course_id = :c ORDER BY e.progress_percent DESC, e.enrolled_at",
                ['c' => $id],
            ),
        ], 'admin');
    }

    /** CSV com separador ";" e BOM UTF-8: abre direto no Excel em português. */
    public function export(): Response
    {
        $type = (string) $this->request->query('tipo', 'cursos');
        [$header, $rows] = match ($type) {
            'alunos' => $this->students(),
            'matriculas' => $this->enrollments((int) $this->request->query('curso', 0)),
            'certificados' => $this->certificates(),
            default => $this->courses(),
        };
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header, ';');
        foreach ($rows as $r) {
            fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, array_values($r)), ';');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        Audit::log('report.exported', null, null, ['tipo' => $type, 'linhas' => count($rows)]);
        return Response::download($csv, "relatorio-{$type}-" . date('Y-m-d') . '.csv', 'text/csv; charset=UTF-8');
    }

    private function courses(): array
    {
        $rows = array_map(fn ($c) => [
            $c['title'], $c['status'] === 'published' ? 'Publicado' : 'Rascunho', $c['workload_hours'], (int) $c['enrolled'],
            (int) $c['active'], (int) $c['completed'], (int) $c['cancelled'],
            $c['enrolled'] ? round($c['completed'] / $c['enrolled'] * 100) . '%' : '0%', (int) $c['avg_progress'] . '%',
            $c['avg_score'] !== null ? $c['avg_score'] . '%' : '', (int) $c['certificates'],
        ], ReportService::courses());
        return [['Curso', 'Situação', 'Carga horária', 'Matrículas', 'Em andamento', 'Concluídas', 'Canceladas', 'Taxa de conclusão', 'Progresso médio', 'Nota média', 'Certificados'], $rows];
    }

    private function students(): array
    {
        [$from, $params] = (new StudentController($this->request))->filters();
        $rows = DB::fetchAll(
            "SELECT u.name, u.email, u.cpf, u.phone, u.city, IF(u.status = 'active', 'Ativo', 'Inativo') AS status,
                    DATE_FORMAT(u.created_at, '%d/%m/%Y') AS created,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.user_id = u.id AND e.status <> 'cancelled') AS enrollments,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.user_id = u.id AND e.status = 'completed') AS completed,
                    DATE_FORMAT(u.last_login_at, '%d/%m/%Y %H:%i') AS last_login,
                    IF(u.marketing_opt_in, 'Sim', 'Não') AS marketing {$from} ORDER BY u.name",
            $params,
        );
        return [['Nome', 'E-mail', 'CPF', 'Telefone', 'Cidade', 'Situação', 'Cadastro', 'Matrículas', 'Concluídos', 'Último acesso', 'Aceita e-mails'], $rows];
    }

    private function enrollments(int $courseId): array
    {
        $rows = DB::fetchAll(
            "SELECT u.name, u.email, c.title, e.status, e.progress_percent, DATE_FORMAT(e.enrolled_at, '%d/%m/%Y') AS enrolled,
                    DATE_FORMAT(e.completed_at, '%d/%m/%Y') AS completed, DATE_FORMAT(e.last_access_at, '%d/%m/%Y') AS last_access
               FROM enrollments e JOIN users u ON u.id = e.user_id JOIN courses c ON c.id = e.course_id
              WHERE (:c1 = 0 OR e.course_id = :c2) ORDER BY c.title, u.name",
            ['c1' => $courseId, 'c2' => $courseId],
        );
        $labels = ['active' => 'Em andamento', 'completed' => 'Concluída', 'cancelled' => 'Cancelada'];
        foreach ($rows as &$r) {
            $r['status'] = $labels[$r['status']];
            $r['progress_percent'] .= '%';
        }
        return [['Aluno', 'E-mail', 'Curso', 'Situação', 'Progresso', 'Matrícula', 'Conclusão', 'Último acesso'], $rows];
    }

    private function certificates(): array
    {
        $rows = DB::fetchAll(
            "SELECT code, student_name, course_title, workload_hours, final_score, DATE_FORMAT(issued_at, '%d/%m/%Y') AS issued,
                    IF(status = 'valid', 'Válido', 'Revogado') AS status, revoke_reason FROM certificates ORDER BY issued_at DESC",
        );
        return [['Código', 'Aluno', 'Curso', 'Carga horária', 'Nota final', 'Emissão', 'Situação', 'Motivo da revogação'], $rows];
    }
}
