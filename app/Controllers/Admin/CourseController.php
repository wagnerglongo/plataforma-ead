<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Upload;
use App\Core\ValidationException;
use App\Models\Course;

final class CourseController extends Controller
{
    private const LABELS = [
        'title' => 'título', 'slug' => 'endereço', 'summary' => 'resumo', 'category_id' => 'categoria',
        'workload_hours' => 'carga horária', 'level' => 'nível', 'pass_score' => 'nota mínima', 'cover_color' => 'cor da capa',
    ];

    public function index(): Response
    {
        $status = (string) $this->request->query('status', '');
        $q = trim((string) $this->request->query('q', ''));
        $where = ['1=1'];
        $params = [];
        if (in_array($status, ['draft', 'published'], true)) {
            $where[] = 'c.status = :st';
            $params['st'] = $status;
        }
        if ($q !== '') {
            $where[] = 'c.title LIKE :q';
            $params['q'] = '%' . $q . '%';
        }
        $courses = DB::fetchAll(
            "SELECT c.*, cat.name AS category_name,
                    (SELECT COUNT(*) FROM modules m WHERE m.course_id = c.id) AS modules_count,
                    (SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = c.id) AS lessons_count,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status <> 'cancelled') AS students,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'completed') AS completed
               FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id
              WHERE " . implode(' AND ', $where) . ' ORDER BY c.status = \'draft\' DESC, c.updated_at DESC',
            $params,
        );
        return $this->view('admin/courses/index', ['title' => 'Cursos', 'courses' => $courses, 'status' => $status, 'q' => $q], 'admin');
    }

    public function create(): Response
    {
        return $this->form(['status' => 'draft', 'level' => 'iniciante', 'pass_score' => 70, 'certificate_enabled' => 1, 'cover_color' => '#1F3A93', 'workload_hours' => 8]);
    }

    public function edit(string $id): Response
    {
        $course = Course::find((int) $id) ?? throw HttpException::notFound('Curso não encontrado.');
        return $this->form($course);
    }

    public function store(): Response
    {
        $data = $this->validated();
        $data['slug'] = $data['slug'] ?: Course::uniqueSlug($data['title']);
        $data += ['created_at' => now(), 'updated_at' => now(), 'published_at' => $data['status'] === 'published' ? now() : null];
        if ($file = $this->request->file('thumbnail')) {
            $data['thumbnail'] = $this->storeThumb($file);
        }
        $id = DB::insert('courses', $data);
        Audit::log('course.created', 'course', $id, ['title' => $data['title']]);
        flash('success', 'Curso criado. Agora monte os módulos e as aulas.');
        return $this->redirect("admin/cursos/{$id}/conteudo");
    }

    public function update(string $id): Response
    {
        $course = Course::find((int) $id) ?? throw HttpException::notFound();
        $data = $this->validated((int) $id);
        $data['slug'] = $data['slug'] ?: Course::uniqueSlug($data['title'], (int) $id);
        $data['updated_at'] = now();
        if ($data['status'] === 'published' && !$course['published_at']) {
            $data['published_at'] = now();
        }
        if ($file = $this->request->file('thumbnail')) {
            $data['thumbnail'] = $this->storeThumb($file);
            Upload::deletePublic($course['thumbnail']);
        } elseif ($this->request->input('remove_thumbnail')) {
            Upload::deletePublic($course['thumbnail']);
            $data['thumbnail'] = null;
        }
        DB::update('courses', $data, 'id = :id', ['id' => (int) $id]);
        Audit::log('course.updated', 'course', (int) $id, ['title' => $data['title']]);
        flash('success', 'Curso salvo.');
        return $this->redirect("admin/cursos/{$id}");
    }

    public function destroy(string $id): Response
    {
        $course = Course::find((int) $id) ?? throw HttpException::notFound();
        if ((int) $course['students_count'] > 0) {
            flash('error', 'Este curso tem alunos matriculados. Mude para rascunho em vez de excluir, para preservar o histórico.');
            return $this->back();
        }
        DB::delete('courses', 'id = :id', ['id' => (int) $id]);
        Upload::deletePublic($course['thumbnail']);
        Audit::log('course.deleted', 'course', (int) $id, ['title' => $course['title']]);
        flash('success', 'Curso excluído.');
        return $this->redirect('admin/cursos');
    }

    private function form(array $course): Response
    {
        return $this->view('admin/courses/form', [
            'title' => isset($course['id']) ? 'Editar curso' : 'Novo curso',
            'course' => $course,
            'categories' => DB::fetchAll('SELECT * FROM categories ORDER BY name'),
            'instructors' => DB::fetchAll("SELECT u.id, u.name FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug <> 'aluno' AND u.status = 'active' ORDER BY u.name"),
        ], 'admin');
    }

    private function validated(?int $id = null): array
    {
        $data = $this->validate([
            'title' => 'required|min:3|max:160',
            'slug' => 'nullable|slug|max:180|unique:courses,slug' . ($id ? ',' . $id : ''),
            'summary' => 'required|min:20|max:300',
            'description' => 'nullable|max:20000',
            'learning_outcomes' => 'nullable|max:5000',
            'requirements' => 'nullable|max:5000',
            'target_audience' => 'nullable|max:2000',
            'category_id' => 'nullable|integer|exists:categories,id',
            'instructor_id' => 'nullable|integer|exists:users,id',
            'workload_hours' => 'required|integer|min:1|max:2000',
            'level' => 'required|in:iniciante,intermediario,avancado',
            'pass_score' => 'required|integer|min:0|max:100',
            'cover_color' => 'required|color',
            'status' => 'required|in:draft,published',
            'featured' => 'boolean',
            'certificate_enabled' => 'boolean',
        ], self::LABELS);
        return $data;
    }

    private function storeThumb(array $file): string
    {
        try {
            return Upload::storeImage($file, 'courses', 1280, 16 / 9);
        } catch (ValidationException $e) {
            throw new ValidationException(['thumbnail' => array_merge(...array_values($e->errors))]);
        }
    }
}
