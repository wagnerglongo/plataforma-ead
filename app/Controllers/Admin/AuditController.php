<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\Paginator;
use App\Core\Response;

final class AuditController extends Controller
{
    public function index(): Response
    {
        $scope = (string) $this->request->query('quem', 'equipe');
        $action = (string) $this->request->query('acao', '');
        $where = ['1=1'];
        $params = [];
        if ($scope === 'equipe') {
            $where[] = "r.slug <> 'aluno'";
        } elseif ($scope === 'alunos') {
            $where[] = "r.slug = 'aluno'";
        }
        if ($action !== '') {
            $where[] = 'a.action LIKE :a';
            $params['a'] = $action . '%';
        }
        $result = Paginator::paginate(
            'a.*, u.name, u.email, r.name AS role_name',
            'FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN roles r ON r.id = u.role_id WHERE ' . implode(' AND ', $where),
            $params, 'a.created_at DESC, a.id DESC', $this->page(), 30,
        );
        return $this->view('admin/audit', [
            'title' => 'Registro de atividades',
            'result' => $result,
            'scope' => $scope,
            'action' => $action,
            'actions' => DB::column("SELECT DISTINCT SUBSTRING_INDEX(action, '.', 1) FROM audit_logs ORDER BY 1"),
        ], 'admin');
    }
}
