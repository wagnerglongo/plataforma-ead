<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\CertificateService;

/** Validação pública de certificados (sem login). */
final class CertificateController extends Controller
{
    public function form(): Response
    {
        $code = (string) $this->request->query('codigo', '');
        if ($code !== '') {
            $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
            if (strlen($clean) === 12) {
                return $this->redirect('certificado/' . implode('-', str_split($clean, 4)));
            }
            flash('error', 'O código tem 12 letras e números, no formato XXXX-XXXX-XXXX.');
            return $this->redirect('validar');
        }
        return $this->view('public/validate', [
            'title' => 'Validar certificado',
            'description' => 'Confira a autenticidade de um certificado emitido por ' . site_name() . '.',
        ]);
    }

    public function show(string $code): Response
    {
        $cert = CertificateService::findByCode($code);
        if (!$cert) {
            return Response::html(\App\Core\View::render('public/certificate', [
                'title' => 'Certificado não encontrado',
                'cert' => null,
                'code' => strtoupper($code),
                'noindex' => true,
            ], 'public'), 404);
        }
        return $this->view('public/certificate', [
            'title' => 'Certificado ' . $cert['code'],
            'cert' => $cert,
            'intact' => CertificateService::isIntact($cert),
            'code' => $cert['code'],
            'noindex' => true,
        ]);
    }

    public function image(string $code): Response
    {
        $cert = $this->validOrFail($code);
        return (new Response(CertificateService::png($cert), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]));
    }

    public function pdf(string $code): Response
    {
        $cert = $this->validOrFail($code);
        return (new Response(CertificateService::pdf($cert), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificado-' . $cert['code'] . '.pdf"',
            'Cache-Control' => 'private, max-age=600',
        ]));
    }

    private function validOrFail(string $code): array
    {
        $cert = CertificateService::findByCode($code);
        if (!$cert || $cert['status'] !== 'valid' || !CertificateService::isIntact($cert)) {
            throw HttpException::notFound('Certificado não encontrado ou sem validade.');
        }
        return $cert;
    }
}
