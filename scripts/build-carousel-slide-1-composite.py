#!/usr/bin/env python3
"""Bake carousel slide 1 into a single PNG (accent + form + cursor)."""

from __future__ import annotations

from collections import deque
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
IMG = ROOT / "assets/react/islands/pages/Landing/images"
SCALE = 2
STAGE_W, STAGE_H = 687.5, 409
PAGE_BG = (243, 243, 243, 255)


def strip_matte(path: Path, bg: tuple[int, int, int] = (239, 239, 239), tol: int = 12) -> Image.Image:
	im = Image.open(path).convert("RGBA")
	w, h = im.size
	pixels = im.load()
	seen = [[False] * w for _ in range(h)]

	def is_bg(r: int, g: int, b: int, a: int) -> bool:
		return a > 0 and abs(r - bg[0]) <= tol and abs(g - bg[1]) <= tol and abs(b - bg[2]) <= tol

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
		pixels[x, y] = (pixels[x, y][0], pixels[x, y][1], pixels[x, y][2], 0)
		for nx, ny in ((x - 1, y), (x + 1, y), (x, y - 1), (x, y + 1)):
			if 0 <= nx < w and 0 <= ny < h and not seen[ny][nx] and is_bg(*pixels[nx, ny]):
				seen[ny][nx] = True
				queue.append((nx, ny))

	return im


def place(canvas: Image.Image, layer: Image.Image, x: float, y: float, w: float, h: float) -> None:
	resized = layer.resize((round(w * SCALE), round(h * SCALE)), Image.Resampling.LANCZOS)
	canvas.alpha_composite(resized, (round(x * SCALE), round(y * SCALE)))


def main() -> None:
	canvas = Image.new("RGBA", (round(STAGE_W * SCALE), round(STAGE_H * SCALE)), PAGE_BG)
	accent = Image.open(IMG / "carousel-slide-1-accent.png").convert("RGBA")
	form = strip_matte(IMG / "carousel-slide-1-visual.png")
	cursor = Image.open(IMG / "carousel-slide-1-cursor.png").convert("RGBA")

	place(canvas, accent, 0, 119, 507, 290)
	place(canvas, form, 26, 0, 661.5, 380.4)
	place(canvas, cursor, 470, 332, 19.5, 31.2)

	out = IMG / "carousel-slide-1-composite.png"
	canvas.save(out, optimize=True)
	print(f"saved {out} ({canvas.size[0]}x{canvas.size[1]})")


if __name__ == "__main__":
	main()
