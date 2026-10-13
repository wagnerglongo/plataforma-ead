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
use App\Lib\FileStreamer;
use App\Services\VideoService;

final class LessonController extends Controller
{
    public function create(string $moduleId): Response
    {
        $module = $this->module((int) $moduleId);
        return $this->form(['module_id' => $module['id'], 'type' => 'video', 'video_provider' => 'youtube', 'duration_seconds' => 0], $module);
    }

    public function edit(string $id): Response
    {
        $lesson = $this->lesson((int) $id);
        return $this->form($lesson, $this->module((int) $lesson['module_id']));
    }

    public function store(string $moduleId): Response
    {
        $module = $this->module((int) $moduleId);
        $data = $this->validated(null);
        $data['module_id'] = $module['id'];
        $data['position'] = (int) DB::value('SELECT COALESCE(MAX(position), 0) + 1 FROM lessons WHERE module_id = :m', ['m' => $module['id']]);
        $data['created_at'] = $data['updated_at'] = now();
        $id = DB::insert('lessons', $data);
        Audit::log('lesson.saved', 'lesson', $id, ['title' => $data['title']]);
        CurriculumController::recalculateCourse((int) $module['course_id']);
        flash('success', 'Aula criada. Se quiser, anexe materiais abaixo.');
        return $this->redirect("admin/aulas/{$id}");
    }

    public function update(string $id): Response
    {
        $lesson = $this->lesson((int) $id);
        $data = $this->validated($lesson);
        $data['updated_at'] = now();
        DB::update('lessons', $data, 'id = :id', ['id' => $lesson['id']]);
        if ($lesson['video_provider'] === 'local' && ($data['video_ref'] ?? null) !== $lesson['video_ref']) {
            Upload::deletePrivate($lesson['video_ref']);
        }
        Audit::log('lesson.saved', 'lesson', (int) $lesson['id'], ['title' => $data['title']]);
        flash('success', 'Aula salva.');
        return $this->redirect("admin/aulas/{$lesson['id']}");
    }

    public function destroy(string $id): Response
    {
        $lesson = $this->lesson((int) $id);
        $module = $this->module((int) $lesson['module_id']);
        foreach (DB::fetchAll('SELECT file_path FROM materials WHERE lesson_id = :l', ['l' => $lesson['id']]) as $m) {
            Upload::deletePrivate($m['file_path']);
        }
        if ($lesson['video_provider'] === 'local') {
            Upload::deletePrivate($lesson['video_ref']);
        }
        DB::delete('lessons', 'id = :id', ['id' => $lesson['id']]);
        Audit::log('lesson.deleted', 'lesson', (int) $lesson['id'], ['title' => $lesson['title']]);
        CurriculumController::recalculateCourse((int) $module['course_id']);
        flash('success', 'Aula excluída.');
        return $this->redirect("admin/cursos/{$module['course_id']}/conteudo");
    }

    public function storeMaterial(string $id): Response
    {
        $lesson = $this->lesson((int) $id);
        $data = $this->validate(['title' => 'required|max:160'], ['title' => 'nome do material']);
        $file = $this->request->file('file') ?? throw ValidationException::withMessage('file', 'Escolha um arquivo para enviar.');
        $stored = Upload::storePrivate($file, 'materials', Upload::MATERIALS, (int) config('uploads.max_material_mb'));
        DB::insert('materials', [
            'lesson_id' => $lesson['id'], 'title' => $data['title'], 'file_path' => $stored['path'],
            'original_name' => $stored['original'], 'mime' => $stored['mime'], 'size_bytes' => $stored['size'], 'created_at' => now(),
        ]);
        Audit::log('lesson.saved', 'lesson', (int) $lesson['id'], ['material' => $stored['original']]);
        flash('success', 'Material anexado.');
        return $this->redirect("admin/aulas/{$lesson['id']}#materiais");
    }

    public function destroyMaterial(string $id): Response
    {
        $material = DB::fetch('SELECT * FROM materials WHERE id = :id', ['id' => (int) $id]) ?? throw HttpException::notFound();
        Upload::deletePrivate($material['file_path']);
        DB::delete('materials', 'id = :id', ['id' => $material['id']]);
        flash('success', 'Material removido.');
        return $this->redirect("admin/aulas/{$material['lesson_id']}#materiais");
    }

