const SAME_WIDTH = '[data-content-type="buttons"][data-same-width="true"]';
const BUTTON_ITEM = '[data-content-type="button-item"]';
const BUTTON = '[data-element="link"], [data-element="empty_link"], a, button';

export function widestOf(widths: number[]): number {
    return widths.reduce((widest, width) => (width > widest ? width : widest), 0);
}

export function buttonsIn(group: Element): HTMLElement[] {
    return Array.from(group.querySelectorAll<HTMLElement>(BUTTON_ITEM))
        .map((item) => item.querySelector<HTMLElement>(BUTTON))
        .filter((button): button is HTMLElement => button !== null);
}

export function equaliseButtons(group: Element): void {
    const buttons = buttonsIn(group);
    if (buttons.length < 2) {
        return;
    }

    buttons.forEach((button) => {
        button.style.width = "";
    });

    const widest = widestOf(buttons.map((button) => button.getBoundingClientRect().width));
    if (widest === 0) {
        return;
    }

    buttons.forEach((button) => {
        button.style.width = `${widest}px`;
    });
}

export function enhanceButtons(root: ParentNode = document): void {
    root.querySelectorAll<HTMLElement>(SAME_WIDTH).forEach(equaliseButtons);
}

enhanceButtons();
