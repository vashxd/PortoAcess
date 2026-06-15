"""
PortoAccess — Ponte ALPR (Opção B da documentação)

Captura frames de uma câmera IP (HTTP/RTSP), webcam ou arquivo de imagem,
roda reconhecimento de placas local (fast-alpr, open-source/offline)
e envia os eventos para o webhook do PortoAccess.

Uso (com .env configurado):
    # Câmera de entrada (usa CAMERA_ENTRADA_URL do .env)
    python bridge.py --camera entrada

    # Câmera de saída
    python bridge.py --camera saida

    # Sobrepor URL manualmente
    python bridge.py --source "rtsp://user:pass@192.168.1.7:554/stream1" --camera entrada

    # Teste com uma foto
    python bridge.py --source foto-carro.jpg --camera entrada --once
"""

import argparse
import base64
import os
import re
import sys
import time
from datetime import datetime
from pathlib import Path

import cv2
import requests

BR_PLATE = re.compile(r"^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$")


def load_dotenv(path: Path):
    """Carrega variáveis de um arquivo .env sem dependências extras."""
    if not path.exists():
        return
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        if "=" in line:
            key, _, val = line.partition("=")
            os.environ.setdefault(key.strip(), val.strip())


# Carrega .env do mesmo diretório do script
load_dotenv(Path(__file__).parent / ".env")


def parse_args():
    p = argparse.ArgumentParser(description="Ponte ALPR -> PortoAccess")

    # --source: se não informado, usa CAMERA_ENTRADA_URL ou CAMERA_SAIDA_URL do .env
    p.add_argument("--source", default=None,
                   help="URL HTTP/RTSP, índice de webcam (0, 1…) ou caminho de imagem. "
                        "Padrão: CAMERA_ENTRADA_URL / CAMERA_SAIDA_URL do .env")
    p.add_argument("--camera", choices=["entrada", "saida"], default="entrada",
                   help="Qual cancela esta câmera observa")
    p.add_argument("--webhook",
                   default=os.environ.get("WEBHOOK_URL", "http://127.0.0.1:8000/api/camera/events"),
                   help="URL do webhook do PortoAccess")
    p.add_argument("--token",
                   default=os.environ.get("WEBHOOK_TOKEN", "dev-camera-token-123"),
                   help="CAMERA_WEBHOOK_TOKEN do .env do PortoAccess")
    p.add_argument("--interval", type=float, default=0.7,
                   help="Segundos entre análises de frame (padrão 0.7)")
    p.add_argument("--min-conf", type=float,
                   default=float(os.environ.get("MIN_CONF", "0.55")),
                   help="Confiança mínima do OCR para enviar (0-1)")
    p.add_argument("--cooldown", type=int,
                   default=int(os.environ.get("COOLDOWN", "30")),
                   help="Segundos sem reenviar a mesma placa")
    p.add_argument("--any-plate", action="store_true",
                   help="Envia qualquer texto lido (sem validar formato de placa BR)")
    p.add_argument("--show", action="store_true",
                   help="Mostra janela com o vídeo e as detecções")
    p.add_argument("--once", action="store_true",
                   help="Analisa um único frame/imagem e sai")

    args = p.parse_args()

    # Resolve --source padrão com base em --camera
    if args.source is None:
        env_key = "CAMERA_ENTRADA_URL" if args.camera == "entrada" else "CAMERA_SAIDA_URL"
        args.source = os.environ.get(env_key)
        if not args.source:
            p.error(f"Informe --source ou defina {env_key} no alpr-bridge/.env")

    return args


def open_source(source: str):
    """Abre HTTP/RTSP, webcam ou imagem. Retorna (capture|None, frame_estático|None)."""
    if source.isdigit():
        cap = cv2.VideoCapture(int(source), cv2.CAP_DSHOW)
        return cap, None
    if source.lower().startswith(("rtsp://", "http://", "https://")):
        cap = cv2.VideoCapture(source, cv2.CAP_FFMPEG)
        cap.set(cv2.CAP_PROP_BUFFERSIZE, 2)
        return cap, None
    frame = cv2.imread(source)
    if frame is None:
        sys.exit(f"[ERRO] Não foi possível ler a imagem: {source}")
    return None, frame


