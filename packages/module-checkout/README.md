# MageObsidian — Checkout

[![Latest Version](https://img.shields.io/packagist/v/mage-obsidian/module-checkout.svg?style=flat-square)](https://packagist.org/packages/mage-obsidian/module-checkout)
[![License](https://img.shields.io/packagist/l/mage-obsidian/module-checkout.svg?style=flat-square)](https://packagist.org/packages/mage-obsidian/module-checkout)

[![Star MageObsidian](https://img.shields.io/github/stars/mage-obsidian/module-modern-frontend?style=flat-square&label=Star%20the%20core%20repo&logo=github)](https://github.com/mage-obsidian/module-modern-frontend)

📚 [Documentation](https://mage-obsidian.jeanmarcos.dev/) · 🚀 [Live demo](https://mage-obsidian-demo.jeanmarcos.dev/) · 💬 [Discussions](https://github.com/mage-obsidian/module-modern-frontend/discussions)

Checkout domain compatibility for [MageObsidian](https://mage-obsidian.jeanmarcos.dev/). This module adapts Magento's cart frontend (mini-cart and the shopping bag page) to the modern Vite + Twig + Vue stack, building on the `mage-obsidian/module-storefront` foundation and paired with the `MageObsidian/default` theme:

- **Mini-cart** — `cart/MiniCart`: an off-canvas drawer (reusing the storefront's shared `Drawer`) fed by Magento's `cart` customer-data section, with quantity stepper and line removal.
- **Cart page** — re-declares `checkout_cart_index`, reusing core `Cart` / `Totals` / `Coupon` block classes as data sources behind Twig: server-rendered items, totals and coupon with a progressive-enhancement enhancer.
- **Block** — `CartTitle`: restores the `<h1>`/title that the suppressed core checkout layout would have provided.

It depends on the storefront foundation for cross-cutting cart primitives (`useCart`, the shared `Drawer`, the toast/badge islands). The one-page checkout itself is out of scope for this module.
