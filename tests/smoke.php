<?php

declare(strict_types=1);

/*
 * Teste de fumaça: entra com cada perfil e abre todas as páginas GET,
 * conferindo o código HTTP esperado (200 para quem pode, 403 para quem não pode).
 *
 *   php -S 127.0.0.1:8000 -t public public/index.php   (em outro terminal)
 *   php tests/smoke.php [http://127.0.0.1:8000]
 */

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
$fail = 0;

function client(string $base): array
{
    $jar = tempnam(sys_get_temp_dir(), 'ead');
    $get = function (string $path, array $post = null) use ($base, $jar): array {
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => false, CURLOPT_TIMEOUT => 30,
        ]);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $body = (string) curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $loc = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);
        return [$code, $body, $loc];
    };
    return [$get];
}

function login(callable $get, string $email): void
{
    [, $html] = $get('/entrar');
    preg_match('/name="_token" value="([^"]+)"/', $html, $m);
    [$code] = $get('/entrar', ['_token' => $m[1] ?? '', 'email' => $email, 'password' => 'demo1234']);
    if ($code !== 302) {
        throw new RuntimeException("login falhou para {$email}");
    }
}

function check(callable $get, string $who, string $path, int $expected, int &$fail): void
{
    [$code, $body] = $get($path);
    $ok = $code === $expected && !str_contains($body, 'Fatal error') && !str_contains($body, 'Warning:');
    if (!$ok) {
        $fail++;
    }
    printf("  %s %-11s %3d (esperado %3d) %s\n", $ok ? '✓' : '✗', $who, $code, $expected, $path);
}

$pdo = new PDO('mysql:host=127.0.0.1;dbname=ead;charset=utf8mb4', 'ead', 'ead');
$courseId = (int) $pdo->query("SELECT id FROM courses WHERE status = 'published' LIMIT 1")->fetchColumn();
$lessonId = (int) $pdo->query("SELECT l.id FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = {$courseId} LIMIT 1")->fetchColumn();
$moduleId = (int) $pdo->query("SELECT id FROM modules WHERE course_id = {$courseId} LIMIT 1")->fetchColumn();
$quizId = (int) $pdo->query("SELECT id FROM quizzes WHERE course_id = {$courseId} LIMIT 1")->fetchColumn();
$studentId = (int) $pdo->query("SELECT id FROM users WHERE email = 'aluno@demo.com'")->fetchColumn();
$certId = (int) $pdo->query('SELECT id FROM certificates LIMIT 1')->fetchColumn();

$public = ['/', '/cursos', '/cursos?q=excel&nivel=iniciante', '/cursos?categoria=tecnologia&ordem=populares', '/validar', '/entrar', '/cadastro', '/privacidade', '/termos', '/cookies', '/sobre-o-projeto'];
$student = ['/aluno', '/aluno?filtro=todos', '/aluno/certificados', '/aluno/perfil', '/aluno/privacidade'];
$admin = [
    '/admin' => 'dashboard.view', '/admin/cursos' => 'courses.view', "/admin/cursos/{$courseId}" => 'courses.view',
    "/admin/cursos/{$courseId}/conteudo" => 'courses.view', '/admin/cursos/novo' => 'courses.manage',
    "/admin/aulas/{$lessonId}" => 'courses.manage', "/admin/modulos/{$moduleId}/aulas/nova" => 'courses.manage',
    "/admin/avaliacoes/{$quizId}" => 'courses.manage', "/admin/cursos/{$courseId}/avaliacoes/nova" => 'courses.manage',
    '/admin/categorias' => 'courses.manage', '/admin/alunos' => 'students.view', '/admin/alunos?q=ana&status=active' => 'students.view',
    "/admin/alunos/{$studentId}" => 'students.view', '/admin/alunos/novo' => 'students.manage',
    '/admin/certificados' => 'certificates.view', "/admin/certificados/{$certId}/pdf" => 'certificates.view',
    '/admin/relatorios' => 'reports.view', "/admin/relatorios/curso/{$courseId}" => 'reports.view',
    '/admin/relatorios/exportar?tipo=alunos' => 'reports.export', '/admin/relatorios/exportar?tipo=cursos' => 'reports.export',
    '/admin/equipe' => 'users.manage', '/admin/equipe/novo' => 'users.manage', '/admin/perfis' => 'users.manage',
    '/admin/configuracoes' => 'settings.manage', '/admin/atividades' => 'audit.view', '/admin/backups' => 'backups.manage', '/admin/lgpd' => 'lgpd.manage',
];
$roles = [
    'admin@demo.com' => '*',
    'coordenacao@demo.com' => ['dashboard.view', 'courses.view', 'courses.manage', 'students.view', 'enrollments.manage', 'certificates.view', 'certificates.manage', 'reports.view', 'reports.export'],
    'suporte@demo.com' => ['dashboard.view', 'courses.view', 'students.view', 'students.manage', 'enrollments.manage', 'certificates.view', 'lgpd.manage'],
];

echo "Visitante\n";
[$get] = client($base);
foreach ($public as $p) {
    check($get, 'visitante', $p, 200, $fail);
}
check($get, 'visitante', '/aluno', 302, $fail);
check($get, 'visitante', '/admin', 302, $fail);
check($get, 'visitante', '/pagina-que-nao-existe', 404, $fail);

echo "Aluno\n";
[$get] = client($base);
login($get, 'aluno@demo.com');
foreach ($student as $p) {
    check($get, 'aluno', $p, 200, $fail);
}
check($get, 'aluno', '/admin', 403, $fail);
check($get, 'aluno', '/admin/alunos', 403, $fail);

foreach ($roles as $email => $perms) {
    $who = explode('@', $email)[0];
    echo ucfirst($who) . "\n";
    [$get] = client($base);
    login($get, $email);
    foreach ($admin as $path => $perm) {
        check($get, $who, $path, $perms === '*' || in_array($perm, $perms, true) ? 200 : 403, $fail);
    }
}

echo $fail ? "\n{$fail} falha(s).\n" : "\nTudo certo.\n";
exit($fail ? 1 : 0);
