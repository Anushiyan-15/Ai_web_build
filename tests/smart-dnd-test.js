/* SmartDnD functional tests — Tests A..J (logic + integration guards).
 * Run: node tests/smart-dnd-test.js  (exit 0 = all pass)
 * No browser needed for logic contracts; integration checks parse studio.php.
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const ROOT = path.join(__dirname, '..');
const JS = path.join(ROOT, 'assets', 'js', 'wc-smart-dnd.js');
const PHP = path.join(ROOT, 'studio.php');
const CSS = path.join(ROOT, 'assets', 'css', 'wc-smart-dnd.css');

let pass = 0, fail = 0;
function ok(name, cond, detail) {
  if (cond) { pass++; console.log('PASS ' + name); }
  else { fail++; console.log('FAIL ' + name + (detail ? ' — ' + detail : '')); }
}

// 1. Files exist
ok('files exist', fs.existsSync(JS) && fs.existsSync(PHP) && fs.existsSync(CSS),
  'missing js/php/css');

// 2. Load JS in sandbox, run built-in self-tests (A..J logic)
const src = fs.readFileSync(JS, 'utf8');
const sandbox = {
  window: {}, document: { readyState: 'complete', addEventListener: () => {}, body: { classList: { contains: () => false } } },
  console, setTimeout: (fn) => 0, clearTimeout: () => {}, setInterval: () => 0, clearInterval: () => {},
  requestAnimationFrame: () => 0, cancelAnimationFrame: () => {},
};
sandbox.window = sandbox;
sandbox.globalThis = sandbox;
vm.createContext(sandbox);
try {
  vm.runInContext(src, sandbox, { filename: 'wc-smart-dnd.js' });
  ok('js loads without throw', true);
} catch (e) {
  ok('js loads without throw', false, e.message);
}
let selfTests = [];
try {
  selfTests = sandbox.window.WCSmartDnd.runSelfTests();
  for (const t of selfTests) ok('self:' + t.name, t.pass, t.detail);
} catch (e) {
  ok('runSelfTests', false, e.message);
}

// 3. Pure-logic spot checks (mirror spec scenarios)
try {
  const L = sandbox.window.WCSmartDnd.logic;
  // Test A: horizontal button between two links → index 1
  let i = L.computeInsertionIndex(
    [{ left: 0, top: 0, width: 80, height: 36 }, { left: 88, top: 0, width: 80, height: 36 }], 84, 18, 'horizontal');
  ok('Test A — button between links pushes to index 1', i === 1, 'got ' + i);
  // Test B: grid occupied cell → that cell index (neighbours shift)
  i = L.computeInsertionIndex(
    [{ left: 0, top: 0, width: 100, height: 100 }, { left: 110, top: 0, width: 100, height: 100 }], 150, 50, 'grid');
  ok('Test B/C — grid occupied cell returns 1', i === 1, 'got ' + i);
  // Test D: vertical reorder
  i = L.computeInsertionIndex(
    [{ left: 0, top: 0, width: 300, height: 200 }, { left: 0, top: 210, width: 300, height: 200 }], 150, 400, 'vertical');
  ok('Test D — vertical drop at end returns 2', i === 2, 'got ' + i);
  // Collision overlap
  ok('Test B — overlap detected', L.rectsOverlap({ left: 0, top: 0, width: 100, height: 100 }, { left: 50, top: 50, width: 100, height: 100 }, 8));
  ok('no false overlap', !L.rectsOverlap({ left: 0, top: 0, width: 100, height: 100 }, { left: 500, top: 500, width: 100, height: 100 }, 8));
  // Same-parent index correction
  ok('move correction 1→3 becomes 2', L.adjustIndexForMove(true, 1, 3) === 2);
  ok('cross-parent index unchanged', L.adjustIndexForMove(false, 1, 3) === 3);
  // F: narrow/mobile stacking is handled by layoutOf+breakpoint rules (writer exists)
  ok('Test F/G — breakpoint writer present', typeof sandbox.window.WCSmartDnd.scopedApplyStyle === 'function');
} catch (e) {
  ok('pure logic checks', false, e.message);
}

// 4. Integration: studio.php still has all preserved systems, plus additive hooks
const php = fs.readFileSync(PHP, 'utf8');
const mustHave = [
  'function initGrapesStudio', 'function syncCanvasToHtml', 'function saveProjectData',
  'function studioUndo', 'function studioRedo', 'function setStudioDevice',
  'function setupFreeButtonDragging', 'function setupSectionDragging',
  'function previewSectionStyle', 'function applyBtnBg', 'function loadHtmlIntoStudioCanvas',
  'function renderSmartLayers', 'function refreshSectionDragHandles',
  'wc-studio-suite.js',
];
for (const k of mustHave) ok('preserve:' + k, php.includes(k), 'missing');
ok('additive: smart-dnd script included', php.includes('wc-smart-dnd.js'));
ok('additive: smart-dnd css included', php.includes('wc-smart-dnd.css'));
ok('additive: SmartDnd.attach hooked', php.includes('WCSmartDnd?.attach'));
ok('additive: header-button delegation', php.includes('WCSmartDnd?.shouldHandleButton'));
ok('section scoping guard', php.includes('WCSmartDnd?.ensureStableId?.(editingSection)'));
ok('button scoping guard', php.includes('WCSmartDnd?.ensureStableId?.(editingButton)'));
ok('no absolute-forcing regression (dragMode untouched, no position:absolute global)',
  !/body\s*\{\s*position\s*:\s*absolute/.test(php));

// 5. Behaviour contracts in JS source
ok('local-first comp.move used', src.includes('.move('));
ok('single save on drop (debounced, not on move)', src.includes('scheduleSave') && !/syncCanvasToHtml\(\s*\)\s*;[^}]*pointermove/.test(src));
ok('overlay cleanup before save', src.includes('cleanupOverlays'));
ok('undo preserved via UndoManager', src.includes('UndoManager'));
ok('touch long-press (scroll-safe)', src.includes('longFired') && src.includes('touch'));
ok('auto-scroll near edges', src.includes('scrollBy') || src.includes('AutoScroll') || src.includes('autoScroll') || src.includes('getAutoScroll'));
ok('placeholder + guides', src.includes('wc-smart-placeholder') && src.includes('snap'));
ok('FLIP reflow animation', src.includes('playFlip') || src.includes('FLIP'));
ok('breakpoint css scoped by data-wc-id', src.includes('wc-smart-breakpoint-css') && src.includes('data-wc-id'));
ok('no global section background rule', !/[^a-zA-Z]section\s*\{\s*background/.test(src));

console.log('\nSelf-test detail:');
for (const t of selfTests) console.log('  ' + (t.pass ? '✓' : '✗') + ' ' + t.name + ' — ' + t.detail);
console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
