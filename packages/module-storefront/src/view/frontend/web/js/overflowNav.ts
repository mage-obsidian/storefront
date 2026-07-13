/**
 * How many leading nav items fit in `containerWidth`, following the priority+
 * pattern: if every item fits, they all stay; otherwise room is reserved for the
 * "More" trigger (plus one gap separating it from the last visible item) and as
 * many leading items as fit are kept, the rest going into the More dropdown.
 *
 * Pure and DOM-free so the overflow math is unit-tested; the component owns the
 * measurement (offsetWidth / ResizeObserver).
 */
export function computeVisibleCount(
    itemWidths: number[],
    gap: number,
    moreWidth: number,
    containerWidth: number,
): number {
    const n = itemWidths.length;
    if (n === 0) {
        return 0;
    }

    const totalWithoutMore = itemWidths.reduce(
        (sum, width, i) => sum + width + (i > 0 ? gap : 0),
        0,
    );
    if (totalWithoutMore <= containerWidth) {
        return n;
    }

    // Overflow: reserve the More trigger and the gap before it, then greedily
    // fit leading items. May return 0 (even one item + More does not fit), in
    // which case every item lives in the dropdown.
    const budget = containerWidth - moreWidth - gap;
    let used = 0;
    let count = 0;
    for (let i = 0; i < n; i++) {
        const next = used + itemWidths[i] + (count > 0 ? gap : 0);
        if (next > budget) {
            break;
        }
        used = next;
        count++;
    }
    return count;
}
