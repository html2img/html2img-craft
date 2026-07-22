# Release Notes for Auto Open Graph Images

## 1.0.0 - 2026-07-22

### Added
- Initial release
- Automatic Open Graph image generation on entry save, rendered by the HTML to Image API through a queued job
- Bundled self-contained default card template, replaceable with any Twig template in your project
- Settings cascade across plugin defaults, per-section overrides and per-entry fields (`ogHeadline`, `ogSubtitle`, `ogDisabled` and a configurable editor image field)
- Input hashing so unchanged saves make no API calls
- CDN URL and asset volume storage modes
- Standalone meta tag output via `craft.ogImages.meta()` and `craft.ogImages.url()`, or seo-field integration for SEOmatic and Ether SEO
- Control panel preview route, settings page preview and a Generate button on the entry edit screen
- `og-images/generate` console command with `--section` and `--force`
