<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PDO;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    public function generateSqlBackup(): string
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $dump = $this->tryMysqldump($connection->getConfig());
            if ($dump !== null && trim($dump) !== '') {
                return $dump;
            }
        }

        return $this->generatePdoFallbackDump($connection->getPdo(), $driver);
    }

    protected function tryMysqldump(array $config): ?string
    {
        $binary = is_file('C:\\xampp\\mysql\\bin\\mysqldump.exe')
            ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe'
            : 'mysqldump';

        $args = [
            $binary,
            '--single-transaction',
            '--quick',
            '--skip-comments',
            '--host=' . ($config['host'] ?? '127.0.0.1'),
            '--port=' . (string) ($config['port'] ?? 3306),
            '--user=' . ($config['username'] ?? 'root'),
        ];

        $password = (string) ($config['password'] ?? '');
        if ($password !== '') {
            $args[] = '--password=' . $password;
        }

        $args[] = (string) $config['database'];

        try {
            $process = new Process($args);
            $process->setTimeout(60);
            $process->run();

            if ($process->isSuccessful()) {
                return "-- PCB-SSC Inventory System Database Backup\n-- Generated: " . now()->toDateTimeString() . "\n\n" . $process->getOutput();
            }
        } catch (\Throwable) {
            // Fall through to PDO backup generator
        }

        return null;
    }

    protected function generatePdoFallbackDump(PDO $pdo, string $driver): string
    {
        $lines = [
            '-- PCB-SSC Inventory Management System SQL Backup',
            '-- Generated At: ' . now()->toDateTimeString(),
            '-- Driver: ' . $driver,
            '',
        ];

        $tables = [];
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $tables[] = ['name' => $row['name'], 'ddl' => $row['sql'] . ';'];
            }
        } else {
            $stmt = $pdo->query('SHOW TABLES');
            foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
                $t = $row[0];
                $ddlRow = $pdo->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_ASSOC);
                $ddl = $ddlRow['Create Table'] ?? array_values($ddlRow)[1] ?? '';
                $tables[] = ['name' => $t, 'ddl' => "DROP TABLE IF EXISTS `{$t}`;\n{$ddl};"];
            }
        }

        foreach ($tables as $table) {
            $name = $table['name'];
            $lines[] = "-- --------------------------------------------------------";
            $lines[] = "-- Table structure for `{$name}`";
            $lines[] = "-- --------------------------------------------------------";
            $lines[] = $table['ddl'];
            $lines[] = '';

            $rows = $pdo->query("SELECT * FROM `{$name}`")->fetchAll(PDO::FETCH_ASSOC);
            if (! empty($rows)) {
                $columns = array_map(fn ($c) => "`{$c}`", array_keys($rows[0]));
                $colList = implode(', ', $columns);
                foreach ($rows as $row) {
                    $vals = array_map(function ($v) use ($pdo): string {
                        if ($v === null) {
                            return 'NULL';
                        }
                        if (is_int($v) || is_float($v)) {
                            return (string) $v;
                        }

                        return $pdo->quote((string) $v);
                    }, array_values($row));

                    $lines[] = "INSERT INTO `{$name}` ({$colList}) VALUES (" . implode(', ', $vals) . ');';
                }
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }
}
