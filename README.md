# MageObsidian storefront

Development monorepo of the MageObsidian storefront: the Magento-area modules and `theme-base`. Each directory under `packages/` is split on every push to its own read-only repository under `mage-obsidian/`, which is what Packagist publishes.

The JavaScript test harnesses import framework sources: clone `mage-obsidian/framework` next to this repository, or point `MAGE_OBSIDIAN_FRAMEWORK_DIR` at a checkout.

All packages share one version. Release with `bin/release X.Y.Z`, after the framework release it requires.

`tools/data/core-uncacheable-handles.json` lists the layout handles that Magento core marks `cacheable="false"`. Under an Obsidian theme the core layouts are not loaded, so `StorefrontUncacheableHandlesTest` fails when the storefront implements one of those handles without a mark of its own, or when a page listed in `tools/data/uncacheable-handle-policy.json` loses it. Regenerate the list with `bin/core-uncacheable-handles <magento root>` whenever the newest supported Magento release changes, and review the diff.