    public function video(string $id): Response
    {
        $lesson = $this->lesson((int) $id);
        $file = $lesson['video_provider'] === 'local' ? VideoService::localFile((string) $lesson['video_ref']) : null;
        return $file ? FileStreamer::serve($file[0], $file[1]) : throw HttpException::notFound();
    }

    private function form(array $lesson, array $module): Response
    {
        $course = DB::fetch('SELECT * FROM courses WHERE id = :id', ['id' => $module['course_id']]);
        return $this->view('admin/lessons/form', [
            'title' => empty($lesson['id']) ? 'Nova aula' : 'Editar aula',
            'lesson' => $lesson,
            'module' => $module,
            'course' => $course,
            'materials' => empty($lesson['id']) ? [] : DB::fetchAll('SELECT * FROM materials WHERE lesson_id = :l ORDER BY id', ['l' => $lesson['id']]),
        ], 'admin');
    }

    private function validated(?array $current): array
    {
        $data = $this->validate([
            'title' => 'required|min:2|max:160',
            'type' => 'required|in:video,text',
            'video_provider' => 'nullable|in:local,youtube,vimeo,embed',
            'video_ref' => 'nullable|max:500',
            'duration' => 'nullable|max:10',
            'content' => 'nullable|max:60000',
            'is_preview' => 'boolean',
        ], ['title' => 'título', 'video_ref' => 'link do vídeo', 'duration' => 'duração']);

        $seconds = 0;
        if (preg_match('/^(\d{1,3})(?::(\d{1,2}))?(?::(\d{1,2}))?$/', (string) $data['duration'], $m)) {
            $parts = array_values(array_filter([$m[1], $m[2] ?? null, $m[3] ?? null], fn ($p) => $p !== null && $p !== ''));
            $seconds = match (count($parts)) {
                1 => (int) $parts[0] * 60,
                2 => (int) $parts[0] * 60 + (int) $parts[1],
                default => (int) $parts[0] * 3600 + (int) $parts[1] * 60 + (int) $parts[2],
            };
        }

        $out = [
            'title' => $data['title'],
            'type' => $data['type'],
            'content' => $data['content'],
            'is_preview' => $data['is_preview'],
            'video_provider' => null,
            'video_ref' => null,
            'duration_seconds' => $seconds,
        ];
        if ($data['type'] === 'text') {
            $out['duration_seconds'] = $seconds ?: max(120, (int) (str_word_count((string) $data['content']) / 3));
            return $out;
        }

        $provider = $data['video_provider'] ?: 'youtube';
        $out['video_provider'] = $provider;
        if ($provider === 'local') {
            if ($file = $this->request->file('video_file')) {
                try {
                    $out['video_ref'] = Upload::storePrivate($file, 'videos', Upload::VIDEOS, (int) config('uploads.max_video_mb'))['path'];
                } catch (ValidationException $e) {
                    throw new ValidationException(['video_file' => array_merge(...array_values($e->errors))]);
                }
            } elseif ($current && $current['video_provider'] === 'local' && $current['video_ref']) {
                $out['video_ref'] = $current['video_ref'];
            } else {
                throw ValidationException::withMessage('video_file', 'Envie o arquivo do vídeo (MP4 ou WebM).');
            }
        } else {
            $ref = trim((string) $data['video_ref']);
            if ($ref === '') {
                throw ValidationException::withMessage('video_ref', 'Cole o link do vídeo.');
            }
            if ($error = VideoService::validateRef($provider, $ref)) {
                throw ValidationException::withMessage('video_ref', $error);
            }
            $out['video_ref'] = $ref;
        }
        if ($out['duration_seconds'] <= 0) {
            throw ValidationException::withMessage('duration', 'Informe a duração do vídeo (minutos:segundos). Ela define quando a aula conta como assistida.');
        }
        return $out;
    }

    private function module(int $id): array
    {
        return DB::fetch('SELECT * FROM modules WHERE id = :id', ['id' => $id]) ?? throw HttpException::notFound('Módulo não encontrado.');
    }

    private function lesson(int $id): array
    {
        return DB::fetch('SELECT * FROM lessons WHERE id = :id', ['id' => $id]) ?? throw HttpException::notFound('Aula não encontrada.');
    }
}
