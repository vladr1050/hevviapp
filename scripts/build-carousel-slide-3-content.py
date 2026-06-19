#!/usr/bin/env python3
"""Prepare carousel slide 3 assets from latest mockup exports (no baked green)."""

from __future__ import annotations

from collections import deque
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
IMG = ROOT / "assets/react/islands/pages/Landing/images"
MOCKUP_SRC = IMG / "carousel-slide-3-mockup-source.png"
MAP_SRC = IMG / "carousel-slide-3-map-source.png"
PHONE_SRC = IMG / "carousel-slide-3-phone-source.png"
ACCENT_SRC = IMG / "carousel-slide-3-accent-source.png"
ACCENT_OUT = IMG / "carousel-slide-3-accent.png"
CONTENT_OUT = IMG / "carousel-slide-3-content.png"

# Map card crop on 1024×644 mockup (Frame 1933 only — no green, no phone).
MAP_CROP_BOX = (52, 150, 418, 468)

CONTENT_W, CONTENT_H = 689, 390
MAP_W, MAP_H = 532, 366
MAP_X, MAP_Y = 27, 12
PHONE_W, PHONE_H = 189, 390
PHONE_X, PHONE_Y = 500, 0
MAP_SOURCE_W, MAP_SOURCE_H = MAP_W * 2, MAP_H * 2
SCALE_W, SCALE_H = CONTENT_W * 2, CONTENT_H * 2


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


def strip_green_accent(im: Image.Image) -> Image.Image:
	"""Remove Frame 1762 lime fill if it leaked into a crop."""
	rgba = im.convert("RGBA")
	w, h = rgba.size
	pixels = rgba.load()
	seen = [[False] * w for _ in range(h)]

	def is_accent(r: int, g: int, b: int, a: int) -> bool:
		return a > 128 and g > 195 and r > 140 and b < 110 and (g - b) > 70 and abs(g - r) < 90

	queue: deque[tuple[int, int]] = deque()
	for x in range(w):
		for y in range(h):
			if is_accent(*pixels[x, y]) and x < w * 0.35 and y > h * 0.55:
				seen[y][x] = True
				queue.append((x, y))

	while queue:
		x, y = queue.popleft()
		pixels[x, y] = (0, 0, 0, 0)
		for nx, ny in ((x - 1, y), (x + 1, y), (x, y - 1), (x, y + 1)):
			if 0 <= nx < w and 0 <= ny < h and not seen[ny][nx] and is_accent(*pixels[nx, ny]):
				seen[ny][nx] = True
				queue.append((nx, ny))

	return rgba


def crop_content(im: Image.Image) -> Image.Image:
	bbox = im.getbbox()
	if not bbox:
		raise SystemExit("Image is empty after matte removal")
	return im.crop(bbox)


def prepare_map_source() -> None:
	if not MOCKUP_SRC.exists():
		raise SystemExit(f"Missing mockup export: {MOCKUP_SRC}")

	crop = Image.open(MOCKUP_SRC).crop(MAP_CROP_BOX)
	clean = crop_content(strip_green_accent(strip_black_matte(crop)))
	out = clean.resize((MAP_SOURCE_W, MAP_SOURCE_H), Image.Resampling.LANCZOS)
	out.save(MAP_SRC, optimize=True)
	print(f"saved {MAP_SRC} ({out.size[0]}x{out.size[1]})")


def prepare_accent() -> None:
	if not ACCENT_SRC.exists():
		raise SystemExit(f"Missing accent export: {ACCENT_SRC}")

	out = crop_content(strip_black_matte(Image.open(ACCENT_SRC)))
	out.save(ACCENT_OUT, optimize=True)
	print(f"saved {ACCENT_OUT} ({out.size[0]}x{out.size[1]})")


def build_content() -> None:
	if not MAP_SRC.exists():
		raise SystemExit(f"Missing map source: {MAP_SRC}")
	if not PHONE_SRC.exists():
		raise SystemExit(f"Missing phone source: {PHONE_SRC}")

	map_img = crop_content(strip_green_accent(strip_black_matte(Image.open(MAP_SRC)))).resize(
		(MAP_W, MAP_H), Image.Resampling.LANCZOS
	)
	phone_img = crop_content(strip_black_matte(Image.open(PHONE_SRC))).resize(
		(PHONE_W, PHONE_H), Image.Resampling.LANCZOS
	)

	composite = Image.new("RGBA", (CONTENT_W, CONTENT_H), (0, 0, 0, 0))
	composite.alpha_composite(map_img, (MAP_X, MAP_Y))
	composite.alpha_composite(phone_img, (PHONE_X, PHONE_Y))

	out = composite.resize((SCALE_W, SCALE_H), Image.Resampling.LANCZOS)
	out.save(CONTENT_OUT, optimize=True)
	print(f"saved {CONTENT_OUT} ({out.size[0]}x{out.size[1]})")


def main() -> None:
	prepare_map_source()
	prepare_accent()
	build_content()


if __name__ == "__main__":
	main()
