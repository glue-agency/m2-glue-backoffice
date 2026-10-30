# Release Notes

## 1.2.0
- Send each store view's Magento store id as `id`, in place of a `uid` that held its code, so a renamed store code no longer reads as one store view removed and another added. Needs a Dashboard that accepts `id`; the first report after upgrading replaces the shop's store views on the Dashboard once.

## 1.1.0
- Report the shop's store views and the Composer packages it requires directly, as the Dashboard keeps them for Magento, instead of sites and modules
- Report Magento's edition: Open Source or Adobe Commerce
- Store view locales are sent as Magento writes them (`nl_BE`)

## 1.0.0 - Initial release
