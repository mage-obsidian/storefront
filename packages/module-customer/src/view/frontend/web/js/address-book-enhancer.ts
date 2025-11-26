// Progressive enhancement for the address book: a confirm dialog before a delete
// form submits. Without JS the delete still works (native POST + form key); this
// only guards against an accidental click. confirmFn is injected for testing.

export function enhanceDeleteForms(
    root: ParentNode,
    confirmFn: (message: string) => boolean = window.confirm.bind(window),
): void {
    const forms = root.querySelectorAll<HTMLFormElement>("[data-delete-address]");
    for (const form of forms) {
        form.addEventListener("submit", (event) => {
            const trigger = form.querySelector<HTMLButtonElement>("[data-confirm]");
            const message = trigger?.dataset.confirm ?? "";
            if (message !== "" && !confirmFn(message)) {
                event.preventDefault();
            }
        });
    }
}

const container = document.querySelector("[data-address-book]");
if (container) {
    enhanceDeleteForms(container);
}
