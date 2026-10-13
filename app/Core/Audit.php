<?php

declare(strict_types=1);

namespace App\Core;

/** Trilha de auditoria (quem fez o quê e quando). Base para prestação de contas da LGPD. */
final class Audit
{
    public static function log(string $action, ?string $entity = null, ?int $entityId = null, array $details = [], ?int $userId = null): void
    {
        try {
            Database::insert('audit_logs', [
                'user_id' => $userId ?? Auth::id(),
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip' => Request::current()?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Falha ao gravar auditoria: ' . $e->getMessage());
        }
    }

    public static function label(string $action): string
    {
        return [
            'auth.login' => 'Entrou no sistema',
            'auth.logout' => 'Saiu do sistema',
            'auth.failed' => 'Tentativa de login recusada',
            'auth.register' => 'Criou conta',
            'auth.password_reset' => 'Redefiniu a senha',
            'course.created' => 'Criou curso',
            'course.updated' => 'Editou curso',
            'course.deleted' => 'Excluiu curso',
            'module.saved' => 'Salvou módulo',
            'module.deleted' => 'Excluiu módulo',
            'lesson.saved' => 'Salvou aula',
            'lesson.deleted' => 'Excluiu aula',
            'quiz.saved' => 'Salvou avaliação',
            'quiz.deleted' => 'Excluiu avaliação',
            'student.created' => 'Cadastrou aluno',
            'student.updated' => 'Editou aluno',
            'user.saved' => 'Salvou usuário da equipe',
            'role.updated' => 'Alterou permissões de perfil',
            'enrollment.created' => 'Fez matrícula em um curso',
            'enrollment.admin_created' => 'Matriculou aluno',
            'enrollment.reactivated' => 'Reativou matrícula',
            'material.downloaded' => 'Baixou material',
            'user.status' => 'Alterou situação de usuário',
            'enrollment.cancelled' => 'Cancelou matrícula',
            'certificate.issued' => 'Recebeu certificado',
            'certificate.manual' => 'Emitiu certificado manualmente',
            'certificate.revoked' => 'Revogou certificado',
            'certificate.restored' => 'Reativou certificado',
            'settings.updated' => 'Alterou configurações',
            'backup.created' => 'Gerou backup',
            'backup.downloaded' => 'Baixou backup',
            'lgpd.export' => 'Exportou dados pessoais',
            'lgpd.deletion_requested' => 'Pediu exclusão de dados',
            'lgpd.anonymized' => 'Anonimizou dados de aluno',
            'lgpd.rejected' => 'Recusou pedido de titular',
            'report.exported' => 'Exportou relatório',
            'profile.updated' => 'Atualizou o perfil',
        ][$action] ?? $action;
    }
}
