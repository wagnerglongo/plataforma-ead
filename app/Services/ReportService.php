<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database as DB;

/** Consultas de indicadores usadas no painel, nos relatórios e nas exportações. */
final class ReportService
{
    public static function kpis(): array
    {
        $since30 = date('Y-m-d H:i:s', strtotime('-30 days'));
        $since60 = date('Y-m-d H:i:s', strtotime('-60 days'));
        return DB::fetch(
            "SELECT
               (SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'aluno' AND u.status = 'active') AS students,
               (SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'aluno' AND u.created_at >= :a1) AS students_30,
               (SELECT COUNT(*) FROM enrollments WHERE status <> 'cancelled') AS enrollments,
               (SELECT COUNT(*) FROM enrollments WHERE enrolled_at >= :a2) AS enrollments_30,
               (SELECT COUNT(*) FROM enrollments WHERE enrolled_at >= :b1 AND enrolled_at < :a3) AS enrollments_prev_30,
               (SELECT COUNT(*) FROM enrollments WHERE status = 'completed') AS completed,
               (SELECT COUNT(*) FROM certificates WHERE status = 'valid') AS certificates,
               (SELECT COUNT(*) FROM certificates WHERE issued_at >= :a4) AS certificates_30,
               (SELECT COUNT(DISTINCT user_id) FROM enrollments WHERE last_access_at >= :a5) AS active_learners",
            ['a1' => $since30, 'a2' => $since30, 'a3' => $since30, 'a4' => $since30, 'a5' => $since30, 'b1' => $since60],
        ) ?? [];
    }

    /** Matrículas por dia nos últimos N dias. */
    public static function enrollmentsByDay(int $days = 30): array
    {
        $rows = DB::fetchAll(
            'SELECT DATE(enrolled_at) AS d, COUNT(*) AS n FROM enrollments WHERE enrolled_at >= :s GROUP BY DATE(enrolled_at)',
            ['s' => date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'))],
        );
        $map = array_column($rows, 'n', 'd');
        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('d/m', strtotime($d));
            $values[] = (int) ($map[$d] ?? 0);
        }
        return [$labels, $values];
    }

    /** Matrículas e conclusões por mês (últimos N meses). */
    public static function monthly(int $months = 6): array
    {
        $start = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));
        $enr = array_column(DB::fetchAll("SELECT DATE_FORMAT(enrolled_at, '%Y-%m') AS m, COUNT(*) AS n FROM enrollments WHERE enrolled_at >= :s GROUP BY m", ['s' => $start]), 'n', 'm');
        $cmp = array_column(DB::fetchAll("SELECT DATE_FORMAT(completed_at, '%Y-%m') AS m, COUNT(*) AS n FROM enrollments WHERE completed_at >= :s GROUP BY m", ['s' => $start]), 'n', 'm');
        $names = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
        $labels = $a = $b = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ts = strtotime(date('Y-m-01') . " -{$i} months");
            $key = date('Y-m', $ts);
            $labels[] = $names[(int) date('n', $ts) - 1] . '/' . date('y', $ts);
            $a[] = (int) ($enr[$key] ?? 0);
            $b[] = (int) ($cmp[$key] ?? 0);
        }
        return [$labels, $a, $b];
    }

    /** Indicadores por curso. */
    public static function courses(): array
    {
        return DB::fetchAll(
            "SELECT c.id, c.title, c.slug, c.status, c.workload_hours,
                    COUNT(e.id) AS enrolled,
                    SUM(e.status = 'active') AS active,
                    SUM(e.status = 'completed') AS completed,
                    SUM(e.status = 'cancelled') AS cancelled,
                    ROUND(AVG(CASE WHEN e.status <> 'cancelled' THEN e.progress_percent END)) AS avg_progress,
                    SUM(e.progress_percent > 0 AND e.status <> 'cancelled') AS started,
                    (SELECT ROUND(AVG(ct.final_score)) FROM certificates ct WHERE ct.course_id = c.id AND ct.status = 'valid') AS avg_score,
                    (SELECT COUNT(*) FROM certificates ct WHERE ct.course_id = c.id AND ct.status = 'valid') AS certificates
               FROM courses c LEFT JOIN enrollments e ON e.course_id = c.id
              GROUP BY c.id ORDER BY enrolled DESC, c.title",
        );
    }

    /** Quantos matriculados concluíram cada aula: mostra onde os alunos param. */
    public static function lessonFunnel(int $courseId): array
    {
        return DB::fetchAll(
            "SELECT l.id, l.title, m.title AS module_title, m.position AS mpos, l.position AS lpos,
                    (SELECT COUNT(*) FROM lesson_progress lp JOIN enrollments e ON e.user_id = lp.user_id AND e.course_id = m.course_id
                      WHERE lp.lesson_id = l.id AND lp.completed_at IS NOT NULL AND e.status <> 'cancelled') AS completed
               FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = :c ORDER BY m.position, l.position",
            ['c' => $courseId],
        );
    }

    /** Taxa de acerto por questão (apenas tentativas com respostas registradas). */
    public static function questionStats(int $courseId): array
    {
        $questions = DB::fetchAll(
            'SELECT qs.id, qs.statement, q.title AS quiz_title, (SELECT id FROM question_options o WHERE o.question_id = qs.id AND o.is_correct = 1 LIMIT 1) AS correct_id
               FROM questions qs JOIN quizzes q ON q.id = qs.quiz_id WHERE q.course_id = :c ORDER BY q.id, qs.position',
            ['c' => $courseId],
        );
        $attempts = DB::column(
            'SELECT qa.answers FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id WHERE q.course_id = :c AND qa.answers IS NOT NULL',
            ['c' => $courseId],
        );
        $stats = [];
        foreach ($questions as $q) {
            $stats[$q['id']] = $q + ['answered' => 0, 'right' => 0];
        }
        foreach ($attempts as $json) {
            foreach ((array) json_decode((string) $json, true) as $qid => $chosen) {
                if (isset($stats[$qid])) {
                    $stats[$qid]['answered']++;
                    if ((int) $chosen === (int) $stats[$qid]['correct_id']) {
                        $stats[$qid]['right']++;
                    }
                }
            }
        }
        return array_values($stats);
    }
}
