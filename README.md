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

## Organisationsverwaltung und Ressourcen

Die Organisationsverwaltung ist unter `/apps/jugend/organization` verfügbar und App-Administratoren vorbehalten. Die App verwendet Organisationsperioden, Arbeitsgruppen, historische Mitgliedschaftsintervalle und genau zwei Gruppenleitungen je Arbeitsgruppe. Gruppenleitungen nutzen `/apps/jugend/groups` für ihre aktiven Arbeitsgruppen und passende Entwürfe.

### Implementierter Funktionsumfang

- Organisationsperioden mit frei wählbaren, optionalen Start- und Enddaten; der Wechsel wird immer manuell bestätigt.
- Entwürfe können unabhängig von der aktiven Periode vorbereitet und aus einer bestehenden Periode kopiert werden.
- Gruppen, Mitglieder und zwei gleichberechtigte Gruppenleitungen werden verwaltet.
- Aktivierungsvorschau mit Gruppen-, Mitgliedschafts- und Leitungsänderungen sowie serverseitiger Validierung.
- RBAC mit App-Administratoren, benutzerdefinierten Rollen, Berechtigungen und globalen, Perioden- und Gruppen-Scopes.
- Audit-Log für zentrale Organisations-, Rollen- und Provisionierungsaktionen.
- Authentik-Arbeitsgruppengruppen, Nextcloud-Gruppenfreigaben, Talk-Räume, Groupfolder-Unterordner und CalDAV-Kalender.
- Wiederholbare Provisionierungsjobs mit persistiertem Status, Fehlertext, Versuchsanzahl und Lease-Fencing.
- Archivierte Dateiordner und Kalender werden lesend für die aktuelle Abteilungsgruppe freigegeben; historische Talk-Räume werden mit aktuellen Abteilungsmitgliedern abgeglichen.
- Gruppenleitungen sehen ihre aktiven Arbeitsgruppen und gleichnamige Gruppen in Entwurfsperioden. Über den stabilen Gruppenslug können sie Mitglieder und die zwei Leitungsrollen für den nächsten Organisationswechsel planen.

### Vorbereiteter Organisationswechsel

Eine geplante Periode kann vor dem Wechsel bereits ihre drei Ressourcen je Arbeitsgruppe anlegen:

- Talk-Raum wird erstellt, aber noch nicht mit Gruppenmitgliedern geteilt.
- Groupfolder-Unterordner wird erstellt, aber noch nicht freigegeben.
- Kalender wird erstellt, aber noch nicht geteilt.
- Authentik-Arbeitsgruppen werden vor der Aktivierung noch nicht angelegt oder befüllt.

Die Aktivierung wird serverseitig blockiert, solange nicht für jede Arbeitsgruppe alle drei Ressourcen erfolgreich vorbereitet und lokal registriert sind. Nach der Aktivierung synchronisiert der reguläre Abgleich die Authentik-Mitgliedschaften, Talk-Teilnehmer und Datei-/Kalenderfreigaben.

### Noch offene Arbeiten und Grenzen


- Vollständige Laufzeit- und Integrationstests gegen die konkrete Nextcloud-35-/Talk-/Groupfolders-/Calendar-/Authentik-Installation stehen aus.
- Eine automatische Löschung verwaister Remote-Ressourcen nach teilweise fehlgeschlagenen API-/Datenbankoperationen ist nicht implementiert. Groupfolder-Pfade und Kalender werden über deterministische Kennungen wiederverwendet; neu angelegte Talk-Räume werden bei einem erneuten Lauf anhand einer eindeutigen Kennung im Raumnamen wiedergefunden und lokal registriert. Schlägt auch diese Wiederherstellung fehl, ist eine manuelle Prüfung erforderlich.

Die CI prüft Syntax und Metadaten, ersetzt aber keine Integrationstests auf der Zielinstanz. Bis diese Tests erfolgreich durchgeführt wurden, ist die Ressourcenorchestrierung nicht als produktiv verifiziert zu betrachten.

Die bestehende Authentik-Zugriffsgruppe bleibt die äußere Zugangskontrolle. Das Entfernen einer Person aus dieser Gruppe entzieht den App-Zugang; historische Mitgliedschafts- und Organisationsdatensätze bleiben erhalten.
