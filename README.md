# Nextcloud Jugend

Native Nextcloud app replacing the existing JugendSendClient.

## Implemented in this branch

- Native Nextcloud PHP AppFramework UI and API.
- Nextcloud session authentication; every user who can open the app can send messages.
- Technical settings and connection tests are restricted to Nextcloud administrators.
- Authentik group lookup using a bearer token. All active group members are Matrix recipients; only users with `sms=true` and a non-empty `phone_number` attribute are SMS recipients.
- Matrix Client-Server API integration: verify bot identity, detect existing accounts, reuse joined direct rooms from `m.direct`, create and invite to trusted private rooms when needed, update `m.direct`, and send `m.room.message` events. Rate-limit responses are retried with backoff.
- Nextcloud Talk bot message delivery using the Talk bot HMAC-SHA256 request headers.
- Signed Talk webhook endpoint (`/apps/jugend/api/v1/talk/webhook`) with raw-body HMAC verification, replay protection, event logging and `!ping` → `🏓 pong`.
- MikroTik RouterOS REST SMS delivery using HTTP Basic authentication and JSON payloads.
- GSM-7 validation and multipart SMS splitting (150 septets per part to reserve room for the legacy `[n]` prefixes); unsupported characters fail the SMS delivery for that recipient rather than being silently transliterated.
- Per-channel result reporting for Talk, Matrix and SMS, including per-user Matrix and SMS failures.
- Explicit connection-test buttons. The Talk test sends a visible test message to the configured room.
- Settings for all endpoints, IDs, interfaces and credentials. Secrets are never returned to the browser and are marked as sensitive Nextcloud app-config values. This flag excludes them from filtered config listings; it is **not encryption**. Secret fields remain blank on reload, can be left unchanged, or explicitly cleared.
- GitHub Actions checks PHP syntax, JavaScript syntax and app metadata XML.

## User interface

The app uses a responsive, full-width layout with Nextcloud theme variables and panel-based content areas. Administrator settings are grouped by integration, including Matrix homeserver, server name, bot user ID and access token. Secret values are never displayed; entering a new value replaces the stored secret, leaving the field empty keeps it, and the explicit remove action deletes it. Connection tests save the current form values before testing the selected integration.

## Runtime behavior and limitations

- Sending currently runs synchronously in the web request. A large recipient list or multipart SMS campaign can exceed PHP/proxy timeouts. A queued job and persisted delivery history are not included in this first replacement slice.
- SMS gateway connection test checks reachability of the configured gateway base URL. It does not send a test SMS.
- Talk connection test sends the literal message `Verbindungstest der Jugend-App.` to the configured Talk room.
- The app reports that the RouterOS API accepted an SMS request; it cannot confirm delivery to the handset.
- Authentik attribute names are read as `PhoneNumber` and `Sms` (lowercase variants are also accepted). Confirm these match the actual group payload before production use.
- No live credentials, network access, Nextcloud instance or MikroTik device were available during implementation. Connection and end-to-end tests must be performed in the target environment.

## Configuration

Configure the following in Nextcloud's administration settings (Administration settings → Jugend). The "Einstellungen" button on the app's main page links there for administrators:

| Service | Settings |
|---|---|
| Authentik | Base URL, group ID, API token |
| Nextcloud Talk | Nextcloud base URL, room token, bot secret |
| Matrix | Homeserver URL, server name, bot user ID, bot access token |
| MikroTik SMS | Gateway base URL, RouterOS username/password, LTE interface (default `lte1`) |

The Nextcloud server must be able to reach both Authentik and the MikroTik gateway. Never commit credentials, tokens, phone numbers or production configuration to this repository.

## Installation and test sequence

