<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database as DB;
use App\Core\HttpException;
use App\Core\Mailer;
use App\Core\Paginator;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\LgpdService;
use App\Services\ProgressService;

final class StudentController extends Controller
{
    private const LABELS = ['name' => 'nome', 'email' => 'e-mail', 'cpf' => 'CPF', 'phone' => 'telefone', 'course_id' => 'curso'];

    public function index(): Response
    {
        [$from, $params, $filters] = $this->filters();
        $order = match ($filters['ordem']) {
            'nome' => 'u.name',
            'acesso' => 'u.last_login_at IS NULL, u.last_login_at DESC',
            default => 'u.created_at DESC',
        };
        $result = Paginator::paginate(
            "u.*, (SELECT COUNT(*) FROM enrollments e WHERE e.user_id = u.id AND e.status <> 'cancelled') AS enrollments,
                  (SELECT COUNT(*) FROM enrollments e WHERE e.user_id = u.id AND e.status = 'completed') AS completed,
                  (SELECT ROUND(AVG(e.progress_percent)) FROM enrollments e WHERE e.user_id = u.id AND e.status <> 'cancelled') AS avg_progress",
            $from, $params, $order, $this->page(), 20,
        );
        return $this->view('admin/students/index', [
            'title' => 'Alunos',
            'result' => $result,
            'filters' => $filters,
            'courses' => DB::fetchAll('SELECT id, title FROM courses ORDER BY title'),
        ], 'admin');
    }

    public function show(string $id): Response
    {
        $student = $this->student((int) $id);
        $enrollments = DB::fetchAll(
            "SELECT e.*, c.title, c.slug, c.workload_hours,
                    (SELECT code FROM certificates ct WHERE ct.user_id = e.user_id AND ct.course_id = e.course_id AND ct.status = 'valid' LIMIT 1) AS certificate_code
               FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.user_id = :u ORDER BY e.enrolled_at DESC",
            ['u' => $student['id']],
        );
        return $this->view('admin/students/show', [
            'title' => $student['name'],
            'student' => $student,
            'enrollments' => $enrollments,
            'attempts' => DB::fetchAll(
                'SELECT qa.*, q.title, c.title AS course_title FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id JOIN courses c ON c.id = q.course_id
                  WHERE qa.user_id = :u ORDER BY qa.finished_at DESC LIMIT 10',
                ['u' => $student['id']],
            ),
            'available' => DB::fetchAll(
                "SELECT id, title FROM courses WHERE status = 'published' AND id NOT IN (SELECT course_id FROM enrollments WHERE user_id = :u) ORDER BY title",
                ['u' => $student['id']],
            ),
            'activity' => DB::fetchAll('SELECT * FROM audit_logs WHERE user_id = :u ORDER BY created_at DESC LIMIT 8', ['u' => $student['id']]),
            'consents' => DB::fetchAll('SELECT * FROM consents WHERE user_id = :u ORDER BY created_at DESC LIMIT 6', ['u' => $student['id']]),
        ], 'admin');
    }

    public function create(): Response
    {
        return $this->view('admin/students/create', [
            'title' => 'Novo aluno',
            'courses' => DB::fetchAll("SELECT id, title FROM courses WHERE status = 'published' ORDER BY title"),
        ], 'admin');
    }

