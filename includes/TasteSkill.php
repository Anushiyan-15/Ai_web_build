<?php
// ═══════════════════════════════════════════════════════════════
//  includes/TasteSkill.php — Design-intelligence layer
//
//  Adapts the official Taste Skill approach
//  (https://github.com/Leonxlnx/taste-skill — design-taste-frontend
//  v2 + redesign-existing-projects) to this project's AI pipeline.
//
//  Role in the chain:
//    wizard (vibe + 3 dials) → data['taste']
//      → taste_prompt_block() injected into every generation prompt
//        (OpenCodeService + GeminiService)
//      → taste_edit_addendum() injected into every AI-edit prompt
//      → taste_audit_html() scores produced HTML (pre-flight check)
//
//  The skill files themselves live under skills/taste/ (SKILL.md).
//  This file is the executable adapter — no network, no deps.
// ═══════════════════════════════════════════════════════════════

// ── Normalize raw taste prefs (wizard / API) ───────────────────
// Shape: ['vibe'=>string, 'variance'=>int 0-10 (0=auto),
//         'motion'=>int, 'density'=>int]
function taste_normalize($taste): array {
    if (!is_array($taste)) $taste = [];
    $vibe = strtolower(trim((string)($taste['vibe'] ?? 'auto')));
    $valid = ['auto', 'minimalist', 'premium', 'playful', 'editorial', 'brutalist', 'trust'];
    if (!in_array($vibe, $valid, true)) $vibe = 'auto';
    $clamp = function ($v) {
        $v = (int)$v;
        if ($v < 0) $v = 0;
        if ($v > 10) $v = 10;
        return $v;
    };
    return [
        'vibe'     => $vibe,
        'variance' => $clamp($taste['variance'] ?? 0),
        'motion'   => $clamp($taste['motion'] ?? 0),
        'density'  => $clamp($taste['density'] ?? 0),
    ];
}

// ── 0. BRIEF INFERENCE (Taste Skill §0 — read the room first) ──
// Returns ['page_kind','vibe','audience','design_read',
//          'variance','motion','density']
function taste_infer_brief(array $data, array $taste = []): array {
    $taste = taste_normalize($taste);
    $bizType  = strtolower((string)($data['biz_type'] ?? ''));
    $audience = trim((string)($data['biz_audience'] ?? '')) ?: 'modern clients';
    $dirText  = strtolower((string)($data['design_direction'] ?? '') . ' ' . ($data['design_style'] ?? ''));

    // Page kind from business type
    $pageKind = 'landing page';
    if (preg_match('/portfolio|creator|freelance|photograph|designer/i', $bizType)) {
        $pageKind = 'portfolio';
    } elseif (preg_match('/shop|store|retail|e-?commerce|boutique|fashion|jewelry|grocery|bakery|furniture/i', $bizType)) {
        $pageKind = 'shop landing page';
    } elseif (preg_match('/coach|consultant|therapist|tutor|salon|clinic|trade|plumb|electric|service/i', $bizType)) {
        $pageKind = 'solo-professional landing page';
    } elseif (preg_match('/school|college|education/i', $bizType)) {
        $pageKind = 'editorial/education landing page';
    }

    // Vibe: explicit picker wins, else keyword inference, else style map
    $vibe = $taste['vibe'];
    if ($vibe === 'auto') {
        if (preg_match('/brutal|industrial|raw|swiss/i', $dirText)) $vibe = 'brutalist';
        elseif (preg_match('/minimal|calm|linear|clean|editorial|scandinavian/i', $dirText)) $vibe = 'minimalist';
        elseif (preg_match('/playful|wild|awwwards|experimental|agency|dribbble|bold|dynamic/i', $dirText)) $vibe = 'playful';
        elseif (preg_match('/luxury|premium|apple|brand|dark|gold|elegant/i', $dirText)) $vibe = 'premium';
        elseif (preg_match('/trust|legal|clinic|medical|public|bank|insurance/i', $dirText . ' ' . $bizType)) $vibe = 'trust';
        else $vibe = 'minimalist';
    }

    $dials = taste_infer_dials($vibe, $taste);

    $family = [
        'minimalist' => 'restrained utilities + Plus Jakarta Sans / Geist-style sans + quiet motion',
        'premium'    => 'glass + ambient glow + display sans + spring motion',
        'playful'    => 'asymmetric grid + oversized type + scroll choreography',
        'editorial'  => 'magazine grid + serif accents + generous whitespace',
        'brutalist'  => 'raw borders + mono + hard shadows, near-zero motion',
        'trust'      => 'symmetric grid + high-contrast type + static-first',
    ][$vibe] ?? 'restrained utilities + display sans + quiet motion';

    return [
        'page_kind' => $pageKind,
        'vibe'      => $vibe,
        'audience'  => $audience,
        'design_read' => "Reading this as: {$pageKind} for {$audience}, with a {$vibe} language, leaning toward {$family}.",
        'variance'  => $dials[0],
        'motion'    => $dials[1],
        'density'   => $dials[2],
    ];
}

