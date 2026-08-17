# Auto Open Graph Images for Craft CMS

> **You need an API key.** Accounts are free and include a monthly allowance of credits that renews automatically. **[Create your free account at app.html2img.com](https://app.html2img.com/register)**, copy your key and you are ready to render.

Auto Open Graph Images generates social share images for your Craft CMS entries with the [HTML to Image API](https://html2img.com). You design the card as an ordinary Twig template in your own project, with your own fonts and CSS. When an entry is saved, a queued job renders that template against the entry, sends the HTML to the API and stores the finished PNG. Real Chrome does the rendering, so what you see in your browser is what lands in the image.

No canvas editors, no drag and drop card builders, no per-seat pricing. A template, a queue job and a meta tag.

- Renders on entry save through the Craft queue, so saving stays fast
- One template for the whole site, per-section overrides where you need them
- Editors can override the headline, the subtitle or the whole image per entry
- Input hashing means routine saves that change nothing spend no credits
- Store the CDN URL the API returns, or download the PNG into an asset volume
- Emit the meta tags yourself or hand the image to SEOmatic or Ether SEO
- A live browser preview and a Generate button in the control panel, no key needed to design

## Requirements

- Craft CMS 5.10 or later
- PHP 8.3 or later
- An HTML to Image API key. The free tier renews monthly, and the [pricing page](https://html2img.com/pricing) covers paid volumes.

## Installation

Install the plugin with Composer, then install it in Craft:

```bash
composer require html2img/craft-og-images
php craft plugin/install og-images
```

Add your API key to `.env`:

```bash
HTML2IMG_API_KEY="your-key-here"
```

The key travels in the `X-API-Key` header, as described in the [authentication docs](https://html2img.com/docs/authentication). Requests go through the official [html2img/html2img-php](https://github.com/html2img/html2img-php) SDK, the same client covered in the [PHP integration guide](https://html2img.com/integrations/php/).

Then open **Settings → Auto Open Graph Images** in the control panel, choose the sections to generate images for and save. From that point every save of an entry in those sections queues a render.

## Designing your card

The card is a normal Twig template in your project's `templates/` folder. Point the **Template** setting at it, for example `_og/card`. Until then the bundled default at `og-images/_default` is used, a self-contained 1200 by 630 card with Google Fonts, an adaptive headline size and your site name and date.

Templates receive:

| Variable | Value |
| --- | --- |
| `entry` | The entry element. Every native and custom field works as normal. |
| `ogHeadline` | The entry's `ogHeadline` field if present and filled, otherwise the title. |
| `ogSubtitle` | The entry's `ogSubtitle` field if present and filled, otherwise null. |
| `siteName` | The **Site name** setting, falling back to the Craft site name. |
| `siteLogo` | The **Site logo URL** setting. |

Set the page size with `width: 100vw; height: 100vh` on the body and the card fills whatever dimensions you configure. For layout ideas, the [template gallery](https://html2img.com/templates) collects ready-made cards and the [Open Graph image template](https://html2img.com/templates/open-graph-image) is a good starting point to adapt.

### Per-entry overrides

Editors override behaviour through plain fields on the entry type. The plugin looks for these handles by convention:

| Field handle | Type | Effect |
| --- | --- | --- |
| `ogHeadline` | Plain text | Replaces the title in the card. |
| `ogSubtitle` | Plain text | Passed to the template as `ogSubtitle`. |
| `ogDisabled` | Lightswitch | Skips generation for the entry while on. |

There is one more, named by you rather than by convention: set **Editor image field** to the handle of an asset field, and whenever an editor picks an image there it wins over the generated one in the output. Add whichever fields you need to the entry type; none of them are required.

### Previewing

The control panel has a preview route at `admin/og-images/preview` that renders your template at the configured dimensions with sample data, or against a real entry with `?entryId=123`. The settings page embeds it, so you can iterate on the template and refresh. No API key is involved, because your browser renders the same HTML the API's Chrome does.

On each entry's edit screen a sidebar panel shows the current generated image and a **Generate** button that performs a real API render immediately, ignoring the input hash. That is the parity check between browser preview and rendered PNG.

## Local development and public URLs

The API renders your HTML on its own servers, so every URL in the template must be reachable from the public internet. An image at `https://my-site.ddev.site/logo.png` renders fine in your browser preview and comes out missing in the PNG.

Remedies:

- Host template assets somewhere public and reference absolute URLs
- Google Fonts always work, since they load from Google's CDN
- Inline small images as data URIs
- Expose your dev site with a tunnel such as `ddev share` while testing

The bundled default template is fully self-contained, so first renders work on any dev site.

## Configuration

Settings live in the control panel, and a `config/og-images.php` file overrides them per environment. Every option:

| Setting | Default | What it does |
| --- | --- | --- |
| `apiKey` | `$HTML2IMG_API_KEY` | Your API key, normally an environment variable reference. |
| `sections` | `[]` | Section handles that generate images on save. |
| `template` | `og-images/_default` | The Twig template rendered for each card. |
| `width` | `1200` | Viewport width in CSS pixels. |
| `height` | `630` | Viewport height in CSS pixels. |
| `dpi` | `2` | Device pixel ratio. 2 doubles the output pixels for crisp text. |
| `overrides` | `[]` | Per-section overrides for template, width, height and dpi. |
| `storage` | `cdn` | `cdn` stores the returned URL, `asset` downloads the PNG. |
| `volume` | `null` | Volume handle used by asset storage. |
| `integration` | `standalone` | `standalone` emits meta tags, `seo-field` fills an asset field. |
| `seoField` | `null` | Asset field handle written to in seo-field mode. |
| `siteName` | `''` | Passed to templates. Falls back to the Craft site name. |
| `siteLogo` | `null` | Absolute logo URL passed to templates. |
| `fallbackImage` | `null` | Absolute URL used when an entry has no image at all. |
| `imageField` | `''` | Asset field handle an editor can use to override the output. |
| `regenerateOnResave` | `false` | Also regenerate during bulk resave operations. |

A config file example with a per-section override:

```php
<?php

return [
    'sections' => ['blog', 'caseStudies'],
    'template' => '_og/card',
    'overrides' => [
        'caseStudies' => [
            'template' => '_og/case-study',
            'height' => 675,
        ],
    ],
];
```

Images are generated per entry per site, keyed against the site the entry was saved in.

## Output modes

### Standalone

Drop the meta tag into your layout's `<head>`:

```twig
{% if entry is defined and entry %}
    {{ craft.ogImages.meta(entry) }}
{% endif %}
```

That emits `og:image`, `og:image:width`, `og:image:height`, `og:image:alt`, `og:image:type`, `twitter:card` and `twitter:image`, resolving the cascade in order: the editor's chosen image if set, then the generated image, then the site fallback image.

Need just the URL, for a JSON feed or a newsletter template?

```twig
{{ craft.ogImages.url(entry) }}
```

### SEO plugin field

If SEOmatic or Ether SEO already owns your `<head>`, set **Integration** to `seo-field` and name an asset field. The plugin writes each generated image into that field and emits nothing itself. Point your SEO plugin's social image at the same field and it keeps full control of the markup. This mode always stores images as assets.

## Storage modes

**cdn** (default) stores the `i.html2img.com` URL the API returns. The frontend outputs a plain URL, which keeps pages static-cache friendly and adds no weight to your server.

**asset** downloads each PNG into the volume you choose and stores the asset instead, so the site has no runtime dependency on the API. Regenerating replaces the existing asset's file in place, keeping references to it valid.

## Bulk regeneration

```bash
php craft og-images/generate
php craft og-images/generate --section=blog
php craft og-images/generate --force
```

The command walks the enabled sections, prints a line per entry and finishes with a summary. Entries whose render inputs are unchanged are skipped thanks to the input hash, so a plain run only spends credits on entries that actually changed. `--force` re-renders everything regardless.

Bulk resaves such as `resave/entries` are ignored by default for the same reason. Turn on **Regenerate on bulk resaves** if you want them included.

## Asynchronous rendering

Synchronous renders have a 30 second budget, which a card template never approaches, and the queue keeps even that away from editors. For very large captures the API also supports webhook delivery through the [webhook_url parameter](https://html2img.com/docs/parameters/webhook-url). This plugin does not use it, but it is there if you outgrow synchronous renders in your own integrations.

## Development

The repository is a plain Composer package. The comfortable way to work on it is against a [ddev](https://ddev.com) Craft site with a path repository in its `composer.json`:

```json
"repositories": [
    { "type": "path", "url": "../craft-og-images" }
]
```

Then `composer require html2img/craft-og-images:@dev` symlinks the plugin into the site and edits are live immediately.

```bash
composer test      # Pest unit tests
composer phpstan   # static analysis
composer check-cs  # code style
composer fix-cs    # fix code style
```

## Links

[HTML to Image API](https://html2img.com) · [Screenshot API](https://html2img.com/screenshot-api/) · [HTML to PDF API](https://html2img.com/html-to-pdf/) · [Documentation](https://html2img.com/docs) · [Craft CMS guide](https://html2img.com/integrations/craft/) · [Templates](https://html2img.com/templates) · [Pricing](https://html2img.com/pricing) · [PHP SDK](https://github.com/html2img/html2img-php)

## Licence

MIT, see [LICENSE](LICENSE).
