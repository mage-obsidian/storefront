# MageObsidian — Search

[![Latest Version](https://img.shields.io/packagist/v/mage-obsidian/module-search.svg?style=flat-square)](https://packagist.org/packages/mage-obsidian/module-search)
[![License](https://img.shields.io/packagist/l/mage-obsidian/module-search.svg?style=flat-square)](https://packagist.org/packages/mage-obsidian/module-search)

[![Star MageObsidian](https://img.shields.io/github/stars/mage-obsidian/module-modern-frontend?style=flat-square&label=Star%20the%20core%20repo&logo=github)](https://github.com/mage-obsidian/module-modern-frontend)

📚 [Documentation](https://mage-obsidian.jeanmarcos.dev/) · 🚀 [Live demo](https://mage-obsidian-demo.jeanmarcos.dev/) · 💬 [Discussions](https://github.com/mage-obsidian/module-modern-frontend/discussions)

Filtering, sorting and paging a category or search listing stop re-assembling the
whole page. The first view is still server-rendered HTML; every interaction after
it fetches only the regions that change.

## What it actually does

A listing URL with `?obsidian_fragment=1` runs the **native controller in full** —
category registered, layer resolved, custom design applied, layout built — and
then, instead of rendering the page, answers with the regions that change:

```json
{ "sections": { "filters": "<div …>", "listing": "<div …>" } }
```

The HTML is produced by the page's own blocks, classes and templates, so it
cannot drift from the markup it replaces. Prices in particular come out of the
renderer pool the page uses rather than being re-derived from raw numbers.

Nothing here is required for the listing to work. Every filter, sort and pager
control is a real link or a `<select>` whose options are real URLs; without JS,
with the feature off, or after a failed exchange the same click navigates.

## Measured

Magento 2.4.8, production mode with an optimised classmap and opcache, Luma
sample data, p50 of 15 uncached requests measured at nginx:

| | page | fragment | |
|---|---:|---:|---|
| Category listing | 94,4 ms · 152 KB | **75,7 ms · 117 KB** | −20 % |
| Category, one filter applied | 129,7 ms · 105 KB | **115,5 ms · 66 KB** | −11 % |
| Category, page 2 | 91,6 ms · 152 KB | **75,0 ms · 117 KB** | −18 % |
| Search results | 92,9 ms | **76,8 ms** | −17 % |

The saving is page assembly, not data: the layout is still generated in full so
that the fragment is the page's own markup — only the head, the chrome and the
page template are skipped.

## Install

```bash
composer require mage-obsidian/module-search
bin/magento module:enable MageObsidian_Search
bin/magento setup:upgrade
```

On by default. **Stores → Configuration → MageObsidian → Frontend → Product
Listing** turns it off; `?obsidian_data=0` turns it off for one visit.

## Theme contract

The module swaps regions the theme marks. `mage-obsidian/theme-default` already
carries them; a custom theme needs one attribute per region:

| Section | Where the attribute goes | Page |
|---|---|---|
| `filters` | root of `Magento_LayeredNavigation::layer/view.twig`, **outside** its visibility check | both |
| `listing` | root of `Magento_Catalog::product/list.twig` | category |
| `results` | root of `Magento_CatalogSearch::result.twig` | search |

```twig
<div data-obsidian-section="listing"> … </div>
```

The attribute must wrap the block's whole output, including its empty state — a
filter combination with no results still has to leave something to swap back.

Search marks `result.twig` rather than the grid inside it on purpose: that
template asks for the result count before rendering the grid, and that call is
what settles the toolbar's sort state. A fragment built from the grid alone
reproduces the markup of a page that was never rendered that way.

## Animation

A swap runs inside `document.startViewTransition`, so the exchange is animated
rather than snapped in. The choreography is entirely CSS, scoped to the
`obsidian-listing-swap` class the navigator holds on `<html>` while a swap is in
flight — nothing here reaches a full page navigation.

- Products on both sides of the change **travel** to their new slot. That comes
  free from the `view-transition-name: product-<id>` the theme already puts on
  every card, which is also why the effect is worth having: reordering a grid is
  the one moment where a listing can show the visitor that nothing was replaced,
  only rearranged.
- Arriving and leaving cards get their own animation. The navigator tags them,
  because it is the only place that knows which side of the swap a product was
  on: it reads the names out of the fragment before applying it and compares.
- The sidebar and the two toolbars fade out and then in rather than crossing
  over. Their two states carry different text in the same place — a filter group
  disappears, `1-12 of 13` becomes `1 item` — and overlapping them reads as
  double vision.
- Everything else is held perfectly still: the old root snapshot is dropped, so
  the header, breadcrumbs and page title never cross-fade against themselves.

A theme gets all of this by carrying the names above on `.product-item` and the
`toolbar-top` / `toolbar-bottom` classes on its toolbars. Carrying neither is not
an error: the swap still animates, just as one crossfade instead of many parts.

It steps aside where an animation would be wrong: browsers without the API, a
visitor with `prefers-reduced-motion` (the theme neutralises every
`::view-transition-*` animation), and while a modal `<dialog>` is open — the
mobile filter drawer lives in the top layer, which is painted into neither
snapshot, so transitioning around it would blink the drawer out of existence.

Scrolling, when a swap needs it, happens **before** the transition opens, so both
snapshots share a viewport and a surviving card moves one slot instead of the
length of the page.

## Extending

`SectionPool` is the whole contract. A module that adds a region to a listing
declares it:

```xml
<type name="MageObsidian\Search\Model\Fragment\SectionPool">
    <arguments>
        <argument name="sections" xsi:type="array">
            <item name="catalog_category_view" xsi:type="array">
                <item name="promo" xsi:type="string">my.promo.block</item>
            </item>
        </argument>
    </arguments>
</type>
```

**Declaration order is render order, and it has to match the page.** The sidebar
sits before the content in every two-column layout, and rendering the layered
navigation is what settles the layer before the grid asks it anything; listing
first and the toolbar comes back with a different sort and page size than the
page had. Set a block name to an empty string to switch a section off.

`mageobsidian_listing_fragment` is a layout handle added before the layout is
built; it removes the page furniture. Override it if a servable region of yours
lives in a container it drops.

The exchange announces itself on the event bus as `listing_navigate_before`,
`_after` and `_failed` (mirrored on `window` as `obsidian:listing_navigate_*`).
`_after` is what re-binds enhancers whose elements were just replaced.

## Failure behaviour

- A section whose block the layout did not produce → the whole request fails:
  HTTP 500, `{"error":true}`, `no-store`, `critical` in the log. Never half a
  listing.
- The client swaps all sections or none, then falls back to a full navigation.
  Two dead exchanges in a row and it unbinds itself for the rest of the visit.
- A forward, a redirect or a 404 answers as a page; the client sees no JSON and
  navigates.

## Caching

The fragment is exactly as cacheable as the page it comes from: the same layout
decides it, and `X-Magento-Tags` is stamped from the same blocks, so a product or
category save invalidates both. Fragments are separate cache objects from the
pages, and the client sorts the query string so one set of filters is one object
however the visitor clicked into it.

Cardinality is the limit, not configuration: a single category with 13 filter
groups has more combinations than any cache can hold. What caches is the head of
the distribution — one or two filters — which is what the traffic actually is.

## Known limits

- A **static-only category** (display mode PAGE) renders no listing region, so
  the fragment would produce one the page does not have. Unreachable in practice:
  such a page has no filter, sort or pager control to intercept, and the
  all-or-nothing swap falls back if one is ever requested.
- Search results with any parameter beyond the query are marked `no-cache` by
  Magento itself (`CatalogSearch\Controller\Result\Index::getNotCacheableResult`).
  That applies to the page too; fragments inherit it.

## Documentation

For more details, visit the [official documentation](https://mage-obsidian.jeanmarcos.dev/).
