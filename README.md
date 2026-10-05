# MageObsidian storefront

Development monorepo of the MageObsidian storefront: the Magento-area modules and `theme-base`. Each directory under `packages/` is split on every push to its own read-only repository under `mage-obsidian/`, which is what Packagist publishes.

The JavaScript test harnesses import framework sources: clone `mage-obsidian/framework` next to this repository, or point `MAGE_OBSIDIAN_FRAMEWORK_DIR` at a checkout.

All packages share one version. Release with `bin/release X.Y.Z`, after the framework release it requires.
