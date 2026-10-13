<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Auth;
use App\Core\Database as DB;
use App\Core\Response;
use App\Models\Course;
use App\Services\ProgressService;

final class DashboardController extends StudentController
{
    public function index(): Response
    {
        $userId = (int) Auth::id();
        $filter = (string) $this->request->query('filtro', 'andamento');
        $all = DB::fetchAll(
            "SELECT e.*, c.title, c.slug, c.cover_color, c.thumbnail, c.workload_hours, cat.name AS category_name,
                    (SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = c.id) AS lessons_count,
                    (SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id JOIN modules m ON m.id = l.module_id
                      WHERE m.course_id = c.id AND lp.user_id = e.user_id AND lp.completed_at IS NOT NULL) AS lessons_done,
                    (SELECT code FROM certificates ct WHERE ct.user_id = e.user_id AND ct.course_id = c.id AND ct.status = 'valid' LIMIT 1) AS certificate_code
               FROM enrollments e JOIN courses c ON c.id = e.course_id LEFT JOIN categories cat ON cat.id = c.category_id
              WHERE e.user_id = :u AND e.status <> 'cancelled'
              ORDER BY COALESCE(e.last_access_at, e.enrolled_at) DESC",
            ['u' => $userId],
        );
        $active = array_values(array_filter($all, fn ($e) => $e['status'] === 'active'));
        $completed = array_values(array_filter($all, fn ($e) => $e['status'] === 'completed'));
        $list = match ($filter) {
            'concluidos' => $completed,
            'todos' => $all,
            default => $active,
        };

        $resume = null;
        if ($active) {
            $target = ProgressService::resumeTarget($userId, $active[0]);
            if ($target) {
                $resume = ['enrollment' => $active[0], 'target' => $target];
            }
        }

        $enrolledIds = array_column($all, 'course_id');
        $suggestions = array_values(array_filter(Course::featured(6), fn ($c) => !in_array($c['id'], $enrolledIds)));

        return $this->view('student/dashboard', [
            'title' => 'Meus cursos',
            'list' => $list,
            'filter' => $filter,
            'counts' => ['andamento' => count($active), 'concluidos' => count($completed), 'todos' => count($all)],
            'resume' => $resume,
            'suggestions' => array_slice($suggestions, 0, 3),
            'certificates' => (int) DB::value("SELECT COUNT(*) FROM certificates WHERE user_id = :u AND status = 'valid'", ['u' => $userId]),
            'noindex' => true,
        ], 'student');
    }
}
