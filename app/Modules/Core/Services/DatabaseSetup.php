<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Exceptions\DatabaseSetupException;
use PDO;
use PDOException;

class DatabaseSetup
{
    /**
     * Test the given credentials, creating the database when missing.
     *
     * @throws DatabaseSetupException
     */
    public function testConnection(
        string $host,
        int $port,
        string $database,
        string $username,
        string $password,
        bool $createIfMissing = true,
    ): void {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new DatabaseSetupException('Nome de banco inválido: use apenas letras, números e underscore.');
        }

        try {
            $server = new PDO(
                sprintf('mysql:host=%s;port=%d', $host, $port),
                $username,
                $password,
                [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        } catch (PDOException $exception) {
            throw new DatabaseSetupException('Não foi possível conectar: '.$this->cleanMessage($exception), previous: $exception);
        }

        $statement = $server->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
        $statement->execute([$database]);
        $exists = (bool) $statement->fetchColumn();

        if (! $exists) {
            if (! $createIfMissing) {
                throw new DatabaseSetupException("O banco \"{$database}\" não existe.");
            }

            try {
                $server->exec(
                    "CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );
            } catch (PDOException $exception) {
                throw new DatabaseSetupException(
                    "Não foi possível criar o banco \"{$database}\": ".$this->cleanMessage($exception),
                    previous: $exception,
                );
            }
        }

        try {
            new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s', $host, $port, $database),
                $username,
                $password,
                [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        } catch (PDOException $exception) {
            throw new DatabaseSetupException(
                'Conexão OK, mas sem permissão sobre o banco: '.$this->cleanMessage($exception),
                previous: $exception,
            );
        }
    }

    private function cleanMessage(PDOException $exception): string
    {
        return preg_replace('/\s+/', ' ', $exception->getMessage()) ?? 'erro desconhecido';
    }
}
