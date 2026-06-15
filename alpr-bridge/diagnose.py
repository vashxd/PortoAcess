"""
Diagnóstico do PortoAccess ALPR Bridge.
Roda todos os testes e diz exatamente o que está falhando.

Uso:
    python diagnose.py
"""
import os, sys, time, re
from pathlib import Path

# Carrega .env
def load_dotenv(path):
    if not path.exists():
        return
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        if "=" in line:
            k, _, v = line.partition("=")
            os.environ.setdefault(k.strip(), v.strip())

load_dotenv(Path(__file__).parent / ".env")

ENTRADA_URL = os.environ.get("CAMERA_ENTRADA_URL", "http://192.168.1.20:8080/video")
SAIDA_URL   = os.environ.get("CAMERA_SAIDA_URL",   "http://192.168.1.27:8080/video")
WEBHOOK_URL = os.environ.get("WEBHOOK_URL", "http://127.0.0.1:8000/api/camera/events")
TOKEN       = os.environ.get("WEBHOOK_TOKEN", "dev-camera-token-123")

OK   = "\033[92m[OK]\033[0m"
FAIL = "\033[91m[FALHOU]\033[0m"
INFO = "\033[94m[INFO]\033[0m"

print("=" * 60)
print("  PortoAccess — Diagnóstico ALPR")
print("=" * 60)

# ── 1. OpenCV ──────────────────────────────────────────────────
print("\n1. Importando OpenCV...")
try:
    import cv2
    print(f"  {OK} OpenCV {cv2.__version__}")
except ImportError as e:
    print(f"  {FAIL} {e}")
    sys.exit(1)

# ── 2. Câmera ENTRADA ─────────────────────────────────────────
def test_camera(url, label):
    print(f"\n2. Testando câmera {label}: {url}")
    cap = cv2.VideoCapture(url)
    if not cap.isOpened():
        # tenta sem especificar backend
        cap = cv2.VideoCapture(url, cv2.CAP_ANY)
    if not cap.isOpened():
        print(f"  {FAIL} Não conseguiu abrir o stream")
        print(f"  {INFO} Verifique se o IP Webcam está rodando e a URL está certa")
        return None
    ok, frame = cap.read()
    cap.release()
    if not ok or frame is None:
        print(f"  {FAIL} Stream abriu mas não leu frame")
        return None
    h, w = frame.shape[:2]
    path = f"snapshot_{label}.jpg"
    cv2.imwrite(path, frame)
    print(f"  {OK} Frame capturado {w}x{h} — salvo em {path}")
    return frame

frame_entrada = test_camera(ENTRADA_URL, "ENTRADA")
frame_saida   = test_camera(SAIDA_URL,   "SAIDA")

# ── 3. ALPR ───────────────────────────────────────────────────
print("\n3. Carregando modelos ALPR (aguarde, pode baixar na 1ª vez)...")
try:
    from fast_alpr import ALPR
    alpr = ALPR(
        detector_model="yolo-v9-t-384-license-plate-end2end",
        ocr_model="global-plates-mobile-vit-v2-model",
    )
    print(f"  {OK} Modelos carregados")
except Exception as e:
    print(f"  {FAIL} {e}")
    sys.exit(1)

def test_alpr(frame, label):
    if frame is None:
        print(f"  {INFO} Pulando ALPR {label} (câmera sem frame)")
        return
    print(f"\n4. Rodando ALPR no frame da câmera {label}...")
    results = alpr.predict(frame)
    if not results:
        print(f"  {FAIL} Nenhuma placa detectada no frame atual")
        print(f"  {INFO} Tente aproximar o carro/placa da câmera")
        print(f"  {INFO} Verifique o arquivo snapshot_{label}.jpg")
        return
    BR = re.compile(r"^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$")
    for r in results:
        if r.ocr:
            plate = re.sub(r"[^A-Z0-9]", "", r.ocr.text.upper())
            conf = r.ocr.confidence
            if isinstance(conf, (list, tuple)):
                conf = sum(conf) / len(conf) if conf else 0
            is_br = "BR válida" if BR.match(plate) else "formato não-BR"
            status = OK if conf >= 0.55 else f"\033[93m[CONF BAIXA]\033[0m"
            print(f"  {status} Placa lida: {plate}  confiança: {conf:.0%}  ({is_br})")
            if conf < 0.55:
                print(f"  {INFO} Abaixo do mínimo 55% — não seria enviada")
                print(f"  {INFO} Use --min-conf 0.3 para reduzir o limiar")

test_alpr(frame_entrada, "ENTRADA")
test_alpr(frame_saida,   "SAIDA")

# ── 5. Webhook ────────────────────────────────────────────────
print(f"\n5. Testando webhook: {WEBHOOK_URL}")
try:
    import requests, base64, datetime
    payload = {
        "camera": "entrada",
        "plate": "TST0D00",
        "confidence": 99,
        "occurred_at": datetime.datetime.now().isoformat(),
    }
    r = requests.post(
        WEBHOOK_URL,
        json=payload,
        headers={"X-Camera-Token": TOKEN, "Accept": "application/json"},
        timeout=5,
    )
    if r.status_code == 201:
        print(f"  {OK} Webhook respondeu 201 — evento criado (id={r.json().get('id')})")
        print(f"  {INFO} Um evento TST0D00 foi criado na fila da guarita (pode apagar)")
    else:
        print(f"  {FAIL} Webhook respondeu {r.status_code}: {r.text[:200]}")
        if r.status_code == 401:
            print(f"  {INFO} Token inválido — confira WEBHOOK_TOKEN no .env")
except Exception as e:
    print(f"  {FAIL} {e}")
    print(f"  {INFO} PHP server está rodando? (php artisan serve)")

print("\n" + "=" * 60)
print("  Diagnóstico concluído.")
print("=" * 60 + "\n")
