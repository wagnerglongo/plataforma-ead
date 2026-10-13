<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Mailer;
use App\Core\Response;
use App\Services\LgpdService;

/** Fila de pedidos de titulares (acesso, exclusão). */
final class LgpdController extends Controller
{
    public function index(): Response
    {
        return $this->view('admin/lgpd', [
            'title' => 'Pedidos LGPD',
            'requests' => DB::fetchAll(
                "SELECT d.*, u.name, u.anonymized_at FROM data_requests d LEFT JOIN users u ON u.id = d.user_id
                  ORDER BY d.status = 'pending' DESC, d.created_at DESC LIMIT 200",
            ),
            'consentStats' => DB::fetchAll(
                "SELECT type, SUM(granted) AS yes, COUNT(*) - SUM(granted) AS no FROM consents GROUP BY type",
            ),
        ], 'admin');
    }

    public function update(string $id): Response
    {
        $req = DB::fetch('SELECT * FROM data_requests WHERE id = :id', ['id' => (int) $id]) ?? throw HttpException::notFound();
        $decision = $this->request->input('decision');
        $notes = mb_substr((string) $this->request->input('notes', ''), 0, 500);
        $email = $req['requester'];
        if ($decision === 'approve' && $req['type'] === 'deletion' && $req['user_id']) {
            LgpdService::anonymize((int) $req['user_id']);
            DB::update('data_requests', ['status' => 'done', 'processed_at' => now(), 'notes' => $notes ?: 'Dados anonimizados.'], 'id = :id', ['id' => $req['id']]);
            Mailer::send($email, 'Seus dados foram excluídos', Mailer::template('Pedido de exclusão atendido', '<p>Seus dados pessoais foram eliminados da plataforma ' . e(site_name()) . ', como você pediu. Certificados já emitidos continuam válidos para conferência.</p>'));
            flash('success', 'Pedido atendido: dados anonimizados e titular avisado.');
        } elseif ($decision === 'reject') {
            DB::update('data_requests', ['status' => 'rejected', 'processed_at' => now(), 'notes' => $notes ?: null], 'id = :id', ['id' => $req['id']]);
            Audit::log('lgpd.rejected', 'data_request', (int) $req['id'], ['motivo' => $notes]);
            flash('success', 'Pedido marcado como recusado. Informe o motivo ao titular.');
        } else {
            DB::update('data_requests', ['status' => 'done', 'processed_at' => now(), 'notes' => $notes ?: null], 'id = :id', ['id' => $req['id']]);
            flash('success', 'Pedido marcado como atendido.');
        }
        return $this->redirect('admin/lgpd');
    }
}
