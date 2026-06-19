#!/usr/bin/env python3
"""Prepare carousel slide 2 card PNG: base card + confirm panel, no green background."""

from __future__ import annotations

from collections import deque
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
IMG = ROOT / "assets/react/islands/pages/Landing/images"
CARD_SRC = IMG / "carousel-slide-2-card-source.png"
CONFIRM_SRC = IMG / "carousel-slide-2-confirm-source.png"
OUT = IMG / "carousel-slide-2-card.png"

CARD_W, CARD_H = 659, 380
CONFIRM_W, CONFIRM_H = 312, 103
CONFIRM_X, CONFIRM_Y = 317, 249
SCALE_W, SCALE_H = CARD_W * 2, CARD_H * 2


def strip_black_matte(im: Image.Image) -> Image.Image:
	rgba = im.convert("RGBA")
	w, h = rgba.size
	pixels = rgba.load()
	seen = [[False] * w for _ in range(h)]

	def is_bg(r: int, g: int, b: int, a: int) -> bool:
		return a > 0 and r < 35 and g < 35 and b < 35

	queue: deque[tuple[int, int]] = deque()
	for x in range(w):
		for y in (0, h - 1):
			if is_bg(*pixels[x, y]):
				seen[y][x] = True
				queue.append((x, y))
	for y in range(h):
		for x in (0, w - 1):
			if not seen[y][x] and is_bg(*pixels[x, y]):
				seen[y][x] = True
				queue.append((x, y))

	while queue:
		x, y = queue.popleft()
		pixels[x, y] = (0, 0, 0, 0)
		for nx, ny in ((x - 1, y), (x + 1, y), (x, y - 1), (x, y + 1)):
			if 0 <= nx < w and 0 <= ny < h and not seen[ny][nx] and is_bg(*pixels[nx, ny]):
				seen[ny][nx] = True
				queue.append((nx, ny))

	return rgba


def crop_content(im: Image.Image) -> Image.Image:
	bbox = im.getbbox()
	if not bbox:
		raise SystemExit("Image is empty after matte removal")
	return im.crop(bbox)


def main() -> None:
	if not CARD_SRC.exists():
		raise SystemExit(f"Missing source export: {CARD_SRC}")
	if not CONFIRM_SRC.exists():
		raise SystemExit(f"Missing confirm export: {CONFIRM_SRC}")

	card = crop_content(strip_black_matte(Image.open(CARD_SRC))).resize(
		(CARD_W, CARD_H), Image.Resampling.LANCZOS
	)
	confirm = crop_content(strip_black_matte(Image.open(CONFIRM_SRC))).resize(
		(CONFIRM_W, CONFIRM_H), Image.Resampling.LANCZOS
	)

	composite = card.copy()
	composite.alpha_composite(confirm, (CONFIRM_X, CONFIRM_Y))

	out = composite.resize((SCALE_W, SCALE_H), Image.Resampling.LANCZOS)
	out.save(OUT, optimize=True)
	print(f"saved {OUT} ({out.size[0]}x{out.size[1]})")


if __name__ == "__main__":
	main()
