<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database as DB;
use App\Core\Logger;

/**
 * Backup lógico do banco em PHP puro (não depende de mysqldump, que nem sempre
 * existe em hospedagem compartilhada). Gera storage/backups/backup-AAAAMMDD-HHMMSS.sql.gz.
 */
final class BackupService
{
    public static function dir(): string
    {
        $dir = BASE_PATH . '/storage/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public static function create(): string
    {
        $file = self::dir() . '/backup-' . date('Ymd-His') . '.sql.gz';
        $gz = gzopen($file, 'wb6');
        if (!$gz) {
            throw new \RuntimeException('Não foi possível criar o arquivo de backup.');
        }
        $pdo = DB::pdo();
        gzwrite($gz, "-- Backup gerado em " . date('d/m/Y H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach (DB::column('SHOW TABLES') as $table) {
            $create = DB::fetch("SHOW CREATE TABLE `{$table}`");
            gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n");
            $stmt = $pdo->query("SELECT * FROM `{$table}`");
            $batch = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $batch[] = '(' . implode(',', array_map(
                    fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)),
                    $row,
                )) . ')';
                if (count($batch) === 200) {
                    gzwrite($gz, self::insert($table, $row, $batch));
                    $batch = [];
                }
            }
            if ($batch) {
                gzwrite($gz, self::insert($table, $row ?: [], $batch, $stmt));
            }
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        Logger::info('Backup criado', ['file' => basename($file)]);
        return $file;
    }

    private static function insert(string $table, array|false $row, array $batch, ?\PDOStatement $stmt = null): string
    {
        $cols = [];
        if ($stmt) {
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $cols[] = '`' . $stmt->getColumnMeta($i)['name'] . '`';
            }
        } else {
            $cols = array_map(fn ($c) => "`{$c}`", array_keys((array) $row));
        }
        return "INSERT INTO `{$table}` (" . implode(',', $cols) . ") VALUES\n" . implode(",\n", $batch) . ";\n";
    }

    public static function restore(string $file): void
    {
        $path = is_file($file) ? $file : self::dir() . '/' . basename($file);
        if (!is_file($path)) {
            throw new \RuntimeException('Arquivo de backup não encontrado.');
        }
        $sql = str_ends_with($path, '.gz') ? (string) gzdecode((string) file_get_contents($path)) : (string) file_get_contents($path);
        $pdo = DB::pdo();
        foreach (Installer::splitSql($sql) as $statement) {
            $pdo->exec($statement);
        }
    }

    /** @return array<int, array{name:string, size:int, date:string}> */
    public static function list(): array
    {
        $files = glob(self::dir() . '/backup-*.sql.gz') ?: [];
        rsort($files);
        return array_map(fn ($f) => ['name' => basename($f), 'size' => (int) filesize($f), 'date' => date('Y-m-d H:i:s', (int) filemtime($f))], $files);
    }

    public static function prune(int $keep = 14): int
    {
        $files = glob(self::dir() . '/backup-*.sql.gz') ?: [];
        rsort($files);
        $removed = 0;
        foreach (array_slice($files, $keep) as $f) {
            @unlink($f) && $removed++;
        }
        return $removed;
    }

    public static function path(string $name): ?string
    {
        if (!preg_match('/^backup-\d{8}-\d{6}\.sql\.gz$/', $name)) {
            return null;
        }
        $path = self::dir() . '/' . $name;
        return is_file($path) ? $path : null;
    }
}
