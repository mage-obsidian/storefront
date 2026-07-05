# MageObsidian — Catalog

[![Latest Version](https://img.shields.io/packagist/v/mage-obsidian/module-catalog.svg?style=flat-square)](https://packagist.org/packages/mage-obsidian/module-catalog)
[![License](https://img.shields.io/packagist/l/mage-obsidian/module-catalog.svg?style=flat-square)](https://packagist.org/packages/mage-obsidian/module-catalog)

📚 [Documentation](https://mage-obsidian.jeanmarcos.dev/) · 🚀 [Live demo](https://mage-obsidian-demo.jeanmarcos.dev/) · 💬 [Discussions](https://github.com/mage-obsidian/module-modern-frontend/discussions)

Catalog domain compatibility for [MageObsidian](https://mage-obsidian.jeanmarcos.dev/). This module adapts Magento's catalog frontend (category listing and product detail page) to the modern Vite + Twig + Vue stack, building on the `mage-obsidian/module-storefront` foundation and paired with the `MageObsidian/default` theme:

- **ViewModels** — `ProductCard`, `ProductView`, `ProductGallery`, `CategoryLanding`: the data the catalog templates read.
- **Blocks** — `CategoryTitle`, `Breadcrumbs`: restore the `<h1>`/title and trail that the suppressed core catalog layout would have provided.
- **Layout** — re-declares `catalog_category_view` / `catalog_product_view` reusing core block classes as data sources behind Twig.
- **Vue island** — `catalog/ProductForm`: configurable swatch selection and add-to-cart (delegating to the storefront's `useCart`).
- **Gallery enhancer** — `js/gallery`: thumb/variant image swaps with the View Transitions API.

It depends on the storefront foundation for cross-cutting cart primitives (`useCart`, the add-to-cart enhancer, the toast/badge islands).
