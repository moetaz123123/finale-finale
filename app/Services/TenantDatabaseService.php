<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TenantDatabaseService
{
    public function createDatabase(string $databaseName)
    {
        $exists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$databaseName]);
        if ($exists) {
            throw new \Exception('Ce sous-domaine est déjà utilisé.');
        }
        DB::statement("CREATE DATABASE `$databaseName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
} 