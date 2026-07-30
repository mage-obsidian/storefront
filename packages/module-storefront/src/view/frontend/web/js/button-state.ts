const BUSY_ATTRIBUTE = 'data-obsidian-busy';
const BUSY_CLASS = 'is-loading';
const LABEL_CLASS = 'btn__label';
const SPINNER_CLASS = 'btn__spinner';

export function setButtonBusy(button: HTMLButtonElement | null, busy: boolean): void {
    if (!button) {
        return;
    }

    if (busy) {
        if (button.hasAttribute(BUSY_ATTRIBUTE)) {
            return;
        }
        button.setAttribute(BUSY_ATTRIBUTE, button.innerHTML);
        button.setAttribute('aria-busy', 'true');
        button.classList.add(BUSY_CLASS);
        button.disabled = true;
        button.innerHTML =
            `<span class="${LABEL_CLASS}">${button.getAttribute(BUSY_ATTRIBUTE)}</span>` +
            `<span class="${SPINNER_CLASS}"></span>`;
        return;
    }

    const label = button.getAttribute(BUSY_ATTRIBUTE);
    if (label === null) {
        return;
    }
    button.innerHTML = label;
    button.removeAttribute(BUSY_ATTRIBUTE);
    button.removeAttribute('aria-busy');
    button.classList.remove(BUSY_CLASS);
    button.disabled = false;
}
