<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database as DB;
use App\Lib\CertificateRenderer;

/**
 * Emissão e verificação de certificados.
 * O certificado guarda uma cópia dos dados (nome, curso, carga horária) e uma
 * assinatura HMAC-SHA256 desses dados com a chave da aplicação: se alguém alterar
 * o registro direto no banco, a página de validação acusa a divergência.
 */
final class CertificateService
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem 0/O e 1/I

    public static function issue(int $userId, int $courseId): array
    {
        $existing = DB::fetch(
            "SELECT * FROM certificates WHERE user_id = :u AND course_id = :c ORDER BY status = 'valid' DESC, id DESC LIMIT 1",
            ['u' => $userId, 'c' => $courseId],
        );
        if ($existing) {
            return $existing;
        }
        $user = DB::fetch('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
        $course = DB::fetch('SELECT * FROM courses WHERE id = :id', ['id' => $courseId]);
        $data = [
            'code' => self::newCode(),
            'user_id' => $userId,
            'course_id' => $courseId,
            'student_name' => $user['name'],
            'student_doc' => mask_cpf($user['cpf']),
            'course_title' => $course['title'],
            'workload_hours' => (int) $course['workload_hours'],
            'final_score' => self::finalScore($userId, $courseId),
            'issued_at' => now(),
            'status' => 'valid',
        ];
        $data['checksum'] = self::checksum($data);
        $data['id'] = DB::insert('certificates', $data);
        Audit::log('certificate.issued', 'certificate', $data['id'], ['code' => $data['code']], $userId);
        return $data;
    }

    public static function findByCode(string $code): ?array
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if (strlen($code) !== 12) {
            return null;
        }
        $formatted = implode('-', str_split($code, 4));
        return DB::fetch('SELECT * FROM certificates WHERE code = :c', ['c' => $formatted]);
    }

    public static function checksum(array $c): string
    {
        $payload = implode('|', [$c['code'], $c['student_name'], $c['course_title'], $c['workload_hours'], $c['issued_at']]);
        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    public static function isIntact(array $c): bool
    {
        return hash_equals($c['checksum'], self::checksum($c));
    }

    public static function validationUrl(string $code): string
    {
        return absolute_url('certificado/' . $code);
    }

    public static function renderData(array $c): array
    {
        return [
            'code' => $c['code'],
            'student_name' => $c['student_name'],
            'student_doc' => $c['student_doc'],
            'course_title' => $c['course_title'],
            'workload_hours' => (int) $c['workload_hours'],
            'issued_at' => $c['issued_at'],
            'final_score' => $c['final_score'] !== null ? (int) $c['final_score'] : null,
            'site_name' => site_name(),
            'signer_name' => (string) setting('certificate_signer_name', 'Coordenação Pedagógica'),
            'signer_role' => (string) setting('certificate_signer_role', 'Coordenação'),
            'validation_url' => self::validationUrl($c['code']),
        ];
    }

    public static function pdf(array $c): string
    {
        return (new CertificateRenderer())->pdf(self::renderData($c));
    }

    /** PNG com cache em disco (a imagem só muda se os dados mudarem). */
    public static function png(array $c, int $width = 1400): string
    {
        $key = substr(hash('sha256', $c['checksum'] . $width . site_name() . setting('certificate_signer_name', '') . config('app.url')), 0, 24);
        $file = BASE_PATH . "/storage/cache/cert-{$key}.png";
        if (is_file($file)) {
            return (string) file_get_contents($file);
        }
        $png = (new CertificateRenderer())->png(self::renderData($c), $width);
        @file_put_contents($file, $png);
        return $png;
    }

    private static function newCode(): string
    {
        do {
            $raw = '';
            for ($i = 0; $i < 12; $i++) {
                $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $code = implode('-', str_split($raw, 4));
        } while (DB::value('SELECT 1 FROM certificates WHERE code = :c', ['c' => $code]));
        return $code;
    }

    /** Nota final: melhor nota na avaliação final; sem ela, média das melhores notas. */
    private static function finalScore(int $userId, int $courseId): ?int
    {
        $final = DB::value(
            'SELECT MAX(qa.score) FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id
              WHERE qa.user_id = :u AND q.course_id = :c AND q.module_id IS NULL',
            ['u' => $userId, 'c' => $courseId],
        );
        if ($final !== null) {
            return (int) $final;
        }
        $avg = DB::value(
            'SELECT AVG(best) FROM (SELECT MAX(qa.score) AS best FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id
              WHERE qa.user_id = :u AND q.course_id = :c GROUP BY qa.quiz_id) t',
            ['u' => $userId, 'c' => $courseId],
        );
        return $avg !== null ? (int) round((float) $avg) : null;
    }
}
