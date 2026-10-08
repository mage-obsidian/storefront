// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { watchEffect } from 'vue';
import { useCustomerData } from 'MageObsidian_ModernFrontend::js/customer-data';

const welcome = document.querySelector<HTMLElement>('[data-guest-welcome]');

if (welcome) {
    const customerData = useCustomerData();

    watchEffect(() => {
        welcome.hidden = Boolean(customerData.section('customer')?.firstname);
    });
}
