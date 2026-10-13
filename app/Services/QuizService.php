<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database as DB;

final class QuizService
{
    /** Questões com alternativas (sem revelar qual é a correta, a menos que $withAnswers). */
    public static function questions(int $quizId, bool $withAnswers = false): array
    {
        $questions = DB::fetchAll('SELECT * FROM questions WHERE quiz_id = :q ORDER BY position, id', ['q' => $quizId]);
        if (!$questions) {
            return [];
        }
        [$in, $params] = DB::in(array_column($questions, 'id'));
        $options = DB::fetchAll("SELECT * FROM question_options WHERE question_id IN {$in} ORDER BY position, id", $params);
        $byQuestion = [];
        foreach ($options as $o) {
            if (!$withAnswers) {
                unset($o['is_correct']);
            }
            $byQuestion[$o['question_id']][] = $o;
        }
        foreach ($questions as &$q) {
            $q['options'] = $byQuestion[$q['id']] ?? [];
        }
        return $questions;
    }

    /** @return array{score:int, passed:bool, correct:int, total:int, answers:array} */
    public static function grade(array $quiz, array $answers): array
    {
        $questions = self::questions((int) $quiz['id'], true);
        $correct = 0;
        $normalized = [];
        foreach ($questions as $q) {
            $chosen = (int) ($answers[$q['id']] ?? 0);
            $normalized[$q['id']] = $chosen;
            foreach ($q['options'] as $o) {
                if ((int) $o['id'] === $chosen && $o['is_correct']) {
                    $correct++;
                }
            }
        }
        $total = count($questions);
        $score = $total > 0 ? (int) round($correct / $total * 100) : 0;
        return [
            'score' => $score,
            'passed' => $score >= (int) $quiz['pass_score'],
            'correct' => $correct,
            'total' => $total,
            'answers' => $normalized,
        ];
    }

    public static function attempts(int $userId, int $quizId): array
    {
        return DB::fetchAll('SELECT * FROM quiz_attempts WHERE user_id = :u AND quiz_id = :q ORDER BY finished_at DESC', ['u' => $userId, 'q' => $quizId]);
    }
}
