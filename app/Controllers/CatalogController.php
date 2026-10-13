<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Core\Upload;
use App\Lib\FileStreamer;
use App\Models\Course;

final class CatalogController extends Controller
{
    public function index(): Response
    {
        $filters = [
            'q' => mb_substr((string) $this->request->query('q', ''), 0, 80),
            'categoria' => (string) $this->request->query('categoria', ''),
            'nivel' => (string) $this->request->query('nivel', ''),
            'duracao' => (string) $this->request->query('duracao', ''),
            'ordem' => (string) $this->request->query('ordem', 'relevancia'),
        ];
        $result = Course::catalog($filters, $this->page());
        $categories = Course::categoriesWithCounts();
        $activeCategory = null;
        foreach ($categories as $c) {
            if ($c['slug'] === $filters['categoria']) {
                $activeCategory = $c;
            }
        }
        return $this->view('public/catalog', [
            'title' => $activeCategory ? 'Cursos de ' . $activeCategory['name'] : 'Cursos',
            'description' => 'Catálogo de cursos online com certificado. Filtre por área, nível e carga horária.',
            'result' => $result,
            'filters' => $filters,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'hasFilters' => (bool) array_filter(array_diff_key($filters, ['ordem' => 1])),
        ]);
    }

    public function show(string $slug): Response
    {
        $course = Course::findBySlug($slug, !Auth::isStaff());
        if (!$course) {
            throw HttpException::notFound('Este curso não existe ou não está mais disponível.');
        }
        $curriculum = Course::curriculum((int) $course['id']);
        $enrollment = Auth::check()
            ? DB::fetch('SELECT * FROM enrollments WHERE user_id = :u AND course_id = :c', ['u' => Auth::id(), 'c' => $course['id']])
            : null;
        $preview = null;
        foreach ($curriculum['modules'] as $m) {
            foreach ($m['lessons'] as $l) {
                if ($l['is_preview'] && !$preview) {
                    $preview = $l;
                }
            }
        }
        $quizzes = count(array_filter(array_column($curriculum['modules'], 'quiz'))) + ($curriculum['final'] ? 1 : 0);

        return $this->view('public/course', [
            'title' => $course['title'],
            'description' => $course['summary'],
            'course' => $course,
            'curriculum' => $curriculum,
            'enrollment' => $enrollment,
            'preview' => $preview,
            'quizzes' => $quizzes,
            'related' => Course::related($course),
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'Course',
                'name' => $course['title'],
                'description' => $course['summary'],
                'provider' => ['@type' => 'Organization', 'name' => site_name(), 'sameAs' => absolute_url('/')],
                'timeRequired' => 'PT' . (int) $course['workload_hours'] . 'H',
                'educationalLevel' => level_label($course['level']),
                'inLanguage' => 'pt-BR',
                'hasCourseInstance' => ['@type' => 'CourseInstance', 'courseMode' => 'online', 'courseWorkload' => 'PT' . (int) $course['workload_hours'] . 'H'],
            ],
        ]);
    }

    /** Aula gratuita: qualquer visitante assiste (sem registrar progresso). */
    public function preview(string $slug, string $id): Response
    {
        [$course, $lesson] = $this->previewLesson($slug, (int) $id);
        return $this->view('public/preview', [
            'title' => $lesson['title'] . ' · aula gratuita',
            'course' => $course,
            'lesson' => $lesson,
            'enrolled' => Auth::check() && DB::value('SELECT 1 FROM enrollments WHERE user_id = :u AND course_id = :c AND status <> \'cancelled\'', ['u' => Auth::id(), 'c' => $course['id']]),
        ]);
    }

    public function previewVideo(string $slug, string $id): Response
    {
        [, $lesson] = $this->previewLesson($slug, (int) $id);
        $file = $lesson['video_provider'] === 'local'
            ? \App\Services\VideoService::localFile((string) $lesson['video_ref'], (string) $this->request->query('formato', 'mp4'))
            : null;
        if (!$file) {
            throw HttpException::notFound();
        }
        return FileStreamer::serve($file[0], $file[1]);
    }

    public function enroll(string $slug): Response
    {
        $course = Course::findBySlug($slug);
        if (!$course) {
            throw HttpException::notFound();
        }
        $userId = (int) Auth::id();
        $existing = DB::fetch('SELECT * FROM enrollments WHERE user_id = :u AND course_id = :c', ['u' => $userId, 'c' => $course['id']]);
        if (!$existing) {
            $id = DB::insert('enrollments', [
                'user_id' => $userId, 'course_id' => $course['id'], 'status' => 'active',
                'progress_percent' => 0, 'enrolled_at' => now(), 'last_access_at' => now(),
            ]);
            Audit::log('enrollment.created', 'enrollment', $id, ['course' => $course['title']]);
            flash('success', 'Matrícula feita! Bons estudos.');
            Session::flash('_track', ['event' => 'enroll', 'course' => $course['slug']]);
        } elseif ($existing['status'] === 'cancelled') {
            DB::update('enrollments', ['status' => 'active'], 'id = :id', ['id' => $existing['id']]);
            Audit::log('enrollment.created', 'enrollment', (int) $existing['id'], ['course' => $course['title'], 'reativada' => true]);
            flash('success', 'Sua matrícula foi reativada. Seu progresso anterior foi mantido.');
        }
        return $this->redirect('aluno/curso/' . $course['slug']);
    }

    private function previewLesson(string $slug, int $id): array
    {
        $course = Course::findBySlug($slug);
        $lesson = $course ? DB::fetch(
            'SELECT l.* FROM lessons l JOIN modules m ON m.id = l.module_id WHERE l.id = :id AND m.course_id = :c AND l.is_preview = 1',
            ['id' => $id, 'c' => $course['id']],
        ) : null;
        if (!$lesson) {
            throw HttpException::notFound('Esta aula não está disponível como aula gratuita.');
        }
        return [$course, $lesson];
    }
}
