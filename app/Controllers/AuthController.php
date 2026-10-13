<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Mailer;
use App\Core\RateLimiter;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;

final class AuthController extends Controller
{
    private const LABELS = [
        'name' => 'nome completo', 'email' => 'e-mail', 'password' => 'senha',
        'cpf' => 'CPF', 'terms' => 'aceite dos termos',
    ];

    public function loginForm(): Response
    {
        return $this->view('auth/login', ['title' => 'Entrar', 'noFlashContainer' => true, 'noindex' => true]);
    }

    public function login(): Response
    {
        $data = $this->validate(['email' => 'required|email', 'password' => 'required'], self::LABELS);
        $key = 'login:' . $data['email'] . '|' . $this->request->ip();
        if (RateLimiter::tooMany($key, 5, 900)) {
            throw ValidationException::withMessage('email', 'Muitas tentativas seguidas. Aguarde 15 minutos ou redefina sua senha.');
        }
        $user = Auth::attempt($data['email'], (string) $this->request->input('password'));
        if (!$user) {
            RateLimiter::hit($key);
            Audit::log('auth.failed', null, null, ['email' => $data['email']]);
            throw ValidationException::withMessage('email', 'E-mail ou senha incorretos.');
        }
        if (!empty($user['_inactive'])) {
            throw ValidationException::withMessage('email', 'Esta conta está desativada. Fale com o suporte para reativar.');
        }
        RateLimiter::clear($key);
        Auth::login($user);
        Audit::log('auth.login', 'user', (int) $user['id']);

        $intended = Session::pull('_intended');
        if ($intended && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) {
            return $this->redirect($intended);
        }
        return $this->redirect(Auth::isStaff() ? 'admin' : 'aluno');
    }

    public function registerForm(): Response
    {
        return $this->view('auth/register', [
            'title' => 'Criar conta',
            'noFlashContainer' => true,
            'course' => (string) $this->request->query('curso', ''),
        ]);
    }

