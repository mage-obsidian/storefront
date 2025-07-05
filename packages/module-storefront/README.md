# MageObsidian — Storefront

Storefront logic for [MageObsidian](https://mage-obsidian.jeanmarcos.dev/). This module hosts the PHP layer that brings **Luma-level functionality** to the modern frontend, paired with the `MageObsidian/default` theme:

- **ViewModels** — the functional equivalents of Luma's blocks/helpers, consumed by the theme's templates.
- **Legacy layout neutralization** — enumerated `<referenceBlock remove="true"/>` overrides (with `<sequence>` over the relevant core modules) that switch off the inert RequireJS/Knockout markup of each migrated module.
- **Shared Vue islands** — reusable interactive components mounted by `renderVueComponent`.

It reuses Magento's native backends (e.g. customer section data at `/customer/section/load`) rather than reimplementing them.

## Documentation

For more details, visit the [official documentation](https://mage-obsidian.jeanmarcos.dev/).
