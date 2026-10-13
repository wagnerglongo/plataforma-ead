<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Response;
use App\Services\CertificateService;

final class CertificateController extends Controller
{
    public function index(): Response
    {
        $q = trim((string) $this->request->query('q', ''));
        $status = (string) $this->request->query('status', '');
        $course = (int) $this->request->query('curso', 0);
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(ct.code LIKE :q1 OR ct.student_name LIKE :q2)';
            $params += ['q1' => '%' . strtoupper($q) . '%', 'q2' => '%' . $q . '%'];
        }
        if (in_array($status, ['valid', 'revoked'], true)) {
            $where[] = 'ct.status = :st';
            $params['st'] = $status;
        }
        if ($course) {
            $where[] = 'ct.course_id = :c';
            $params['c'] = $course;
        }
        $result = Paginator::paginate('ct.*', 'FROM certificates ct WHERE ' . implode(' AND ', $where), $params, 'ct.issued_at DESC', $this->page(), 20);
        return $this->view('admin/certificates', [
            'title' => 'Certificados',
            'result' => $result,
            'filters' => compact('q', 'status', 'course'),
            'courses' => DB::fetchAll('SELECT id, title FROM courses ORDER BY title'),
        ], 'admin');
    }

    public function pdf(string $id): Response
    {
        $cert = $this->cert((int) $id);
        return new Response(CertificateService::pdf($cert), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificado-' . $cert['code'] . '.pdf"',
        ]);
    }

    public function revoke(string $id): Response
    {
        $cert = $this->cert((int) $id);
        $data = $this->validate(['reason' => 'required|min:5|max:255'], ['reason' => 'motivo']);
        DB::update('certificates', ['status' => 'revoked', 'revoked_at' => now(), 'revoke_reason' => $data['reason']], 'id = :id', ['id' => $cert['id']]);
        Audit::log('certificate.revoked', 'certificate', (int) $cert['id'], ['code' => $cert['code'], 'motivo' => $data['reason']]);
        flash('success', "Certificado {$cert['code']} revogado. A página de validação já mostra a revogação.");
        return $this->back('admin/certificados');
    }

    public function restore(string $id): Response
    {
        $cert = $this->cert((int) $id);
        DB::update('certificates', ['status' => 'valid', 'revoked_at' => null, 'revoke_reason' => null], 'id = :id', ['id' => $cert['id']]);
        Audit::log('certificate.restored', 'certificate', (int) $cert['id'], ['code' => $cert['code']]);
        flash('success', "Certificado {$cert['code']} reativado.");
        return $this->back('admin/certificados');
    }

    /** Emissão manual (ex.: aluno que concluiu presencialmente). */
    public function issue(string $enrollmentId): Response
    {
        $e = DB::fetch('SELECT * FROM enrollments WHERE id = :id', ['id' => (int) $enrollmentId]) ?? throw HttpException::notFound();
        $cert = CertificateService::issue((int) $e['user_id'], (int) $e['course_id']);
        Audit::log('certificate.manual', 'certificate', (int) $cert['id'], ['code' => $cert['code'], 'enrollment' => $e['id']]);
        flash('success', "Certificado {$cert['code']} emitido.");
        return $this->redirect("admin/alunos/{$e['user_id']}");
    }

    private function cert(int $id): array
    {
        return DB::fetch('SELECT * FROM certificates WHERE id = :id', ['id' => $id]) ?? throw HttpException::notFound('Certificado não encontrado.');
    }
}
