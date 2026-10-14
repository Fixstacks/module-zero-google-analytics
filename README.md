# Zero_GoogleAnalytics

GA4 e-commerce events for the Zero storefront, sent
with gtag.js (GA4 directly) or through a Google Tag Manager container, with
Google Consent Mode v2 following the visitor's choice in Zero_Consent's
banner. Off by default:
Stores > Configuration > Zero > Google Analytics.

More in the theme's `docs/analytics.md` (https://fixstacks.com/zero/docs).

## Install

Requires Magento Open Source 2.4.7–2.4.9 and PHP 8.2–8.4.

```bash
composer require fixstacks/module-zero-google-analytics
bin/magento setup:upgrade
bin/magento setup:di:compile           # production mode
bin/magento setup:static-content:deploy # production mode
bin/magento cache:flush
```

Composer installs `fixstacks/module-zero-core`, `fixstacks/module-zero-consent` with it.

Part of [`fixstacks/zero`](https://fixstacks.com/zero), which installs the Zero theme and every Zero module at once.

## License

OSL-3.0 or AFL-3.0, your choice: see `NOTICE.md`.

---

Zero is made by [Fixstacks](https://fixstacks.com/zero) in partnership with [Vilmate](https://vilmate.com).
Documentation: https://fixstacks.com/zero/docs. Demo store: https://zero.fixstacks.com.
