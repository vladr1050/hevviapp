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
MAP_TILE_SRC = IMG / "carousel-slide-3-map-tile-source.png"
PHONE_SRC = IMG / "carousel-slide-3-phone-source.png"
ACCENT_SRC = IMG / "carousel-slide-3-accent-source.png"

MAP_SRC = IMG / "carousel-slide-3-map-source.png"
FRAME_OUT = IMG / "carousel-slide-3-frame.png"
MAP_OUT = IMG / "carousel-slide-3-map.png"
PHONE_OUT = IMG / "carousel-slide-3-phone.png"
ACCENT_OUT = IMG / "carousel-slide-3-accent.png"
CONTENT_OUT = IMG / "carousel-slide-3-content.png"

CONTENT_W, CONTENT_H = 689, 390
FRAME_W, FRAME_H = 532, 366
FRAME_X, FRAME_Y = 27, 12
MAP_INSET = 10
MAP_W, MAP_H = FRAME_W - MAP_INSET * 2, FRAME_H - MAP_INSET * 2
MAP_X, MAP_Y = FRAME_X + MAP_INSET, FRAME_Y + MAP_INSET
MAP_TILE_W, MAP_TILE_H = MAP_W, MAP_H
MAP_TILE_X, MAP_TILE_Y = 0, 0
PHONE_W, PHONE_H = 210, 390
PHONE_X, PHONE_Y = 479, 0
FRAME_SOURCE_W, FRAME_SOURCE_H = FRAME_W * 2, FRAME_H * 2
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


def crop_content(im: Image.Image) -> Image.Image:
	bbox = im.getbbox()
	if not bbox:
		raise SystemExit("Image is empty after matte removal")
	return im.crop(bbox)


def is_lime_accent(r: int, g: int, b: int, a: int) -> bool:
	if a < 40:
		return False
	if g > 185 and b < 130 and (g - b) > 60 and g >= r:
		return True
	return False


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


def frame_mask() -> Image.Image:
	frame = crop_content(
		strip_light_matte(strip_black_matte(Image.open(FRAME_SRC)))
	).resize((FRAME_W, FRAME_H), Image.Resampling.LANCZOS)
	return frame.split()[3]


def extract_route_and_shadow(art: Image.Image, width: int, height: int) -> Image.Image:
	layer = Image.new("RGBA", (width, height), (0, 0, 0, 0))
	src = art.load()
	out = layer.load()
	for y in range(height):
		for x in range(width):
			r, g, b, a = src[x, y]
			if a < 40 or is_lime_accent(r, g, b, a):
				continue
			if r < 75 and g < 75 and b < 75 and a > 150:
				out[x, y] = (r, g, b, a)
				continue
			if r < 140 and g < 140 and b < 140 and abs(r - g) < 25 and abs(g - b) < 25 and a > 80:
				out[x, y] = (r, g, b, min(a, 220))
	return layer


def extract_route_only(art: Image.Image, width: int, height: int) -> Image.Image:
	layer = Image.new("RGBA", (width, height), (0, 0, 0, 0))
	src = art.load()
	out = layer.load()
	for y in range(height):
		for x in range(width):
			r, g, b, a = src[x, y]
			if a < 40 or is_lime_accent(r, g, b, a):
				continue
			if r < 75 and g < 75 and b < 75 and a > 150:
				out[x, y] = (r, g, b, a)
	return layer


def extract_shadow_only(art: Image.Image, width: int, height: int) -> Image.Image:
	layer = Image.new("RGBA", (width, height), (0, 0, 0, 0))
	src = art.load()
	out = layer.load()
	for y in range(height):
		for x in range(width):
			r, g, b, a = src[x, y]
			if a < 40 or is_lime_accent(r, g, b, a):
				continue
			if r < 140 and g < 140 and b < 140 and abs(r - g) < 25 and abs(g - b) < 25 and a > 80:
				out[x, y] = (r, g, b, min(a, 220))
	return layer


def mockup_art() -> Image.Image:
	mockup = Image.open(MOCKUP_SRC).convert("RGBA")
	return mockup.crop(detect_map_bbox(mockup)).resize((FRAME_W, FRAME_H), Image.Resampling.LANCZOS)


def build_frame_plate(art: Image.Image) -> Image.Image:
	mask = frame_mask()
	plate = Image.new("RGBA", (FRAME_W, FRAME_H), (0, 0, 0, 0))
	plate.paste(Image.new("RGBA", (FRAME_W, FRAME_H), (255, 255, 255, 255)), (0, 0), mask)
	plate.alpha_composite(extract_shadow_only(art, FRAME_W, FRAME_H))
	return plate


