#!/usr/bin/env bash
# Launch the kohandezh.com local dev server with the AI pet widget working.
#
#   ./run-dev.sh                  # uses ZAI_API_KEY from env, model=glm-4.5-flash
#   ./run-dev.sh glm-4.6          # use a specific model
#
# Then open http://localhost:8735/

set -e
cd "$(dirname "$0")"

# 1. API key — read from .env, ~/.kohandezh-zai-key, or shell env.
KEY="${ZAI_API_KEY:-}"
if [ -z "$KEY" ] && [ -f .env ]; then
	KEY=$(grep -E '^ZAI_API_KEY=' .env | cut -d= -f2- | tr -d "'\"")
fi
if [ -z "$KEY" ] && [ -f "$HOME/.kohandezh-zai-key" ]; then
	KEY=$(tr -d '[:space:]' < "$HOME/.kohandezh-zai-key")
fi
if [ -z "$KEY" ]; then
	echo "No ZAI_API_KEY found. Set it via env var, .env, or ~/.kohandezh-zai-key." >&2
	echo "The widget will fall back to keyword-search without it." >&2
fi

# 2. Model — first CLI arg or default.
MODEL="${1:-glm-4.5-flash}"

# 3. Find PHP.
PHP=$(command -v php || true)
[ -z "$PHP" ] && [ -x /usr/local/opt/php/bin/php ] && PHP=/usr/local/opt/php/bin/php
[ -z "$PHP" ] && { echo "PHP not found. Install with: brew install php"; exit 1; }

# 4. Find an open port (prefer 8735).
PORT=8735
if lsof -i ":${PORT}" -t >/dev/null 2>&1; then
	for P in 8736 8737 8738 8739 8740; do
		if ! lsof -i ":${P}" -t >/dev/null 2>&1; then PORT=$P; break; fi
	done
fi

echo "──────────────────────────────────────────────────────────────"
echo "  Kohandezh dev server"
echo "  URL:   http://localhost:${PORT}/"
echo "  Model: ${MODEL}"
echo "  Key:   ${KEY:0:8}…${KEY: -4}"
echo ""
echo "  Ctrl+C to stop."
echo "──────────────────────────────────────────────────────────────"

ZAI_API_KEY="$KEY" ZAI_MODEL="$MODEL" \
	"$PHP" -S "127.0.0.1:${PORT}" -t . dev-server.php
