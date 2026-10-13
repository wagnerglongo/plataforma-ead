<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database as DB;

/** Criação do banco, dados básicos (perfis/permissões) e dados de demonstração. */
final class Installer
{
    public const PERMISSIONS = [
        'Painel' => ['dashboard.view' => 'Ver o painel administrativo'],
        'Cursos' => ['courses.view' => 'Ver cursos', 'courses.manage' => 'Criar e editar cursos, módulos, aulas e avaliações'],
        'Alunos' => ['students.view' => 'Ver alunos', 'students.manage' => 'Cadastrar e editar alunos', 'enrollments.manage' => 'Matricular e cancelar matrículas'],
        'Certificados' => ['certificates.view' => 'Ver certificados', 'certificates.manage' => 'Emitir, revogar e reativar certificados'],
        'Relatórios' => ['reports.view' => 'Ver relatórios', 'reports.export' => 'Exportar relatórios em CSV'],
        'Sistema' => [
            'users.manage' => 'Gerenciar equipe e permissões',
            'settings.manage' => 'Alterar configurações e integrações',
            'audit.view' => 'Ver registro de atividades',
            'backups.manage' => 'Gerar e baixar backups',
            'lgpd.manage' => 'Atender pedidos de titulares (LGPD)',
        ],
    ];

    public const ROLES = [
        'admin' => ['Administrador', 'Acesso total ao sistema.', '*'],
        'coordenacao' => ['Coordenação pedagógica', 'Cuida dos cursos, certificados e relatórios.',
            ['dashboard.view', 'courses.view', 'courses.manage', 'students.view', 'enrollments.manage', 'certificates.view', 'certificates.manage', 'reports.view', 'reports.export']],
        'suporte' => ['Suporte ao aluno', 'Atende alunos, matrículas e pedidos de dados.',
            ['dashboard.view', 'courses.view', 'students.view', 'students.manage', 'enrollments.manage', 'certificates.view', 'lgpd.manage']],
        'aluno' => ['Aluno', 'Acesso à área do aluno.', []],
    ];

    public static function migrate(callable $log): void
    {
        $sql = (string) file_get_contents(BASE_PATH . '/database/schema.sql');
        foreach (self::splitSql($sql) as $statement) {
            DB::pdo()->exec($statement);
        }
        DB::query('INSERT IGNORE INTO schema_migrations (version, applied_at) VALUES (:v, :t)', ['v' => '0001_schema', 't' => now()]);
        $applied = DB::column('SELECT version FROM schema_migrations');
        foreach (glob(BASE_PATH . '/database/migrations/*.sql') ?: [] as $file) {
            $version = basename($file, '.sql');
            if (in_array($version, $applied, true)) {
                continue;
            }
            foreach (self::splitSql((string) file_get_contents($file)) as $statement) {
                DB::pdo()->exec($statement);
            }
            DB::insert('schema_migrations', ['version' => $version, 'applied_at' => now()]);
            $log("migração aplicada: {$version}");
        }
        $log('estrutura do banco atualizada');
    }

