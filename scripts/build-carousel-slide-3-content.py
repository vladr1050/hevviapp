#!/usr/bin/env python3
"""Build carousel slide 3: green accent + map/phone content (no green in content)."""

from __future__ import annotations

import sys
from collections import deque
from pathlib import Path

from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parent))
from carousel_image_utils import CAROUSEL_DPR, resize_for_carousel, save_carousel_png

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
PHONE_ABOVE_FRAME = 12  # Frame 552 is 12px above Frame 1933, 22px above map tile
PHONE_Y = FRAME_Y - PHONE_ABOVE_FRAME
PHONE_H = FRAME_H + PHONE_ABOVE_FRAME * 2
PHONE_W = 210
PHONE_X = CONTENT_W - PHONE_W
FRAME_SOURCE_W, FRAME_SOURCE_H = round(FRAME_W * CAROUSEL_DPR), round(FRAME_H * CAROUSEL_DPR)
MAP_SOURCE_W, MAP_SOURCE_H = round(MAP_W * CAROUSEL_DPR), round(MAP_H * CAROUSEL_DPR)
SCALE_W, SCALE_H = round(CONTENT_W * CAROUSEL_DPR), round(CONTENT_H * CAROUSEL_DPR)


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


def frame_mask(width: int, height: int) -> Image.Image:
	frame = crop_content(
		strip_light_matte(strip_black_matte(Image.open(FRAME_SRC)))
	).resize((width, height), Image.Resampling.LANCZOS)
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
	cropped = mockup.crop(detect_map_bbox(mockup))
	return resize_for_carousel(cropped, FRAME_W, FRAME_H)


def build_frame_plate(art: Image.Image) -> Image.Image:
	width, height = art.size
	mask = frame_mask(width, height)
	plate = Image.new("RGBA", (width, height), (0, 0, 0, 0))
	plate.paste(Image.new("RGBA", (width, height), (255, 255, 255, 255)), (0, 0), mask)
	plate.alpha_composite(extract_shadow_only(art, width, height))
	return plate


def build_map_layer(art: Image.Image) -> Image.Image:
	width, height = art.size
	inset = round(MAP_INSET * width / FRAME_W)
	map_w, map_h = width - inset * 2, height - inset * 2
	tile = resize_for_carousel(
		Image.open(MAP_TILE_SRC).convert("RGBA"),
		MAP_W,
		MAP_H,
	).resize((map_w, map_h), Image.Resampling.LANCZOS)
	layer = Image.new("RGBA", (map_w, map_h), (0, 0, 0, 0))
	layer.paste(tile, (0, 0), tile)
	route = extract_route_only(art, width, height).crop((inset, inset, width - inset, height - inset))
	layer.alpha_composite(route)
	return layer


def build_map_card() -> Image.Image:
	if not MOCKUP_SRC.exists():
		raise SystemExit(f"Missing mockup export: {MOCKUP_SRC}")
	if not FRAME_SRC.exists():
		raise SystemExit(f"Missing frame export: {FRAME_SRC}")
	if not MAP_TILE_SRC.exists():
		raise SystemExit(f"Missing map tile export: {MAP_TILE_SRC}")

	art = mockup_art()
	width, height = art.size
	inset = round(MAP_INSET * width / FRAME_W)
	map_w, map_h = width - inset * 2, height - inset * 2
	mask = frame_mask(width, height)

	tile = resize_for_carousel(
		Image.open(MAP_TILE_SRC).convert("RGBA"),
		MAP_W,
		MAP_H,
	).resize((map_w, map_h), Image.Resampling.LANCZOS)

	card = Image.new("RGBA", (width, height), (0, 0, 0, 0))
	card.paste(Image.new("RGBA", (width, height), (255, 255, 255, 255)), (0, 0), mask)
	card.paste(tile, (inset, inset), tile)
	card.alpha_composite(extract_route_and_shadow(art, width, height))
	return card


def prepare_layer_exports() -> None:
	art = mockup_art()
	frame = build_frame_plate(art)
	map_layer = build_map_layer(art)
	save_carousel_png(frame, FRAME_OUT)
	save_carousel_png(map_layer, MAP_OUT)
	print(f"saved {FRAME_OUT} ({frame.size[0]}x{frame.size[1]})")
	print(f"saved {MAP_OUT} ({map_layer.size[0]}x{map_layer.size[1]})")


def prepare_phone() -> None:
	if not PHONE_SRC.exists():
		raise SystemExit(f"Missing phone source: {PHONE_SRC}")

	phone_img = crop_content(strip_black_matte(Image.open(PHONE_SRC)))
	src_w, src_h = phone_img.size
	phone_w = round(src_w * PHONE_H / src_h)
	out = resize_for_carousel(phone_img, phone_w, PHONE_H)
	save_carousel_png(out, PHONE_OUT)
	print(f"saved {PHONE_OUT} ({out.size[0]}x{out.size[1]})")


def prepare_map_source() -> None:
	card = build_map_card()
	save_carousel_png(card, MAP_SRC)
	print(f"saved {MAP_SRC} ({card.size[0]}x{card.size[1]})")


def prepare_accent() -> None:
	if not ACCENT_SRC.exists():
		raise SystemExit(f"Missing accent export: {ACCENT_SRC}")

	src = strip_black_matte(Image.open(ACCENT_SRC))
	out = resize_for_carousel(src, 399, 228)
	save_carousel_png(out, ACCENT_OUT)
	print(f"saved {ACCENT_OUT} ({out.size[0]}x{out.size[1]})")


def build_content() -> None:
	if not MAP_OUT.exists():
		raise SystemExit(f"Missing map layer: {MAP_OUT}")
	if not PHONE_OUT.exists() and not PHONE_SRC.exists():
		raise SystemExit(f"Missing phone source: {PHONE_SRC}")

	map_img = Image.open(MAP_OUT).convert("RGBA").resize((MAP_W, MAP_H), Image.Resampling.LANCZOS)
	phone_full = Image.open(PHONE_OUT).convert("RGBA")
	phone_img = phone_full.resize(
		(round(phone_full.size[0] / CAROUSEL_DPR), round(phone_full.size[1] / CAROUSEL_DPR)),
		Image.Resampling.LANCZOS,
	)
	phone_x = CONTENT_W - phone_img.size[0]

	composite = Image.new("RGBA", (CONTENT_W, CONTENT_H), (0, 0, 0, 0))
	composite.alpha_composite(map_img, (MAP_X, MAP_Y))
	composite.alpha_composite(phone_img, (phone_x, PHONE_Y))

	out = composite.resize((SCALE_W, SCALE_H), Image.Resampling.LANCZOS)
	save_carousel_png(out, CONTENT_OUT)
	print(f"saved {CONTENT_OUT} ({out.size[0]}x{out.size[1]})")


def main() -> None:
	prepare_map_source()
	prepare_layer_exports()
	prepare_phone()
	prepare_accent()
	build_content()


if __name__ == "__main__":
	main()
