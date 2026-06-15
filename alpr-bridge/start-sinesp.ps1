# PortoAccess — Inicia o sidecar de consulta Sinesp Cidadao
# Execute com: .\start-sinesp.ps1            (modo mock — padrao)
#          ou: .\start-sinesp.ps1 real       (modo real — lib nao oficial)
# (Se bloquear com erro de politica: Set-ExecutionPolicy -Scope CurrentUser RemoteSigned)
param([string]$Mode = $env:SINESP_MODE)

$base = Split-Path -Parent $MyInvocation.MyCommand.Path
$python = "$base\.venv\Scripts\python.exe"
if (-not (Test-Path $python)) { $python = "python" }  # sidecar so usa a stdlib

# Modo: "mock" (demonstracao) ou "real" (lib nao oficial — ver sinesp_service.py)
if ($Mode) { $env:SINESP_MODE = $Mode }
if (-not $env:SINESP_MODE) { $env:SINESP_MODE = "mock" }
if (-not $env:SINESP_PORT) { $env:SINESP_PORT = "8077" }

Write-Host "Iniciando sidecar Sinesp em http://127.0.0.1:$($env:SINESP_PORT) (modo=$($env:SINESP_MODE))..." -ForegroundColor Cyan
& $python "$base\sinesp_service.py"
