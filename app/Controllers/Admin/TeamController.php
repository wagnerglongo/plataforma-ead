<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;

/** Usuários da equipe (todos os perfis, menos aluno). */
final class TeamController extends Controller
{
    public function index(): Response
    {
        return $this->view('admin/team/index', [
            'title' => 'Equipe',
            'users' => DB::fetchAll(
                "SELECT u.*, r.name AS role_name, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id
                  WHERE r.slug <> 'aluno' ORDER BY u.status, u.name",
            ),
        ], 'admin');
    }

    public function create(): Response
    {
        return $this->form(['status' => 'active']);
    }

    public function edit(string $id): Response
    {
        return $this->form($this->member((int) $id));
    }

    public function store(): Response
    {
        $data = $this->validated();
        if (!$this->request->input('password')) {
            throw ValidationException::withMessage('password', 'Defina uma senha inicial.');
        }
        $id = DB::insert('users', [
            'role_id' => $data['role_id'], 'name' => $data['name'], 'email' => $data['email'],
            'password_hash' => password_hash((string) $this->request->input('password'), PASSWORD_DEFAULT),
            'status' => $data['status'], 'bio' => $data['bio'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        Audit::log('user.saved', 'user', $id, ['email' => $data['email']]);
        flash('success', 'Pessoa adicionada à equipe.');
        return $this->redirect('admin/equipe');
    }

    public function update(string $id): Response
    {
        $member = $this->member((int) $id);
        $data = $this->validated((int) $id);
        if ((int) $member['id'] === Auth::id() && ($data['status'] !== 'active' || (int) $data['role_id'] !== (int) $member['role_id'])) {
            throw ValidationException::withMessage('role_id', 'Você não pode mudar o próprio perfil nem desativar a própria conta.');
        }
        $update = ['role_id' => $data['role_id'], 'name' => $data['name'], 'email' => $data['email'], 'status' => $data['status'], 'bio' => $data['bio'], 'updated_at' => now()];
        if ($pw = (string) $this->request->input('password')) {
            $update['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
        }
        DB::update('users', $update, 'id = :id', ['id' => $member['id']]);
        Audit::log('user.saved', 'user', (int) $member['id'], ['email' => $data['email']]);
        flash('success', 'Dados salvos.');
        return $this->redirect('admin/equipe');
    }

    private function form(array $member): Response
    {
        return $this->view('admin/team/form', [
            'title' => empty($member['id']) ? 'Nova pessoa na equipe' : $member['name'],
            'member' => $member,
            'roles' => DB::fetchAll("SELECT * FROM roles WHERE slug <> 'aluno' ORDER BY name"),
        ], 'admin');
    }

    private function validated(?int $id = null): array
    {
        $data = $this->validate([
            'name' => 'required|min:3|max:120',
            'email' => 'required|email|max:160|unique:users,email' . ($id ? ',' . $id : ''),
            'role_id' => 'required|integer|exists:roles,id',
            'status' => 'required|in:active,inactive',
            'password' => 'nullable|min:10|max:72|password',
            'bio' => 'nullable|max:500',
        ], ['name' => 'nome', 'email' => 'e-mail', 'role_id' => 'perfil', 'password' => 'senha', 'bio' => 'apresentação']);
        if (DB::value("SELECT slug FROM roles WHERE id = :id", ['id' => $data['role_id']]) === 'aluno') {
            throw ValidationException::withMessage('role_id', 'Para alunos, use a área Alunos.');
        }
        return $data;
    }

    private function member(int $id): array
    {
        return DB::fetch("SELECT u.* FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id AND r.slug <> 'aluno'", ['id' => $id])
            ?? throw HttpException::notFound('Usuário não encontrado.');
    }
}
