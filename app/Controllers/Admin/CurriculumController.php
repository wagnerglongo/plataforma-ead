<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Models\Course;

/** Montagem da grade: módulos, ordem das aulas e avaliações. */
final class CurriculumController extends Controller
{
    public function index(string $id): Response
    {
        $course = Course::find((int) $id) ?? throw HttpException::notFound('Curso não encontrado.');
        return $this->view('admin/courses/curriculum', [
            'title' => 'Conteúdo: ' . $course['title'],
            'course' => $course,
            'curriculum' => Course::curriculum((int) $id),
        ], 'admin');
    }

    public function storeModule(string $id): Response
    {
        $course = Course::find((int) $id) ?? throw HttpException::notFound();
        $data = $this->validate(['title' => 'required|min:2|max:160'], ['title' => 'nome do módulo']);
        $position = (int) DB::value('SELECT COALESCE(MAX(position), 0) + 1 FROM modules WHERE course_id = :c', ['c' => $course['id']]);
        $moduleId = DB::insert('modules', ['course_id' => $course['id'], 'title' => $data['title'], 'position' => $position, 'created_at' => now()]);
        Audit::log('module.saved', 'module', $moduleId, ['title' => $data['title']]);
        flash('success', 'Módulo criado. Adicione as aulas dele.');
        return $this->redirect("admin/cursos/{$course['id']}/conteudo#modulo-{$moduleId}");
    }

    public function updateModule(string $id): Response
    {
        $module = DB::fetch('SELECT * FROM modules WHERE id = :id', ['id' => (int) $id]) ?? throw HttpException::notFound();
        $data = $this->validate(['title' => 'required|min:2|max:160'], ['title' => 'nome do módulo']);
        DB::update('modules', ['title' => $data['title']], 'id = :id', ['id' => $module['id']]);
        Audit::log('module.saved', 'module', (int) $module['id'], ['title' => $data['title']]);
        flash('success', 'Módulo renomeado.');
        return $this->redirect("admin/cursos/{$module['course_id']}/conteudo#modulo-{$module['id']}");
    }

    public function destroyModule(string $id): Response
    {
        $module = DB::fetch('SELECT * FROM modules WHERE id = :id', ['id' => (int) $id]) ?? throw HttpException::notFound();
        DB::delete('modules', 'id = :id', ['id' => $module['id']]);
        Audit::log('module.deleted', 'module', (int) $module['id'], ['title' => $module['title']]);
        $this->recalculateCourse((int) $module['course_id']);
        flash('success', 'Módulo excluído com as aulas dele.');
        return $this->redirect("admin/cursos/{$module['course_id']}/conteudo");
    }

    /** Recebe a nova ordem (arrastar e soltar) em JSON. */
    public function reorder(string $id): Response
    {
        $type = $this->request->input('type');
        $ids = array_map('intval', (array) $this->request->input('ids', []));
        if (!$ids) {
            return $this->json(['ok' => false], 422);
        }
        [$in, $params] = DB::in($ids);
        if ($type === 'modules') {
            $valid = DB::column("SELECT id FROM modules WHERE course_id = :c AND id IN {$in}", ['c' => (int) $id] + $params);
            $table = 'modules';
        } else {
            $valid = DB::column("SELECT l.id FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = :c AND l.id IN {$in}", ['c' => (int) $id] + $params);
            $table = 'lessons';
        }
        if (count($valid) !== count($ids)) {
            return $this->json(['ok' => false, 'error' => 'Itens não pertencem a este curso.'], 422);
        }
        DB::transaction(function () use ($ids, $table) {
            foreach ($ids as $pos => $itemId) {
                DB::update($table, ['position' => $pos + 1], 'id = :id', ['id' => $itemId]);
            }
        });
        return $this->json(['ok' => true]);
    }

    /** Depois de remover conteúdo, o percentual de cada matrícula muda. */
    public static function recalculateCourse(int $courseId): void
    {
        foreach (DB::column("SELECT user_id FROM enrollments WHERE course_id = :c AND status <> 'cancelled'", ['c' => $courseId]) as $userId) {
            \App\Services\ProgressService::recalculate((int) $userId, $courseId);
        }
    }
}
