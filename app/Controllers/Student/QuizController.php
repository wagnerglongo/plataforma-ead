<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Auth;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Services\ProgressService;
use App\Services\QuizService;

final class QuizController extends StudentController
{
    public function show(string $slug, string $id): Response
    {
        [$course, $enrollment] = $this->courseAccess($slug);
        $quiz = $this->quiz($course, (int) $id);
        $ctx = $this->playerContext($course, $enrollment, 'quiz', (int) $quiz['id']);
        $attempts = QuizService::attempts((int) Auth::id(), (int) $quiz['id']);
        $passed = (bool) array_filter($attempts, fn ($a) => $a['passed']);
        $remaining = $quiz['max_attempts'] !== null ? max(0, (int) $quiz['max_attempts'] - count($attempts)) : null;

        return $this->view('student/quiz', $ctx + [
            'title' => $quiz['title'],
            'quiz' => $quiz,
            'questions' => QuizService::questions((int) $quiz['id']),
            'attempts' => $attempts,
            'passed' => $passed,
            'remaining' => $remaining,
            'lockReason' => $ctx['locks'][$quiz['id']] ?? null,
            'preview' => $enrollment === null,
        ], 'player');
    }

    public function submit(string $slug, string $id): Response
    {
        [$course, $enrollment] = $this->courseAccess($slug);
        if (!$enrollment) {
            flash('info', 'Na visualização da equipe as respostas não são registradas.');
            return $this->back();
        }
        $quiz = $this->quiz($course, (int) $id);
        $ctx = $this->playerContext($course, $enrollment, 'quiz', (int) $quiz['id']);
        if (isset($ctx['locks'][$quiz['id']])) {
            flash('warning', $ctx['locks'][$quiz['id']]);
            return $this->back();
        }
        $attempts = QuizService::attempts((int) Auth::id(), (int) $quiz['id']);
        if ($quiz['max_attempts'] !== null && count($attempts) >= (int) $quiz['max_attempts'] && !array_filter($attempts, fn ($a) => $a['passed'])) {
            flash('error', 'Você usou todas as tentativas desta avaliação. Fale com o suporte para liberar uma nova.');
            return $this->back();
        }
        $answers = (array) ($this->request->input('answers') ?? []);
        $questions = QuizService::questions((int) $quiz['id']);
        $missing = array_filter($questions, fn ($q) => empty($answers[$q['id']]));
        if ($missing) {
            Session::flash('_old_input', ['answers' => $answers]);
            flash('error', 'Responda todas as questões antes de enviar. ' . plural(count($missing), 'questão ficou', 'questões ficaram') . ' sem resposta.');
            return $this->back();
        }

        $result = QuizService::grade($quiz, $answers);
        $attemptId = DB::insert('quiz_attempts', [
            'user_id' => Auth::id(),
            'quiz_id' => $quiz['id'],
            'score' => $result['score'],
            'passed' => (int) $result['passed'],
            'answers' => json_encode($result['answers']),
            'finished_at' => now(),
        ]);
        $progress = ProgressService::recalculate((int) Auth::id(), (int) $course['id']);
        if ($progress['newly_completed'] && $progress['certificate']) {
            Session::flash('_track', ['event' => 'course_complete', 'course' => $course['slug']]);
            return $this->redirect('aluno/certificados?novo=' . $progress['certificate']['code']);
        }
        return $this->redirect("aluno/curso/{$slug}/avaliacao/{$quiz['id']}/resultado/{$attemptId}");
    }

    public function result(string $slug, string $id, string $attempt): Response
    {
        [$course, $enrollment] = $this->courseAccess($slug);
        $quiz = $this->quiz($course, (int) $id);
        $row = DB::fetch('SELECT * FROM quiz_attempts WHERE id = :a AND quiz_id = :q AND user_id = :u', ['a' => (int) $attempt, 'q' => $quiz['id'], 'u' => Auth::id()]);
        if (!$row) {
            throw HttpException::notFound('Tentativa não encontrada.');
        }
        $ctx = $this->playerContext($course, $enrollment, 'quiz', (int) $quiz['id']);
        $attempts = QuizService::attempts((int) Auth::id(), (int) $quiz['id']);
        $remaining = $quiz['max_attempts'] !== null ? max(0, (int) $quiz['max_attempts'] - count($attempts)) : null;
        return $this->view('student/quiz-result', $ctx + [
            'title' => 'Resultado: ' . $quiz['title'],
            'quiz' => $quiz,
            'attempt' => $row,
            'answers' => json_decode((string) $row['answers'], true) ?: [],
            'questions' => QuizService::questions((int) $quiz['id'], true),
            'remaining' => $remaining,
            'preview' => false,
        ], 'player');
    }

    private function quiz(array $course, int $id): array
    {
        $quiz = DB::fetch(
            'SELECT q.*, (SELECT COUNT(*) FROM questions qs WHERE qs.quiz_id = q.id) AS questions_count FROM quizzes q WHERE q.id = :id AND q.course_id = :c',
            ['id' => $id, 'c' => $course['id']],
        );
        if (!$quiz) {
            throw HttpException::notFound('Avaliação não encontrada.');
        }
        return $quiz;
    }
}
