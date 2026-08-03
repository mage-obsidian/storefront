# MageObsidian — Showcase

Lets a visitor turn MageObsidian features on and off for their own visit, so a
demo store can **show** what each one does instead of describing it.

Not on Packagist on purpose. It is installed straight from its repository on the
machine that runs the demo; nothing reaches it by `composer require`.

## What it does

A panel in the corner of the storefront lists every switchable feature with the
value in force right now, and lets the visitor change it. The change belongs to
that visitor: nobody else's session moves, and the store's own configuration is
untouched.

```
Cacheable listing fragments   SEARCH        [ On  ▾ ]
Stock visualizer              INVENTORY…    [ On  ▾ ]
Checkout layout               MODERNFRONT…  [ One page ▾ ]
```

A store view still sets the defaults — that is what a shareable "edition" of the
demo is. The panel only says where this visitor departs from it.

## Enabling it

The switch is a **deployment** setting, in `app/etc/env.php`:

```php
'mage_obsidian' => [
    'showcase_enabled' => true,
],
```

Not an admin field, and that is deliberate: this module rewrites configuration
from a cookie, so the decision to allow that belongs somewhere a store operator
cannot reach by accident and the module itself can never rewrite. With the key
absent the whole module is inert — no panel, no overrides, no vary.

## Declaring a feature

`etc/di.xml` is the registry, and it is also the **allowlist**: a config path
that is not declared there can never be reached by a visitor's cookie, and a
value the feature does not accept is dropped rather than passed through.

```xml
<type name="MageObsidian\Showcase\Model\FeaturePool">
    <arguments>
        <argument name="features" xsi:type="array">
            <item name="listing_fragments" xsi:type="array">
                <item name="path" xsi:type="string">mage_obsidian/listing/fragments_enabled</item>
                <item name="module" xsi:type="string">MageObsidian_Search</item>
                <item name="label" xsi:type="string" translate="true">Cacheable listing fragments</item>
                <item name="description" xsi:type="string" translate="true">Only the regions that change are fetched.</item>
            </item>
        </argument>
    </arguments>
</type>
```

`type` defaults to `flag` (on/off); `choice` takes an `options` map instead. An
entry whose `module` is not installed is skipped, so a demo can carry the switch
for a module it has not added yet. Nothing declares itself — a feature's own
module knows nothing about this one.

**The registry has to stay in the global `etc/di.xml`.** The pool is built the
first time anything reads configuration, which happens while the area's own DI
has not been applied yet; declared in `etc/frontend/di.xml` the argument arrives
too late and the pool holds nothing for the rest of the request.

## How a feature gets switched

Every MageObsidian flag is read through `ScopeConfigInterface`, so one plugin on
`getValue` covers all of them — including the ones a layout gates with
`ifconfig`, which resolves through `isSetFlag` and therefore through the same
call. A feature is switchable exactly to the extent that its module reads a
config path; anything wired unconditionally into `di.xml` or layout XML is out of
reach, of this and of any other mechanism.

## Caching

The visitor's profile is added to `Magento\Framework\App\Http\Context`, the same
way core varies a page by customer group, so it lands in `X-Magento-Vary` and
every cache in the path keys on it. Two visitors with different profiles get
different cached pages; the same profile reached by different clicks is one cache
entry, because the profile is canonicalised before it is used.

Only what departs from the store view travels. A choice that merely repeats the
store's own value is left out of the cookie, the shared link and the cache key.

**Picking a value navigates rather than reloads.** The full page cache resolves
its key before the request is dispatched, so a plain reload would still be keyed
on the profile the visitor is leaving and would hand back the page they were
trying to change. Going through `?showcase=…` is a URL the cache has never seen:
it renders the new profile and, on the way out, stamps the vary cookie that every
later navigation is keyed on. The parameter is then taken off the address bar.

## Sharing

**Copy link** produces a URL carrying the current profile. Opening it renders
that setup on the first paint and adopts it, which is how a link in a chat hands
someone the exact configuration being discussed.

## Limits

- Everything switchable must be **installed**. Magento cannot enable a module per
  store view, so a demo carrying a module's switch is a demo carrying its code —
  and on a public demo, its compiled JS and CSS are served to anyone.
- A cookie can only move what the registry declares. That is the security
  boundary, and it is the reason the registry is not open-ended.
