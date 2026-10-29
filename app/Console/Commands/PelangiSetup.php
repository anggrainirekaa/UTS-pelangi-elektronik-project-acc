<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;

/**
 * Menyiapkan database Pelangi Elektronik dari kondisi kosong: membuat database (MySQL) bila belum ada,
 * menjalankan seluruh migration, lalu mengisi data demo hanya saat database masih kosong.
 */
class PelangiSetup extends Command
{
    protected $signature = 'pelangi:setup {--fresh : Hapus seluruh tabel lalu bangun ulang beserta data demo}';

    protected $description = 'Buat database (jika perlu), jalankan migration, dan isi data demo Pelangi Elektronik';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        try {
            $this->ensureDatabase($connection, $config);
            $this->components->info("Database \"{$config['database']}\" ({$connection}) siap.");

            if ($this->option('fresh')) {
                Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true], $this->output);

                return self::SUCCESS;
            }

            Artisan::call('migrate', ['--force' => true], $this->output);

            if (! User::query()->exists()) {
                $this->components->info('Database masih kosong: mengisi data demo ...');
                Artisan::call('db:seed', ['--force' => true], $this->output);
            } else {
                $this->components->info('Data sudah ada; data demo tidak ditimpa.');
            }
        } catch (PDOException|\Illuminate\Database\QueryException $e) {
            $this->components->error('Tidak dapat terhubung ke database: '.$e->getMessage());
            $this->line('Periksa DB_CONNECTION, DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD di .env dan pastikan server MySQL sudah menyala.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $config */
    private function ensureDatabase(string $connection, array $config): void
    {
        if ($connection === 'sqlite') {
            $path = $config['database'];

            if ($path !== ':memory:' && ! is_file($path)) {
                touch($path);
            }

            return;
        }

        if (! in_array($connection, ['mysql', 'mariadb'], true)) {
            return;
        }

        $name = $config['database'];

        if (! preg_match('/^[A-Za-z0-9_]+$/', (string) $name)) {
            throw new PDOException('Nama database hanya boleh berisi huruf, angka, dan garis bawah.');
        }

        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s', $config['host'], $config['port']),
            $config['username'],
            $config['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        DB::purge($connection);
    }
}
