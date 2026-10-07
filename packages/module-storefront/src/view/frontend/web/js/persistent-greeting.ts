// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { watchEffect } from 'vue';
import { useCustomerData } from 'MageObsidian_ModernFrontend::js/customer-data';

const GREETING = '[data-persistent-greeting]';
const NAME = '[data-persistent-name]';
const PLACEHOLDER = '%1';

function init(): void {
    const greeting = document.querySelector<HTMLElement>(GREETING);
    if (!greeting) {
        return;
    }
    const customerData = useCustomerData();
    const template = greeting.dataset.welcome ?? PLACEHOLDER;

    const link = greeting.querySelector<HTMLAnchorElement>('[data-persistent-not-you]');
    link?.addEventListener('click', async (event) => {
        event.preventDefault();
        try {
            await fetch(link.href, { credentials: 'same-origin', redirect: 'manual' });
        } catch {
            return window.location.assign(link.href);
        }
        await customerData.reload([]);
        window.location.assign(greeting.dataset.loginUrl || link.href);
    });

    watchEffect(() => {
        const fullname = customerData.section('persistent')?.fullname;
        const signedIn = Boolean(customerData.section('customer')?.firstname);
        const recognised = typeof fullname === 'string' && fullname !== '' && !signedIn;

        if (recognised) {
            const name = greeting.querySelector<HTMLElement>(NAME);
            if (name) {
                name.textContent = template.replace(PLACEHOLDER, fullname);
            }
        }
        greeting.hidden = !recognised;
    });
}

init();
