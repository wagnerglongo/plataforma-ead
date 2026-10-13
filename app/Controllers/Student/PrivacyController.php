<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database as DB;
use App\Core\Mailer;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\LgpdService;

final class PrivacyController extends StudentController
{
    public function index(): Response
    {
        $user = Auth::user();
        return $this->view('student/privacy', [
            'title' => 'Privacidade e dados',
            'heading' => 'Privacidade e dados',
            'subheading' => 'Seus direitos pela LGPD, sem precisar abrir chamado.',
            'user' => $user,
            'requests' => DB::fetchAll('SELECT * FROM data_requests WHERE user_id = :u ORDER BY created_at DESC', ['u' => $user['id']]),
            'pendingDeletion' => (bool) DB::value("SELECT 1 FROM data_requests WHERE user_id = :u AND type = 'deletion' AND status = 'pending'", ['u' => $user['id']]),
            'noindex' => true,
        ], 'student');
    }

    public function export(): Response
    {
        $userId = (int) Auth::id();
        $data = LgpdService::export($userId);
        DB::insert('data_requests', [
            'user_id' => $userId, 'requester' => Auth::user()['email'], 'type' => 'export', 'status' => 'done',
            'notes' => 'Arquivo gerado pelo próprio titular.', 'created_at' => now(), 'processed_at' => now(),
        ]);
        Audit::log('lgpd.export', 'user', $userId);
        return Response::download(
            (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'meus-dados-' . date('Y-m-d') . '.json',
            'application/json; charset=UTF-8',
        );
    }

    public function consents(): Response
    {
        $user = Auth::user();
        $marketing = $this->request->input('marketing') ? 1 : 0;
        if ($marketing !== (int) $user['marketing_opt_in']) {
            DB::update('users', ['marketing_opt_in' => $marketing, 'updated_at' => now()], 'id = :id', ['id' => $user['id']]);
            DB::insert('consents', [
                'user_id' => $user['id'], 'type' => 'marketing', 'version' => config('lgpd.privacy_version'), 'granted' => $marketing,
                'ip' => $this->request->ip(), 'user_agent' => $this->request->userAgent(), 'created_at' => now(),
            ]);
        }
        flash('success', $marketing ? 'Você vai receber novidades por e-mail.' : 'Pronto: você não vai mais receber e-mails de novidades.');
        return $this->redirect('aluno/privacidade');
    }

    public function deletion(): Response
    {
        $user = Auth::user();
        if (!password_verify((string) $this->request->input('password'), $user['password_hash'])) {
            throw ValidationException::withMessage('password', 'Senha incorreta. Confirme com a senha da sua conta.');
        }
        $id = DB::insert('data_requests', [
            'user_id' => $user['id'], 'requester' => $user['email'], 'type' => 'deletion', 'status' => 'pending',
            'notes' => mb_substr((string) $this->request->input('reason', ''), 0, 500) ?: null, 'created_at' => now(),
        ]);
        Audit::log('lgpd.deletion_requested', 'data_request', $id);
        if ($dpo = setting('dpo_email')) {
            Mailer::send($dpo, 'Pedido de exclusão de dados', Mailer::template('Novo pedido de exclusão', '<p>' . e($user['name']) . ' pediu a exclusão dos dados pessoais. Atenda pelo painel em LGPD.</p>', 'Abrir pedidos', absolute_url('admin/lgpd')));
        }
        flash('success', 'Pedido registrado. A equipe responde em até 15 dias, como prevê a LGPD, e você recebe a confirmação por e-mail.');
        return $this->redirect('aluno/privacidade');
    }
}
