<?php

declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\Admin;
use App\Controllers\AuthController;
use App\Controllers\CatalogController;
use App\Controllers\CertificateController;
use App\Controllers\HomeController;
use App\Controllers\InstallController;
use App\Controllers\PageController;
use App\Controllers\Student;

// ---------------------------------------------------------------- público
$router->get('/', [HomeController::class, 'index']);
$router->get('/cursos', [CatalogController::class, 'index']);
$router->get('/curso/{slug}', [CatalogController::class, 'show']);
$router->get('/curso/{slug}/aula-gratuita/{id:\d+}', [CatalogController::class, 'preview']);
$router->get('/curso/{slug}/aula-gratuita/{id:\d+}/video', [CatalogController::class, 'previewVideo']);
$router->post('/curso/{slug}/matricular', [CatalogController::class, 'enroll'], ['auth']);

$router->get('/validar', [CertificateController::class, 'form']);
$router->get('/certificado/{code}', [CertificateController::class, 'show']);
$router->get('/certificado/{code}/imagem', [CertificateController::class, 'image']);
$router->get('/certificado/{code}/pdf', [CertificateController::class, 'pdf']);

$router->get('/privacidade', [PageController::class, 'privacy']);
$router->get('/termos', [PageController::class, 'terms']);
$router->get('/cookies', [PageController::class, 'cookies']);
$router->post('/cookies/consentimento', [PageController::class, 'cookieConsent']);
$router->get('/sobre-o-projeto', [PageController::class, 'about']);

$router->get('/instalar', [InstallController::class, 'index']);
$router->post('/instalar', [InstallController::class, 'run']);

// --------------------------------------------------------- autenticação
$router->group('', ['guest'], function ($r) {
    $r->get('/entrar', [AuthController::class, 'loginForm']);
    $r->post('/entrar', [AuthController::class, 'login']);
    $r->get('/cadastro', [AuthController::class, 'registerForm']);
    $r->post('/cadastro', [AuthController::class, 'register']);
    $r->get('/recuperar-senha', [AuthController::class, 'forgotForm']);
    $r->post('/recuperar-senha', [AuthController::class, 'forgot']);
    $r->get('/redefinir-senha/{token}', [AuthController::class, 'resetForm']);
    $r->post('/redefinir-senha', [AuthController::class, 'reset']);
});
$router->post('/sair', [AuthController::class, 'logout'], ['auth']);

// ------------------------------------------------------------ área do aluno
$router->group('/aluno', ['auth'], function ($r) {
    $r->get('', [Student\DashboardController::class, 'index']);
    $r->get('/curso/{slug}', [Student\PlayerController::class, 'resume']);
    $r->get('/curso/{slug}/aula/{id:\d+}', [Student\PlayerController::class, 'lesson']);
    $r->post('/aula/{id:\d+}/progresso', [Student\PlayerController::class, 'track']);
    $r->post('/aula/{id:\d+}/concluir', [Student\PlayerController::class, 'complete']);
    $r->get('/aula/{id:\d+}/video', [Student\PlayerController::class, 'video']);
    $r->get('/material/{id:\d+}', [Student\PlayerController::class, 'material']);
    $r->get('/curso/{slug}/avaliacao/{id:\d+}', [Student\QuizController::class, 'show']);
    $r->post('/curso/{slug}/avaliacao/{id:\d+}', [Student\QuizController::class, 'submit']);
    $r->get('/curso/{slug}/avaliacao/{id:\d+}/resultado/{attempt:\d+}', [Student\QuizController::class, 'result']);
    $r->get('/certificados', [Student\CertificateController::class, 'index']);
    $r->get('/certificados/{code}/pdf', [Student\CertificateController::class, 'pdf']);
    $r->get('/perfil', [Student\ProfileController::class, 'edit']);
    $r->post('/perfil', [Student\ProfileController::class, 'update']);
    $r->post('/perfil/senha', [Student\ProfileController::class, 'password'], ['not-demo']);
    $r->post('/perfil/foto', [Student\ProfileController::class, 'avatar']);
    $r->get('/privacidade', [Student\PrivacyController::class, 'index']);
    $r->post('/privacidade/exportar', [Student\PrivacyController::class, 'export']);
    $r->post('/privacidade/consentimentos', [Student\PrivacyController::class, 'consents']);
    $r->post('/privacidade/excluir', [Student\PrivacyController::class, 'deletion'], ['not-demo']);
});

