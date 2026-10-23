<?php
/**
 * includes/brand-logo.php — Official //WEBbuilders.lk logo component.
 *
 * Single source of truth for the platform wordmark. Customer-generated
 * sites never include this file, so customer branding stays untouched.
 *
 * Usage:
 *   require_once __DIR__ . '/brand-logo.php';
 *   echo wc_brand_logo();                                  // md, dark text
 *   echo wc_brand_logo(['size' => 'lg']);                  // large
 *   echo wc_brand_logo(['variant' => 'invert']);           // on dark chrome
 *   echo wc_brand_logo(['href' => SITE_URL . '/']);        // linked
 *   echo wc_brand_logo(['tagline' => SITE_TAGLINE]);       // stacked tagline
 *
 * The markup is intentionally text-based (no raster asset required) so it
 * stays crisp at every size and inherits the official brand colours.
 */

if (!function_exists('wc_brand_logo')) {
    /**
     * @param array $opts {
     *   @type string $size    xs|sm|md|lg|xl  (default md)
     *   @type string $variant default|invert  (invert = light text on dark)
     *   @type string $class   extra classes on the lockup span
     *   @type string $href    optional link URL (wraps in <a>)
     *   @type string $tagline optional tagline under the wordmark
     *   @type string $label   accessible label (default //WEBbuilders.lk)
     * }
     */
    function wc_brand_logo(array $opts = []): string
    {
        $size    = strtolower((string)($opts['size'] ?? 'md'));
        $variant = strtolower((string)($opts['variant'] ?? 'default'));
        $href    = isset($opts['href']) ? (string)$opts['href'] : '';
        $tagline = isset($opts['tagline']) ? trim((string)$opts['tagline']) : '';
        $label   = (string)($opts['label'] ?? '//WEBbuilders.lk');
        $extra   = trim((string)($opts['class'] ?? ''));

        if (!in_array($size, ['xs', 'sm', 'md', 'lg', 'xl'], true)) {
            $size = 'md';
        }
        $invert = ($variant === 'invert' || $variant === 'light');

        $classes = 'wc-brand wc-brand--' . $size;
        if ($invert) $classes .= ' wc-brand--invert';
        if ($extra !== '') $classes .= ' ' . $extra;

        $mark = '<span class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
              . '<span class="wc-brand__slash" aria-hidden="true">//</span>'
              . '<span class="wc-brand__web" aria-hidden="true">WEB</span>'
              . '<span class="wc-brand__builders" aria-hidden="true">builders</span>'
              . '<span class="wc-brand__tld" aria-hidden="true">.lk</span>'
              . '</span>';

        if ($href !== '') {
            $mark = '<a class="wc-brand-link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' Home">' . $mark . '</a>';
        }

        if ($tagline === '') {
            return $mark;
        }

        $lockupClass = 'wc-brand-lockup' . ($invert ? ' wc-brand-lockup--invert' : '');
        return '<span class="' . $lockupClass . '">' . $mark
             . '<span class="wc-brand-lockup__tag">' . htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8') . '</span>'
             . '</span>';
    }
}
