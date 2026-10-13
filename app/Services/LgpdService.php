<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database as DB;
use App\Core\Upload;

/** Direitos do titular (LGPD art. 18): portabilidade/acesso e eliminação por anonimização. */
final class LgpdService
{
    /** Cópia completa e legível por máquina dos dados do titular. */
    public static function export(int $userId): array
    {
        $user = DB::fetch(
            'SELECT u.id, u.name, u.email, u.cpf, u.phone, u.city, u.bio, u.status, u.marketing_opt_in, u.terms_accepted_at,
                    u.last_login_at, u.created_at, u.updated_at, r.name AS perfil
               FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id',
            ['id' => $userId],
        );
        $q = fn (string $sql) => DB::fetchAll($sql, ['u' => $userId]);
        return [
            'gerado_em' => date('c'),
            'controlador' => site_name(),
            'titular' => $user,
            'consentimentos' => $q('SELECT type AS tipo, version AS versao, granted AS concedido, ip, created_at AS data FROM consents WHERE user_id = :u ORDER BY created_at'),
            'matriculas' => $q('SELECT c.title AS curso, e.status, e.progress_percent AS progresso, e.enrolled_at AS matricula, e.completed_at AS conclusao
                                  FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.user_id = :u'),
            'progresso_aulas' => $q('SELECT l.title AS aula, lp.watched_seconds AS segundos_assistidos, lp.completed_at AS concluida_em
                                       FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id WHERE lp.user_id = :u'),
            'avaliacoes' => $q('SELECT q.title AS avaliacao, qa.score AS nota, qa.passed AS aprovado, qa.finished_at AS data
                                  FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id WHERE qa.user_id = :u ORDER BY qa.finished_at'),
            'certificados' => $q('SELECT code AS codigo, course_title AS curso, workload_hours AS carga_horaria, issued_at AS emissao, status FROM certificates WHERE user_id = :u'),
            'registros_de_acesso' => $q('SELECT action AS acao, ip, created_at AS data FROM audit_logs WHERE user_id = :u ORDER BY created_at DESC LIMIT 500'),
            'pedidos_lgpd' => $q('SELECT type AS tipo, status, created_at AS data, processed_at AS atendido_em FROM data_requests WHERE user_id = :u'),
        ];
    }

    /**
     * Elimina os dados pessoais mantendo a integridade do histórico:
     * estatísticas continuam corretas e certificados emitidos continuam validáveis
     * (o certificado é um documento já entregue a terceiros; o nome nele é um registro histórico).
     */
    public static function anonymize(int $userId, ?int $byUserId = null): void
    {
        $user = DB::fetch('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
        if (!$user || $user['anonymized_at']) {
            return;
        }
        DB::transaction(function () use ($user, $userId) {
            DB::update('users', [
                'name' => 'Titular removido #' . $userId,
                'email' => 'removido-' . $userId . '-' . bin2hex(random_bytes(4)) . '@anonimizado.invalid',
                'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                'cpf' => null, 'phone' => null, 'city' => null, 'bio' => null, 'avatar' => null,
                'status' => 'inactive', 'marketing_opt_in' => 0,
                'anonymized_at' => now(), 'updated_at' => now(),
            ], 'id = :id', ['id' => $userId]);
            DB::delete('password_resets', 'user_id = :u', ['u' => $userId]);
            DB::query('UPDATE audit_logs SET ip = NULL WHERE user_id = :u', ['u' => $userId]);
            DB::query('UPDATE consents SET ip = NULL, user_agent = NULL WHERE user_id = :u', ['u' => $userId]);
            DB::query("UPDATE data_requests SET status = 'done', processed_at = :n, requester = 'anonimizado' WHERE user_id = :u AND status = 'pending'", ['n' => now(), 'u' => $userId]);
            Upload::deletePublic($user['avatar']);
        });
        Audit::log('lgpd.anonymized', 'user', $userId, [], $byUserId);
    }
}
