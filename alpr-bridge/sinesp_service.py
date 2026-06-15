"""
Sidecar de consulta Sinesp Cidadao (lib NAO oficial).

O Laravel chama este servico em http://127.0.0.1:8077/consulta?placa=ABC1D23
e recebe um JSON normalizado com a situacao do veiculo (inclui roubo/furto).

Modos (env SINESP_MODE):
  - mock  (padrao): retorna dados ficticios deterministicos, util para
                    demonstrar o fluxo de ponta a ponta sem depender do Sinesp.
  - real           : usa a lib nao oficial (ver consultar_sinesp_real()).

IMPORTANTE: as libs nao oficiais do Sinesp quebram com frequencia (reCAPTCHA /
anti-bot). Por isso o servico SEMPRE responde algo: em caso de falha devolve
situacao="indisponivel" e o Laravel degrada graciosamente (a guarita nao trava).

Sem dependencias externas — roda com a stdlib do Python 3.
Subir:  python sinesp_service.py
"""

import json
import os
import re
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlparse, parse_qs

PORT = int(os.environ.get("SINESP_PORT", "8077"))
MODE = os.environ.get("SINESP_MODE", "mock").lower()
TOKEN = os.environ.get("SINESP_TOKEN", "")  # se definido, exige header X-Sinesp-Token


def normalize_plate(placa: str) -> str:
    return re.sub(r"[^A-Za-z0-9]", "", placa or "").upper()


def agora_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def indisponivel(placa: str, mensagem: str) -> dict:
    return {
        "disponivel": False,
        "situacao": "indisponivel",
        "roubo_furto": False,
        "mensagem": mensagem,
        "placa": placa,
        "marca": None, "modelo": None, "cor": None,
        "ano": None, "ano_modelo": None, "uf": None, "municipio": None,
        "consultado_em": agora_iso(),
    }


# --------------------------------------------------------------------------- #
# MOCK — dados ficticios para demonstrar o fluxo                              #
# --------------------------------------------------------------------------- #
_MOCK_FROTA = {
    "ABC1D23": {"situacao": "regular", "marca": "VW", "modelo": "GOL 1.6", "cor": "PRATA",
                "ano": "2019", "ano_modelo": "2020", "uf": "AM", "municipio": "MANAUS"},
    "RES1A11": {"situacao": "restricao", "marca": "FIAT", "modelo": "TORO", "cor": "BRANCA",
                "ano": "2021", "ano_modelo": "2021", "uf": "AM", "municipio": "MANAUS",
                "mensagem": "Restricao administrativa / financeira."},
    "ROU0B00": {"situacao": "roubo_furto", "marca": "HONDA", "modelo": "CIVIC", "cor": "PRETA",
                "ano": "2018", "ano_modelo": "2018", "uf": "SP", "municipio": "SAO PAULO",
                "mensagem": "Veiculo com registro de ROUBO/FURTO."},
}


def consultar_sinesp_mock(placa: str) -> dict:
    dados = _MOCK_FROTA.get(placa)
    if not dados:
        # Heuristica deterministica: placas terminadas em 7,8,9 -> alerta de roubo (demo)
        if placa and placa[-1] in "789":
            return {
                "disponivel": True, "situacao": "roubo_furto", "roubo_furto": True,
                "mensagem": "Veiculo com registro de ROUBO/FURTO (mock).", "placa": placa,
                "marca": "GM", "modelo": "ONIX", "cor": "VERMELHA",
                "ano": "2017", "ano_modelo": "2018", "uf": "AM", "municipio": "MANAUS",
                "consultado_em": agora_iso(),
            }
        return {
            "disponivel": True, "situacao": "nao_encontrado", "roubo_furto": False,
            "mensagem": "Placa nao encontrada na base (mock).", "placa": placa,
            "marca": None, "modelo": None, "cor": None,
            "ano": None, "ano_modelo": None, "uf": None, "municipio": None,
            "consultado_em": agora_iso(),
        }

    situacao = dados["situacao"]
    return {
        "disponivel": True,
        "situacao": situacao,
        "roubo_furto": situacao == "roubo_furto",
        "mensagem": dados.get("mensagem", "Situacao regular (mock)."),
        "placa": placa,
        "marca": dados.get("marca"), "modelo": dados.get("modelo"), "cor": dados.get("cor"),
        "ano": dados.get("ano"), "ano_modelo": dados.get("ano_modelo"),
        "uf": dados.get("uf"), "municipio": dados.get("municipio"),
        "consultado_em": agora_iso(),
    }


# --------------------------------------------------------------------------- #
# REAL — ponto de plugue da lib NAO oficial                                   #
# --------------------------------------------------------------------------- #
def consultar_sinesp_real(placa: str) -> dict:
    """
    >>> PLUGUE AQUI A LIB NAO OFICIAL DO SINESP <<<

    Exemplo (depende da lib escolhida e de ela estar operante):

        from sinespy import sinespy            # pip install sinespy
        bruto = sinespy(placa)                 # dict cru do Sinesp
        situacao_bruta = (bruto.get("situacao") or "").lower()
        roubo = "roubo" in situacao_bruta or "furto" in situacao_bruta
        return {
            "disponivel": True,
            "situacao": "roubo_furto" if roubo else "regular",
            "roubo_furto": roubo,
            "mensagem": bruto.get("situacao") or "",
            "placa": placa,
            "marca": bruto.get("marca"), "modelo": bruto.get("modelo"),
            "cor": bruto.get("cor"), "ano": bruto.get("ano"),
            "ano_modelo": bruto.get("anoModelo"),
            "uf": bruto.get("uf"), "municipio": bruto.get("municipio"),
            "consultado_em": agora_iso(),
        }

    Enquanto nao houver lib operante, devolvemos "indisponivel" para o Laravel
    degradar com seguranca.
    """
    return indisponivel(placa, "Provedor real do Sinesp ainda nao configurado.")


def consultar(placa: str) -> dict:
    placa = normalize_plate(placa)
    if len(placa) < 7:
        return indisponivel(placa, "Placa invalida.")
    try:
        if MODE == "real":
            return consultar_sinesp_real(placa)
        return consultar_sinesp_mock(placa)
    except Exception as exc:  # noqa: BLE001 — nunca deixar o sidecar derrubar a guarita
        return indisponivel(placa, f"Falha na consulta: {exc}")


class Handler(BaseHTTPRequestHandler):
    def _send(self, status: int, payload: dict):
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):  # noqa: N802 (assinatura do BaseHTTPRequestHandler)
        parsed = urlparse(self.path)

        if parsed.path == "/health":
            return self._send(200, {"ok": True, "mode": MODE})

        if parsed.path != "/consulta":
            return self._send(404, {"erro": "rota nao encontrada"})

        if TOKEN and self.headers.get("X-Sinesp-Token", "") != TOKEN:
            return self._send(401, {"erro": "token invalido"})

        placa = (parse_qs(parsed.query).get("placa", [""]) or [""])[0]
        return self._send(200, consultar(placa))

    def log_message(self, *args):  # silencia log padrao ruidoso
        return


if __name__ == "__main__":
    print(f"[sinesp] sidecar ouvindo em http://127.0.0.1:{PORT}  (modo={MODE})")
    ThreadingHTTPServer(("127.0.0.1", PORT), Handler).serve_forever()
