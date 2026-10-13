<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database as DB;
use App\Core\Response;
use App\Core\Upload;
use App\Core\ValidationException;

final class ProfileController extends StudentController
{
    public function edit(): Response
    {
        return $this->view('student/profile', [
            'title' => 'Meu perfil',
            'heading' => 'Meu perfil',
            'subheading' => 'Mantenha seus dados atualizados: o nome aparece nos próximos certificados.',
            'user' => Auth::user(),
            'noindex' => true,
        ], 'student');
    }

    public function update(): Response
    {
        $user = Auth::user();
        $isDemo = config('app.demo') && str_ends_with($user['email'], '@demo.com');
        $data = $this->validate([
            'name' => 'required|min:3|max:120',
            'email' => 'required|email|max:160|unique:users,email,' . $user['id'],
            'cpf' => 'nullable|cpf',
            'phone' => 'nullable|max:20',
            'city' => 'nullable|max:80',
            'bio' => 'nullable|max:500',
        ], ['name' => 'nome', 'email' => 'e-mail', 'phone' => 'telefone', 'city' => 'cidade', 'bio' => 'sobre você']);
        if ($isDemo && $data['email'] !== $user['email']) {
            throw ValidationException::withMessage('email', 'Na demonstração, o e-mail das contas de teste não pode ser alterado.');
        }
        $cpf = $data['cpf'] ? preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', preg_replace('/\D/', '', $data['cpf'])) : null;
        DB::update('users', [
            'name' => preg_replace('/\s+/', ' ', $data['name']),
            'email' => $data['email'],
            'cpf' => $cpf,
            'phone' => $data['phone'],
            'city' => $data['city'],
            'bio' => $data['bio'],
            'updated_at' => now(),
        ], 'id = :id', ['id' => $user['id']]);
        Audit::log('profile.updated', 'user', (int) $user['id']);
        flash('success', 'Perfil atualizado.');
        return $this->redirect('aluno/perfil');
    }

    public function password(): Response
    {
        $user = Auth::user();
        $this->validate(['current_password' => 'required', 'password' => 'required|min:8|max:72|password|confirmed'], ['current_password' => 'senha atual', 'password' => 'nova senha']);
        if (!password_verify((string) $this->request->input('current_password'), $user['password_hash'])) {
            throw ValidationException::withMessage('current_password', 'A senha atual não confere.');
        }
        DB::update('users', ['password_hash' => password_hash((string) $this->request->input('password'), PASSWORD_DEFAULT), 'updated_at' => now()], 'id = :id', ['id' => $user['id']]);
        Audit::log('auth.password_reset', 'user', (int) $user['id']);
        flash('success', 'Senha alterada.');
        return $this->redirect('aluno/perfil');
    }

    public function avatar(): Response
    {
        $file = $this->request->file('avatar');
        if (!$file) {
            throw ValidationException::withMessage('avatar', 'Escolha uma imagem JPG, PNG ou WebP.');
        }
        try {
            $path = Upload::storeImage($file, 'avatars', 400, 1.0);
        } catch (ValidationException $e) {
            throw new ValidationException(['avatar' => array_merge(...array_values($e->errors))]);
        }
        $user = Auth::user();
        Upload::deletePublic($user['avatar']);
        DB::update('users', ['avatar' => $path, 'updated_at' => now()], 'id = :id', ['id' => $user['id']]);
        flash('success', 'Foto atualizada.');
        return $this->redirect('aluno/perfil');
    }
}
