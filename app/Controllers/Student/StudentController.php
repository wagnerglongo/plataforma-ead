<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Models\Course;
use App\Services\ProgressService;

/** Base da área do aluno: carrega curso + matrícula e monta o contexto do player. */
abstract class StudentController extends Controller
{
    /** @return array{0: array, 1: ?array} curso e matrícula (null quando é a equipe visualizando) */
    protected function courseAccess(string $slug): array
    {
        $course = Course::findBySlug($slug, !Auth::isStaff());
        if (!$course) {
            throw HttpException::notFound('Curso não encontrado.');
        }
        $enrollment = DB::fetch(
            "SELECT * FROM enrollments WHERE user_id = :u AND course_id = :c AND status <> 'cancelled'",
            ['u' => Auth::id(), 'c' => $course['id']],
        );
        if (!$enrollment && !Auth::can('courses.view')) {
            throw HttpException::forbidden('Você precisa estar matriculado neste curso para acessar as aulas.');
        }
        return [$course, $enrollment];
    }

    /** Aula + curso a que pertence, conferindo o acesso do aluno. */
    protected function lessonAccess(int $lessonId): array
    {
        $lesson = DB::fetch(
            'SELECT l.*, m.course_id, c.slug AS course_slug FROM lessons l JOIN modules m ON m.id = l.module_id JOIN courses c ON c.id = m.course_id WHERE l.id = :id',
            ['id' => $lessonId],
        );
        if (!$lesson) {
            throw HttpException::notFound('Aula não encontrada.');
        }
        [$course, $enrollment] = $this->courseAccess($lesson['course_slug']);
        return [$lesson, $course, $enrollment];
    }

    /** Dados comuns ao player (barra lateral, progresso, navegação). */
    protected function playerContext(array $course, ?array $enrollment, string $type, int $currentId): array
    {
        $userId = (int) Auth::id();
        $curriculum = Course::curriculum((int) $course['id']);
        $sequence = Course::sequence($curriculum);
        $lessons = ProgressService::lessonMap($userId, (int) $course['id']);
        $quizzes = ProgressService::quizMap($userId, (int) $course['id']);

        $index = 0;
        foreach ($sequence as $i => $item) {
            if ($item['type'] === $type && $item['id'] === $currentId) {
                $index = $i;
            }
        }
        $link = fn (?array $item) => $item ? url('aluno/curso/' . $course['slug'] . '/' . ($item['type'] === 'lesson' ? 'aula' : 'avaliacao') . '/' . $item['id']) : null;

        return [
            'course' => $course,
            'enrollment' => $enrollment,
            'curriculum' => $curriculum,
            'lessonProgress' => $lessons,
            'quizProgress' => $quizzes,
            'locks' => $this->quizLocks($curriculum, $lessons, $quizzes),
            'prev' => $sequence[$index - 1] ?? null,
            'next' => $sequence[$index + 1] ?? null,
            'prevUrl' => $link($sequence[$index - 1] ?? null),
            'nextUrl' => $link($sequence[$index + 1] ?? null),
            'current' => ['type' => $type, 'id' => $currentId],
            'percent' => (int) ($enrollment['progress_percent'] ?? 0),
            'bodyClass' => 'player-body',
            'noindex' => true,
        ];
    }

    /**
     * Avaliação do módulo libera quando as aulas do módulo foram concluídas;
     * a final, quando todas as aulas e avaliações de módulo estão concluídas.
     * @return array<int, string> quiz_id => motivo do bloqueio
     */
    protected function quizLocks(array $curriculum, array $lessons, array $quizzes): array
    {
        $locks = [];
        $allDone = true;
        foreach ($curriculum['modules'] as $m) {
            $moduleDone = true;
            foreach ($m['lessons'] as $l) {
                if (empty($lessons[$l['id']]['completed_at'])) {
                    $moduleDone = false;
                }
            }
            if ($m['quiz']) {
                if (!$moduleDone) {
                    $locks[(int) $m['quiz']['id']] = 'Conclua as aulas deste módulo para liberar.';
                }
                if (empty($quizzes[$m['quiz']['id']]['passed'])) {
                    $allDone = false;
                }
            }
            $allDone = $allDone && $moduleDone;
        }
        if ($curriculum['final'] && !$allDone) {
            $locks[(int) $curriculum['final']['id']] = 'Conclua todas as aulas e testes para liberar.';
        }
        return $locks;
    }
}