    public function register(): Response
    {
        $data = $this->validate([
            'name' => 'required|min:3|max:120',
            'email' => 'required|email|max:160|unique:users,email',
            'cpf' => 'nullable|cpf',
            'password' => 'required|min:8|max:72|password|confirmed',
            'terms' => 'required',
            'marketing' => 'boolean',
            'course' => 'nullable|max:180',
        ], self::LABELS);
        if (!preg_match('/\s/', trim($data['name']))) {
            throw ValidationException::withMessage('name', 'Informe nome e sobrenome, como devem aparecer no certificado.');
        }

        $roleId = (int) DB::value("SELECT id FROM roles WHERE slug = 'aluno'");
        $userId = DB::transaction(function () use ($data, $roleId) {
            $id = DB::insert('users', [
                'role_id' => $roleId,
                'name' => preg_replace('/\s+/', ' ', trim($data['name'])),
                'email' => $data['email'],
                'password_hash' => password_hash((string) $this->request->input('password'), PASSWORD_DEFAULT),
                'cpf' => $data['cpf'] ? $this->formatCpf($data['cpf']) : null,
                'status' => 'active',
                'marketing_opt_in' => $data['marketing'],
                'terms_accepted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach (['terms' => 1, 'privacy' => 1, 'marketing' => $data['marketing']] as $type => $granted) {
                DB::insert('consents', [
                    'user_id' => $id, 'type' => $type, 'version' => config('lgpd.terms_version'), 'granted' => $granted,
                    'ip' => $this->request->ip(), 'user_agent' => $this->request->userAgent(), 'created_at' => now(),
                ]);
            }
            return $id;
        });
        Audit::log('auth.register', 'user', $userId, [], $userId);
        Mailer::send($data['email'], 'Boas-vindas ao ' . site_name(), Mailer::template(
            'Sua conta foi criada',
            '<p>Olá, ' . e(explode(' ', $data['name'])[0]) . '! Sua conta está pronta. Escolha um curso e comece quando quiser.</p>',
            'Ver cursos',
            absolute_url('cursos'),
        ));

        Auth::login((array) DB::fetch('SELECT * FROM users WHERE id = :id', ['id' => $userId]));
        Session::flash('_track', ['event' => 'sign_up']);
        if ($data['course']) {
            Session::put('_intended', '/curso/' . $data['course']);
            flash('success', 'Conta criada! Agora é só confirmar a matrícula.');
            return $this->redirect('curso/' . rawurlencode($data['course']));
        }
        flash('success', 'Conta criada! Escolha um curso para começar.');
        return $this->redirect('aluno');
    }

    public function logout(): Response
    {
        Audit::log('auth.logout', 'user', Auth::id());
        Auth::logout();
        flash('info', 'Você saiu da sua conta.');
        return $this->redirect('/');
    }

    public function forgotForm(): Response
    {
        return $this->view('auth/forgot', ['title' => 'Recuperar senha', 'noFlashContainer' => true]);
    }

    public function forgot(): Response
    {
        $data = $this->validate(['email' => 'required|email'], self::LABELS);
        $key = 'reset:' . $this->request->ip();
        if (RateLimiter::tooMany($key, 5, 3600)) {
            throw new HttpException(429, 'Muitos pedidos de redefinição. Tente de novo em uma hora.');
        }
        RateLimiter::hit($key);
        if (config('app.demo') && str_ends_with($data['email'], '@demo.com')) {
            flash('warning', 'As contas de demonstração não podem ter a senha alterada. Use a senha demo1234.');
            return $this->redirect('entrar');
        }
        $user = DB::fetch("SELECT * FROM users WHERE email = :e AND status = 'active'", ['e' => $data['email']]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            DB::insert('password_resets', [
                'user_id' => $user['id'], 'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'created_at' => now(),
            ]);
            Mailer::send($user['email'], 'Redefinição de senha', Mailer::template(
                'Redefina sua senha',
                '<p>Recebemos um pedido para redefinir a senha da sua conta. O link vale por 1 hora.</p><p>Se não foi você, ignore este e-mail: sua senha continua a mesma.</p>',
                'Criar nova senha',
                absolute_url('redefinir-senha/' . $token),
            ));
        }
        // Mesma resposta exista ou não a conta: não revela quais e-mails estão cadastrados.
        flash('success', 'Se este e-mail estiver cadastrado, você vai receber um link para criar uma nova senha em instantes.');
        return $this->redirect('entrar');
    }

    public function resetForm(string $token): Response
    {
        if (!$this->findReset($token)) {
            flash('error', 'Este link de redefinição expirou ou já foi usado. Peça um novo.');
            return $this->redirect('recuperar-senha');
        }
        return $this->view('auth/reset', ['title' => 'Nova senha', 'token' => $token, 'noFlashContainer' => true, 'noindex' => true]);
    }

    public function reset(): Response
    {
        $token = (string) $this->request->input('token');
        $reset = $this->findReset($token);
        if (!$reset) {
            flash('error', 'Este link de redefinição expirou ou já foi usado. Peça um novo.');
            return $this->redirect('recuperar-senha');
        }
        $this->validate(['password' => 'required|min:8|max:72|password|confirmed'], self::LABELS);
        DB::transaction(function () use ($reset) {
            DB::update('users', ['password_hash' => password_hash((string) $this->request->input('password'), PASSWORD_DEFAULT), 'updated_at' => now()], 'id = :id', ['id' => $reset['user_id']]);
            DB::update('password_resets', ['used_at' => now()], 'user_id = :u AND used_at IS NULL', ['u' => $reset['user_id']]);
        });
        Audit::log('auth.password_reset', 'user', (int) $reset['user_id'], [], (int) $reset['user_id']);
        flash('success', 'Senha alterada. Entre com a nova senha.');
        return $this->redirect('entrar');
    }

    private function findReset(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return DB::fetch(
            'SELECT * FROM password_resets WHERE token_hash = :h AND used_at IS NULL AND expires_at > :n ORDER BY id DESC LIMIT 1',
            ['h' => hash('sha256', $token), 'n' => now()],
        );
    }

    private function formatCpf(string $cpf): string
    {
        $d = preg_replace('/\D/', '', $cpf);
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }
}
