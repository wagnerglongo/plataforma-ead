<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Response;

/** Perfis de acesso e matriz de permissões. */
final class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = DB::fetchAll(
            "SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS users FROM roles r WHERE r.slug <> 'aluno' ORDER BY r.id",
        );
        $grants = [];
        foreach (DB::fetchAll('SELECT role_id, permission_id FROM role_permissions') as $g) {
            $grants[$g['role_id']][$g['permission_id']] = true;
        }
        return $this->view('admin/roles', [
            'title' => 'Perfis e permissões',
            'roles' => $roles,
            'permissions' => DB::fetchAll('SELECT * FROM permissions ORDER BY id'),
            'grants' => $grants,
        ], 'admin');
    }

    public function store(): Response
    {
        $data = $this->validate(['name' => 'required|min:3|max:60', 'description' => 'nullable|max:255'], ['name' => 'nome do perfil']);
        $slug = str_slug($data['name']);
        if (DB::value('SELECT 1 FROM roles WHERE slug = :s', ['s' => $slug])) {
            $slug .= '-' . random_int(10, 99);
        }
        $id = DB::insert('roles', ['name' => $data['name'], 'slug' => $slug, 'description' => $data['description'], 'is_system' => 0, 'created_at' => now()]);
        Audit::log('role.updated', 'role', $id, ['criado' => $data['name']]);
        flash('success', 'Perfil criado. Marque as permissões dele abaixo.');
        return $this->redirect('admin/perfis#perfil-' . $id);
    }

    public function update(string $id): Response
    {
        $role = DB::fetch('SELECT * FROM roles WHERE id = :id', ['id' => (int) $id]) ?? throw HttpException::notFound();
        if (in_array($role['slug'], ['admin', 'aluno'], true)) {
            flash('warning', 'O perfil ' . $role['name'] . ' tem permissões fixas.');
            return $this->redirect('admin/perfis');
        }
        $ids = array_map('intval', (array) $this->request->input('permissions', []));
        $valid = DB::column('SELECT id FROM permissions');
        DB::transaction(function () use ($role, $ids, $valid) {
            DB::delete('role_permissions', 'role_id = :r', ['r' => $role['id']]);
            foreach (array_intersect($ids, array_map('intval', $valid)) as $pid) {
                DB::insert('role_permissions', ['role_id' => $role['id'], 'permission_id' => $pid]);
            }
        });
        Audit::log('role.updated', 'role', (int) $role['id'], ['perfil' => $role['name'], 'permissoes' => count($ids)]);
        flash('success', 'Permissões de ' . $role['name'] . ' salvas. Valem a partir do próximo clique de quem tem esse perfil.');
        return $this->redirect('admin/perfis');
    }
}
