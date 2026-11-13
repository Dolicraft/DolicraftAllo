# Changelog

All notable changes to **DolicraftAllo** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.3] - 2026-11-13

### Fixed
- **Contact sync broken with new Allo API keys** ("CONTACTS_READ" scope required): the module called the deprecated v1 endpoint `GET /v1/api/contacts`, whose scope can no longer be granted. Contacts are now read from the v2 endpoint `POST /v2/api/crm/people/search` (scope `CRM_READ`), with v2 pagination (`pagination.has_more` / `total_pages`). `getContact()`, `createContact()` and `updateContact()` also moved to `/v2/api/crm/people`.
- Allo error messages are now readable: v2 (`error.message`) and v1 (`code` + `details`) formats are parsed instead of displaying the raw JSON.
- Call sync now reports the API error when every configured number fails, instead of "0 calls imported".
- Contacts created during sync kept only the first name in the mapping (`allo_name`); the full name is now stored.

### Added
- New `class/allo_sync.class.php` (`DolicraftAlloSync`): `syncCalls()` and `syncContacts($user)` hold the synchronization logic, shared by the manual buttons (`ajax/allo.php`) and the scheduled jobs. Suggested by a customer.
- Two scheduled jobs, disabled by default: "Allo - Call sync" (hourly) and "Allo - Contact sync" (daily, only when "Enable contact sync" is ticked; that option had no effect before). Alternative to the webhook when Dolibarr is not reachable from the internet.
- Documentation: "API key scopes" section (minimum scopes per feature), "Scheduled jobs" section, troubleshooting entry for `API_KEY_INSUFFICIENT_SCOPE`; scope hint under the API key field in the setup.
- `LICENSE` file (GPL v3).

### Upgrade
- Disable then re-enable the module to create the scheduled jobs. Settings and API key are kept.

### Known
- Call sync still uses the deprecated v1 endpoint `GET /v1/api/calls` (works today). Migration to `GET /v2/api/conversations` is planned.

## [1.0.2] - 2026-11-03

### Fixed
- **Fatal PHP error on "Test connection"** (and on every API call made from `ajax/allo.php`: call sync, SMS sending, contact sync): `Call to undefined function dolibarr_set_const()` in `class/allo_api.class.php`. The rate-limit counter uses `dolibarr_set_const()`, but `core/lib/admin.lib.php` was only loaded by the admin pages. The API class now loads it itself. The setup page showed the generic "Erreur de communication" message.

### Added
- `dol_syslog()` (LOG_ERR) on failed API requests (cURL error, non-2xx HTTP status) and on a failed connection test, so failures show up in the Dolibarr log.

## [1.0.1] - 2026-05-31

### Fixed
- Setup page showed a green "Connection successful" badge as soon as an API key was set, without actually testing the key against the Allo API. Badge now reads "API key configured" with guidance to use the "Test connection" button for the real check.
- Inbound calls received via webhook stored the duration in seconds inside the `duration_minutes` column, producing nonsense displays like `120m00s` for a 2-minute call. The webhook now converts seconds → decimal minutes before storing, and uses `float` instead of `int` casting to preserve sub-minute precision.
- Webhook calls/SMS without an explicit `call_date`/`sms_date` field failed to insert silently: `dol_print_date(..., 'dayhour')` returned a localized display string like `05/31/2026 11:55 AM`, which isn't a valid MySQL DATETIME. Now uses `$db->idate($now)` to produce a proper `YYYY-MM-DD HH:MM:SS` value.

### Added
- Missing translations: `Last30Days`, `KeyIndicators`, `CallVolumeByDay`, `TopContactsByCallVolume`, `NoDataForPeriod`, `NoSMSYet`, `SelectAConversation`, `ApiKeyConfigured` (FR + EN).

## [1.0.0] - 2026-05-31

### Added
- Initial release.
