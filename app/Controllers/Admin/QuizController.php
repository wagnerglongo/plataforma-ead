<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\QuizService;

/**
 * Avaliações de múltipla escolha. Ao editar, questões e alternativas existentes
 * são atualizadas (não recriadas), para não quebrar o histórico de respostas.
 */
final class QuizController extends Controller
{
    public function create(string $courseId): Response
    {
        $course = $this->course((int) $courseId);
        $moduleId = $this->request->query('modulo') ? (int) $this->request->query('modulo') : null;
        return $this->form($course, [
            'module_id' => $moduleId,
            'title' => $moduleId ? 'Teste rápido' : 'Avaliação final',
            'pass_score' => $course['pass_score'],
            'max_attempts' => $moduleId ? null : 3,
            'description' => '',
        ], []);
    }

    public function edit(string $id): Response
    {
        $quiz = $this->quiz((int) $id);
        return $this->form($this->course((int) $quiz['course_id']), $quiz, QuizService::questions((int) $quiz['id'], true));
    }

    public function store(string $courseId): Response
    {
        $course = $this->course((int) $courseId);
        [$data, $questions] = $this->validated((int) $course['id']);
        $id = DB::transaction(function () use ($data, $questions, $course) {
            $id = DB::insert('quizzes', $data + ['course_id' => $course['id'], 'created_at' => now(), 'updated_at' => now()]);
            $this->syncQuestions($id, $questions);
            return $id;
        });
        Audit::log('quiz.saved', 'quiz', $id, ['title' => $data['title']]);
        CurriculumController::recalculateCourse((int) $course['id']);
        flash('success', 'Avaliação criada.');
        return $this->redirect("admin/cursos/{$course['id']}/conteudo");
    }

    public function update(string $id): Response
    {
        $quiz = $this->quiz((int) $id);
        [$data, $questions] = $this->validated((int) $quiz['course_id'], (int) $quiz['id']);
        DB::transaction(function () use ($data, $questions, $quiz) {
            DB::update('quizzes', $data + ['updated_at' => now()], 'id = :id', ['id' => $quiz['id']]);
            $this->syncQuestions((int) $quiz['id'], $questions);
        });
        Audit::log('quiz.saved', 'quiz', (int) $quiz['id'], ['title' => $data['title']]);
        flash('success', 'Avaliação salva.');
        return $this->redirect("admin/avaliacoes/{$quiz['id']}");
    }

    public function destroy(string $id): Response
    {
        $quiz = $this->quiz((int) $id);
        DB::delete('quizzes', 'id = :id', ['id' => $quiz['id']]);
        Audit::log('quiz.deleted', 'quiz', (int) $quiz['id'], ['title' => $quiz['title']]);
        CurriculumController::recalculateCourse((int) $quiz['course_id']);
        flash('success', 'Avaliação excluída.');
        return $this->redirect("admin/cursos/{$quiz['course_id']}/conteudo");
    }

    private function form(array $course, array $quiz, array $questions): Response
    {
        $old = old('questions');
        if (is_array($old) && $old) { // volta do erro de validação: reapresenta o que foi digitado
            $questions = [];
            foreach ($old as $key => $q) {
                $opts = [];
                foreach ((array) ($q['options'] ?? []) as $okey => $text) {
                    $opts[] = ['key' => (string) $okey, 'text' => $text, 'is_correct' => (string) $okey === (string) ($q['correct'] ?? '')];
                }
                $questions[] = ['key' => (string) $key, 'statement' => $q['statement'] ?? '', 'explanation' => $q['explanation'] ?? '', 'options' => $opts];
            }
        }
        if (!$questions) {
            $questions = [['id' => null, 'statement' => '', 'explanation' => '', 'options' => [['id' => null, 'text' => '', 'is_correct' => 1], ['id' => null, 'text' => '', 'is_correct' => 0], ['id' => null, 'text' => '', 'is_correct' => 0]]]];
        }
        $usedModules = DB::column('SELECT module_id FROM quizzes WHERE course_id = :c AND module_id IS NOT NULL AND id <> :q', ['c' => $course['id'], 'q' => $quiz['id'] ?? 0]);
        return $this->view('admin/quizzes/form', [
            'title' => empty($quiz['id']) ? 'Nova avaliação' : 'Editar avaliação',
            'course' => $course,
            'quiz' => $quiz,
            'questions' => $questions,
            'modules' => DB::fetchAll('SELECT * FROM modules WHERE course_id = :c ORDER BY position', ['c' => $course['id']]),
            'usedModules' => array_map('intval', $usedModules),
            'hasFinal' => (bool) DB::value('SELECT 1 FROM quizzes WHERE course_id = :c AND module_id IS NULL AND id <> :q', ['c' => $course['id'], 'q' => $quiz['id'] ?? 0]),
            'attempts' => empty($quiz['id']) ? 0 : (int) DB::value('SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = :q', ['q' => $quiz['id']]),
        ], 'admin');
    }

