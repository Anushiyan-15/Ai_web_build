<?php
// tests/test-studio-interact.php — run: php tests/test-studio-interact.php
// Studio interaction contract:
//  - click/tap a button = SELECT ONLY (editor never auto-opens)
//  - right-click menu = the way into Edit (✏️ Edit / Edit Text / Edit Link)
//  - drag a button over a section = highlight + push-aside placeholder,
//    drop inserts it into the flow (auto-adjust); elsewhere = free place
//  - edge auto-scroll while dragging.
$s = (string)@file_get_contents(__DIR__ . '/../studio.php');
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }

// 1. No automatic editor on select or on plain click.
ok(strpos($s, 'if (isButtonLike(model) && !suppressEditorOpen && !activeFreeDrag) openButtonEditor(model)') === false,
    'select no longer auto-opens button editor');
ok(strpos($s, '} else if (isButtonLike(d.comp)) openButtonEditor(d.comp);') === false,
    'plain click no longer auto-opens button editor');
ok(strpos($s, 'Click/tap = SELECT ONLY') !== false, 'select-only intent documented');

// 2. Right-click path into Edit stays intact.
ok(strpos($s, "id=\"custom-context-menu\"") !== false, 'context menu exists');
ok(strpos($s, 'onclick="ctxEditSelected()"') !== false, 'menu has Edit item');
ok(strpos($s, 'if (ctxTargetComponent && isButtonLike(ctxTargetComponent)) openButtonEditor(ctxTargetComponent)') !== false,
    'right-click Edit opens button editor');
ok(strpos($s, 'onclick="ctxEditLink()"') !== false, 'menu has Edit Link item');
ok(strpos($s, 'function quickEditButton(idx)') !== false, 'layers quick-edit entry intact');

// 3. Drop-target engine: detect + highlight + push-aside placeholder + scroll.
foreach (['findButtonDropTarget', 'updateButtonDropIndicator', 'clearButtonDropIndicator',
    'autoScrollCanvas', 'wc-drop-placeholder', 'wc-drop-highlight',
    'Drop here — auto-adjusts'] as $k) {
    ok(strpos($s, $k) !== false, "drop engine has $k");
}
// 4. In-flow drop joins the section (absolute styles cleared), else free place.
ok(strpos($s, 'd.comp.move(d.dropTarget.containerComp') !== false, 'drop moves model into hovered section');
ok(strpos($s, "'Button placed — section auto-adjusted'") !== false
    || strpos($s, 'Button placed — section auto-adjusted') !== false, 'in-flow drop confirms auto-adjust');
ok(strpos($s, "'🔘 Button dropped'") !== false || strpos($s, 'Button dropped') !== false, 'free drop path kept');

// 5. Editor itself untouched (opens only from explicit actions).
ok(strpos($s, 'function openButtonEditor(comp)') !== false, 'button editor still exists');

echo $fail === 0 ? "\nALL STUDIO-INTERACT TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
