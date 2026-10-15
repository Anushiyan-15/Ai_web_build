<?php
// includes/BizIntelligence/Diff.php — spec comparison for requirement updates.
// Added / modified / removed / affected, before destructive changes.
require_once __DIR__ . '/Schema.php';

function biz_diff_specs(array $old, array $new): array {
    $idx = function($arr, $k) { $m = []; foreach (($arr ?? []) as $r) { if (isset($r[$k])) $m[$r[$k]] = $r; } return $m; };
    $or = $idx($old['requirements'] ?? [], 'id');
    $nr = $idx($new['requirements'] ?? [], 'id');
    $added = array_values(array_diff(array_keys($nr), array_keys($or)));
    $removed = array_values(array_diff(array_keys($or), array_keys($nr)));
    $modified = [];
    foreach (array_intersect(array_keys($or), array_keys($nr)) as $id) {
        if (json_encode($or[$id]) !== json_encode($nr[$id])) $modified[] = $id;
    }
    $oe = $idx($old['entities'] ?? [], 'key');
    $ne = $idx($new['entities'] ?? [], 'key');
    $affected = [];
    foreach (array_intersect(array_keys($oe), array_keys($ne)) as $k) {
        if (json_encode($oe[$k]) !== json_encode($ne[$k])) $affected[] = 'entity:' . $k;
    }
    return ['added' => $added, 'modified' => $modified, 'removed' => $removed,
        'affected' => $affected, 'safe' => empty($removed)];
}
