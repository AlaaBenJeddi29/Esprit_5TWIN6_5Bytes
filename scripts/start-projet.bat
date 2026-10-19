@echo off
REM =====================================================================
REM  AquaSecure — demarrage du projet + IA locale Ollama (Windows)
REM
REM  A lancer depuis la racine du projet :  scripts\start-projet.bat
REM  Le script :
REM    1. verifie que Ollama est installe (sinon tentative via winget) ;
REM    2. demarre le serveur Ollama s'il ne repond pas sur le port 11434 ;
REM    3. telecharge le modele llama3.2 s'il est absent ;
REM    4. lance php artisan serve.
REM
REM  Si le projet tourne sous XAMPP/Apache (docroot + vhost), retirez la
REM  derniere ligne (php artisan serve) : Apache sert deja l'application.
REM =====================================================================
chcp 65001 >nul
setlocal
cd /d "%~dp0.."

echo.
echo [1/4] Recherche d'Ollama...
set "OLLAMA_BIN="
where ollama >nul 2>nul && set "OLLAMA_BIN=ollama"
if not defined OLLAMA_BIN if exist "%LOCALAPPDATA%\Programs\Ollama\ollama.exe" set "OLLAMA_BIN=%LOCALAPPDATA%\Programs\Ollama\ollama.exe"

if not defined OLLAMA_BIN (
    echo       Ollama non trouve : tentative d'installation avec winget...
    winget install -e --id Ollama.Ollama --silent --accept-package-agreements --accept-source-agreements
    if exist "%LOCALAPPDATA%\Programs\Ollama\ollama.exe" set "OLLAMA_BIN=%LOCALAPPDATA%\Programs\Ollama\ollama.exe"
)

if not defined OLLAMA_BIN (
    echo       Installation impossible. Le rapport PDF se generera malgre tout,
    echo       avec le texte de secours calcule par regles ^(sans IA^).
) else (
    echo       Ollama detecte : %OLLAMA_BIN%

    echo.
    echo [2/4] Verification du serveur sur http://localhost:11434...
    curl.exe -s -o nul -m 3 http://localhost:11434/api/tags
    if errorlevel 1 (
        echo       Serveur inactif : demarrage d'ollama serve en arriere-plan...
        start /B "" "%OLLAMA_BIN%" serve
        timeout /t 5 /nobreak >nul
    ) else (
        echo       Serveur deja actif.
    )

    echo.
    echo [3/4] Verification du modele llama3.2...
    "%OLLAMA_BIN%" list 2>nul | findstr /i /b "llama3.2" >nul
    if errorlevel 1 (
        echo       Model llama3.2 absent : telechargement ^(peut prendre plusieurs minutes^)...
        "%OLLAMA_BIN%" pull llama3.2
    ) else (
        echo       Model llama3.2 deja present.
    )
)

echo.
echo [4/4] Demarrage du serveur Laravel...
echo       ^(retirez cette ligne si vous utilisez XAMPP/Apache^)
php artisan serve

endlocal
