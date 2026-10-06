---
name: taste-design-intelligence
description: Project-level design-intelligence layer adapting Taste Skill (design-taste-frontend v2 + redesign-existing-projects) for AI website generation and AI-powered editing. Use when generating, refining, or auditing any customer-facing page in this project.
---

# Taste — Design-Intelligence Layer (this project)

Upstream: https://github.com/Leonxlnx/taste-skill
Install names used as reference: `design-taste-frontend` (generation),
`redesign-existing-projects` (edits/audits).

Executable adapter: `includes/TasteSkill.php`. Every rule below maps to a
function there. The AI never needs to fetch the upstream repo at runtime —
the distilled rules are injected into prompts server-side.

## 0. Brief inference (before anything else)

Run `taste_infer_brief($data, $taste)` first. It outputs one line:

> "Reading this as: \<page kind> for \<audience>, with a \<vibe> language,
> leaning toward \<design family>."

- Page kind comes from business type (landing / portfolio / shop / solo-pro).
- Vibe: explicit wizard picker wins; else keyword inference from
  `design_direction`; else style map. Values: minimalist / premium /
  playful / editorial / brutalist / trust.
- Never default to AI-purple gradients, centered hero over dark mesh,
  three equal feature cards, or Inter-everywhere unless the read says so.

## 1. The three dials

`taste_infer_dials($vibe, $taste)` → VARIANCE / MOTION / DENSITY.
Baseline `8 / 6 / 4`. Vibe presets (minimalist 6/4/3, premium 7/6/3,
playful 9/8/3, editorial 6/4/3, brutalist 8/3/4, trust 3/2/5).
Explicit customer slider values always win. Dials are injected into every
generation prompt as `DESIGN_VARIANCE / MOTION_INTENSITY / VISUAL_DENSITY`.

## 2. Where the layer fires

| Pipeline step | Injection |
|---|---|
| Analyze (requirements → brief) | brief-inference instruction in `opencode_analyze_system()` |
| Generate (OpenCode free lane) | `taste_prompt_block()` in `opencode_generate_variation()` + `opencode_generate_quick()` + system addendum |
| Generate (Gemini fast lane) | `taste_prompt_block()` in `gemini_generate_one()` + taste system line |
| AI edit / refine | `taste_edit_addendum()` (audit-first protocol) in edit system prompts + refine prompt |
| Snippet micro-edit (Studio) | `taste_snippet_addendum()` |
| Post-generation | `taste_audit_html()` pre-flight score returned as `taste_audit` in API responses. Weak pages (score < 85) trigger the polish loop below |
| Safety net (free) | `taste_apply_safety_net()` patches smooth-scroll + reduced-motion CSS only when missing — idempotent, zero AI cost |
| AI polish pass | `gemini_repair_html()` fixes ONLY listed audit issues, keeps the better-scoring version. Exposed as `POST /api/opencode.php {"action":"taste_repair",…}` and auto-fired by the frontend (`polishIfNeeded`) in its own HTTP budget |

## 3. Hard bans (enforced in prompts, verified in audit)

1. No AI-purple/blue glow unless the brand palette is purple.
2. Centered hero only for editorial/manifesto briefs.
3. Max 1 eyebrow label per 3 sections.
4. One accent color locked page-wide.
5. Hero fits first viewport: H1 ≤ 2 lines, subtext ≤ 20 words, CTA above fold.
6. No em-dash decoration, no lorem ipsum, no invented precise stats.
7. CTAs: contrast-checked, single line, ≤ 3 words, one label per intent.
8. Real photos (Unsplash niche assets) with alt text in hero/about.
9. Every multi-column layout collapses to single column below 768px.
10. Motion only via transform/opacity; `prefers-reduced-motion` fallback required.

## 4. Edit protocol (redesign skill)

SCAN the full document → DIAGNOSE against anti-slop rules → FIX surgically.
Preserve every class/id/token/animation unless told to change. New pieces
match the existing design system. Never restyle the whole page for a local
edit. Never introduce a second accent color.

## 5. Audit API

`POST /api/opencode.php {"action":"taste_audit","html":"..."}`
→ `{"success":true,"audit":{"score":0-100,"passed":bool,"issues":[...]}}`.
Generation and edit responses also carry `taste` (design read + dials) and
`taste_audit` so the frontend can show a design-quality badge.
