"""
Teste de conexão com câmera IP.

Uso:
    # Câmera HTTP porta 8080 (padrão das câmeras da guarita)
    python test_rtsp.py 192.168.1.20
    python test_rtsp.py 192.168.1.27

    # Câmera RTSP com senha (ex: Yoosee)
    python test_rtsp.py 192.168.1.7 --senha MINHA_SENHA
"""

import argparse
import sys
from urllib.parse import quote

import cv2


def parse_args():
    p = argparse.ArgumentParser()
    p.add_argument("ip", help="IP da câmera (ex: 192.168.1.20)")
    p.add_argument("--senha", default="", help="Senha RTSP do dispositivo")
    return p.parse_args()


def try_url(url: str, label: str) -> bool:
    shown = url
    print(f"  Tentando {label}: {shown} ...", end=" ", flush=True)
    cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
    if cap.isOpened():
        ok, frame = cap.read()
        if ok and frame is not None:
            cv2.imwrite("snapshot.jpg", frame)
            h, w = frame.shape[:2]
            print(f"OK! Resolução {w}x{h} — frame salvo em snapshot.jpg")
            cap.release()
            return True
    print("falhou")
    cap.release()
    return False


def main():
    args = parse_args()
    ip = args.ip
    pw = quote(args.senha, safe="")

    print(f"\nTestando câmera em {ip}...\n")

    # URLs HTTP (porta 8080 — câmeras sem autenticação ou com stream aberto)
    http_candidates = [
        (f"http://{ip}:8080/video",             "HTTP MJPEG /video"),
        (f"http://{ip}:8080/?action=stream",    "HTTP MJPEG ?action=stream"),
        (f"http://{ip}:8080/mjpeg",             "HTTP MJPEG /mjpeg"),
        (f"http://{ip}:8080/videostream.cgi",   "HTTP CGI /videostream.cgi"),
        (f"http://{ip}:8080/stream",            "HTTP /stream"),
    ]

    # URLs RTSP padrão (porta 554)
    rtsp_base = [
        (f"rtsp://{ip}:554/onvif1",                          "RTSP ONVIF stream1"),
        (f"rtsp://{ip}:554/onvif2",                          "RTSP ONVIF stream2"),
        (f"rtsp://{ip}:554/live/ch00_0",                     "RTSP /live/ch00_0"),
        (f"rtsp://{ip}:554/stream1",                         "RTSP /stream1"),
    ]
    if pw:
        rtsp_auth = [
            (f"rtsp://admin:{pw}@{ip}:554/onvif1",           "RTSP admin /onvif1"),
            (f"rtsp://admin:{pw}@{ip}:554/onvif2",           "RTSP admin /onvif2"),
            (f"rtsp://admin:{pw}@{ip}:554/live/ch00_0",      "RTSP admin /live/ch00_0"),
            (f"rtsp://admin:{pw}@{ip}:554/stream1",          "RTSP admin /stream1"),
        ]
    else:
        rtsp_auth = []

    all_candidates = http_candidates + rtsp_base + rtsp_auth

    for url, label in all_candidates:
        if try_url(url, label):
            print(f"\n=== URL para usar no .env: {url} ===\n")
            sys.exit(0)

    print("\nNenhuma URL conectou. Verifique:")
    print("  - A câmera está ligada e acessível nessa rede?")
    print("    ping", ip)
    print("  - A porta 8080 está aberta?")
    print("    Test-NetConnection", ip, "-Port 8080")
    print("  - Se for câmera RTSP com senha, passe --senha MINHA_SENHA")
    sys.exit(1)


if __name__ == "__main__":
    main()
