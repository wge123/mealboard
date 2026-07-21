# Mealboard — pre-Phase-3 decisions (2026-07-18)

#1 Discovery cadence+volume: DAILY, 3 candidates per run.
#2 AI driver: shell out to the local `claude` CLI (`claude -p`, --model sonnet) exactly like yt2md does. No ANTHROPIC_API_KEY, no API billing, no budget-cap infrastructure (subscription covers it).
#3 Rejected titles in discovery prompts: SUMMARIZED into taste-profile themes, not verbatim lists.
#4 yt2md invocation: thin transcript-only subprocess — YouTubeDriver shells to python3 using youtube_transcript_api (reuse yt2md's venv at /Users/willem/Developer/Personal/yt2md/venv) to fetch raw transcripts only; Mealboard's own AI driver does the structuring. Captionless videos: mark failed, skip.
#5 Channel list home: DATABASE table + small Livewire settings UI (phone-editable).
#6 Healthy/easy thresholds: total time ≤30 min AND ≤10 ingredients, whole-food-leaning bias in prompts.
#7 Second-brain consumption: BOTH markdown push to the vault meals folder AND JSON/iCal pull endpoints (steps 26 and 27 both build).

#8 UI framework (2026-07-20, supersedes the "vanilla CSS / Bootstrap CDN ok, no Tailwind" convention): full UI overhaul to Tailwind CSS v4 + daisyUI 5 via Vite after the user rejected the Bootstrap UI ("it looks terrible"). Custom mealboard/mealboard-dark oklch themes (cream/stone base, herb-green primary), Inter + Fraunces self-hosted via @fontsource, meal-type accent vars, bottom tab bar on phones. Build step now required: npm run build. No further UI deps (Flux/MaryUI/etc.) without an explicit decision.

#9 Recipe sources (2026-07-21): EVERY recipe must carry a source_url — the recipe page or video it came from or was inspired by (display wording: "Inspired from <host>"). Enforced at all three layers: form validation (required|url), CandidateValidator (candidates without a valid http(s) URL are malformed), and DB NOT NULL. The claude discovery driver must CITE a real published source (user chose citation over label-only attribution or retiring the driver); hallucination guard: the driver HEAD-checks each cited URL and discards the candidate on 404/410/dead host, while 403/429 (bot-blocking) still count as resolving. Seed + pre-existing rows were backfilled with live-verified URLs.
