<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Services\InstallerLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Prompt;
use RuntimeException;

class UninstallCommand extends Command
{
    protected $signature = 'cms:uninstall {--force : Pular a confirmação}';

    protected $description = 'Remove a instalação do sgcore: dropa as tabelas e apaga o lock (o .env é mantido)';

    public function handle(InstallerLock $lock): int
    {
        if (! $lock->exists()) {
            $this->error('O sgcore não está instalado.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! Prompt::confirm('Isto apagará TODOS os dados do banco. Continuar?', default: false)) {
            $this->comment('Cancelado.');

            return self::SUCCESS;
        }

        $this->dropAllTables();
        $lock->forget();

        $this->components->info('sgcore desinstalado. O arquivo .env foi mantido.');

        return self::SUCCESS;
    }

    private function dropAllTables(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $this->dropSqliteTables($connection->getDatabaseName());

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $connection->statement('SET FOREIGN_KEY_CHECKS=0');

            $tables = $connection->select(
                'SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?',
                [$connection->getDatabaseName()],
            );

            foreach ($tables as $table) {
                $name = (string) $table->name;

                if (preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                    $connection->statement("DROP TABLE IF EXISTS `{$name}`");
                }
            }

            $connection->statement('SET FOREIGN_KEY_CHECKS=1');

            return;
        }

        throw new RuntimeException("Driver de banco não suportado para desinstalação: {$driver}");
    }

    private function dropSqliteTables(string $database): void
    {
        if ($database !== ':memory:' && $database !== '') {
            foreach ([$database, "{$database}-wal", "{$database}-shm"] as $file) {
                File::delete($file);
            }

            return;
        }

        $tables = DB::select(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
        );

        // FKs reference tables that may already be gone; drop children first
        // (migrations always create them last) and with enforcement disabled.
        DB::statement('PRAGMA foreign_keys = OFF');

        foreach (array_reverse($tables) as $table) {
            $name = (string) $table->name;

            if (preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                DB::statement("DROP TABLE IF EXISTS \"{$name}\"");
            }
        }

        DB::statement('PRAGMA foreign_keys = ON');
    }
}
