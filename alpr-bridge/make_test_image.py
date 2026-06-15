"""Gera uma imagem de teste: 'carro' visto de trás com placa Mercosul BRA2E19."""

import cv2
import numpy as np

img = np.full((720, 1280, 3), (70, 75, 80), np.uint8)  # fundo asfalto

# Traseira do carro
cv2.rectangle(img, (340, 120), (940, 640), (40, 30, 140), -1)   # lataria vermelho escuro (BGR)
cv2.rectangle(img, (380, 160), (900, 320), (60, 60, 60), -1)    # vidro
cv2.ellipse(img, (430, 600), (60, 28), 0, 0, 360, (25, 25, 25), -1)  # roda
cv2.ellipse(img, (850, 600), (60, 28), 0, 0, 360, (25, 25, 25), -1)

# Placa Mercosul (proporção 400x130)
px, py, pw, ph = 440, 430, 400, 130
cv2.rectangle(img, (px, py), (px + pw, py + ph), (255, 255, 255), -1)
cv2.rectangle(img, (px, py), (px + pw, py + 36), (180, 90, 0), -1)        # faixa azul
cv2.putText(img, "BRASIL", (px + 150, py + 28), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (255, 255, 255), 2)
cv2.putText(img, "BRA2E19", (px + 28, py + 110), cv2.FONT_HERSHEY_DUPLEX, 2.6, (10, 10, 10), 8)
cv2.rectangle(img, (px, py), (px + pw, py + ph), (0, 0, 0), 3)

cv2.imwrite("teste-placa.jpg", img)
print("teste-placa.jpg gerado (placa BRA2E19)")
