/**
 * Hex color to OKLch conversion and palette generation.
 *
 * Converts admin-configured hex colors into OKLch CSS variable overrides
 * for the auth page theming system.
 */

/** Parse hex (#rrggbb) to linear RGB [0..1]. */
function hexToLinearRgb(hex) {
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;

    // sRGB → linear
    const toLinear = (c) => (c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
    return [toLinear(r), toLinear(g), toLinear(b)];
}

/** Linear RGB → OKLab. */
function linearRgbToOklab(rgb) {
    const [r, g, b] = rgb;

    const l_ = 0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b;
    const m_ = 0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b;
    const s_ = 0.0883024619 * r + 0.2220049412 * g + 0.6736926970 * b;

    const l = Math.cbrt(l_);
    const m = Math.cbrt(m_);
    const s = Math.cbrt(s_);

    return [
        0.2104542553 * l + 0.7936177850 * m - 0.0040720468 * s,
        1.9779984951 * l - 2.4285922050 * m + 0.4505937099 * s,
        0.0259040371 * l + 0.7827717662 * m - 0.8086757660 * s,
    ];
}

/** OKLab → OKLch. */
function oklabToOklch(lab) {
    const [L, a, b] = lab;
    const C = Math.sqrt(a * a + b * b);
    let h = (Math.atan2(b, a) * 180) / Math.PI;
    if (h < 0) h += 360;
    return [L, C, h];
}

/** Convert hex to OKLch values [L, C, h]. */
export function hexToOklch(hex) {
    if (!hex || !hex.match(/^#[0-9a-fA-F]{6}$/)) return [0.5, 0, 0];
    const linear = hexToLinearRgb(hex);
    const lab = linearRgbToOklab(linear);
    return oklabToOklch(lab);
}

/** Format OKLch values as CSS `oklch(L C h)` string. */
function oklch(L, C, h) {
    return `oklch(${L.toFixed(3)} ${C.toFixed(3)} ${h.toFixed(3)})`;
}

/**
 * Generate full CSS variable palette from branding config.
 *
 * @param {object} config - Branding settings (primary_color, background_color, dark_mode, font_family, border_radius)
 * @returns {Record<string, string>} CSS variable name → value map
 */
export function generatePalette(config) {
    const vars = {};
    const {
        primary_color = '#8b5320',
        background_color = '#f5f5f4',
        dark_mode = false,
        font_family = 'system-ui',
        border_radius = 8,
    } = config || {};

    const [pL, pC, pH] = hexToOklch(primary_color);
    const [bgL, bgC, bgH] = hexToOklch(background_color);

    if (dark_mode) {
        // Dark mode palette
        vars['--background'] = oklch(0.15, bgC * 0.3, bgH);
        vars['--foreground'] = oklch(0.95, 0.005, bgH);
        vars['--card'] = oklch(0.20, bgC * 0.2, bgH);
        vars['--card-foreground'] = oklch(0.95, 0.005, bgH);
        vars['--popover'] = oklch(0.20, bgC * 0.2, bgH);
        vars['--popover-foreground'] = oklch(0.95, 0.005, bgH);
        vars['--muted'] = oklch(0.25, bgC * 0.15, bgH);
        vars['--muted-foreground'] = oklch(0.65, 0.01, bgH);
        vars['--accent'] = oklch(0.25, bgC * 0.15, bgH);
        vars['--accent-foreground'] = oklch(0.95, 0.005, bgH);
        vars['--secondary'] = oklch(0.25, 0.005, bgH);
        vars['--secondary-foreground'] = oklch(0.90, 0.005, bgH);
        vars['--border'] = oklch(0.30, bgC * 0.1, bgH);
        vars['--input'] = oklch(0.30, bgC * 0.1, bgH);
        vars['--ring'] = oklch(0.50, 0.01, bgH);

        // Boost primary lightness for dark surfaces
        const boostedL = Math.min(pL + 0.15, 0.85);
        vars['--primary'] = oklch(boostedL, pC, pH);
        vars['--primary-foreground'] = boostedL > 0.6 ? oklch(0.15, pC * 0.1, pH) : oklch(0.98, 0.005, pH);
    } else {
        // Light mode palette
        vars['--background'] = oklch(bgL, bgC, bgH);
        vars['--foreground'] = oklch(0.147, 0.004, bgH);
        vars['--card'] = oklch(1, 0, 0);
        vars['--card-foreground'] = oklch(0.147, 0.004, bgH);
        vars['--popover'] = oklch(1, 0, 0);
        vars['--popover-foreground'] = oklch(0.147, 0.004, bgH);
        vars['--muted'] = oklch(Math.min(bgL + 0.02, 0.97), bgC * 0.5, bgH);
        vars['--muted-foreground'] = oklch(0.55, 0.013, bgH);
        vars['--accent'] = oklch(Math.min(bgL + 0.02, 0.97), bgC * 0.5, bgH);
        vars['--accent-foreground'] = oklch(0.216, 0.006, bgH);
        vars['--secondary'] = oklch(0.967, 0.001, bgH);
        vars['--secondary-foreground'] = oklch(0.21, 0.006, bgH);
        vars['--border'] = oklch(0.923, bgC * 0.3, bgH);
        vars['--input'] = oklch(0.923, bgC * 0.3, bgH);
        vars['--ring'] = oklch(0.709, 0.01, bgH);

        vars['--primary'] = oklch(pL, pC, pH);
        vars['--primary-foreground'] = pL > 0.6 ? oklch(0.15, pC * 0.1, pH) : oklch(0.987, 0.022, pH);
    }

    // Font and radius
    if (font_family && font_family !== 'system-ui') {
        vars['--font-sans'] = `"${font_family}", ui-sans-serif, system-ui, -apple-system, sans-serif`;
    }

    vars['--radius'] = `${border_radius / 16}rem`;

    return vars;
}

/**
 * Check if a hex color is "light" (L > 0.6 in OKLch).
 */
export function isLightColor(hex) {
    const [L] = hexToOklch(hex);
    return L > 0.6;
}