// ---------------------------------------------------------------- admin
$router->group('/admin', ['auth', 'staff'], function ($r) {
    $r->get('', [Admin\DashboardController::class, 'index'], ['can:dashboard.view']);

    $r->group('', ['can:courses.view'], function ($r) {
        $r->get('/cursos', [Admin\CourseController::class, 'index']);
        $r->get('/cursos/{id:\d+}', [Admin\CourseController::class, 'edit']);
        $r->get('/cursos/{id:\d+}/conteudo', [Admin\CurriculumController::class, 'index']);
    });
    $r->group('', ['can:courses.manage'], function ($r) {
        $r->get('/cursos/novo', [Admin\CourseController::class, 'create']);
        $r->post('/cursos', [Admin\CourseController::class, 'store']);
        $r->post('/cursos/{id:\d+}', [Admin\CourseController::class, 'update']);
        $r->post('/cursos/{id:\d+}/excluir', [Admin\CourseController::class, 'destroy'], ['not-demo']);
        $r->post('/cursos/{id:\d+}/modulos', [Admin\CurriculumController::class, 'storeModule']);
        $r->post('/modulos/{id:\d+}', [Admin\CurriculumController::class, 'updateModule']);
        $r->post('/modulos/{id:\d+}/excluir', [Admin\CurriculumController::class, 'destroyModule'], ['not-demo']);
        $r->post('/cursos/{id:\d+}/ordem', [Admin\CurriculumController::class, 'reorder']);
        $r->get('/modulos/{id:\d+}/aulas/nova', [Admin\LessonController::class, 'create']);
        $r->post('/modulos/{id:\d+}/aulas', [Admin\LessonController::class, 'store']);
        $r->get('/aulas/{id:\d+}', [Admin\LessonController::class, 'edit']);
        $r->post('/aulas/{id:\d+}', [Admin\LessonController::class, 'update']);
        $r->post('/aulas/{id:\d+}/excluir', [Admin\LessonController::class, 'destroy'], ['not-demo']);
        $r->post('/aulas/{id:\d+}/materiais', [Admin\LessonController::class, 'storeMaterial']);
        $r->post('/materiais/{id:\d+}/excluir', [Admin\LessonController::class, 'destroyMaterial'], ['not-demo']);
        $r->get('/aulas/{id:\d+}/video', [Admin\LessonController::class, 'video']);
        $r->get('/cursos/{id:\d+}/avaliacoes/nova', [Admin\QuizController::class, 'create']);
        $r->post('/cursos/{id:\d+}/avaliacoes', [Admin\QuizController::class, 'store']);
        $r->get('/avaliacoes/{id:\d+}', [Admin\QuizController::class, 'edit']);
        $r->post('/avaliacoes/{id:\d+}', [Admin\QuizController::class, 'update']);
        $r->post('/avaliacoes/{id:\d+}/excluir', [Admin\QuizController::class, 'destroy'], ['not-demo']);
        $r->get('/categorias', [Admin\CategoryController::class, 'index']);
        $r->post('/categorias', [Admin\CategoryController::class, 'store']);
        $r->post('/categorias/{id:\d+}', [Admin\CategoryController::class, 'update']);
        $r->post('/categorias/{id:\d+}/excluir', [Admin\CategoryController::class, 'destroy'], ['not-demo']);
    });

    $r->group('', ['can:students.view'], function ($r) {
        $r->get('/alunos', [Admin\StudentController::class, 'index']);
        $r->get('/alunos/{id:\d+}', [Admin\StudentController::class, 'show']);
    });
    $r->group('', ['can:students.manage'], function ($r) {
        $r->get('/alunos/novo', [Admin\StudentController::class, 'create']);
        $r->post('/alunos', [Admin\StudentController::class, 'store']);
        $r->post('/alunos/{id:\d+}', [Admin\StudentController::class, 'update'], ['not-demo']);
        $r->post('/alunos/{id:\d+}/anonimizar', [Admin\StudentController::class, 'anonymize'], ['not-demo']);
    });
    $r->group('', ['can:enrollments.manage'], function ($r) {
        $r->post('/alunos/{id:\d+}/matricular', [Admin\StudentController::class, 'enroll']);
        $r->post('/matriculas/{id:\d+}/cancelar', [Admin\StudentController::class, 'cancelEnrollment']);
        $r->post('/matriculas/{id:\d+}/reativar', [Admin\StudentController::class, 'reactivateEnrollment']);
    });

    $r->get('/certificados', [Admin\CertificateController::class, 'index'], ['can:certificates.view']);
    $r->get('/certificados/{id:\d+}/pdf', [Admin\CertificateController::class, 'pdf'], ['can:certificates.view']);
    $r->group('', ['can:certificates.manage'], function ($r) {
        $r->post('/certificados/{id:\d+}/revogar', [Admin\CertificateController::class, 'revoke']);
        $r->post('/certificados/{id:\d+}/reativar', [Admin\CertificateController::class, 'restore']);
        $r->post('/matriculas/{id:\d+}/emitir-certificado', [Admin\CertificateController::class, 'issue']);
    });

    $r->get('/relatorios', [Admin\ReportController::class, 'index'], ['can:reports.view']);
    $r->get('/relatorios/curso/{id:\d+}', [Admin\ReportController::class, 'course'], ['can:reports.view']);
    $r->get('/relatorios/exportar', [Admin\ReportController::class, 'export'], ['can:reports.export']);

    $r->group('', ['can:users.manage'], function ($r) {
        $r->get('/equipe', [Admin\TeamController::class, 'index']);
        $r->get('/equipe/novo', [Admin\TeamController::class, 'create']);
        $r->post('/equipe', [Admin\TeamController::class, 'store'], ['not-demo']);
        $r->get('/equipe/{id:\d+}', [Admin\TeamController::class, 'edit']);
        $r->post('/equipe/{id:\d+}', [Admin\TeamController::class, 'update'], ['not-demo']);
        $r->get('/perfis', [Admin\RoleController::class, 'index']);
        $r->post('/perfis', [Admin\RoleController::class, 'store'], ['not-demo']);
        $r->post('/perfis/{id:\d+}', [Admin\RoleController::class, 'update'], ['not-demo']);
    });

    $r->get('/configuracoes', [Admin\SettingsController::class, 'index'], ['can:settings.manage']);
    $r->post('/configuracoes', [Admin\SettingsController::class, 'update'], ['can:settings.manage', 'not-demo']);
    $r->get('/atividades', [Admin\AuditController::class, 'index'], ['can:audit.view']);
    $r->get('/backups', [Admin\BackupController::class, 'index'], ['can:backups.manage']);
    $r->post('/backups', [Admin\BackupController::class, 'store'], ['can:backups.manage']);
    $r->get('/backups/{name}', [Admin\BackupController::class, 'download'], ['can:backups.manage', 'not-demo']);
    $r->get('/lgpd', [Admin\LgpdController::class, 'index'], ['can:lgpd.manage']);
    $r->post('/lgpd/{id:\d+}', [Admin\LgpdController::class, 'update'], ['can:lgpd.manage', 'not-demo']);
});
