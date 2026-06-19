"""Shared helpers for carousel raster assets (layout stays 1x; bitmaps target 3x DPR)."""

from __future__ import annotations

from PIL import Image

# 3x gives headroom on retina displays and moderate browser zoom without changing CSS sizes.
CAROUSEL_DPR = 3
# When Figma sources are still at 2x, allow a single upscale pass to 2x (never chain resizes).
CAROUSEL_MIN_DPR = 2


def target_size(design_w: float, design_h: float, dpr: int) -> tuple[int, int]:
	return round(design_w * dpr), round(design_h * dpr)


def resize_for_carousel(
	im: Image.Image,
	design_w: float,
	design_h: float,
	*,
	dpr: int = CAROUSEL_DPR,
	min_dpr: int = CAROUSEL_MIN_DPR,
) -> Image.Image:
	"""Prefer downscale to design×dpr; otherwise keep native or do one upscale to min_dpr."""
	target_w, target_h = target_size(design_w, design_h, dpr)
	if im.size[0] >= target_w and im.size[1] >= target_h:
		return im.resize((target_w, target_h), Image.Resampling.LANCZOS)

	min_w, min_h = target_size(design_w, design_h, min_dpr)
	if im.size[0] >= min_w and im.size[1] >= min_h:
		return im

	return im.resize((min_w, min_h), Image.Resampling.LANCZOS)


def save_carousel_png(im: Image.Image, path) -> None:
	im.save(path, optimize=False)
