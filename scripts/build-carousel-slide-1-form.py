#!/usr/bin/env python3
"""Prepare carousel slide 1 form PNG: transparent matte only, no green background."""

from __future__ import annotations

from collections import deque
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "assets/react/islands/pages/Landing/images/carousel-slide-1-form-source.png"
OUT = ROOT / "assets/react/islands/pages/Landing/images/carousel-slide-1-form.png"
SCALE_W, SCALE_H = round(661.5 * 2), round(380.4 * 2)


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


def main() -> None:
	if not SRC.exists():
		raise SystemExit(f"Missing source export: {SRC}")

	im = strip_black_matte(Image.open(SRC))
	bbox = im.getbbox()
	if not bbox:
		raise SystemExit("Form export is empty after matte removal")

	cropped = im.crop(bbox)
	out = cropped.resize((SCALE_W, SCALE_H), Image.Resampling.LANCZOS)
	out.save(OUT, optimize=True)
	print(f"saved {OUT} ({out.size[0]}x{out.size[1]})")


if __name__ == "__main__":
	main()
