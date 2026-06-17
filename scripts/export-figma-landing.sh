#!/usr/bin/env bash
# Export landing PNG assets from Figma (Hevvi Design → MacBook Air - 15, node 1611:18372).
#
# Usage:
#   FIGMA_TOKEN=figd_... ./scripts/export-figma-landing.sh
#
# File key: H7HtqYOoxZetHunb8PQiHN
# Dev link:  https://www.figma.com/design/H7HtqYOoxZetHunb8PQiHN/Hevvi-Design?node-id=1611-18372&m=dev
#
# Node map (MacBook Air - 15 / carousel frames):
#   logo                    1611:18471  Group 1119
#   hero-visual             1611:18430  Group 1185
#   hero-features           1611:18380  Frame 2085
#   registration-trucks     1611:18501  Banner
#   logo-footer             1611:18490  Group 633216
#   carousel-slide-1        1611:18525  Frame 1859 — form
#   carousel-slide-1-accent 1611:18373 Frame 1762 — green accent
#   carousel-slide-1-cursor 1611:18576 Group 1177
#   carousel-slide-2        1611:18817  Group 1063 — price card (carousel 1611:18815)
#   carousel-slide-2-accent 1611:18816  Frame 1762
#   carousel-slide-2-cursor 1611:18944  Group 633214
#   carousel-slide-3-map    1611:18964  Group 1166 (carousel 1611:18949)
#   carousel-slide-3-phone  1611:18979  Frame 552
#   carousel-slide-3-accent 1611:18950  Frame 1762
#   carousel-arrow          1611:18574  fi_3114931
#   landing-mobile          1611:19016  iPhone 13 & 14 - 1
set -euo pipefail

TOKEN="${FIGMA_TOKEN:?Set FIGMA_TOKEN}"
FILE_KEY="H7HtqYOoxZetHunb8PQiHN"
OUT_DIR="$(cd "$(dirname "$0")/.." && pwd)/assets/react/islands/pages/Landing/images"
mkdir -p "$OUT_DIR"

declare -A NODES=(
  [logo]="1611:18471"
  [hero-visual]="1611:18430"
  [hero-features]="1611:18380"
  [registration-trucks]="1611:18501"
  [logo-footer]="1611:18490"
  [carousel-slide-1]="1611:18525"
  [carousel-slide-1-accent]="1611:18373"
  [carousel-slide-1-cursor]="1611:18576"
  [carousel-slide-2]="1611:18817"
  [carousel-slide-2-accent]="1611:18816"
  [carousel-slide-2-cursor]="1611:18944"
  [carousel-slide-3-map]="1611:18964"
  [carousel-slide-3-phone]="1611:18979"
  [carousel-slide-3-accent]="1611:18950"
  [carousel-arrow]="1611:18574"
  [landing-mobile]="1611:19016"
)

IDS=""
for id in "${NODES[@]}"; do
  enc="${id//:/%3A}"
  [[ -n "$IDS" ]] && IDS+=","
  IDS+="$enc"
done

echo "Requesting render URLs from Figma…"
sleep 2
RESP=$(curl -sS -H "X-Figma-Token: $TOKEN" \
  "https://api.figma.com/v1/images/${FILE_KEY}?ids=${IDS}&format=png&scale=2")

python3 - "$OUT_DIR" "$RESP" <<'PY'
import json, subprocess, sys, os

out_dir = sys.argv[1]
resp = json.loads(sys.argv[2])
if resp.get("err"):
    raise SystemExit(resp)
images = resp.get("images", {})
names = {
    "1611:18471": "logo",
    "1611:18430": "hero-visual",
    "1611:18380": "hero-features",
    "1611:18501": "registration-trucks",
    "1611:18490": "logo-footer",
    "1611:18525": "carousel-slide-1",
    "1611:18373": "carousel-slide-1-accent",
    "1611:18576": "carousel-slide-1-cursor",
    "1611:18817": "carousel-slide-2",
    "1611:18816": "carousel-slide-2-accent",
    "1611:18944": "carousel-slide-2-cursor",
    "1611:18964": "carousel-slide-3-map",
    "1611:18979": "carousel-slide-3-phone",
    "1611:18950": "carousel-slide-3-accent",
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
