<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Services\DatabaseSetup;
use App\Modules\Core\Services\EnvironmentWriter;
use App\Modules\Core\Services\InstallerLock;
use App\Modules\Core\Services\InstallerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Prompt;
use Throwable;

class InstallCommand extends Command
{
    protected $signature = 'cms:install
        {--db-host= : Host do banco de dados}
        {--db-port= : Porta do banco de dados}
        {--db-database= : Nome do banco de dados}
        {--db-username= : Usuário do banco de dados}
        {--db-password= : Senha do banco de dados}
        {--site-name= : Nome do site}
        {--admin-name= : Nome do administrador}
        {--admin-email= : E-mail do administrador}
        {--admin-password= : Senha do administrador}
        {--sample : Criar conteúdo de exemplo (páginas e menu)}';

    protected $description = 'Instala o sgcore: banco, migrations, admin e lock (equivalente CLI do assistente web)';

    public function handle(
        DatabaseSetup $database,
        EnvironmentWriter $env,
        InstallerLock $lock,
        InstallerService $installer,
    ): int {
        if ($lock->exists()) {
            $this->error('O sgcore já está instalado. Use cms:uninstall para remover.');

            return self::FAILURE;
        }

        $credentials = $this->resolveDatabaseCredentials();

        try {
            $database->testConnection(
                $credentials['host'],
                $credentials['port'],
                $credentials['database'],
                $credentials['username'],
                $credentials['password'],
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        try {
            $env->write([
                'APP_NAME' => 'sgcore',
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $credentials['host'],
                'DB_PORT' => (string) $credentials['port'],
                'DB_DATABASE' => $credentials['database'],
                'DB_USERNAME' => $credentials['username'],
                'DB_PASSWORD' => $credentials['password'],
            ]);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $context = [
            'site_name' => $this->optionOrPrompt('site-name', 'Nome do site', 'Meu Site'),
            'admin' => [
                'name' => $this->optionOrPrompt('admin-name', 'Nome do administrador', 'Administrador'),
                'email' => $this->optionOrPrompt('admin-email', 'E-mail do administrador', 'admin@example.com'),
                'password' => $this->optionOrPrompt('admin-password', 'Senha do administrador', null, secret: true),
            ],
            'sample_content' => (bool) $this->option('sample'),
        ];

        try {
            $installer->install($context);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('sgcore instalado com sucesso.');
        $this->line('  Painel: '.url('/'.admin_path()));

        return self::SUCCESS;
    }

    /**
     * @return array{host: string, port: int, database: string, username: string, password: string}
     */
    private function resolveDatabaseCredentials(): array
    {
        $configured = config('database.default') === 'mysql'
            ? config('database.connections.mysql')
            : null;

        $host = $this->option('db-host')
            ?? ($configured['host'] ?? null)
            ?? Prompt::text('Host do banco de dados', default: '127.0.0.1');

        $port = (int) ($this->option('db-port')
            ?? ($configured['port'] ?? null)
            ?? Prompt::text('Porta do banco de dados', default: '3306'));

        $database = $this->option('db-database')
            ?? ($configured['database'] ?? null)
            ?? Prompt::text('Nome do banco de dados', default: 'sgcore');

        $username = $this->option('db-username')
            ?? ($configured['username'] ?? null)
            ?? Prompt::text('Usuário do banco de dados', default: 'root');

        $password = $this->option('db-password');
        $password ??= ($configured['password'] ?? null);
        $password ??= Prompt::secret('Senha do banco de dados');

        return [
            'host' => (string) $host,
            'port' => $port,
            'database' => (string) $database,
            'username' => (string) $username,
            'password' => (string) $password,
        ];
    }

    private function optionOrPrompt(string $option, string $label, ?string $default, bool $secret = false): string
    {
        $value = $this->option($option);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $value = $secret
            ? Prompt::secret($label, required: true)
            : Prompt::text($label, default: $default, required: $default === null);

        return (string) $value;
    }
}