// ── 1. THE THREE DIALS (Taste Skill §1) ─────────────────────────
// Baseline 8/6/4; vibe presets override; explicit user values win.
function taste_infer_dials(string $vibe, array $taste): array {
    $presets = [
        'minimalist' => [6, 4, 3],
        'premium'    => [7, 6, 3],
        'playful'    => [9, 8, 3],
        'editorial'  => [6, 4, 3],
        'brutalist'  => [8, 3, 4],
        'trust'      => [3, 2, 5],
    ];
    $base = $presets[$vibe] ?? [8, 6, 4];
    $taste = taste_normalize($taste);
    return [
        $taste['variance'] > 0 ? $taste['variance'] : $base[0],
        $taste['motion']   > 0 ? $taste['motion']   : $base[1],
        $taste['density']  > 0 ? $taste['density']  : $base[2],
    ];
}

// ── Short system-prompt addendum (token-budget safe) ────────────
function taste_system_addendum(): string {
    return <<<'SYS'

TASTE LAYER (design intelligence, highest design authority): brief-inferred design read + dials arrive in the user prompt. Obey them. Anti-slop hard bans: no AI-purple glow default, no centered-hero-over-dark-mesh default, no 3-equal-cards default, Inter only when the brief asks neutral, one accent color locked page-wide, hero fits first viewport (headline ≤2 lines, subtext ≤20 words, CTA visible), motion only via transform/opacity with reduced-motion fallback. Pre-flight before output: every CTA readable (contrast), labels ≤3 words single-line, one label per intent, no em-dashes as decoration, no lorem ipsum, no fake-precision stats.
SYS;
}

// ── Generation user-prompt block (per request) ──────────────────
function taste_prompt_block(array $data, string $variationId = 'classic', int $slot = -1, array $taste = []): string {
    if (isset($data['taste']) && empty($taste)) $taste = (array)$data['taste'];
    $b = taste_infer_brief($data, $taste);
    $layout = [
        'classic'   => 'offset/split hero (variance-gated), varied rhythm, no 3-equal-cards default',
        'bold'      => 'asymmetric bento + oversized display type + gradient mesh used with intent',
        'editorial' => 'magazine rows + narrow measure + alternating compositions (max 2 zigzag splits in a row)',
    ][$variationId] ?? 'offset composition, varied rhythm';
    if ($slot >= 0) $layout .= " [layout slot {$slot}: push further from the other two slots]";
    return "## TASTE LAYER — DESIGN INTELLIGENCE (follow tightly)\n"
        . "Design Read: {$b['design_read']}\n"
        . "Dials: DESIGN_VARIANCE={$b['variance']} MOTION_INTENSITY={$b['motion']} VISUAL_DENSITY={$b['density']}\n"
        . "Layout direction for this variant: {$layout}.\n"
        . "Hard bans: (1) no purple/blue AI-glow unless brand palette is purple; (2) centered hero only if brief is editorial/manifesto; "
        . "(3) max 1 eyebrow label per 3 sections; (4) one accent color page-wide; (5) hero: headline ≤2 lines, subtext ≤20 words, CTA above fold; "
        . "(6) no em-dash decoration, no lorem ipsum, no invented precise stats; (7) buttons: contrast-checked, one line, one label per intent; "
        . "(8) real Unsplash photos with alt text in hero/about; (9) mobile: every multi-column collapses to single column <768px.\n"
        . "Link discipline: EVERY <a href> must reference a real id present in THIS document (e.g. #services, #contact) — href=\"#\" is a hard fail. "
        . "Motion discipline: when the page has scroll-reveal/IntersectionObserver, include an @media (prefers-reduced-motion: reduce) block that disables animation. "
        . "Photo discipline: hero + about sections MUST each contain a real <img> (Unsplash URL with descriptive alt) — text-only heroes fail.";
}

