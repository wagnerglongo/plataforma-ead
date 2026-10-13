<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Lib\FileStreamer;
use App\Services\BackupService;

final class BackupController extends Controller
{
    public function index(): Response
    {
        return $this->view('admin/backups', ['title' => 'Backups', 'backups' => BackupService::list()], 'admin');
    }

    public function store(): Response
    {
        $file = BackupService::create();
        BackupService::prune(14);
        Audit::log('backup.created', null, null, ['arquivo' => basename($file)]);
        flash('success', 'Backup gerado: ' . basename($file));
        return $this->redirect('admin/backups');
    }

    public function download(string $name): Response
    {
        $path = BackupService::path($name) ?? throw HttpException::notFound('Backup não encontrado.');
        Audit::log('backup.downloaded', null, null, ['arquivo' => $name]);
        return FileStreamer::serve($path, 'application/gzip', $name);
    }
}
