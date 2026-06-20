#!/usr/bin/env bash
# Export landing PNG assets from Figma (Hevvi Design → MacBook Air - 15, node 1611:18372).
#
# Usage:
#   FIGMA_TOKEN=figd_... ./scripts/export-figma-landing.sh
#
# File key: H7HtqYOoxZetHunb8PQiHN
# Dev link (hero): https://www.figma.com/design/H7HtqYOoxZetHunb8PQiHN/Hevvi-Design?node-id=1611-18583&m=dev
#
# Node map (MacBook Air - 17 hero / MacBook Air - 15 rest):
#   logo                    1611:18683  Group 1119
#   login-icon              1611:18678  Frame 2240
#   hero-map-content          1611:18638  Group 1163 — map screenshot
#   hero-map-gradient         1611:18643  Rectangle 171 — fade overlay
#   hero-cargo                1611:18644  Group 1082 — box + pallet
#   hero-delivery-panel       1611:18653  Group 1165 — delivery card
#   hero-visual               1611:18635  Group 1185 — full composite fallback
#   hero-features           1611:18585  Frame 2085 (full row)
#   registration-trucks     1611:18501  Banner
#   logo-footer             1611:18490  Group 633216
#   carousel-slide-1-visual       1616:2491  01- desktop (carousel 1616:2490, export at scale=4)
#   carousel-slide-2-visual       1616:2417  02- desktop (carousel 1616:2416, export at scale=4)
#   carousel-slide-3-map-source   1611:18964  Group 1166 (carousel 1611:18949)
#   carousel-slide-3-phone-source 1611:18979  Frame 552
#   carousel-slide-3-accent-source 1611:18950  Frame 1762
#   carousel-arrow          1611:18574  fi_3114931
#   landing-mobile          1611:19016  iPhone 13 & 14 - 1
#
# Carousel sources target 3× DPR (see scripts/carousel_image_utils.py). Override: FIGMA_EXPORT_SCALE=2
set -euo pipefail

TOKEN="${FIGMA_TOKEN:?Set FIGMA_TOKEN}"
FIGMA_EXPORT_SCALE="${FIGMA_EXPORT_SCALE:-3}"
FILE_KEY="H7HtqYOoxZetHunb8PQiHN"
OUT_DIR="$(cd "$(dirname "$0")/.." && pwd)/assets/react/islands/pages/Landing/images"
mkdir -p "$OUT_DIR"

declare -A NODES=(
  [logo]="1611:18683"
  [login-icon]="1611:18678"
  [hero-visual]="1611:18635"
  [hero-map-content]="1611:18638"
  [hero-map-gradient]="1611:18643"
  [hero-cargo]="1611:18644"
  [hero-delivery-panel]="1611:18653"
  [hero-features]="1611:18585"
  [registration-trucks]="1611:18501"
  [logo-footer]="1611:18490"
  [carousel-slide-1-visual]="1616:2491"
  [carousel-slide-2-visual]="1616:2417"
  [carousel-slide-3-map-source]="1611:18964"
  [carousel-slide-3-phone-source]="1611:18979"
  [carousel-slide-3-accent-source]="1611:18950"
  [carousel-arrow]="1611:18574"
  [landing-mobile]="1611:19016"
)

IDS=""
for id in "${NODES[@]}"; do
  enc="${id//:/%3A}"
  [[ -n "$IDS" ]] && IDS+=","
  IDS+="$enc"
done

echo "Requesting render URLs from Figma (scale=${FIGMA_EXPORT_SCALE})…"
sleep 2
RESP=$(curl -sS -H "X-Figma-Token: $TOKEN" \
  "https://api.figma.com/v1/images/${FILE_KEY}?ids=${IDS}&format=png&scale=${FIGMA_EXPORT_SCALE}")

python3 - "$OUT_DIR" "$RESP" <<'PY'
import json, subprocess, sys, os

out_dir = sys.argv[1]
resp = json.loads(sys.argv[2])
if resp.get("err"):
    raise SystemExit(resp)
images = resp.get("images", {})
names = {
    "1611:18683": "logo",
    "1611:18678": "login-icon",
    "1611:18635": "hero-visual",
    "1611:18638": "hero-map-content",
    "1611:18643": "hero-map-gradient",
    "1611:18644": "hero-cargo",
    "1611:18653": "hero-delivery-panel",
    "1611:18585": "hero-features",
    "1611:18501": "registration-trucks",
    "1611:18490": "logo-footer",
    "1616:2491": "carousel-slide-1-visual",
    "1616:2417": "carousel-slide-2-visual",
    "1611:18964": "carousel-slide-3-map-source",
    "1611:18979": "carousel-slide-3-phone-source",
    "1611:18950": "carousel-slide-3-accent-source",
    "1611:18574": "carousel-arrow",
    "1611:19016": "landing-mobile",
}
for nid, name in names.items():
    url = images.get(nid)
    if not url:
        print(f"MISSING {name} ({nid})", file=sys.stderr)
        continue
    path = os.path.join(out_dir, f"{name}.png")
    subprocess.run(["curl", "-sS", "-o", path, url], check=True)
    print(f"saved {path} ({os.path.getsize(path)} bytes)")
PY

echo "Done."
