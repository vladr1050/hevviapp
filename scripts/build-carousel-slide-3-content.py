#!/usr/bin/env python3
"""Build carousel slide 3: green accent + map/phone content (no green in content)."""

from __future__ import annotations

from collections import deque
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
IMG = ROOT / "assets/react/islands/pages/Landing/images"

MOCKUP_SRC = IMG / "carousel-slide-3-mockup-source.png"
FRAME_SRC = IMG / "carousel-slide-3-frame-source.png"
PHONE_SRC = IMG / "carousel-slide-3-phone-source.png"
ACCENT_SRC = IMG / "carousel-slide-3-accent-source.png"

MAP_SRC = IMG / "carousel-slide-3-map-source.png"
ACCENT_OUT = IMG / "carousel-slide-3-accent.png"
CONTENT_OUT = IMG / "carousel-slide-3-content.png"

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


def strip_light_matte(im: Image.Image) -> Image.Image:
	rgba = im.convert("RGBA")
	w, h = rgba.size
	pixels = rgba.load()
	seen = [[False] * w for _ in range(h)]

	def is_bg(r: int, g: int, b: int, a: int) -> bool:
		if a == 0:
			return False
		if r > 232 and g > 232 and b > 232 and max(r, g, b) - min(r, g, b) < 10:
			return True
		return False

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
	rgba = im.convert("RGBA")
	w, h = rgba.size
	pixels = rgba.load()
	seen = [[False] * w for _ in range(h)]

	def is_accent(r: int, g: int, b: int, a: int) -> bool:
		return a > 128 and g > 195 and r > 140 and b < 110 and (g - b) > 70 and abs(g - r) < 90

	queue: deque[tuple[int, int]] = deque()
	for x in range(w):
		for y in range(h):
			if is_accent(*pixels[x, y]) and x < w * 0.4 and y > h * 0.5:
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


def detect_map_bbox(mockup: Image.Image) -> tuple[int, int, int, int]:
	w, h = mockup.size
	pixels = mockup.load()
	minx, miny, maxx, maxy = w, h, 0, 0
	for y in range(int(h * 0.22), int(h * 0.78)):
		for x in range(int(w * 0.04), int(w * 0.44)):
			r, g, b, a = pixels[x, y]
			if a < 200:
				continue
			if r > 238 and g > 238 and b > 238:
				continue
			if g > 200 and r > 150 and b < 100 and g > r + 15:
				continue
			minx = min(minx, x)
			miny = min(miny, y)
			maxx = max(maxx, x)
			maxy = max(maxy, y)
	pad = 2
	return minx - pad, miny - pad, maxx + 1 + pad, maxy + 1 + pad


def build_map_card() -> Image.Image:
	"""White frame mask + mockup art (route/pins) — no green accent, clean edges."""
	if not MOCKUP_SRC.exists():
		raise SystemExit(f"Missing mockup export: {MOCKUP_SRC}")
	if not FRAME_SRC.exists():
		raise SystemExit(f"Missing frame export: {FRAME_SRC}")

	frame = crop_content(
		strip_light_matte(strip_black_matte(Image.open(FRAME_SRC)))
	).resize((MAP_W, MAP_H), Image.Resampling.LANCZOS)

	mockup = Image.open(MOCKUP_SRC).convert("RGBA")
	art = mockup.crop(detect_map_bbox(mockup))
	art = strip_green_accent(art).resize((MAP_W, MAP_H), Image.Resampling.LANCZOS)

	mask = frame.split()[3]
	card = Image.new("RGBA", (MAP_W, MAP_H), (0, 0, 0, 0))
	card.paste(art, (0, 0), mask)
	return card


def prepare_map_source() -> None:
	card = build_map_card()
	out = card.resize((MAP_SOURCE_W, MAP_SOURCE_H), Image.Resampling.LANCZOS)
	out.save(MAP_SRC, optimize=True)
	print(f"saved {MAP_SRC} ({out.size[0]}x{out.size[1]})")


def prepare_accent() -> None:
	if not ACCENT_SRC.exists():
		raise SystemExit(f"Missing accent export: {ACCENT_SRC}")

	src = strip_black_matte(Image.open(ACCENT_SRC))
	out = src.resize((399 * 2, 228 * 2), Image.Resampling.LANCZOS) if src.size != (798, 456) else src
	out.save(ACCENT_OUT, optimize=True)
	print(f"saved {ACCENT_OUT} ({out.size[0]}x{out.size[1]})")


def build_content() -> None:
	if not MAP_SRC.exists():
		raise SystemExit(f"Missing map source: {MAP_SRC}")
	if not PHONE_SRC.exists():
		raise SystemExit(f"Missing phone source: {PHONE_SRC}")

	# Map already has clean alpha — do not strip black matte (destroys card shadow).
	map_img = Image.open(MAP_SRC).convert("RGBA").resize((MAP_W, MAP_H), Image.Resampling.LANCZOS)
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
