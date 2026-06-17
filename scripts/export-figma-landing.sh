#!/usr/bin/env bash
# Export landing PNG assets from Figma (Hevvi Design → Website / MacBook Air - 15).
#
# Usage:
#   FIGMA_TOKEN=figd_... ./scripts/export-figma-landing.sh
#
# Node map (file key H7HtqYOoxZetHunb8PQiHN):
#   logo              1589:652   Group 1119 / hevvi
#   login-cluster     1589:659   Login + arrow button
#   hero-visual       1589:611   Group 1185 (3D cargo + map + delivery card)
#   hero-features     1589:561   Frame 2085 (feature icons row)
#   registration-trucks 1589:682 Banner (trucks photo)
#   logo-footer       1589:671   Group 633216 (white logo for dark footer)
#   carousel-slide-1  1589:706   Frame 1859 — step 01
#   carousel-slide-2  1589:1210  Group 1166 — step 02
#   carousel-slide-3  1589:1225  Frame 552 — step 03
#   carousel-arrow    1589:1009  fi_3114931
#   deco-line-1..3    1589:550, 1589:551, 1589:552
#   landing-mobile    1589:1262  iPhone 13 & 14 - 1 (reference)
set -euo pipefail

TOKEN="${FIGMA_TOKEN:?Set FIGMA_TOKEN}"
FILE_KEY="H7HtqYOoxZetHunb8PQiHN"
OUT_DIR="$(cd "$(dirname "$0")/.." && pwd)/assets/react/islands/pages/Landing/images"
mkdir -p "$OUT_DIR"

declare -A NODES=(
  [logo]="1589:652"
  [hero-visual]="1589:611"
  [hero-features]="1589:561"
  [registration-trucks]="1589:682"
  [logo-footer]="1589:671"
  [carousel-slide-1]="1589:706"
  [carousel-slide-2]="1589:1210"
  [carousel-slide-3]="1589:1225"
  [carousel-arrow]="1589:1009"
  [landing-mobile]="1589:1262"
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
    "1589:652": "logo",
    "1589:611": "hero-visual",
    "1589:561": "hero-features",
    "1589:682": "registration-trucks",
    "1589:671": "logo-footer",
    "1589:706": "carousel-slide-1",
    "1589:1210": "carousel-slide-2",
    "1589:1225": "carousel-slide-3",
    "1589:1009": "carousel-arrow",
    "1589:1262": "landing-mobile",
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
