<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\Installer;

/**
 * Instalação pelo navegador para hospedagens sem SSH.
 * Fica fechada (404). Para abrir: crie o arquivo vazio storage/instalar.liberado
 * (apagado automaticamente ao concluir) ou defina INSTALL_TOKEN no .env e passe ?token= na URL.
 */
final class InstallController extends Controller
{
    public function index(): Response
    {
        $this->guard();
        $installed = false;
        try {
            $installed = (int) DB::value('SELECT COUNT(*) FROM users') > 0;
        } catch (\Throwable) {
        }
        return $this->view('pages/install', ['title' => 'Instalação', 'installed' => $installed, 'token' => $this->request->query('token'), 'noindex' => true]);
    }

    public function run(): Response
    {
        $this->guard();
        $log = [];
        $logger = function (string $m) use (&$log) {
            $log[] = $m;
        };
        $mode = $this->request->input('mode');
        if ($mode === 'demo') {
            Installer::dropAll($logger);
            Installer::migrate($logger);
            Installer::baseData($logger);
            Installer::demoData($logger);
        } else {
            $data = $this->validate(['name' => 'required|min:3', 'email' => 'required|email', 'password' => 'required|min:10|password']);
            Installer::migrate($logger);
            Installer::baseData($logger);
            if ((int) DB::value('SELECT COUNT(*) FROM users') === 0) {
                Installer::createAdmin($data['name'], $data['email'], (string) $this->request->input('password'));
                $logger('administrador criado');
            }
        }
        @unlink(self::MARKER); // instalação feita: o instalador volta a ficar fechado
        return $this->view('pages/install', ['title' => 'Instalação', 'done' => $log, 'installed' => true, 'token' => $this->request->input('token'), 'noindex' => true]);
    }

    /** Arquivo vazio criado pelo gerenciador de arquivos da hospedagem para liberar o instalador uma vez. */
    private const MARKER = BASE_PATH . '/storage/instalar.liberado';

    private function guard(): void
    {
        if (is_file(self::MARKER)) {
            return;
        }
        $token = (string) env('INSTALL_TOKEN', '');
        $sent = (string) ($this->request->input('token') ?? '');
        if (strlen($token) < 20 || !hash_equals($token, $sent)) {
            throw HttpException::notFound();
        }
    }
}
