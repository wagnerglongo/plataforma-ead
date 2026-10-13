<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;
use App\Core\Paginator;

final class Course
{
    public const LEVELS = ['iniciante' => 'Iniciante', 'intermediario' => 'Intermediário', 'avancado' => 'Avançado'];

    public const DURATIONS = [
        'ate-8' => 'Até 8 horas',
        '9-20' => 'De 9 a 20 horas',
        'mais-20' => 'Mais de 20 horas',
    ];

    public const SORTS = [
        'relevancia' => 'Mais relevantes',
        'recentes' => 'Mais recentes',
        'populares' => 'Mais procurados',
        'menor-carga' => 'Menor carga horária',
    ];

    /** Colunas agregadas reutilizadas no catálogo e no painel. */
    private const AGG = "c.*, cat.name AS category_name, cat.slug AS category_slug, u.name AS instructor_name,
        (SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = c.id) AS lessons_count,
        (SELECT COALESCE(SUM(l.duration_seconds),0) FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = c.id) AS total_seconds,
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status <> 'cancelled') AS students_count";

    public static function catalog(array $f, int $page, int $perPage = 9): array
    {
        $where = ["c.status = 'published'"];
        $params = [];
        if (!empty($f['q'])) {
            $where[] = '(c.title LIKE :q1 OR c.summary LIKE :q2 OR cat.name LIKE :q3)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['q']) . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        if (!empty($f['categoria'])) {
            $where[] = 'cat.slug = :cat';
            $params['cat'] = $f['categoria'];
        }
        if (!empty($f['nivel']) && isset(self::LEVELS[$f['nivel']])) {
            $where[] = 'c.level = :lvl';
            $params['lvl'] = $f['nivel'];
        }
        $where[] = match ($f['duracao'] ?? '') {
            'ate-8' => 'c.workload_hours <= 8',
            '9-20' => 'c.workload_hours BETWEEN 9 AND 20',
            'mais-20' => 'c.workload_hours > 20',
            default => '1=1',
        };
        $order = match ($f['ordem'] ?? 'relevancia') {
            'recentes' => 'c.published_at DESC',
            'populares' => 'students_count DESC, c.title',
            'menor-carga' => 'c.workload_hours ASC, c.title',
            default => 'c.featured DESC, students_count DESC, c.title',
        };
        $from = 'FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id LEFT JOIN users u ON u.id = c.instructor_id WHERE ' . implode(' AND ', $where);
        return Paginator::paginate(self::AGG, $from, $params, $order, $page, $perPage);
    }

    public static function featured(int $limit = 3): array
    {
        return DB::fetchAll(
            'SELECT ' . self::AGG . ' FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id LEFT JOIN users u ON u.id = c.instructor_id
             WHERE c.status = \'published\' ORDER BY c.featured DESC, students_count DESC LIMIT ' . (int) $limit,
        );
    }

    public static function findBySlug(string $slug, bool $publishedOnly = true): ?array
    {
        return DB::fetch(
            'SELECT ' . self::AGG . ' FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id LEFT JOIN users u ON u.id = c.instructor_id
             WHERE c.slug = :s' . ($publishedOnly ? " AND c.status = 'published'" : ''),
            ['s' => $slug],
        );
    }

    public static function find(int $id): ?array
    {
        return DB::fetch(
            'SELECT ' . self::AGG . ' FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id LEFT JOIN users u ON u.id = c.instructor_id WHERE c.id = :id',
            ['id' => $id],
        );
    }

    public static function related(array $course, int $limit = 3): array
    {
        return DB::fetchAll(
            'SELECT ' . self::AGG . ' FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id LEFT JOIN users u ON u.id = c.instructor_id
             WHERE c.status = \'published\' AND c.id <> :id ORDER BY (c.category_id <=> :cat) DESC, students_count DESC LIMIT ' . (int) $limit,
            ['id' => $course['id'], 'cat' => $course['category_id']],
        );
    }

    /**
     * Grade curricular: módulos → aulas (+ materiais) e avaliações.
     * @return array<int, array{id:int,title:string,lessons:array,quiz:?array}> + chave 'final' para a avaliação final
     */
    public static function curriculum(int $courseId): array
    {
        $modules = DB::fetchAll('SELECT * FROM modules WHERE course_id = :c ORDER BY position, id', ['c' => $courseId]);
        $lessons = DB::fetchAll(
            'SELECT l.*, (SELECT COUNT(*) FROM materials mt WHERE mt.lesson_id = l.id) AS materials_count
               FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = :c ORDER BY l.position, l.id',
            ['c' => $courseId],
        );
        $quizzes = DB::fetchAll(
            'SELECT q.*, (SELECT COUNT(*) FROM questions qs WHERE qs.quiz_id = q.id) AS questions_count
               FROM quizzes q WHERE q.course_id = :c ORDER BY q.id',
            ['c' => $courseId],
        );
        $byModule = [];
        foreach ($lessons as $l) {
            $byModule[$l['module_id']][] = $l;
        }
        $quizByModule = [];
        $final = null;
        foreach ($quizzes as $q) {
            if ($q['module_id'] === null) {
                $final = $q;
            } else {
                $quizByModule[$q['module_id']] = $q;
            }
        }
        foreach ($modules as &$m) {
            $m['lessons'] = $byModule[$m['id']] ?? [];
            $m['quiz'] = $quizByModule[$m['id']] ?? null;
            $m['seconds'] = array_sum(array_column($m['lessons'], 'duration_seconds'));
        }
        return ['modules' => $modules, 'final' => $final];
    }

    /** Sequência linear de itens (aulas e avaliações) para navegação anterior/próxima. */
    public static function sequence(array $curriculum): array
    {
        $items = [];
        foreach ($curriculum['modules'] as $m) {
            foreach ($m['lessons'] as $l) {
                $items[] = ['type' => 'lesson', 'id' => (int) $l['id'], 'title' => $l['title'], 'module' => $m['title']];
            }
            if ($m['quiz']) {
                $items[] = ['type' => 'quiz', 'id' => (int) $m['quiz']['id'], 'title' => $m['quiz']['title'], 'module' => $m['title']];
            }
        }
        if ($curriculum['final']) {
            $items[] = ['type' => 'quiz', 'id' => (int) $curriculum['final']['id'], 'title' => $curriculum['final']['title'], 'module' => 'Encerramento'];
        }
        return $items;
    }

    public static function categoriesWithCounts(): array
    {
        return DB::fetchAll(
            "SELECT cat.*, COUNT(c.id) AS courses_count FROM categories cat
               LEFT JOIN courses c ON c.category_id = cat.id AND c.status = 'published'
              GROUP BY cat.id HAVING courses_count > 0 ORDER BY cat.name",
        );
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = str_slug($title);
        $slug = $base;
        $i = 2;
        while ((int) DB::value('SELECT COUNT(*) FROM courses WHERE slug = :s AND id <> :id', ['s' => $slug, 'id' => $ignoreId ?? 0]) > 0) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
