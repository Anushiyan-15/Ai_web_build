<?php
// includes/HtmlUtil.php — shared, safe HTML tail insertion helpers.
//
// Generated/AI pages routinely contain the literal string "</body>" inside
// JavaScript template literals (print-ticket builders etc.). The old
// preg_replace('/<\/body>/', $x . '</body>', $html, 1) / str_ireplace()
// approach matched that INLINE STRING first, injecting a <script> into the
// middle of another script. The nested </script> then terminated the real
// runtime early, leaving the AI mock in charge (fake BK- refs, no backend
// writes). Always insert before the LAST real </body> of the document.

if (!function_exists('wc_insert_before_body_end')) {
    /**
     * Insert a chunk immediately before the document's final </body>.
     * Falls back to appending when no body close tag exists.
     */
    function wc_insert_before_body_end(string $html, string $insert): string {
        $pos = strripos($html, '</body');
        if ($pos === false) return $html . "\n" . $insert;
        return substr($html, 0, $pos) . $insert . "\n" . substr($html, $pos);
    }
}
