<?php
declare(strict_types=1);

/* Inline SVG icons used by shared partials and dynamic sections (same markup as the original pages). */

function icon(string $name, string $class = 'icon'): string
{
    static $paths = [
        // 24 × 24
        'chevron-right' => [24, '<path d="M9 6l6 6-6 6"/>'],
        'chevron-down'  => [24, '<path d="M6 9l6 6 6-6"/>'],
        'arrow-right'   => [24, '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>'],
        'menu'          => [24, '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>'],
        'close'         => [24, '<path d="M6 6l12 12"/><path d="M18 6L6 18"/>'],
        'user'          => [24, '<circle cx="12" cy="8" r="4"/><path d="M4 20c1-4 4-6 8-6s7 2 8 6"/>'],
        'phone'         => [24, '<path d="M5 4h3.5l1.8 4.5-2.3 1.4a11 11 0 0 0 6.1 6.1l1.4-2.3L20 15.5V19a1.5 1.5 0 0 1-1.6 1.5C10.9 20 4 13.1 3.5 5.6A1.5 1.5 0 0 1 5 4z"/>'],
        'mail'          => [24, '<rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="M3.5 6.5L12 13l8.5-6.5"/>'],
        'pin'           => [24, '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>'],
        'pin-card'      => [24, '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>'],
        'check'         => [24, '<path d="M5 12.5l4.5 4.5L19 7.5"/>'],
        'play'          => [24, '<path d="M8 5.5v13l10.5-6.5z"/>'],
        // 48 × 48 (homepage stats)
        'stat-license'  => [48, '<path d="M12 5h17l9 9v29H12z"/><path d="M29 5v9h9"/><path d="M18 22h14"/><path d="M18 28h14"/><path d="M18 34h9"/>'],
        'stat-road'     => [48, '<path d="M17 6L6 42"/><path d="M31 6l11 36"/><path d="M24 8v5"/><path d="M24 19v6"/><path d="M24 31v7"/>'],
        'stat-earth'    => [48, '<rect x="4" y="34" width="26" height="8" rx="4"/><path d="M8 34v-9h5l3-6h7v15"/><path d="M17 19v6h6"/><path d="M23 26l10-15 9 7"/><path d="M42 18l2 10h-7z"/>'],
        'stat-depth'    => [48, '<path d="M4 16h9v26h22V16h9"/><path d="M24 21v14"/><path d="M19 30l5 5 5-5"/><path d="M36 4l-5 9"/><path d="M29 11.5l4 2.3"/>'],
    ];
    [$size, $body] = $paths[$name] ?? $paths['chevron-right'];
    return '<svg class="' . $class . '" viewBox="0 0 ' . $size . ' ' . $size . '" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
}
