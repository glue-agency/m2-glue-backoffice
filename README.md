# Glue Backoffice for Magento 2

Reports a Magento 2 shop to the Glue Dashboard after each deploy: its Magento version and edition, PHP version, store views and the Composer packages it requires. The Magento counterpart of `glue-agency/craft-glue-backoffice`.

## Installation

    composer require glue-agency/m2-glue-backoffice
    php bin/magento module:enable GlueAgency_Backoffice
    php bin/magento setup:upgrade

Requires PHP 7.4 or later.

## Configuration

Go to `STORES > Settings > Configuration > GLUE > Backoffice`.

| Setting | |
|---|---|
| URL | The Dashboard. Already filled in (`https://dashboard.glue.be/`). |
| Token | The install's own reporting token, issued by the Dashboard. Stored encrypted. |

The token is only ever set on the **production host**, from the command line:

    php bin/magento config:set --lock-env glue_backoffice/general/token <token>

`--lock-env` runs the value through the field's backend model, so it ends up encrypted in `app/etc/env.php` and the admin field becomes read-only. Do not put the token in `env.php` by hand, or in a `CONFIG__DEFAULT__...` environment variable: the module decrypts what it reads, so a plain value will not work.

Staging hosts run production mode too, which is why the token, not the deploy mode, decides whether anything is reported.

## Reporting

    php bin/magento glue:backoffice:report --repository-name=git@bitbucket.org:glue-team/project.git

`--repository-name` is optional: without it, the repository is left out of the report.

`--dry-run` prints the report as JSON and sends nothing. It needs no token, so it is the way to check what a shop would report on any host, staging and dev included.

The command reports nothing, says why, and exits cleanly when:

- no token is configured (`No Glue Dashboard token configured, not reporting.`);
- Magento runs in developer mode.

When the Dashboard refuses the report it prints every message it sent back, which tell the deployer what to do, and exits with 1. Hooked in after `deploy:symlink`, that fails the deploy after the release is already live, as the Craft plugin does.

What is reported:

- the store name, Magento version (patch suffix included), edition (Open Source or Adobe Commerce) and PHP version;
- one entry per store view (admin excluded): code, name, website, locale, secure base URL and whether it is active;
- the Composer packages the shop requires directly — its root `composer.json`'s `require`, not its whole lock file — with the installed version and description; Magento itself is among them as `magento/product-community-edition`, and platform requirements (`php`, `ext-*`) are left out;
- the repository, when given.

The report carries a uid, a UUID generated on the first run and kept in `core_config_data` as `glue_backoffice/general/uid`. The Dashboard knows the shop by its token, not by this uid: a shop rebuilt on a fresh database reports a new uid, and its own token accepts it.

### Onboarding a shop

The Dashboard has to know the Magento platform first (the release that adds platforms, Oct 2026). A Dashboard from before it ignores the `platform` key and files the shop as a Craft install.

On the Dashboard a brand's Craft site and the Magento shop beside it are **one install**, and both report with that install's one token: the shop's first report adds its Magento section, store views and packages beside what Craft reports.

1. Use the install the brand already has. If it has none yet, add it by hand (platform **Magento**, its domain and current version), switch it to **automatic** and issue it a reporting token.
2. Its token can't be read back once issued: if the Craft site already reports with it, issue a new one and give it to **both** — Craft's `.env` and the shop.
3. On the shop's production host only: `php bin/magento config:set --lock-env glue_backoffice/general/token <token>`.
4. Deploy. The shop's first report fills in its version, store views and packages.

### Deployer

From a Deployer 7 `deploy.php` that uses `recipe/magento2.php`:

```php
desc('Report to the Glue Dashboard');
task('glue:backoffice_report', function () {
    run('{{bin/php}} {{bin/magento}} glue:backoffice:report --repository-name={{repository}}');
});

after('deploy:symlink', 'glue:backoffice_report');
```

`{{bin/magento}}` is what the recipe itself uses; it resolves to `{{release_or_current_path}}/{{magento_dir}}/bin/magento`.
