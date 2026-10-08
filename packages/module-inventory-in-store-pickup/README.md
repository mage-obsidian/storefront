> **Read-only mirror.** This package is developed in [mage-obsidian/storefront](https://github.com/mage-obsidian/storefront) and copied here automatically. Open issues and pull requests there.

# MageObsidian — In-Store Pickup

[![Latest Version](https://img.shields.io/packagist/v/mage-obsidian/module-inventory-in-store-pickup.svg?style=flat-square)](https://packagist.org/packages/mage-obsidian/module-inventory-in-store-pickup)
[![License](https://img.shields.io/packagist/l/mage-obsidian/module-inventory-in-store-pickup.svg?style=flat-square)](https://packagist.org/packages/mage-obsidian/module-inventory-in-store-pickup)

[![Star MageObsidian](https://img.shields.io/github/stars/mage-obsidian/module-modern-frontend?style=flat-square&label=Star%20the%20core%20repo&logo=github)](https://github.com/mage-obsidian/module-modern-frontend)

📚 [Documentation](https://mage-obsidian.jeanmarcos.dev/) · 🚀 [Live demo](https://mage-obsidian-demo.jeanmarcos.dev/) · 💬 [Discussions](https://github.com/mage-obsidian/module-modern-frontend/discussions)

In-store pickup for the [MageObsidian](https://mage-obsidian.jeanmarcos.dev/) checkout: a delivery method selector and a pickup location finder over the Multi-Source Inventory pickup endpoints.

Optional: it requires the Magento Multi-Source Inventory pickup modules, so a store without them does not install it. It builds on the [module-checkout](https://github.com/mage-obsidian/module-checkout) package.

## Installation

```bash
composer require mage-obsidian/module-inventory-in-store-pickup
bin/magento setup:upgrade
bin/magento mage-obsidian:frontend:config --generate
```

Then rebuild your theme (`mage-obsidian:build-themes`). Full guide: [documentation](https://mage-obsidian.jeanmarcos.dev/).

## Support the project

If MageObsidian saves you time, consider [buying me a coffee](https://ko-fi.com/Q5Q816Z9WN). ❤️
