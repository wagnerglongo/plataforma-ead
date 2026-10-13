<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Settings;
use App\Core\ValidationException;

final class SettingsController extends Controller
{
    private const FIELDS = [
        'site_name' => 'required|min:2|max:80',
        'site_tagline' => 'nullable|max:160',
        'contact_email' => 'nullable|email',
        'whatsapp_number' => 'nullable|max:20',
        'gtm_id' => 'nullable|max:20',
        'ga_id' => 'nullable|max:20',
        'certificate_signer_name' => 'required|max:80',
        'certificate_signer_role' => 'required|max:80',
        'dpo_name' => 'nullable|max:80',
        'dpo_email' => 'nullable|email',
        'cookie_banner' => 'boolean',
        'require_video_watch' => 'boolean',
    ];

    public function index(): Response
    {
        return $this->view('admin/settings', ['title' => 'Configurações', 's' => Settings::all()], 'admin');
    }

    public function update(): Response
    {
        $data = $this->validate(self::FIELDS, [
            'site_name' => 'nome da instituição', 'contact_email' => 'e-mail de contato', 'dpo_email' => 'e-mail do encarregado',
            'certificate_signer_name' => 'nome de quem assina', 'certificate_signer_role' => 'cargo de quem assina',
        ]);
        if ($data['gtm_id'] && !preg_match('/^GTM-[A-Z0-9]{4,10}$/', strtoupper($data['gtm_id']))) {
            throw ValidationException::withMessage('gtm_id', 'O ID do Tag Manager tem o formato GTM-XXXXXXX.');
        }
        if ($data['ga_id'] && !preg_match('/^G-[A-Z0-9]{4,12}$/', strtoupper($data['ga_id']))) {
            throw ValidationException::withMessage('ga_id', 'O ID do Google Analytics 4 tem o formato G-XXXXXXXXXX.');
        }
        $data['gtm_id'] = $data['gtm_id'] ? strtoupper($data['gtm_id']) : '';
        $data['ga_id'] = $data['ga_id'] ? strtoupper($data['ga_id']) : '';
        $data['whatsapp_number'] = preg_replace('/\D/', '', (string) $data['whatsapp_number']);
        foreach ($data as $k => $v) {
            Settings::set($k, $v === null ? '' : (string) $v);
        }
        Audit::log('settings.updated', null, null, ['campos' => array_keys($data)]);
        flash('success', 'Configurações salvas.');
        return $this->redirect('admin/configuracoes');
    }
}