def send_event(args, plate: str, confidence: float, frame) -> bool:
    ok, jpg = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, 80])
    payload = {
        "camera": args.camera,
        "plate": plate,
        "confidence": round(confidence * 100, 1),
        "occurred_at": datetime.now().isoformat(),
        "photo_base64": base64.b64encode(jpg.tobytes()).decode() if ok else None,
    }
    try:
        session = requests.Session()
        session.trust_env = False  # ignora proxy do sistema
        r = session.post(
            args.webhook,
            json=payload,
            headers={"X-Camera-Token": args.token, "Accept": "application/json"},
            timeout=10,
        )
        if r.status_code == 201:
            print(f"  -> enviado ao PortoAccess (evento #{r.json().get('id')})")
            return True
        print(f"  -> webhook respondeu {r.status_code}: {r.text[:200]}")
    except requests.RequestException as e:
        print(f"  -> falha ao enviar: {e}")
    return False


def main():
    args = parse_args()

    print(f"[1/3] Câmera '{args.camera}' — fonte: {args.source}")
    print("[2/3] Carregando modelos ALPR (download na primeira execução)...")
    from fast_alpr import ALPR

    alpr = ALPR(
        detector_model="yolo-v9-t-384-license-plate-end2end",
        ocr_model="global-plates-mobile-vit-v2-model",
    )
    print("[3/3] Modelos prontos. Monitorando… (Ctrl+C para sair)")

    cap, static_frame = open_source(args.source)
    while cap is not None and not cap.isOpened():
        print(f"[AVISO] Fonte indisponível ({args.source}). Tentando em 5 s…")
        time.sleep(5)
        cap.release()
        cap, _ = open_source(args.source)

    last_sent: dict[str, float] = {}

    try:
        while True:
            if static_frame is not None:
                frame = static_frame.copy()
            else:
                ret, frame = cap.read()
                if not ret:
                    print("[AVISO] Frame perdido; reconectando em 3 s…")
                    time.sleep(3)
                    cap.release()
                    cap, _ = open_source(args.source)
                    continue

            results = alpr.predict(frame)

            for res in results:
                if res.ocr is None:
                    continue
                plate = re.sub(r"[^A-Z0-9]", "", res.ocr.text.upper())
                conf = res.ocr.confidence
                if isinstance(conf, (list, tuple)):
                    conf = sum(conf) / len(conf) if conf else 0.0

                if conf < args.min_conf:
                    continue
                if not args.any_plate and not BR_PLATE.match(plate):
                    print(f"  lido '{plate}' ({conf:.0%}) — formato não BR, ignorado")
                    continue

                now = time.time()
                if now - last_sent.get(plate, 0) < args.cooldown:
                    continue

                print(f"[{datetime.now():%H:%M:%S}] PLACA {plate} ({conf:.0%})")
                if send_event(args, plate, conf, frame):
                    last_sent[plate] = now

            if args.show:
                for res in results:
                    if res.detection:
                        x1, y1, x2, y2 = map(int, res.detection.bounding_box)
                        cv2.rectangle(frame, (x1, y1), (x2, y2), (0, 255, 0), 2)
                        if res.ocr:
                            cv2.putText(frame, res.ocr.text, (x1, y1 - 8),
                                        cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 255, 0), 2)
                cv2.imshow(f"PortoAccess ALPR — {args.camera}", frame)
                if cv2.waitKey(1) & 0xFF == ord("q"):
                    break

            if args.once:
                if not results:
                    print("Nenhuma placa detectada no frame.")
                break

            time.sleep(args.interval)
    except KeyboardInterrupt:
        print("\nEncerrado.")
    finally:
        if cap is not None:
            cap.release()
        cv2.destroyAllWindows()


if __name__ == "__main__":
    main()