// ── Edit system addendum (redesign-skill audit-first protocol) ──
function taste_edit_addendum(): string {
    return <<<'SYS'

TASTE EDIT PROTOCOL (audit-first, from redesign-existing-projects): 1) SCAN the full document (sections, ids/classes, JS handlers, design tokens). 2) DIAGNOSE against anti-slop rules (generic cards, purple glow, Inter-everywhere, dead buttons, missing hover/active/focus states) — fix only what the instruction touches plus directly-adjacent inconsistencies. 3) FIX surgically: preserve every class/id/token/animation unless told to change; new/changed pieces must match the document's existing design system (radius scale, one accent, type pairing). Never restyle the whole page for a local edit. Never add em-dashes, lorem ipsum, or a second accent color.
SYS;
}

// ── Snippet-edit micro note ─────────────────────────────────────
function taste_snippet_addendum(): string {
    return ' TASTE: match the surrounding design system exactly (same radius scale, same single accent, same type); no new accent colors, no em-dash decoration, keep contrast AA.';
}

// ── Mechanical pre-flight audit → ['score'=>int, 'issues'=>[]] ──
function taste_audit_html(string $html): array {
    $issues = [];
    $fail = function ($msg) use (&$issues) { $issues[] = $msg; };

    if (strlen($html) < 200) {
        return ['score' => 0, 'passed' => false, 'issues' => ['empty or truncated HTML']];
    }

    // Visible-text copy (strip scripts/styles)
    $t = preg_replace('/<script[\s\S]*?<\/script>/i', ' ', $html);
    $t = preg_replace('/<style[\s\S]*?<\/style>/i', ' ', (string)$t);
    $visible = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string)$t), ENT_QUOTES, 'UTF-8')));

    // 1. Hero headline discipline (first h1 ≤ 14 words / 2 lines heuristic)
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
        $h1 = trim(preg_replace('/\s+/', ' ', strip_tags($m[1])));
        if (str_word_count($h1) > 14) $fail('hero H1 too long (' . str_word_count($h1) . ' words — keep ≤14 / 2 lines)');
    } else {
        $fail('no H1 found');
    }

    // 2. Eyebrow restraint: eyebrow-like labels vs section count
    $sections = max(1, preg_match_all('/<section[\s>]/i', $html));
    $eyebrows = preg_match_all('/uppercase[^>]*tracking|tracking[^>]*uppercase/i', $html);
    if ($eyebrows > (int)ceil($sections / 3) + 2) {
        $fail("eyebrow overload ({$eyebrows} labels for {$sections} sections — max ~1 per 3 sections)");
    }

    // 3. Duplicate CTA intent (contact-synonyms appearing as 2+ distinct labels)
    $labels = [];
    if (preg_match_all('/<(?:a|button)[^>]*>([^<]{2,40})<\/(?:a|button)>/i', $html, $mm)) {
        foreach ($mm[1] as $raw) {
            $labels[] = strtolower(trim(preg_replace('/[^a-z ]/i', '', strip_tags($raw))));
        }
    }
    $contactSyn = ['contact us', 'get in touch', 'lets talk', 'start a project', 'reach out', 'talk to us'];
    $found = [];
    foreach ($labels as $l) {
        foreach ($contactSyn as $s) {
            if ($l !== '' && (strpos($l, $s) !== false)) $found[$s] = true;
        }
    }
    if (count($found) > 1) $fail('duplicate contact CTA intent (' . implode(' / ', array_keys($found)) . ' — pick ONE label)');

    // 4. AI-purple tell on non-purple pages
    $paletteHint = '';
    if (preg_match('/#(?:6366f1|a855f7|8b5cf6)/i', $html)) $paletteHint = 'purple-present';
    if ($paletteHint === '' && preg_match('/linear-gradient\(135deg,\s*#4f46e5/i', $html)) {
        $fail('default indigo/purple gradient used — lock to the brand accent instead');
    }

    // 5. Button contrast sanity (white-on-white / invisible labels)
    if (preg_match('/background:\s*#fff[^;]*color:\s*#fff|background:\s*white[^;]*color:\s*white/i', $html)) {
        $fail('button text invisible against button background (white on white)');
    }

    // 6. Em-dash decoration overload in visible copy
    $emd = substr_count($visible, '—');
    if ($emd > 3) $fail("em-dash decoration overload ({$emd} uses — banned as design flourish)");

    // 7. Placeholder / slop copy
    if (stripos($visible, 'lorem ipsum') !== false) $fail('lorem ipsum placeholder shipped');
    if (preg_match('/\b(John Doe|Jane Smith|Acme Corp)\b/i', $visible)) $fail('generic placeholder names (John Doe / Acme Corp)');
    if (preg_match('/\b(elevate|seamless|unleash|game-changer|delve|tapestry)\b/i', $visible, $cm)) {
        $fail('AI-cliche copy ("' . strtolower($cm[1]) . '" — write plain specific language)');
    }

    // 8. Dead controls
    if (preg_match('/href\s*=\s*["\']#["\']/', $html)) $fail('dead links (href="#") — link somewhere real or disable visibly');

    // 8b. Photo discipline: a visual product needs real photography
    $imgCount = preg_match_all('/<img[\s>]/i', $html);
    if ($imgCount === 0) $fail('no real photography — hero/about need real <img> visuals, not CSS shapes');

    // 9. Accessibility basics
    if (preg_match('/<img(?![^>]*alt=)[^>]*>/i', $html)) $fail('images missing alt text');
    if (stripos($html, 'prefers-reduced-motion') === false && stripos($html, 'IntersectionObserver') !== false) {
        $fail('scroll-reveal without prefers-reduced-motion fallback');
    }

    // 9b. Reveal deadlock: content hidden by DEFAULT in CSS (`.reveal{opacity:0}`)
    // with no watchdog/noscript fallback stays blank forever if the one inline
    // script throws — header renders, body empty. The safety net injects
    // data-wcl-reveal-watchdog; anything still unprotected fails here.
    if (stripos($html, 'wcl-reveal-watchdog') === false
        && stripos($html, '.reveal') !== false
        && stripos($html, 'IntersectionObserver') !== false
        && preg_match('/\.reveal[^{]*\{[^}]*opacity\s*:\s*0/i', $html)) {
        $fail('scroll-reveal hides content by default with no fallback — blank page if JS fails');
    }

    // 10. Mobile collapse declared
    if (stripos($html, '@media') === false) $fail('no responsive breakpoints declared');

    $score = max(0, 100 - count($issues) * 9);
    return ['score' => $score, 'passed' => $score >= 70, 'issues' => $issues];
}

