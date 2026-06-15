# PortoAccess — Inicia as duas pontes ALPR em janelas separadas
# Execute com: .\start-cameras.ps1
# (Se bloquear com erro de política: Set-ExecutionPolicy -Scope CurrentUser RemoteSigned)

$base = Split-Path -Parent $MyInvocation.MyCommand.Path
$python = "$base\.venv\Scripts\python.exe"

if (-not (Test-Path $python)) {
    Write-Error "Ambiente virtual não encontrado em $base\.venv"
    Write-Host "Crie-o com: python -m venv .venv && .venv\Scripts\pip install -r requirements.txt"
    exit 1
}

Write-Host "Iniciando câmera ENTRADA (192.168.1.20:8080)..." -ForegroundColor Green
Start-Process -FilePath $python `
    -ArgumentList "bridge.py --camera entrada" `
    -WorkingDirectory $base `
    -WindowStyle Normal

Start-Sleep -Seconds 2   # evita download paralelo dos modelos na primeira execução

Write-Host "Iniciando câmera SAÍDA (192.168.1.27:8080)..." -ForegroundColor Yellow
Start-Process -FilePath $python `
    -ArgumentList "bridge.py --camera saida" `
    -WorkingDirectory $base `
    -WindowStyle Normal

Write-Host ""
Write-Host "Pontes ALPR rodando em segundo plano." -ForegroundColor Cyan
Write-Host "Feche as janelas ou pressione Ctrl+C em cada uma para parar."
