# DolicraftAllo

Dolibarr module connecting [Allo](https://www.withallo.com) cloud telephony to Dolibarr ERP & CRM: call history, SMS, contact synchronization, real-time webhooks and a telephony dashboard.

*Module Dolibarr qui connecte la téléphonie cloud Allo à Dolibarr : journal d'appels, SMS, synchronisation des contacts, webhooks temps réel et tableau de bord.*

## Features

- **Call log**: import of the Allo call history (summary, transcript, recording), linked to Dolibarr thirdparties and contacts by phone number.
- **SMS**: send SMS from thirdparty, contact and invoice cards, reusable templates, inbox for received SMS.
- **Contacts**: Allo contacts mapped to Dolibarr contacts by phone number; missing contacts are created.
- **Webhooks**: real-time reception of calls and SMS (`webhook.php`, requires Dolibarr to be reachable from Allo).
- **Scheduled jobs**: call and contact sync driven by Dolibarr (alternative to the webhook for servers not reachable from the internet).
- **Dashboard**: call volume, missed calls, top contacts.

## Requirements

- Dolibarr 16 or later (tested up to Dolibarr 24)
- PHP 7.4 or later with cURL
- An Allo account with API access

## Installation

1. Copy the `dolicraftallo` folder into `htdocs/custom/`.
2. Enable the module in *Home > Setup > Modules/Applications*.
3. Open the module setup, save your Allo API key and click **Test connection**.
4. Add your Allo numbers in the *Numbers* tab.

## API key scopes

Create the key in Allo (*Settings > API*) with only the scopes of the features you use:

| Feature | Scope |
|---|---|
| Connection test and number list | `PHONE_NUMBERS_READ` |
| Call log sync | `CONVERSATIONS_READ` |
| SMS sending | `SMS_SEND` |
| Contact sync (read only) | `CRM_READ` |

Webhooks are configured in the Allo interface and need no scope.

## Scheduled jobs

Two jobs are created when the module is enabled, disabled by default (*Home > Admin tools > Scheduled jobs*, requires the Scheduled jobs module and the Dolibarr system cron):

- **Allo - Call sync**: hourly, `DolicraftAlloSync::runCallsSync()`
- **Allo - Contact sync**: daily, `DolicraftAlloSync::runContactsSync()` (only when "Enable contact sync" is ticked in the setup)

The synchronization logic lives in `class/allo_sync.class.php` and is shared by the manual sync buttons and the scheduled jobs.

## Releases

Pushing a tag (e.g. `1.0.4`) runs the *Package & Release* GitHub workflow (`.github/workflows/release.yml`): it builds `module_dolicraftallo-<tag>.zip`, ready to deploy from *Home > Setup > Modules/Applications > Deploy/install external module*, and attaches it to a new GitHub release. A tag containing `-` (e.g. `1.0.4-rc1`) creates a pre-release.

## Contributing

Issues and pull requests are welcome. Please describe the Dolibarr and module versions you tested with, and keep the existing coding style (Dolibarr conventions, tabs, `dol_syslog()` for logging).

The module is also distributed on [DoliStore](https://www.dolistore.com) by [Dolicraft](https://dolicraft.com).

## License

GPL v3 or later. See [LICENSE](LICENSE).

Copyright (C) 2026 Dolicraft - contact@dolicraft.com
