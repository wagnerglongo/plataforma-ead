<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\Response;
use App\Services\ReportService;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        [$labels, $values] = ReportService::enrollmentsByDay(30);
        $courses = array_filter(ReportService::courses(), fn ($c) => $c['status'] === 'published');
        return $this->view('admin/dashboard', [
            'title' => 'Painel',
            'kpis' => ReportService::kpis(),
            'chartLabels' => $labels,
            'chartValues' => $values,
            'courses' => array_slice($courses, 0, 6),
            'recent' => DB::fetchAll(
                "SELECT e.enrolled_at, e.status, e.progress_percent, u.id AS user_id, u.name, c.title
                   FROM enrollments e JOIN users u ON u.id = e.user_id JOIN courses c ON c.id = e.course_id
                  ORDER BY e.enrolled_at DESC LIMIT 7",
            ),
            'activity' => DB::fetchAll(
                "SELECT a.*, u.name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
                  WHERE a.action NOT IN ('auth.login', 'auth.logout', 'material.downloaded')
                  ORDER BY a.created_at DESC, a.id DESC LIMIT 8",
            ),
            'pendingLgpd' => (int) DB::value("SELECT COUNT(*) FROM data_requests WHERE status = 'pending'"),
        ], 'admin');
    }
}
