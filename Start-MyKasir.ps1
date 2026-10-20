param(
    [ValidateRange(1, 65535)]
    [int]$Port = 8000
)

$ErrorActionPreference = 'Stop'
Push-Location -LiteralPath $PSScriptRoot

try {
    $null = Get-Command php -ErrorAction Stop
    if (-not (Test-Path -LiteralPath 'vendor/autoload.php' -PathType Leaf)) {
        throw 'Jalankan composer install terlebih dahulu.'
    }
    if (-not (Test-Path -LiteralPath '.env' -PathType Leaf)) {
        Copy-Item -LiteralPath '.env.example' -Destination '.env'
    }

    $phpModules = & php -m
    if ($LASTEXITCODE -ne 0) { throw 'PHP tidak dapat dijalankan. Periksa instalasi PHP.' }
    $phpFlags = @()
    if ($phpModules -notcontains 'bcmath') { $phpFlags += @('-d', 'extension=bcmath') }

    # Resolve Laravel configuration without connecting to a database or exposing credentials.
    & php @phpFlags artisan config:clear
    if ($LASTEXITCODE -ne 0) { throw 'Gagal membaca konfigurasi aplikasi.' }
    $probe = @'
<?php
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->bootstrapWith([
    Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
    Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
]);
$connection = $app['config']->get('database.default');
$database = (new Illuminate\Database\ConfigurationUrlParser)->parseConfiguration(
    $app['config']->get('database.connections.'.$connection, [])
);
echo json_encode([
    'driver' => $database['driver'] ?? $connection,
    'database' => ($database['driver'] ?? $connection) === 'sqlite' ? ($database['database'] ?? null) : null,
    'needs_key' => empty($app['config']->get('app.key')),
], JSON_THROW_ON_ERROR);
'@
    $probeOutput = $probe | & php @phpFlags
    if ($LASTEXITCODE -ne 0) { throw 'Gagal membaca koneksi database dari konfigurasi Laravel.' }
    $configuration = ($probeOutput -join [Environment]::NewLine) | ConvertFrom-Json

    $requiredModules = @('bcmath')
    switch ($configuration.driver) {
        'sqlite' { $requiredModules += @('pdo_sqlite', 'sqlite3') }
        'pgsql' { $requiredModules += 'pdo_pgsql' }
        'mysql' { $requiredModules += 'pdo_mysql' }
        'mariadb' { $requiredModules += 'pdo_mysql' }
        'sqlsrv' { $requiredModules += 'pdo_sqlsrv' }
        default { throw 'Koneksi database belum didukung oleh launcher ini.' }
    }
    foreach ($module in $requiredModules) {
        if ($module -ne 'bcmath' -and $phpModules -notcontains $module) {
            $phpFlags += @('-d', "extension=$module")
        }
    }
    $loadedModules = & php @phpFlags -m
    if ($LASTEXITCODE -ne 0) { throw 'Gagal memuat ekstensi PHP.' }
    foreach ($module in $requiredModules) {
        if ($loadedModules -notcontains $module) {
            throw "Ekstensi PHP $module belum tersedia. Aktifkan di php.ini sebelum menjalankan aplikasi."
        }
    }

    if ($configuration.driver -eq 'sqlite') {
        $databasePath = [string]$configuration.database
        if ([string]::IsNullOrWhiteSpace($databasePath) -or $databasePath -eq ':memory:' -or $databasePath.StartsWith('file:')) {
            throw 'Penggunaan harian membutuhkan DB_DATABASE berupa jalur berkas SQLite yang tersimpan.'
        }
        if (-not [System.IO.Path]::IsPathRooted($databasePath)) {
            $databasePath = Join-Path $PSScriptRoot $databasePath
        }
        $databasePath = [System.IO.Path]::GetFullPath($databasePath)
        if (-not (Test-Path -LiteralPath (Split-Path -Parent $databasePath) -PathType Container)) {
            throw 'Folder database SQLite belum ada. Buat folder sesuai DB_DATABASE terlebih dahulu.'
        }
        if (-not (Test-Path -LiteralPath $databasePath)) {
            $databaseFile = [System.IO.File]::Open($databasePath, [System.IO.FileMode]::CreateNew)
            $databaseFile.Dispose()
        }
        if (-not (Test-Path -LiteralPath $databasePath -PathType Leaf)) {
            throw 'DB_DATABASE harus menunjuk ke berkas SQLite, bukan folder.'
        }
    }

    if ($configuration.needs_key) {
        & php @phpFlags artisan key:generate --force
        if ($LASTEXITCODE -ne 0) { throw 'Gagal membuat kunci aplikasi.' }
    }
    & php @phpFlags artisan migrate --force
    if ($LASTEXITCODE -ne 0) { throw 'Migrasi belum berhasil. Periksa konfigurasi dan layanan database.' }
    & php @phpFlags artisan view:clear
    if ($LASTEXITCODE -ne 0) { throw 'Gagal membersihkan cache tampilan.' }

    Write-Host "MyKasir siap dibuka: http://127.0.0.1:$Port" -ForegroundColor Green
    Write-Host 'Tekan Ctrl+C untuk menghentikan server.'
    Push-Location -LiteralPath (Join-Path $PSScriptRoot 'public')
    try {
        & php @phpFlags -S "127.0.0.1:$Port" (Join-Path $PSScriptRoot 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')
        if ($LASTEXITCODE -ne 0) { throw 'Server tidak dapat berjalan. Pastikan port belum digunakan.' }
    }
    finally { Pop-Location }
}
finally { Pop-Location }