    public function store(): Response
    {
        $data = $this->validate([
            'name' => 'required|min:3|max:120',
            'email' => 'required|email|max:160|unique:users,email',
            'cpf' => 'nullable|cpf',
            'phone' => 'nullable|max:20',
            'course_id' => 'nullable|integer|exists:courses,id',
            'send_email' => 'boolean',
        ], self::LABELS);
        $roleId = (int) DB::value("SELECT id FROM roles WHERE slug = 'aluno'");
        $userId = DB::insert('users', [
            'role_id' => $roleId, 'name' => $data['name'], 'email' => $data['email'],
            'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'cpf' => $data['cpf'] ? $this->formatCpf($data['cpf']) : null, 'phone' => $data['phone'],
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Audit::log('student.created', 'user', $userId, ['email' => $data['email']]);
        if ($data['course_id']) {
            $this->createEnrollment($userId, (int) $data['course_id']);
        }
        if ($data['send_email']) {
            $token = bin2hex(random_bytes(32));
            DB::insert('password_resets', ['user_id' => $userId, 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 86400 * 3), 'created_at' => now()]);
            Mailer::send($data['email'], 'Seu acesso a ' . site_name(), Mailer::template(
                'Sua conta foi criada',
                '<p>Olá, ' . e(explode(' ', $data['name'])[0]) . '! A equipe de ' . e(site_name()) . ' criou uma conta para você. Defina sua senha para acessar os cursos. O link vale por 3 dias.</p>',
                'Definir minha senha',
                absolute_url('redefinir-senha/' . $token),
            ));
        }
        flash('success', 'Aluno cadastrado' . ($data['send_email'] ? ' e convite enviado por e-mail.' : '.'));
        return $this->redirect("admin/alunos/{$userId}");
    }

    public function update(string $id): Response
    {
        $student = $this->student((int) $id);
        $data = $this->validate([
            'name' => 'required|min:3|max:120',
            'email' => 'required|email|max:160|unique:users,email,' . $student['id'],
            'cpf' => 'nullable|cpf',
            'phone' => 'nullable|max:20',
            'status' => 'required|in:active,inactive',
        ], self::LABELS);
        DB::update('users', [
            'name' => $data['name'], 'email' => $data['email'], 'cpf' => $data['cpf'] ? $this->formatCpf($data['cpf']) : null,
            'phone' => $data['phone'], 'status' => $data['status'], 'updated_at' => now(),
        ], 'id = :id', ['id' => $student['id']]);
        Audit::log('student.updated', 'user', (int) $student['id']);
        flash('success', 'Dados do aluno salvos.');
        return $this->redirect("admin/alunos/{$student['id']}");
    }

    public function enroll(string $id): Response
    {
        $student = $this->student((int) $id);
        $data = $this->validate(['course_id' => 'required|integer|exists:courses,id'], self::LABELS);
        if (DB::value('SELECT 1 FROM enrollments WHERE user_id = :u AND course_id = :c', ['u' => $student['id'], 'c' => $data['course_id']])) {
            throw ValidationException::withMessage('course_id', 'O aluno já tem matrícula neste curso.');
        }
        $this->createEnrollment((int) $student['id'], (int) $data['course_id']);
        flash('success', 'Matrícula feita.');
        return $this->redirect("admin/alunos/{$student['id']}");
    }

    public function cancelEnrollment(string $id): Response
    {
        $e = DB::fetch('SELECT * FROM enrollments WHERE id = :id', ['id' => (int) $id]) ?? throw HttpException::notFound();
        DB::update('enrollments', ['status' => 'cancelled'], 'id = :id', ['id' => $e['id']]);
        Audit::log('enrollment.cancelled', 'enrollment', (int) $e['id']);
        flash('success', 'Matrícula cancelada. O progresso fica guardado caso seja reativada.');
        return $this->redirect("admin/alunos/{$e['user_id']}");
    }

    public function reactivateEnrollment(string $id): Response
    {
        $e = DB::fetch('SELECT * FROM enrollments WHERE id = :id', ['id' => (int) $id]) ?? throw HttpException::notFound();
        DB::update('enrollments', ['status' => 'active'], 'id = :id', ['id' => $e['id']]);
        ProgressService::recalculate((int) $e['user_id'], (int) $e['course_id']);
        Audit::log('enrollment.reactivated', 'enrollment', (int) $e['id']);
        flash('success', 'Matrícula reativada.');
        return $this->redirect("admin/alunos/{$e['user_id']}");
    }

    public function anonymize(string $id): Response
    {
        $student = $this->student((int) $id);
        LgpdService::anonymize((int) $student['id']);
        flash('success', 'Dados pessoais anonimizados. Estatísticas e certificados emitidos foram preservados.');
        return $this->redirect("admin/alunos/{$student['id']}");
    }

    /** Filtros compartilhados com a exportação CSV. @return array{0:string,1:array,2:array} */
    public function filters(): array
    {
        $f = [
            'q' => trim((string) $this->request->query('q', '')),
            'status' => (string) $this->request->query('status', ''),
            'curso' => (int) $this->request->query('curso', 0),
            'ordem' => (string) $this->request->query('ordem', 'recentes'),
        ];
        $where = ["r.slug = 'aluno'"];
        $params = [];
        if ($f['q'] !== '') {
            $where[] = '(u.name LIKE :q1 OR u.email LIKE :q2 OR u.cpf LIKE :q3)';
            $like = '%' . $f['q'] . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        if (in_array($f['status'], ['active', 'inactive'], true)) {
            $where[] = 'u.status = :st';
            $params['st'] = $f['status'];
        }
        if ($f['curso']) {
            $where[] = "EXISTS (SELECT 1 FROM enrollments e2 WHERE e2.user_id = u.id AND e2.course_id = :cid AND e2.status <> 'cancelled')";
            $params['cid'] = $f['curso'];
        }
        return ['FROM users u JOIN roles r ON r.id = u.role_id WHERE ' . implode(' AND ', $where), $params, $f];
    }

    private function createEnrollment(int $userId, int $courseId): void
    {
        $id = DB::insert('enrollments', ['user_id' => $userId, 'course_id' => $courseId, 'status' => 'active', 'progress_percent' => 0, 'enrolled_at' => now()]);
        Audit::log('enrollment.admin_created', 'enrollment', $id, ['user' => $userId, 'course' => $courseId]);
    }

    private function student(int $id): array
    {
        return DB::fetch(
            "SELECT u.* FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id AND r.slug = 'aluno'",
            ['id' => $id],
        ) ?? throw HttpException::notFound('Aluno não encontrado.');
    }

    private function formatCpf(string $cpf): string
    {
        $d = preg_replace('/\D/', '', $cpf);
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }
}
