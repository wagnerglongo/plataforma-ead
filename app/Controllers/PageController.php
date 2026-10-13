<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\Response;

final class PageController extends Controller
{
    public function privacy(): Response
    {
        return $this->view('pages/privacy', ['title' => 'Política de privacidade']);
    }

    public function terms(): Response
    {
        return $this->view('pages/terms', ['title' => 'Termos de uso']);
    }

    public function cookies(): Response
    {
        return $this->view('pages/cookies', ['title' => 'Cookies']);
    }

    public function about(): Response
    {
        return $this->view('pages/about', [
            'title' => 'Sobre o projeto',
            'description' => 'Plataforma EAD desenvolvida pela WGL Software em PHP puro e MySQL, sem dependências externas.',
        ]);
    }

    /** Registra a escolha do banner de cookies (prova de consentimento, LGPD art. 8º). */
    public function cookieConsent(): Response
    {
        $choice = $this->request->input('choice') === 'all' ? 'all' : 'essential';
        DB::insert('consents', [
            'user_id' => Auth::id(),
            'type' => 'cookies',
            'version' => config('lgpd.privacy_version'),
            'granted' => $choice === 'all' ? 1 : 0,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'created_at' => now(),
        ]);
        return $this->json(['ok' => true]);
    }
}
