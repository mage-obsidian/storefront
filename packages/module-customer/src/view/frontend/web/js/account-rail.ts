// Below md the account rail is a horizontal scroll strip. The active entry can
// sit anywhere in it, including off-screen, so bring it into view on load. Purely
// cosmetic: without JS the strip still scrolls by hand.

export function revealActiveRailLink(root: ParentNode): void {
    const list = root.querySelector<HTMLElement>(".account-rail__list");
    const active = list?.querySelector<HTMLElement>('[aria-current="page"]');
    if (!list || !active) {
        return;
    }

    // Vertical on desktop: there is nothing to reveal, and scrolling the page to
    // the rail on every account page load would fight the user.
    if (list.scrollWidth <= list.clientWidth) {
        return;
    }

    list.scrollTo({
        left: active.offsetLeft - (list.clientWidth - active.offsetWidth) / 2,
        behavior: "instant" as ScrollBehavior,
    });
}

const rail = document.querySelector(".account-rail");
if (rail) {
    revealActiveRailLink(rail);
}
