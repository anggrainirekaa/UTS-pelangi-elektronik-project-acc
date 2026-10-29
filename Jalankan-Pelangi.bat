
@echo off
title Pelangi Elektronik

cd /d "%~dp0"

set "PHP84=C:\Users\USER\Downloads\php84"

if not exist "%PHP84%\php.exe" (
    echo ERROR: PHP 8.4 tidak ditemukan!
    echo Periksa folder PHP 8.4 kamu.
    pause
    exit /b 1
)

set "PATH=%PHP84%;%PATH%"

echo =====================================
echo       PELANGI ELEKTRONIK
echo =====================================
echo.

echo Memeriksa versi PHP...
php -v
echo.

if not exist "artisan" (
    echo ERROR: File artisan tidak ditemukan!
    pause
    exit /b 1
)

if not exist "vendor\autoload.php" (
    echo ERROR: Dependensi Laravel belum tersedia.
    pause
    exit /b 1
)

if not exist "public\build\manifest.json" (
    echo Menyiapkan tampilan website...
    call npm install
    if errorlevel 1 (
        pause
        exit /b 1
    )
    call npm run build
    if errorlevel 1 (
        pause
        exit /b 1
    )
)

echo Membersihkan cache Laravel...
php artisan optimize:clear
if errorlevel 1 (
    pause
    exit /b 1
)

echo.
echo Membuka halaman login...
start "" http://127.0.0.1:8000/login

php artisan serve

pause