def build_map_layer(art: Image.Image) -> Image.Image:
	tile = Image.open(MAP_TILE_SRC).convert("RGBA").resize(
		(MAP_TILE_W, MAP_TILE_H), Image.Resampling.LANCZOS
	)
	layer = Image.new("RGBA", (MAP_W, MAP_H), (0, 0, 0, 0))
	layer.paste(tile, (MAP_TILE_X, MAP_TILE_Y), tile)
	route = extract_route_only(art, FRAME_W, FRAME_H).crop(
		(MAP_INSET, MAP_INSET, FRAME_W - MAP_INSET, FRAME_H - MAP_INSET)
	)
	layer.alpha_composite(route)
	return layer


def build_map_card() -> Image.Image:
	if not MOCKUP_SRC.exists():
		raise SystemExit(f"Missing mockup export: {MOCKUP_SRC}")
	if not FRAME_SRC.exists():
		raise SystemExit(f"Missing frame export: {FRAME_SRC}")
	if not MAP_TILE_SRC.exists():
		raise SystemExit(f"Missing map tile export: {MAP_TILE_SRC}")

	mask = frame_mask()
	art = mockup_art()

	tile = Image.open(MAP_TILE_SRC).convert("RGBA").resize(
		(MAP_W, MAP_H), Image.Resampling.LANCZOS
	)

	card = Image.new("RGBA", (FRAME_W, FRAME_H), (0, 0, 0, 0))
	card.paste(Image.new("RGBA", (FRAME_W, FRAME_H), (255, 255, 255, 255)), (0, 0), mask)
	card.paste(tile, (MAP_INSET, MAP_INSET), tile)
	card.alpha_composite(extract_route_and_shadow(art, FRAME_W, FRAME_H))
	return card


def prepare_layer_exports() -> None:
	art = mockup_art()
	frame = build_frame_plate(art)
	map_layer = build_map_layer(art)
	frame.resize((FRAME_SOURCE_W, FRAME_SOURCE_H), Image.Resampling.LANCZOS).save(FRAME_OUT, optimize=True)
	map_layer.resize((MAP_SOURCE_W, MAP_SOURCE_H), Image.Resampling.LANCZOS).save(MAP_OUT, optimize=True)
	print(f"saved {FRAME_OUT} ({FRAME_SOURCE_W}x{FRAME_SOURCE_H})")
	print(f"saved {MAP_OUT} ({MAP_SOURCE_W}x{MAP_SOURCE_H})")


def prepare_phone() -> None:
	if not PHONE_SRC.exists():
		raise SystemExit(f"Missing phone source: {PHONE_SRC}")

	phone_img = crop_content(strip_black_matte(Image.open(PHONE_SRC)))
	src_w, src_h = phone_img.size
	# Keep export aspect ratio — forcing 189×390 squashes the status card horizontally.
	scaled_w = round(src_w * PHONE_H / src_h)
	phone_img = phone_img.resize((scaled_w, PHONE_H), Image.Resampling.LANCZOS)
	out = phone_img.resize((scaled_w * 2, PHONE_H * 2), Image.Resampling.LANCZOS)
	out.save(PHONE_OUT, optimize=True)
	print(f"saved {PHONE_OUT} ({out.size[0]}x{out.size[1]})")


def prepare_map_source() -> None:
	card = build_map_card()
	out = card.resize((FRAME_SOURCE_W, FRAME_SOURCE_H), Image.Resampling.LANCZOS)
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
	if not MAP_OUT.exists():
		raise SystemExit(f"Missing map layer: {MAP_OUT}")
	if not PHONE_OUT.exists() and not PHONE_SRC.exists():
		raise SystemExit(f"Missing phone source: {PHONE_SRC}")

	map_img = Image.open(MAP_OUT).convert("RGBA").resize((MAP_W, MAP_H), Image.Resampling.LANCZOS)
	phone_full = Image.open(PHONE_OUT).convert("RGBA")
	phone_img = phone_full.resize(
		(phone_full.size[0] // 2, phone_full.size[1] // 2), Image.Resampling.LANCZOS
	)
	phone_x = CONTENT_W - phone_img.size[0]

	composite = Image.new("RGBA", (CONTENT_W, CONTENT_H), (0, 0, 0, 0))
	composite.alpha_composite(map_img, (MAP_X, MAP_Y))
	composite.alpha_composite(phone_img, (phone_x, PHONE_Y))

	out = composite.resize((SCALE_W, SCALE_H), Image.Resampling.LANCZOS)
	out.save(CONTENT_OUT, optimize=True)
	print(f"saved {CONTENT_OUT} ({out.size[0]}x{out.size[1]})")


def main() -> None:
	prepare_map_source()
	prepare_layer_exports()
	prepare_phone()
	prepare_accent()
	build_content()


if __name__ == "__main__":
	main()
