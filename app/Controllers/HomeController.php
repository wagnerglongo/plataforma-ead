<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\Response;
use App\Models\Course;

final class HomeController extends Controller
{
    public function index(): Response
    {
        $stats = DB::fetch(
            "SELECT (SELECT COUNT(*) FROM courses WHERE status = 'published') AS courses,
                    (SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'aluno') AS students,
                    (SELECT COUNT(*) FROM certificates WHERE status = 'valid') AS certificates",
        );
        return $this->view('public/home', [
            'featured' => Course::featured(3),
            'categories' => Course::categoriesWithCounts(),
            'stats' => $stats,
            'heroCourse' => Course::featured(1)[0] ?? null,
            'description' => site_name() . ': cursos online com aulas em vídeo, avaliações e certificado com validação pública.',
        ]);
    }
}