// ── Deterministic safety net: zero-AI-cost fixes, idempotent ─────
// Patches the three most-repeated AI misses (smooth anchor scroll +
// reduced-motion fallback + reveal watchdog) ONLY when the document
// lacks them. Never touches design tokens, layout, or copy.
//
// REVEAL WATCHDOG (why it exists): Space Bunny wraps nearly every section
// in `.reveal{opacity:0}` + ONE inline script that adds `.in` via
// IntersectionObserver. If that script throws anywhere — or never runs —
// the header renders but the WHOLE body stays blank (verified 2026-10-06:
// AURA LUXE screenshot, header-only page). The watchdog re-adds `.in` to
// any still-hidden `.reveal` after 2.5s, so a JS failure degrades to
// "no animation" instead of "blank page".
function taste_apply_safety_net(string $html): string {
    if (strlen($html) < 200) return $html;
    $patch = '';
    if (stripos($html, 'scroll-behavior') === false) {
        $patch .= 'html{scroll-behavior:smooth}';
    }
    if (stripos($html, 'prefers-reduced-motion') === false) {
        $patch .= '@media (prefers-reduced-motion: reduce){*,*::before,*::after{animation-duration:0.01ms!important;animation-iteration-count:1!important;transition-duration:0.01ms!important;scroll-behavior:auto!important}}';
    }
    if ($patch !== '') {
        $style = '<style data-taste-safety-net>' . $patch . '</style>';
        if (stripos($html, '</head>') !== false) {
            $html = str_ireplace('</head>', $style . '</head>', $html);
        } elseif (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $style . '</body>', $html);
        } else {
            $html .= $style;
        }
    }
    // Reveal watchdog: CSS-default-hidden `.reveal{opacity:0}` + IO, but no
    // watchdog yet → inject one idempotent script before </body>.
    if (stripos($html, 'wcl-reveal-watchdog') === false
        && stripos($html, '.reveal') !== false
        && stripos($html, 'IntersectionObserver') !== false
        && preg_match('/\.reveal[^{]*\{[^}]*opacity\s*:\s*0/i', $html)) {
        $watchdog = '<script data-wcl-reveal-watchdog>setTimeout(function(){try{'
            . 'document.querySelectorAll(\'.reveal:not(.in)\').forEach(function(el){el.classList.add(\'in\')})'
            . '}catch(e){}},2500);</script>';
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $watchdog . '</body>', $html);
        } else {
            $html .= $watchdog;
        }
    }
    // Count-up: stat numbers (50k+, 99.8%, 30+) animate 0-to-target when
    // scrolled into view. AI often ships them static; this additive script
    // auto-detects single-token numbers, skips prices/years/phones and
    // slash fractions (24/7), honors prefers-reduced-motion, and no-ops
    // when the page has no stats. Skipped when a copy already exists
    // (template shared-JS or preview bundle carry the same marker).
    if (stripos($html, 'wc-count-fix') === false) {
        $counter = <<<'WCCOUNT'
<script data-wc-count-fix>(function(){if(window.__wcCountUp)return;window.__wcCountUp=true;function wcParseStat(raw){var t=String(raw==null?"":raw).trim();if(!t||t.length>16)return null;if(t.indexOf("/")!==-1)return null;if(/\s/.test(t))return null;var m=t.match(/^([^0-9.,]*)([0-9][0-9.,]*)([A-Za-z%+]*)$/);if(!m)return null;var pre=m[1]||"",num=m[2],suf=m[3]||"";if(pre.indexOf('$')!==-1)return null;if(pre.indexOf('Rs')!==-1||pre.indexOf('INR')!==-1||pre.indexOf('+')!==-1)return null;var digitsOnly=num.replace(/[^0-9]/g,"");if(digitsOnly.length>7)return null;for(var ci=0;ci<pre.length;ci++){var cc=pre.charCodeAt(ci);if(cc===0x20AC||cc===0x00A3||cc===0x20B9)return null;}if(!/^[kKmMbB]?[%+]{0,2}$/.test(suf))return null;var pure=num.replace(/,/g,"");if((pure.match(/\./g)||[]).length>1)return null;var target=parseFloat(pure);if(!isFinite(target)||target<=0)return null;var dec=0,di=pure.indexOf(".");if(di!==-1)dec=pure.length-di-1;if(dec>2)return null;if(!pre&&!suf&&dec===0&&target>=1900&&target<=2100)return null;return{pre:pre,target:target,dec:dec,suf:suf,final:t};}function wcCollect(){var out=[];function push(el,p){if(el.__wcCounted)return;el.__wcCounted=true;if(out.length<60)out.push({el:el,p:p});}var tagged=null;try{tagged=document.querySelectorAll("[data-count],[data-target]");}catch(e){}if(tagged){for(var k=0;k<tagged.length;k++){var el2=tagged[k],p2=wcParseStat(el2.textContent);if(!p2){var av=parseFloat(String(el2.getAttribute("data-count")||el2.getAttribute("data-target")||"").replace(/,/g,""));if(isFinite(av)&&av>0)p2={pre:"",target:av,dec:0,suf:"",final:el2.textContent};}if(p2)push(el2,p2);}}var els=null;try{els=document.querySelectorAll("h1,h2,h3,h4,div,span,p,strong");}catch(e){}if(els){for(var i=0;i<els.length;i++){var el=els[i];if(el.children&&el.children.length>0)continue;if(el.closest&&el.closest("a,button,nav,form,select,textarea,input,script,style"))continue;var p=wcParseStat(el.textContent);if(p)push(el,p);}}return out;}function wcRender(el,p,v){var s;if(p.dec>0)s=v.toFixed(p.dec);else{try{s=Math.round(v).toLocaleString("en-US");}catch(e){s=String(Math.round(v));}}el.textContent=p.pre+s+p.suf;}function wcAnimate(el,p,delay){var reduce=false;try{reduce=!!(window.matchMedia&&window.matchMedia("(prefers-reduced-motion: reduce)").matches);}catch(e){}if(reduce){el.textContent=p.final;return;}var dur=1600,t0=null;function frame(ts){if(t0===null)t0=ts;var t=Math.min((ts-t0)/dur,1);var ez=t>=1?1:1-Math.pow(2,-10*t);wcRender(el,p,p.target*ez);if(t<1)requestAnimationFrame(frame);else wcRender(el,p,p.target);}setTimeout(function(){try{requestAnimationFrame(frame);}catch(e){el.textContent=p.final;}},delay||0);}function wcInitCounters(){var items=wcCollect();if(!items.length)return;for(var i=0;i<items.length;i++)wcRender(items[i].el,items[i].p,0);if("IntersectionObserver" in window){try{var io=new IntersectionObserver(function(es){for(var j=0;j<es.length;j++){var en=es[j];if(en.isIntersecting){try{io.unobserve(en.target);}catch(e){}for(var q=0;q<items.length;q++){if(items[q].el===en.target){wcAnimate(en.target,items[q].p,(q%4)*120);break;}}}}},{threshold:0.35});for(var m2=0;m2<items.length;m2++)io.observe(items[m2].el);return;}catch(e){}}for(var n=0;n<items.length;n++)wcAnimate(items[n].el,items[n].p,n*120);}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",wcInitCounters);else wcInitCounters();})();
</script>
WCCOUNT;
        if (stripos($html, '</body>') !== false) {
            // str_ireplace (literal) — the script holds '$' and backslashes
            // that preg_replace would reinterpret.
            $html = str_ireplace('</body>', $counter . "\n</body>", $html);
        } else {
            $html .= "\n" . $counter;
        }
    }
    return $html;
}

// ── Repair-pass instruction: fix ONLY listed audit issues ───────
function taste_repair_instruction(array $issues): string {
    $lines = [];
    foreach (array_slice($issues, 0, 8) as $i) $lines[] = '- ' . $i;
    return "TASTE REPAIR PASS — fix ONLY these pre-flight issues, change nothing else "
        . "(same sections, same copy except where an issue demands a rewrite, same design tokens, same layout):\n"
        . implode("\n", $lines);
}
