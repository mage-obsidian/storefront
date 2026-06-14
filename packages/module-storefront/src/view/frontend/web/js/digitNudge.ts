// Optical centering for single-glyph count badges. A circular badge centers the
// digit's advance box, but each glyph's ink sits slightly off within that box
// (font side-bearings). These per-digit horizontal corrections (px, measured from
// Hanken Grotesk's vector metrics at the badge size) re-center the visible ink.
// The vertical value compensates the cap-height/leading offset uniformly.
const H: Record<string, number> = {
    "0": 0,
    "1": 0.36,
    "2": 0,
    "3": 0.05,
    "4": 0,
    "5": 0,
    "6": 0.19,
    "7": 0.02,
    "8": 0,
    "9": 0,
};

const V = 0.6;

/**
 * CSS `translate` value that optically centers a count inside its badge. Single
 * digits get their measured horizontal correction; multi-digit counts are wider
 * and their per-glyph offsets average out, so only the vertical shift applies.
 */
export function digitNudge(count: number | string): string {
    const text = String(count);
    const h = text.length === 1 ? (H[text] ?? 0) : 0;
    return `${h}px ${V}px`;
}

export default digitNudge;