1. Place the app directory (the directory containing `appinfo/info.xml`) at `apps-extra/jugend` on the Nextcloud server.
2. Enable it with `sudo -u www-data php occ app:enable jugend` from the Nextcloud installation directory.
3. As a Nextcloud administrator, open **Jugend → Einstellungen**, enter the Authentik, Matrix, Talk and RouterOS settings, and save.
4. Run the Authentik, Matrix and SMS gateway connection tests. The Talk test sends a message to the configured room.
5. Copy the displayed Talk webhook URL into the Talk bot configuration and use the same bot secret.
6. Test the webhook by sending `!ping` in the Talk room; the bot should answer `🏓 pong`.
7. Start with a harmless short message and a small test recipient group. Verify the Matrix direct messages, Talk result, SMS opt-in filtering, phone numbers, multipart splitting and failed-recipient display before using the full group.

The app must be installed and tested on a staging instance first. The CI workflow checks syntax and metadata only; it does not replace a live Nextcloud app installation test.

## Organisation management (foundation in progress)

The database schema, architecture decisions and current implementation status for period-based work groups are documented in [`docs/ORGANIZATION.md`](docs/ORGANIZATION.md). Period/group planning, scoped authorization and custom role management are implemented as a foundation; period activation, resource provisioning and archival synchronization remain future work.

- Authentik's existing app-access group remains the outer access gate. Removing a person from that group revokes access to the app and department archives; historical records are retained.
- The app will own annual organisation periods, work groups, memberships, two equal group leaders per group, app roles and scoped permissions.
- Each period will receive separate Talk, Files and Calendar resources. Archived Files and Calendar content is read-only for current department members; each period has a separate Talk conversation.

## Deliberately out of scope

- Matrix room encryption is not implemented. The app sends standard unencrypted Matrix room messages; do not use encrypted rooms for the bot.
- Separate Blazor/Authentik login.
- Native mobile client. The API is versioned at `/api/v1` to leave room for one later.


## Organisationsverwaltung und Gruppenleitungsbereich

Die Organisationsverwaltung ist unter `/apps/jugend/organization` für App-Administratoren verfügbar. Gruppenleitungen nutzen `/apps/jugend/groups` für ihre eigenen aktiven Arbeitsgruppen.

- Organisationsperioden können unabhängig vom Kalenderjahr geplant werden. Beginn und Ende sind frei wählbar; Aktivierung erfolgt manuell.
- Entwürfe können aus bestehenden Perioden kopiert und mit Arbeitsgruppen, Mitgliedern und genau zwei gleichberechtigten Gruppenleitungen vorbereitet werden.
- Die Aktivierungsvorschau prüft Gruppen, Mitgliedschaften, Leitungen und die Verfügbarkeit der Benutzer.
- Rollen und Berechtigungen unterstützen globale, Perioden- und Gruppen-Scopes. App-Administratoren benötigen keine globalen Nextcloud-Administratorrechte.
- Gruppenleitungen sehen ihre aktiven Arbeitsgruppen und gleichnamige Gruppen in Entwurfsperioden. Über den stabilen Gruppenslug können sie Mitglieder und die zwei Leitungsrollen für den nächsten Organisationswechsel planen.
- Mitgliedschaftsänderungen werden historisiert. Neue Mitglieder müssen zur aktuellen Authentik-App-Zugriffsgruppe gehören und als Nextcloud-Benutzer existieren.
- Die regelmäßige Ressourcen-Synchronisierung übernimmt aktive Gruppenmitgliedschaften in Authentik, Nextcloud-Gruppen, Talk, Groupfolders und Kalendern.
- Archivierte Dateiordner und Kalender bleiben für die aktuelle Abteilungsgruppe lesend verfügbar. Historische Talk-Räume bleiben getrennt je Organisationsperiode erhalten.

### Technische Grenzen

Die Ressourcenorchestrierung ist noch nicht durch vollständige Laufzeit- und Integrationstests gegen die konkrete Nextcloud-35-/Talk-/Groupfolders-/Calendar-/Authentik-Installation verifiziert. Die einmalige Groupfolder-Einrichtung und die Authentik-Gruppensynchronisierung müssen vor dem Produktiveinsatz praktisch geprüft werden.

Die CI prüft Syntax und Metadaten, ersetzt aber keine Integrationstests auf der Zielinstanz.