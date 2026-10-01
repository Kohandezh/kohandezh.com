#!/bin/zsh
# Auto-resume Claude reviews after the Antigravity quota resets.
# Probes `agy` every 20 minutes; when a probe succeeds, launches the review pipeline (detached) and exits.
# Manual equivalent:  .venv313/bin/python tools/pipeline.py run --review-workers 6 --translate-workers 2
cd /Users/emperor/Documents/AI/kohandezh.com/Books/ai-governance
LOG=work/logs/resume_reviews.log
echo "$(date) resume watcher started (first probe after ${1:-15h}); pid $$" >> $LOG
sleep ${1:-15h}
while true; do
  out=$(timeout 120 /Users/emperor/.local/bin/agy --model claude-sonnet-4-6 --output-format json --dangerously-skip-permissions --print-timeout 90s -p='Reply with exactly the word OK.' 2>/dev/null)
  if echo "$out" | grep -q '"status":"SUCCESS"'; then
    echo "$(date) quota available → launching review pipeline" >> $LOG
    pkill -f "pipeline.p[y] run" 2>/dev/null; sleep 3
    .venv313/bin/python tools/pipeline.py reset-inflight >> $LOG 2>&1
    nohup .venv313/bin/python -u tools/pipeline.py run --translate-workers 2 --review-workers 6 --escalation-workers 1 >> work/logs/pipeline.stdout 2>&1 &
    echo "$(date) launched pid $!" >> $LOG
    exit 0
  fi
  echo "$(date) still exhausted: $(echo "$out" | grep -o 'Resets in [^."]*' | head -1)" >> $LOG
  sleep 20m
done
