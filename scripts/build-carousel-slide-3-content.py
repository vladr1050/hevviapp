#!/usr/bin/env python3
"""Prepare carousel slide 3 content PNG: map + status phone, no green background."""

from __future__ import annotations

from collections import deque
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
IMG = ROOT / "assets/react/islands/pages/Landing/images"
MAP_SRC = IMG / "carousel-slide-3-map-source.png"
PHONE_SRC = IMG / "carousel-slide-3-phone-source.png"
OUT = IMG / "carousel-slide-3-content.png"

CONTENT_W, CONTENT_H = 689, 390
MAP_W, MAP_H = 532, 366
MAP_X, MAP_Y = 27, 12
PHONE_W, PHONE_H = 189, 390
PHONE_X, PHONE_Y = 500, 0
SCALE_W, SCALE_H = CONTENT_W * 2, CONTENT_H * 2


def strip_matte(im: Image.Image) -> Image.Image:
	rgba = im.convert("RGBA")
	w, h = rgba.size
	pixels = rgba.load()
	seen = [[False] * w for _ in range(h)]

	def is_bg(r: int, g: int, b: int, a: int) -> bool:
		if a == 0:
			return False
		if r < 35 and g < 35 and b < 35:
			return True
		return r > 215 and g > 215 and b > 215 and max(r, g, b) - min(r, g, b) < 12

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
	if not MAP_SRC.exists():
		raise SystemExit(f"Missing map source: {MAP_SRC}")
	if not PHONE_SRC.exists():
		raise SystemExit(f"Missing phone source: {PHONE_SRC}")

	map_img = crop_content(strip_matte(Image.open(MAP_SRC))).resize(
		(MAP_W, MAP_H), Image.Resampling.LANCZOS
	)
	phone_img = crop_content(strip_matte(Image.open(PHONE_SRC))).resize(
		(PHONE_W, PHONE_H), Image.Resampling.LANCZOS
	)

	composite = Image.new("RGBA", (CONTENT_W, CONTENT_H), (0, 0, 0, 0))
	composite.alpha_composite(map_img, (MAP_X, MAP_Y))
	composite.alpha_composite(phone_img, (PHONE_X, PHONE_Y))

	out = composite.resize((SCALE_W, SCALE_H), Image.Resampling.LANCZOS)
	out.save(OUT, optimize=True)
	print(f"saved {OUT} ({out.size[0]}x{out.size[1]})")


if __name__ == "__main__":
	main()