    public static function dropAll(callable $log): void
    {
        DB::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (DB::column('SHOW TABLES') as $table) {
            DB::pdo()->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        DB::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
        foreach (glob(BASE_PATH . '/storage/cache/*') ?: [] as $f) {
            if (is_file($f) && !str_ends_with($f, '.gitignore')) {
                @unlink($f);
            }
        }
        $log('tabelas removidas');
    }

    public static function baseData(callable $log): void
    {
        $now = now();
        foreach (self::PERMISSIONS as $group => $perms) {
            foreach ($perms as $slug => $name) {
                DB::query('INSERT IGNORE INTO permissions (slug, name, group_name) VALUES (:s, :n, :g)', ['s' => $slug, 'n' => $name, 'g' => $group]);
            }
        }
        $permIds = array_column(DB::fetchAll('SELECT id, slug FROM permissions'), 'id', 'slug');
        foreach (self::ROLES as $slug => [$name, $desc, $perms]) {
            DB::query('INSERT IGNORE INTO roles (name, slug, description, is_system, created_at) VALUES (:n, :s, :d, 1, :t)', ['n' => $name, 's' => $slug, 'd' => $desc, 't' => $now]);
            $roleId = (int) DB::value('SELECT id FROM roles WHERE slug = :s', ['s' => $slug]);
            if (is_array($perms)) {
                foreach ($perms as $p) {
                    DB::query('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:r, :p)', ['r' => $roleId, 'p' => $permIds[$p]]);
                }
            }
        }
        $defaults = [
            'site_name' => config('app.name'),
            'site_tagline' => 'Cursos online com certificado',
            'contact_email' => config('mail.from'),
            'whatsapp_number' => '',
            'gtm_id' => '',
            'ga_id' => '',
            'certificate_signer_name' => 'Coordenação Pedagógica',
            'certificate_signer_role' => 'Coordenação',
            'dpo_name' => '',
            'dpo_email' => '',
            'cookie_banner' => '1',
            'require_video_watch' => '1',
        ];
        foreach ($defaults as $k => $v) {
            DB::query('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (:k, :v)', ['k' => $k, 'v' => $v]);
        }
        $log('perfis, permissões e configurações básicas criados');
    }

    public static function createAdmin(string $name, string $email, string $password): int
    {
        $roleId = (int) DB::value("SELECT id FROM roles WHERE slug = 'admin'");
        return DB::insert('users', [
            'role_id' => $roleId, 'name' => $name, 'email' => mb_strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'active', 'terms_accepted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ================================================================ DEMO

    public static function demoData(callable $log): void
    {
        mt_srand(20261013); // dados reproduzíveis
        $content = require BASE_PATH . '/database/seeds/content.php';
        $durations = json_decode((string) @file_get_contents(BASE_PATH . '/storage/app/demo/videos/durations.json'), true) ?: [];
        $roles = array_column(DB::fetchAll('SELECT id, slug FROM roles'), 'id', 'slug');
        $pass = password_hash('demo1234', PASSWORD_DEFAULT);
        $daysAgo = fn (float $d, int $hour = 10) => date('Y-m-d H:i:s', (int) (strtotime('today') - $d * 86400 + $hour * 3600 + mt_rand(0, 3500)));

        $settings = [
            'site_name' => 'Instituto Horizonte',
            'site_tagline' => 'Cursos livres online, com certificado verificável',
            'contact_email' => 'contato@institutohorizonte.exemplo',
            'certificate_signer_name' => 'Helena Prado Moreira',
            'certificate_signer_role' => 'Diretora pedagógica',
            'dpo_name' => 'Juliana Prates',
            'dpo_email' => 'privacidade@institutohorizonte.exemplo',
        ];
        foreach ($settings as $k => $v) {
            DB::query('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2', ['k' => $k, 'v' => $v, 'v2' => $v]);
        }

        // Equipe
        $staff = [
            ['admin', 'Carla Mendes', 'admin@demo.com', 'Administração da plataforma.'],
            ['coordenacao', 'Ricardo Teles', 'coordenacao@demo.com', 'Coordenador pedagógico e instrutor. Trabalha com análise de dados e capacitação corporativa há 12 anos.'],
            ['suporte', 'Juliana Prates', 'suporte@demo.com', 'Especialista em experiência do cliente e encarregada de dados do instituto.'],
        ];
        $userIds = [];
        foreach ($staff as [$role, $name, $email, $bio]) {
            $userIds[$email] = DB::insert('users', [
                'role_id' => $roles[$role], 'name' => $name, 'email' => $email, 'password_hash' => $pass, 'bio' => $bio,
                'status' => 'active', 'terms_accepted_at' => $daysAgo(200), 'created_at' => $daysAgo(200), 'updated_at' => $daysAgo(30),
                'last_login_at' => $daysAgo(mt_rand(0, 3)),
            ]);
        }

        // Catálogo
        $catIds = [];
        foreach ($content['categories'] as $cat) {
            $catIds[$cat] = DB::insert('categories', ['name' => $cat, 'slug' => str_slug($cat), 'created_at' => $daysAgo(200)]);
        }
        $courseIds = [];
        foreach ($content['courses'] as $ci => $c) {
            $published = ($c['status'] ?? 'published') === 'published';
            $courseId = DB::insert('courses', [
                'category_id' => $catIds[$c['category']], 'instructor_id' => $userIds[$c['instructor']],
                'title' => $c['title'], 'slug' => $c['slug'], 'summary' => $c['summary'], 'description' => $c['description'],
                'learning_outcomes' => $c['outcomes'], 'requirements' => $c['requirements'], 'target_audience' => $c['audience'],
                'workload_hours' => $c['hours'], 'level' => $c['level'], 'cover_color' => $c['color'],
                'status' => $published ? 'published' : 'draft', 'featured' => $c['featured'], 'pass_score' => 70,
                'certificate_enabled' => 1, 'published_at' => $published ? $daysAgo(180 - $ci * 12) : null,
                'created_at' => $daysAgo(190 - $ci * 12), 'updated_at' => $daysAgo(20),
            ]);
            $courseIds[$c['slug']] = $courseId;
            foreach ($c['modules'] as $mi => $m) {
                $moduleId = DB::insert('modules', ['course_id' => $courseId, 'title' => $m['title'], 'position' => $mi + 1, 'created_at' => $daysAgo(190)]);
                foreach ($m['lessons'] as $li => $l) {
                    $file = "{$c['slug']}-m" . ($mi + 1) . '-a' . ($li + 1) . '.mp4';
                    $isVideo = $l['type'] === 'video';
                    $lessonId = DB::insert('lessons', [
                        'module_id' => $moduleId, 'title' => $l['title'], 'type' => $l['type'],
                        'video_provider' => $isVideo ? 'local' : null,
                        'video_ref' => $isVideo ? 'demo/videos/' . $file : null,
                        'duration_seconds' => $isVideo ? (int) ($durations[$file] ?? 30) : max(120, (int) (str_word_count($l['content']) / 3)),
                        'content' => $l['content'], 'is_preview' => (int) ($l['preview'] ?? 0), 'position' => $li + 1,
                        'created_at' => $daysAgo(190), 'updated_at' => $daysAgo(40),
                    ]);
                    foreach ($l['materials'] ?? [] as $mat) {
                        $path = BASE_PATH . '/storage/app/demo/materials/' . $mat['file'];
                        DB::insert('materials', [
                            'lesson_id' => $lessonId, 'title' => $mat['title'], 'file_path' => 'demo/materials/' . $mat['file'],
                            'original_name' => $mat['file'], 'mime' => is_file($path) ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/pdf',
                            'size_bytes' => is_file($path) ? filesize($path) : 0, 'created_at' => $daysAgo(190),
                        ]);
                    }
                }
                if (!empty($m['quiz'])) {
                    self::insertQuiz($courseId, $moduleId, $m['quiz'], 70, $daysAgo(190));
                }
            }
            if (!empty($c['final'])) {
                self::insertQuiz($courseId, null, $c['final'], $c['final']['pass'] ?? 70, $daysAgo(190));
            }
        }
        $log('catálogo criado: ' . count($courseIds) . ' cursos');

        // Alunos
        $first = ['Ana', 'Bruno', 'Camila', 'Diego', 'Eduarda', 'Felipe', 'Gabriela', 'Henrique', 'Isabela', 'João', 'Karina', 'Leonardo',
            'Mariana', 'Nicolas', 'Olívia', 'Pedro', 'Rafaela', 'Samuel', 'Talita', 'Vinícius', 'Yasmin', 'André', 'Beatriz', 'Caio',
            'Daniela', 'Enzo', 'Fernanda', 'Gustavo', 'Helena', 'Igor', 'Júlia', 'Luana', 'Marcelo', 'Natália', 'Otávio', 'Patrícia',
            'Renato', 'Sofia', 'Thiago', 'Valéria', 'Wesley', 'Alice', 'Bernardo', 'Clara', 'Davi', 'Elisa'];
        $last = ['Silva', 'Souza', 'Oliveira', 'Costa', 'Pereira', 'Almeida', 'Rodrigues', 'Lima', 'Gomes', 'Ribeiro', 'Carvalho',
            'Martins', 'Rocha', 'Barbosa', 'Araújo', 'Fernandes', 'Teixeira', 'Moreira', 'Cardoso', 'Nunes', 'Batista', 'Freitas'];
        $cities = ['São José do Rio Preto', 'Campinas', 'Ribeirão Preto', 'São Paulo', 'Bauru', 'Araraquara', 'Uberlândia', 'Londrina', 'Catanduva', 'Marília'];

        $students = [];
        $lucas = DB::insert('users', [
            'role_id' => $roles['aluno'], 'name' => 'Lucas Ferreira', 'email' => 'aluno@demo.com', 'password_hash' => $pass,
            'cpf' => self::fakeCpf(), 'phone' => '(17) 99123-4567', 'city' => 'São José do Rio Preto', 'status' => 'active',
            'marketing_opt_in' => 1, 'terms_accepted_at' => $daysAgo(64), 'created_at' => $daysAgo(64), 'updated_at' => $daysAgo(5),
            'last_login_at' => $daysAgo(1),
        ]);
        self::consents($lucas, $daysAgo(64), true);

        foreach ($first as $i => $fn) {
            $ln = $last[($i * 7 + 3) % count($last)];
            $ln2 = $last[($i * 5 + 11) % count($last)];
            if ($ln2 === $ln) {
                $ln2 = $last[($i * 5 + 12) % count($last)];
            }
            $name = $i % 3 === 0 ? "$fn $ln $ln2" : "$fn $ln";
            $created = $daysAgo(mt_rand(3, 170));
            $email = str_slug($fn) . '.' . str_slug($ln) . ($i % 4 === 0 ? (string) mt_rand(10, 99) : '') . '@exemplo.com';
            $id = DB::insert('users', [
                'role_id' => $roles['aluno'], 'name' => $name, 'email' => $email, 'password_hash' => $pass,
                'cpf' => mt_rand(0, 3) ? self::fakeCpf() : null, 'city' => $cities[$i % count($cities)],
                'status' => $i % 17 === 5 ? 'inactive' : 'active', 'marketing_opt_in' => (int) (mt_rand(0, 2) === 0),
                'terms_accepted_at' => $created, 'created_at' => $created, 'updated_at' => $created,
                'last_login_at' => date('Y-m-d H:i:s', min(time(), strtotime($created) + mt_rand(0, 60) * 86400)),
            ]);
            self::consents($id, $created, (bool) mt_rand(0, 1));
            $students[] = ['id' => $id, 'created' => $created];
        }

        // Matrículas e progresso simulado
        $published = array_values(array_filter($content['courses'], fn ($c) => ($c['status'] ?? 'published') === 'published'));
        $weights = [34, 24, 18, 12, 8, 7];
        $count = 0;
        foreach ($students as $s) {
            $n = mt_rand(1, 100) > 70 ? mt_rand(2, 3) : 1;
            $chosen = [];
            while (count($chosen) < $n) {
                $chosen[self::weighted($weights)] = true;
            }
            foreach (array_keys($chosen) as $idx) {
                $slug = $published[$idx]['slug'];
                $start = max(strtotime($s['created']), strtotime('-150 days'));
                $enrolledTs = mt_rand($start, max($start, time() - 86400));
                $roll = mt_rand(1, 100);
                $target = match (true) { $roll <= 38 => 1.0, $roll <= 52 => 0.0, default => mt_rand(10, 90) / 100 };
                $cancel = $roll > 97;
                self::simulate($s['id'], $courseIds[$slug], $enrolledTs, $target, $cancel);
                $count++;
            }
        }
        // Jornada do aluno de demonstração
        self::simulate($lucas, $courseIds['atendimento-ao-cliente-e-comunicacao'], strtotime('-58 days'), 1.0, false, 22);
        self::simulate($lucas, $courseIds['excel-essencial-para-o-trabalho'], strtotime('-30 days'), 0.5, false);
        self::simulate($lucas, $courseIds['lgpd-na-pratica-para-empresas'], strtotime('-2 days'), 0.0, false);
        $log("{$count} matrículas simuladas para " . count($students) . ' alunos');

        // LGPD: pedidos de titulares
        $someone = $students[7];
        DB::insert('data_requests', ['user_id' => $someone['id'], 'requester' => (string) DB::value('SELECT email FROM users WHERE id = :id', ['id' => $someone['id']]),
            'type' => 'export', 'status' => 'done', 'notes' => 'Arquivo gerado pelo próprio aluno na área de privacidade.',
            'created_at' => $daysAgo(12), 'processed_at' => $daysAgo(12)]);
        $other = $students[19];
        DB::insert('data_requests', ['user_id' => $other['id'], 'requester' => (string) DB::value('SELECT email FROM users WHERE id = :id', ['id' => $other['id']]),
            'type' => 'deletion', 'status' => 'pending', 'notes' => 'Aluno pediu a exclusão da conta pela área de privacidade.',
            'created_at' => $daysAgo(1), 'processed_at' => null]);

        // Algumas entradas de auditoria da equipe
        $admin = $userIds['admin@demo.com'];
        $coord = $userIds['coordenacao@demo.com'];
        foreach ([
            [$coord, 'course.updated', 'course', $courseIds['excel-essencial-para-o-trabalho'], 6],
            [$coord, 'lesson.saved', 'lesson', null, 6],
            [$admin, 'settings.updated', null, null, 4],
            [$admin, 'backup.created', null, null, 2],
            [$userIds['suporte@demo.com'], 'enrollment.admin_created', 'enrollment', null, 1],
        ] as [$uid, $action, $entity, $eid, $d]) {
            DB::insert('audit_logs', ['user_id' => $uid, 'action' => $action, 'entity' => $entity, 'entity_id' => $eid,
                'details' => null, 'ip' => '127.0.0.1', 'created_at' => $daysAgo($d, 14)]);
        }
        $log('dados de demonstração prontos');
    }

    private static function insertQuiz(int $courseId, ?int $moduleId, array $quiz, int $pass, string $at): void
    {
        $quizId = DB::insert('quizzes', [
            'course_id' => $courseId, 'module_id' => $moduleId, 'title' => $quiz['title'],
            'description' => $moduleId ? 'Confira o que você aprendeu neste módulo.' : 'Avaliação de conclusão do curso. A aprovação libera o certificado.',
            'pass_score' => $pass, 'max_attempts' => $moduleId ? null : 3, 'created_at' => $at, 'updated_at' => $at,
        ]);
        foreach ($quiz['questions'] as $qi => $q) {
            $qid = DB::insert('questions', ['quiz_id' => $quizId, 'statement' => $q['q'], 'explanation' => $q['explain'] ?? null, 'position' => $qi + 1]);
            foreach ($q['options'] as $oi => $text) {
                DB::insert('question_options', ['question_id' => $qid, 'text' => $text, 'is_correct' => (int) ($oi === $q['correct']), 'position' => $oi + 1]);
            }
        }
    }

    /** Simula a jornada de um aluno em um curso até a fração $target da sequência. */
    private static function simulate(int $userId, int $courseId, int $enrolledTs, float $target, bool $cancel, ?int $completedDaysAgo = null): void
    {
        $enrolledAt = date('Y-m-d H:i:s', $enrolledTs);
        $enrollmentId = DB::insert('enrollments', [
            'user_id' => $userId, 'course_id' => $courseId, 'status' => 'active', 'progress_percent' => 0,
            'enrolled_at' => $enrolledAt, 'last_access_at' => $enrolledAt,
        ]);
        DB::insert('audit_logs', ['user_id' => $userId, 'action' => 'enrollment.created', 'entity' => 'enrollment', 'entity_id' => $enrollmentId,
            'details' => null, 'ip' => null, 'created_at' => $enrolledAt]);

        $curriculum = \App\Models\Course::curriculum($courseId);
        $sequence = \App\Models\Course::sequence($curriculum);
        $lessons = [];
        foreach ($curriculum['modules'] as $m) {
            foreach ($m['lessons'] as $l) {
                $lessons[(int) $l['id']] = $l;
            }
        }
        $steps = (int) floor(count($sequence) * $target);
        $span = max(1, min(time() - 3600, $completedDaysAgo !== null ? time() - $completedDaysAgo * 86400 : time()) - $enrolledTs);
        $ts = $enrolledTs;
        $lastLesson = null;
        for ($i = 0; $i < $steps; $i++) {
            $item = $sequence[$i];
            $ts = $completedDaysAgo !== null
                ? $enrolledTs + (int) ($span * ($i + 1) / $steps)
                : min(time() - 600, $ts + mt_rand(1800, (int) max(3600, $span / max(1, count($sequence)))));
            $at = date('Y-m-d H:i:s', $ts);
            if ($item['type'] === 'lesson') {
                $dur = (int) $lessons[$item['id']]['duration_seconds'];
                DB::insert('lesson_progress', ['user_id' => $userId, 'lesson_id' => $item['id'], 'position_seconds' => $dur,
                    'watched_seconds' => $dur, 'completed_at' => $at, 'updated_at' => $at]);
                $lastLesson = $item['id'];
            } else {
                $quiz = DB::fetch('SELECT * FROM quizzes WHERE id = :id', ['id' => $item['id']]);
                $questions = QuizService::questions((int) $item['id'], true);
                if (mt_rand(1, 100) <= 22) { // reprovação antes de passar
                    [$score, $answers] = self::fakeAnswers($questions, mt_rand(20, 60));
                    DB::insert('quiz_attempts', ['user_id' => $userId, 'quiz_id' => $item['id'], 'score' => $score, 'passed' => (int) ($score >= (int) $quiz['pass_score']),
                        'answers' => json_encode($answers), 'finished_at' => date('Y-m-d H:i:s', $ts - 1200)]);
                }
                do {
                    [$score, $answers] = self::fakeAnswers($questions, [70, 75, 80, 80, 85, 90, 100, 100][mt_rand(0, 7)]);
                } while ($score < (int) $quiz['pass_score']);
                DB::insert('quiz_attempts', ['user_id' => $userId, 'quiz_id' => $item['id'], 'score' => $score, 'passed' => 1,
                    'answers' => json_encode($answers), 'finished_at' => $at]);
            }
        }
        // Aula em andamento: a próxima depois da última concluída, com posição parcial.
        $next = $sequence[$steps] ?? null;
        if ($next && $next['type'] === 'lesson' && $steps > 0) {
            $dur = (int) $lessons[$next['id']]['duration_seconds'];
            $pos = (int) ($dur * 0.4);
            DB::insert('lesson_progress', ['user_id' => $userId, 'lesson_id' => $next['id'], 'position_seconds' => $pos,
                'watched_seconds' => $pos, 'completed_at' => null, 'updated_at' => date('Y-m-d H:i:s', $ts + 600)]);
            $lastLesson = $next['id'];
        }
        $result = ProgressService::recalculate($userId, $courseId);
        $finalAt = date('Y-m-d H:i:s', $ts);
        DB::update('enrollments', [
            'last_lesson_id' => $lastLesson, 'last_access_at' => $steps > 0 ? date('Y-m-d H:i:s', min(time() - 300, $ts + 600)) : $enrolledAt,
            'completed_at' => $result['completed'] ? $finalAt : null,
            'status' => $cancel && !$result['completed'] ? 'cancelled' : ($result['completed'] ? 'completed' : 'active'),
        ], 'id = :id', ['id' => $enrollmentId]);
        if ($result['certificate']) {
            $cert = $result['certificate'];
            $cert['issued_at'] = $finalAt;
            DB::update('certificates', ['issued_at' => $finalAt, 'checksum' => CertificateService::checksum($cert)], 'id = :id', ['id' => $cert['id']]);
            DB::query("UPDATE audit_logs SET created_at = :t WHERE action = 'certificate.issued' AND entity_id = :id", ['t' => $finalAt, 'id' => $cert['id']]);
        }
    }

    /**
     * Respostas simuladas próximas da nota desejada. As questões mais difíceis
     * (as últimas de cada avaliação) erram mais, para o relatório ter algo a mostrar.
     * @return array{0:int, 1:array}
     */
    private static function fakeAnswers(array $questions, int $target): array
    {
        $total = count($questions);
        if ($total === 0) {
            return [0, []];
        }
        $right = (int) round($total * $target / 100);
        $order = array_keys($questions);
        usort($order, fn ($a, $b) => ($a + mt_rand(0, 2)) <=> ($b + mt_rand(0, 2)));
        $correctSet = array_slice($order, 0, $right);
        $answers = [];
        foreach ($questions as $i => $q) {
            $good = array_values(array_filter($q['options'], fn ($o) => $o['is_correct']));
            $bad = array_values(array_filter($q['options'], fn ($o) => !$o['is_correct']));
            $pick = in_array($i, $correctSet, true) || !$bad ? $good[0] : $bad[mt_rand(0, count($bad) - 1)];
            $answers[$q['id']] = (int) $pick['id'];
        }
        return [(int) round(count($correctSet) / $total * 100), $answers];
    }

    private static function consents(int $userId, string $at, bool $marketing): void
    {
        foreach (['terms' => true, 'privacy' => true, 'marketing' => $marketing] as $type => $granted) {
            DB::insert('consents', ['user_id' => $userId, 'type' => $type, 'version' => config('lgpd.terms_version'),
                'granted' => (int) $granted, 'ip' => '127.0.0.1', 'user_agent' => 'seed', 'created_at' => $at]);
        }
    }

    private static function weighted(array $weights): int
    {
        $r = mt_rand(1, array_sum($weights));
        foreach ($weights as $i => $w) {
            if (($r -= $w) <= 0) {
                return $i;
            }
        }
        return 0;
    }

    /** CPF com dígitos verificadores válidos, gerado aleatoriamente (fictício). */
    public static function fakeCpf(): string
    {
        $n = [];
        for ($i = 0; $i < 9; $i++) {
            $n[] = mt_rand(0, 9);
        }
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += $n[$i] * (($t + 1) - $i);
            }
            $n[] = ((10 * $sum) % 11) % 10;
        }
        $s = implode('', $n);
        return substr($s, 0, 3) . '.' . substr($s, 3, 3) . '.' . substr($s, 6, 3) . '-' . substr($s, 9, 2);
    }

    /** Divide um arquivo SQL em comandos (ignora comentários "--"). */
    public static function splitSql(string $sql): array
    {
        $lines = array_filter(explode("\n", $sql), fn ($l) => !str_starts_with(trim($l), '--'));
        $statements = preg_split('/;\s*(\r?\n|$)/', implode("\n", $lines)) ?: [];
        return array_values(array_filter(array_map('trim', $statements), 'strlen'));
    }
}
