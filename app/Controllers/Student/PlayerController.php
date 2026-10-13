<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Upload;
use App\Lib\FileStreamer;
use App\Services\ProgressService;
use App\Services\VideoService;

final class PlayerController extends StudentController
{
    /** /aluno/curso/{slug}: leva direto para onde o aluno parou. */
    public function resume(string $slug): Response
    {
        [$course, $enrollment] = $this->courseAccess($slug);
        $target = $enrollment ? ProgressService::resumeTarget((int) Auth::id(), $enrollment) : null;
        if (!$target) {
            $seq = \App\Models\Course::sequence(\App\Models\Course::curriculum((int) $course['id']));
            $target = $seq[0] ?? null;
        }
        if (!$target) {
            flash('info', 'Este curso ainda não tem aulas publicadas.');
            return $this->redirect('curso/' . $slug);
        }
        $path = $target['type'] === 'lesson' ? 'aula' : 'avaliacao';
        return $this->redirect("aluno/curso/{$slug}/{$path}/{$target['id']}");
    }

    public function lesson(string $slug, string $id): Response
    {
        [$course, $enrollment] = $this->courseAccess($slug);
        $lesson = DB::fetch(
            'SELECT l.*, m.title AS module_title FROM lessons l JOIN modules m ON m.id = l.module_id WHERE l.id = :id AND m.course_id = :c',
            ['id' => (int) $id, 'c' => $course['id']],
        );
        if (!$lesson) {
            throw HttpException::notFound('Aula não encontrada neste curso.');
        }
        if ($enrollment) {
            DB::update('enrollments', ['last_lesson_id' => $lesson['id'], 'last_access_at' => now()], 'id = :id', ['id' => $enrollment['id']]);
        }
        $ctx = $this->playerContext($course, $enrollment, 'lesson', (int) $lesson['id']);
        $progress = $ctx['lessonProgress'][$lesson['id']] ?? null;
        $duration = (int) $lesson['duration_seconds'];
        $measurable = $lesson['type'] === 'video' && VideoService::measurable($lesson['video_provider'])
            && setting('require_video_watch', '1') === '1';

        return $this->view('student/lesson', $ctx + [
            'title' => $lesson['title'],
            'lesson' => $lesson,
            'materials' => DB::fetchAll('SELECT * FROM materials WHERE lesson_id = :l ORDER BY id', ['l' => $lesson['id']]),
            'progress' => $progress,
            'completed' => !empty($progress['completed_at']),
            'measurable' => $measurable,
            'watchedPercent' => $duration > 0 ? min(100, (int) floor(($progress['watched_seconds'] ?? 0) / $duration * 100)) : 0,
            'startAt' => empty($progress['completed_at']) ? (int) ($progress['position_seconds'] ?? 0) : 0,
            'preview' => $enrollment === null,
        ], 'player');
    }

    /** Recebe o "pulso" do player a cada ~10 s. */
    public function track(string $id): Response
    {
        [$lesson, $course, $enrollment] = $this->lessonAccess((int) $id);
        if (!$enrollment) {
            return $this->json(['completed' => false, 'watched_percent' => 0, 'preview' => true]);
        }
        $result = ProgressService::track(
            (int) Auth::id(),
            $lesson,
            (int) $this->request->input('position', 0),
            (int) $this->request->input('delta', 0),
            (int) $this->request->input('duration', 0),
        );
        $payload = $result;
        if ($result['just_completed']) {
            $course = ProgressService::recalculate((int) Auth::id(), (int) $lesson['course_id']);
            $payload['course_percent'] = $course['percent'];
            if ($course['newly_completed'] && $course['certificate']) {
                $payload['certificate_url'] = url('aluno/certificados', ['novo' => $course['certificate']['code']]);
            }
        }
        return $this->json($payload);
    }

    /** Conclusão manual (aulas em texto e vídeos incorporados sem API). */
    public function complete(string $id): Response
    {
        [$lesson, $course, $enrollment] = $this->lessonAccess((int) $id);
        if (!$enrollment) {
            return $this->back();
        }
        $manualAllowed = $lesson['type'] === 'text' || !VideoService::measurable($lesson['video_provider'])
            || setting('require_video_watch', '1') !== '1';
        if (!$manualAllowed) {
            flash('warning', 'Esta aula é concluída automaticamente ao assistir pelo menos 85% do vídeo.');
            return $this->back();
        }
        ProgressService::complete((int) Auth::id(), (int) $lesson['id']);
        $result = ProgressService::recalculate((int) Auth::id(), (int) $course['id']);
        if ($result['newly_completed'] && $result['certificate']) {
            return $this->redirect('aluno/certificados?novo=' . $result['certificate']['code']);
        }
        $ctx = $this->playerContext($course, $enrollment, 'lesson', (int) $lesson['id']);
        flash('success', 'Aula concluída.');
        return $ctx['nextUrl'] ? Response::redirect($ctx['nextUrl']) : $this->redirect('aluno/curso/' . $course['slug'] . '/aula/' . $lesson['id']);
    }

    public function video(string $id): Response
    {
        [$lesson] = $this->lessonAccess((int) $id);
        $file = $lesson['video_provider'] === 'local'
            ? VideoService::localFile((string) $lesson['video_ref'], (string) $this->request->query('formato', 'mp4'))
            : null;
        if (!$file) {
            throw HttpException::notFound('Vídeo não encontrado.');
        }
        return FileStreamer::serve($file[0], $file[1]);
    }

    public function material(string $id): Response
    {
        $material = DB::fetch('SELECT * FROM materials WHERE id = :id', ['id' => (int) $id]);
        if (!$material) {
            throw HttpException::notFound('Material não encontrado.');
        }
        $this->lessonAccess((int) $material['lesson_id']);
        $path = Upload::privatePath($material['file_path']);
        if (!$path) {
            throw HttpException::notFound('O arquivo deste material não está mais disponível.');
        }
        Audit::log('material.downloaded', 'material', (int) $material['id']);
        return FileStreamer::serve($path, $material['mime'], $material['original_name']);
    }
}
