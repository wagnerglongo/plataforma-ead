<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Auth;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\CertificateService;

final class CertificateController extends StudentController
{
    public function index(): Response
    {
        $certs = DB::fetchAll(
            "SELECT ct.*, c.slug FROM certificates ct LEFT JOIN courses c ON c.id = ct.course_id
              WHERE ct.user_id = :u ORDER BY ct.issued_at DESC",
            ['u' => Auth::id()],
        );
        $new = null;
        if ($code = $this->request->query('novo')) {
            foreach ($certs as $c) {
                if ($c['code'] === $code) {
                    $new = $c;
                }
            }
        }
        return $this->view('student/certificates', [
            'title' => 'Meus certificados',
            'heading' => 'Meus certificados',
            'subheading' => 'Baixe em PDF ou compartilhe o link de validação com quem precisar conferir.',
            'certificates' => $certs,
            'new' => $new,
            'noindex' => true,
        ], 'student');
    }

    public function pdf(string $code): Response
    {
        $cert = CertificateService::findByCode($code);
        if (!$cert || (int) $cert['user_id'] !== Auth::id()) {
            throw HttpException::notFound('Certificado não encontrado.');
        }
        if ($cert['status'] !== 'valid') {
            throw HttpException::forbidden('Este certificado foi revogado e não pode ser baixado.');
        }
        return Response::download(CertificateService::pdf($cert), 'certificado-' . str_slug($cert['course_title']) . '-' . $cert['code'] . '.pdf', 'application/pdf');
    }
}
