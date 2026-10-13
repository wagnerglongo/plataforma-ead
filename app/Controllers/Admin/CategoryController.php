<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\Response;
use App\Core\ValidationException;

final class CategoryController extends Controller
{
    public function index(): Response
    {
        return $this->view('admin/categories', [
            'title' => 'Categorias',
            'categories' => DB::fetchAll('SELECT cat.*, (SELECT COUNT(*) FROM courses c WHERE c.category_id = cat.id) AS courses FROM categories cat ORDER BY cat.name'),
        ], 'admin');
    }

    public function store(): Response
    {
        $data = $this->validate(['name' => 'required|min:2|max:80|unique:categories,name'], ['name' => 'nome']);
        DB::insert('categories', ['name' => $data['name'], 'slug' => $this->slug($data['name']), 'created_at' => now()]);
        flash('success', 'Categoria criada.');
        return $this->redirect('admin/categorias');
    }

    public function update(string $id): Response
    {
        $data = $this->validate(['name' => 'required|min:2|max:80|unique:categories,name,' . (int) $id], ['name' => 'nome']);
        DB::update('categories', ['name' => $data['name'], 'slug' => $this->slug($data['name'], (int) $id)], 'id = :id', ['id' => (int) $id]);
        flash('success', 'Categoria renomeada.');
        return $this->redirect('admin/categorias');
    }

    public function destroy(string $id): Response
    {
        if ((int) DB::value('SELECT COUNT(*) FROM courses WHERE category_id = :id', ['id' => (int) $id]) > 0) {
            throw ValidationException::withMessage('name', 'Mova os cursos desta categoria antes de excluí-la.');
        }
        DB::delete('categories', 'id = :id', ['id' => (int) $id]);
        flash('success', 'Categoria excluída.');
        return $this->redirect('admin/categorias');
    }

    private function slug(string $name, int $ignore = 0): string
    {
        $base = str_slug($name);
        $slug = $base;
        for ($i = 2; DB::value('SELECT 1 FROM categories WHERE slug = :s AND id <> :id', ['s' => $slug, 'id' => $ignore]); $i++) {
            $slug = "{$base}-{$i}";
        }
        return $slug;
    }
}
