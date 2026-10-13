<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database as DB;
use App\Models\Course;

/**
 * Regras de progresso:
 *  - aula em vídeo conta como concluída quando o aluno ASSISTE de fato ≥ 85% da duração
 *    (tempo real de reprodução; pular para o fim não conta);
 *  - aula em texto ou vídeo incorporado sem API é concluída pelo botão "Marcar como concluída";
 *  - o curso termina quando todas as aulas estão concluídas e todas as avaliações aprovadas;
 *  - ao terminar, o certificado é emitido automaticamente.
 */
final class ProgressService
{
    public const WATCH_THRESHOLD = 0.85;
    private const MAX_DELTA_PER_PING = 30; // o player envia a cada ~10 s

    /** @return array<int, array> lesson_id => progresso */
    public static function lessonMap(int $userId, int $courseId): array
    {
        $rows = DB::fetchAll(
            'SELECT lp.* FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id JOIN modules m ON m.id = l.module_id
              WHERE lp.user_id = :u AND m.course_id = :c',
            ['u' => $userId, 'c' => $courseId],
        );
        return array_column($rows, null, 'lesson_id');
    }

    /** @return array<int, array{best:int, passed:bool, attempts:int}> */
    public static function quizMap(int $userId, int $courseId): array
    {
        $rows = DB::fetchAll(
            'SELECT qa.quiz_id, MAX(qa.score) AS best, MAX(qa.passed) AS passed, COUNT(*) AS attempts
               FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id
              WHERE qa.user_id = :u AND q.course_id = :c GROUP BY qa.quiz_id',
            ['u' => $userId, 'c' => $courseId],
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['quiz_id']] = ['best' => (int) $r['best'], 'passed' => (bool) $r['passed'], 'attempts' => (int) $r['attempts']];
        }
        return $map;
    }

    /** Registra posição e tempo assistido; conclui a aula quando atinge o limite. */
    public static function track(int $userId, array $lesson, int $position, int $delta, int $clientDuration = 0): array
    {
        $delta = max(0, min($delta, self::MAX_DELTA_PER_PING));
        $duration = (int) $lesson['duration_seconds'] ?: $clientDuration;
        $row = DB::fetch('SELECT * FROM lesson_progress WHERE user_id = :u AND lesson_id = :l', ['u' => $userId, 'l' => $lesson['id']]);
        $watched = min(($row['watched_seconds'] ?? 0) + $delta, max($duration, 1) * 2);
        $completedAt = $row['completed_at'] ?? null;
        $justCompleted = false;
        if (!$completedAt && $duration > 0 && $watched >= $duration * self::WATCH_THRESHOLD) {
            $completedAt = now();
            $justCompleted = true;
        }
        self::upsert($userId, (int) $lesson['id'], max(0, $position), (int) $watched, $completedAt);
        return [
            'completed' => $completedAt !== null,
            'just_completed' => $justCompleted,
            'watched_percent' => $duration > 0 ? min(100, (int) floor($watched / $duration * 100)) : 0,
        ];
    }

    public static function complete(int $userId, int $lessonId): void
    {
        $row = DB::fetch('SELECT * FROM lesson_progress WHERE user_id = :u AND lesson_id = :l', ['u' => $userId, 'l' => $lessonId]);
        if (!$row || !$row['completed_at']) {
            self::upsert($userId, $lessonId, (int) ($row['position_seconds'] ?? 0), (int) ($row['watched_seconds'] ?? 0), now());
        }
    }

    private static function upsert(int $userId, int $lessonId, int $position, int $watched, ?string $completedAt): void
    {
        DB::query(
            'INSERT INTO lesson_progress (user_id, lesson_id, position_seconds, watched_seconds, completed_at, updated_at)
             VALUES (:u, :l, :p, :w, :c, :t)
             ON DUPLICATE KEY UPDATE position_seconds = VALUES(position_seconds), watched_seconds = VALUES(watched_seconds),
                                     completed_at = COALESCE(completed_at, VALUES(completed_at)), updated_at = VALUES(updated_at)',
            ['u' => $userId, 'l' => $lessonId, 'p' => $position, 'w' => $watched, 'c' => $completedAt, 't' => now()],
        );
    }

    /**
     * Recalcula o percentual da matrícula; conclui e emite certificado ao chegar a 100%.
     * @return array{percent:int, completed:bool, certificate:?array, newly_completed:bool}
     */
    public static function recalculate(int $userId, int $courseId): array
    {
        $enrollment = DB::fetch('SELECT * FROM enrollments WHERE user_id = :u AND course_id = :c', ['u' => $userId, 'c' => $courseId]);
        if (!$enrollment) {
            return ['percent' => 0, 'completed' => false, 'certificate' => null, 'newly_completed' => false];
        }
        $curriculum = Course::curriculum($courseId);
        $lessonIds = [];
        $quizIds = [];
        foreach (Course::sequence($curriculum) as $item) {
            if ($item['type'] === 'lesson') {
                $lessonIds[] = $item['id'];
            } else {
                $quizIds[] = $item['id'];
            }
        }
        $total = count($lessonIds) + count($quizIds);
        $lessons = self::lessonMap($userId, $courseId);
        $quizzes = self::quizMap($userId, $courseId);
        $done = count(array_filter($lessonIds, fn ($id) => !empty($lessons[$id]['completed_at'])))
              + count(array_filter($quizIds, fn ($id) => !empty($quizzes[$id]['passed'])));
        $percent = $total > 0 ? (int) floor($done / $total * 100) : 0;
        $completed = $total > 0 && $done === $total;

        $newly = $completed && $enrollment['status'] !== 'completed';
        DB::update('enrollments', [
            'progress_percent' => $percent,
            'status' => $completed ? 'completed' : ($enrollment['status'] === 'cancelled' ? 'cancelled' : 'active'),
            'completed_at' => $completed ? ($enrollment['completed_at'] ?? now()) : null,
        ], 'id = :id', ['id' => $enrollment['id']]);

        $certificate = null;
        if ($completed) {
            $course = DB::fetch('SELECT certificate_enabled FROM courses WHERE id = :id', ['id' => $courseId]);
            if ($course && $course['certificate_enabled']) {
                $certificate = CertificateService::issue($userId, $courseId);
            }
        }
        return ['percent' => $percent, 'completed' => $completed, 'certificate' => $certificate, 'newly_completed' => $newly];
    }

    /** Próximo item não concluído (para o botão "Continuar de onde parou"). */
    public static function resumeTarget(int $userId, array $enrollment): ?array
    {
        $curriculum = Course::curriculum((int) $enrollment['course_id']);
        $sequence = Course::sequence($curriculum);
        if (!$sequence) {
            return null;
        }
        if ($enrollment['last_lesson_id']) {
            $lessons = self::lessonMap($userId, (int) $enrollment['course_id']);
            $last = (int) $enrollment['last_lesson_id'];
            if (empty($lessons[$last]['completed_at'])) {
                foreach ($sequence as $item) {
                    if ($item['type'] === 'lesson' && $item['id'] === $last) {
                        return $item + ['position' => (int) ($lessons[$last]['position_seconds'] ?? 0)];
                    }
                }
            }
        }
        $lessons ??= self::lessonMap($userId, (int) $enrollment['course_id']);
        $quizzes = self::quizMap($userId, (int) $enrollment['course_id']);
        foreach ($sequence as $item) {
            $done = $item['type'] === 'lesson' ? !empty($lessons[$item['id']]['completed_at']) : !empty($quizzes[$item['id']]['passed']);
            if (!$done) {
                return $item + ['position' => $item['type'] === 'lesson' ? (int) ($lessons[$item['id']]['position_seconds'] ?? 0) : 0];
            }
        }
        return $sequence[0] + ['position' => 0];
    }
}