    /** @return array{0: array, 1: array} dados da avaliação e questões normalizadas */
    private function validated(int $courseId, ?int $quizId = null): array
    {
        $data = $this->validate([
            'title' => 'required|min:2|max:160',
            'description' => 'nullable|max:500',
            'module_id' => 'nullable|integer',
            'pass_score' => 'required|integer|min:1|max:100',
            'max_attempts' => 'nullable|integer|min:1|max:50',
        ], ['title' => 'título', 'pass_score' => 'nota mínima', 'max_attempts' => 'tentativas']);
        if ($data['module_id'] && !DB::value('SELECT 1 FROM modules WHERE id = :m AND course_id = :c', ['m' => $data['module_id'], 'c' => $courseId])) {
            throw ValidationException::withMessage('module_id', 'Módulo inválido.');
        }
        $dupe = $data['module_id']
            ? DB::value('SELECT 1 FROM quizzes WHERE module_id = :m AND id <> :q', ['m' => $data['module_id'], 'q' => $quizId ?? 0])
            : DB::value('SELECT 1 FROM quizzes WHERE course_id = :c AND module_id IS NULL AND id <> :q', ['c' => $courseId, 'q' => $quizId ?? 0]);
        if ($dupe) {
            throw ValidationException::withMessage('module_id', $data['module_id'] ? 'Este módulo já tem um teste.' : 'O curso já tem uma avaliação final.');
        }

        $questions = [];
        foreach ((array) $this->request->input('questions', []) as $key => $q) {
            $statement = trim((string) ($q['statement'] ?? ''));
            $options = array_filter(array_map(fn ($o) => trim((string) $o), (array) ($q['options'] ?? [])), 'strlen');
            if ($statement === '' && !$options) {
                continue;
            }
            $n = count($questions) + 1;
            if ($statement === '') {
                throw ValidationException::withMessage('questions', "A questão {$n} está sem enunciado.");
            }
            if (count($options) < 2) {
                throw ValidationException::withMessage('questions', "A questão {$n} precisa de pelo menos duas alternativas.");
            }
            $correct = (string) ($q['correct'] ?? '');
            if (!isset($options[$correct])) {
                throw ValidationException::withMessage('questions', "Marque a alternativa correta da questão {$n}.");
            }
            $questions[] = ['key' => (string) $key, 'statement' => $statement, 'explanation' => trim((string) ($q['explanation'] ?? '')) ?: null, 'options' => $options, 'correct' => $correct];
        }
        if (!$questions) {
            throw ValidationException::withMessage('questions', 'Adicione pelo menos uma questão.');
        }
        return [$data, $questions];
    }

    private function syncQuestions(int $quizId, array $questions): void
    {
        $keepQuestions = [];
        foreach ($questions as $pos => $q) {
            $qid = str_starts_with($q['key'], 'e') ? (int) substr($q['key'], 1) : 0;
            $exists = $qid && DB::value('SELECT 1 FROM questions WHERE id = :id AND quiz_id = :q', ['id' => $qid, 'q' => $quizId]);
            if ($exists) {
                DB::update('questions', ['statement' => $q['statement'], 'explanation' => $q['explanation'], 'position' => $pos + 1], 'id = :id', ['id' => $qid]);
            } else {
                $qid = DB::insert('questions', ['quiz_id' => $quizId, 'statement' => $q['statement'], 'explanation' => $q['explanation'], 'position' => $pos + 1]);
            }
            $keepQuestions[] = $qid;
            $keepOptions = [];
            $opos = 0;
            foreach ($q['options'] as $okey => $text) {
                $oid = str_starts_with((string) $okey, 'e') ? (int) substr((string) $okey, 1) : 0;
                $row = ['text' => $text, 'is_correct' => (int) ((string) $okey === $q['correct']), 'position' => ++$opos];
                if ($oid && DB::value('SELECT 1 FROM question_options WHERE id = :id AND question_id = :q', ['id' => $oid, 'q' => $qid])) {
                    DB::update('question_options', $row, 'id = :id', ['id' => $oid]);
                } else {
                    $oid = DB::insert('question_options', $row + ['question_id' => $qid]);
                }
                $keepOptions[] = $oid;
            }
            [$in, $params] = DB::in($keepOptions, 'o');
            DB::query("DELETE FROM question_options WHERE question_id = :q AND id NOT IN {$in}", ['q' => $qid] + $params);
        }
        [$in, $params] = DB::in($keepQuestions, 'k');
        DB::query("DELETE FROM questions WHERE quiz_id = :q AND id NOT IN {$in}", ['q' => $quizId] + $params);
    }

    private function course(int $id): array
    {
        return DB::fetch('SELECT * FROM courses WHERE id = :id', ['id' => $id]) ?? throw HttpException::notFound('Curso não encontrado.');
    }

    private function quiz(int $id): array
    {
        return DB::fetch('SELECT * FROM quizzes WHERE id = :id', ['id' => $id]) ?? throw HttpException::notFound('Avaliação não encontrada.');
    }
}
