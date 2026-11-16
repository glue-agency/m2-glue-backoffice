# Release Notes

## 1.3.0 - Every environment reports
- Staging, dev and any other environment report too: only where each store view answers there, which the Dashboard lists beside live's. The deploy names the environment with the new `--environment` option, as it names the repository
- `--environment` is required: without it the command refuses and fails the deploy, so a staging or dev host can never report as live. Name every host's environment in `deploy.php` in the same commit as this upgrade
- Live's report is unchanged, plus its environment
- Developer mode no longer stops a report: the token decides
- Without a token, the command names the environment it skipped
- Every report says which environment it came from and prints every value it sent, each store view's URL included
- Needs a Dashboard that knows environments (Nov 2026): an older one refuses a report from anywhere but live

## 1.2.0
- Send each store view's Magento store id as `id`, in place of a `uid` that held its code, so a renamed store code no longer reads as one store view removed and another added. Needs a Dashboard that accepts `id`; the first report after upgrading replaces the shop's store views on the Dashboard once.

## 1.1.0
- Report the shop's store views and the Composer packages it requires directly, as the Dashboard keeps them for Magento, instead of sites and modules
- Report Magento's edition: Open Source or Adobe Commerce
- Store view locales are sent as Magento writes them (`nl_BE`)

## 1.0.0 - Initial release
