# Mealboard — pre-Phase-3 decisions (2026-07-18)

#1 Discovery cadence+volume: DAILY, 3 candidates per run.
#2 AI driver: shell out to the local `claude` CLI (`claude -p`, --model sonnet) exactly like yt2md does. No ANTHROPIC_API_KEY, no API billing, no budget-cap infrastructure (subscription covers it).
#3 Rejected titles in discovery prompts: SUMMARIZED into taste-profile themes, not verbatim lists.
#4 yt2md invocation: thin transcript-only subprocess — YouTubeDriver shells to python3 using youtube_transcript_api (reuse yt2md's venv at /Users/willem/Developer/Personal/yt2md/venv) to fetch raw transcripts only; Mealboard's own AI driver does the structuring. Captionless videos: mark failed, skip.
#5 Channel list home: DATABASE table + small Livewire settings UI (phone-editable).
#6 Healthy/easy thresholds: total time ≤30 min AND ≤10 ingredients, whole-food-leaning bias in prompts.
#7 Second-brain consumption: BOTH markdown push to the vault meals folder AND JSON/iCal pull endpoints (steps 26 and 27 both build).
