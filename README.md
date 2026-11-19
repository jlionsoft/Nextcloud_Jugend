# Nextcloud Jugend

Native Nextcloud app replacing the existing JugendSendClient.

## Implemented in this branch

- Native Nextcloud PHP AppFramework UI and API.
- Nextcloud session authentication; every user who can open the app can send messages.
- Technical settings and connection tests are restricted to Nextcloud administrators.
- Authentik group lookup using a bearer token. Only users with `Sms=true` and a non-empty `PhoneNumber` attribute are SMS recipients.
- Nextcloud Talk bot message delivery using the Talk bot HMAC-SHA256 request headers.
- Signed Talk webhook endpoint (`/apps/jugend/api/v1/talk/webhook`) with raw-body HMAC verification, replay protection, event logging and `!ping` → `🏓 pong`.
- MikroTik RouterOS REST SMS delivery using HTTP Basic authentication and JSON payloads.
- GSM-7 validation and multipart SMS splitting (150 septets per part to reserve room for the legacy `[n]` prefixes); unsupported characters fail the SMS delivery for that recipient rather than being silently transliterated.
- Per-channel result reporting, including failed SMS recipients.
- Explicit connection-test buttons. The Talk test sends a visible test message to the configured room.
- Settings for all endpoints, IDs, interfaces and credentials. Secrets are never returned to the browser and are marked as sensitive Nextcloud app-config values. This flag excludes them from filtered config listings; it is **not encryption**. Secret fields remain blank on reload, can be left unchanged, or explicitly cleared.
- GitHub Actions checks PHP syntax, JavaScript syntax and app metadata XML.

## Runtime behavior and limitations

- Sending currently runs synchronously in the web request. A large recipient list or multipart SMS campaign can exceed PHP/proxy timeouts. A queued job and persisted delivery history are not included in this first replacement slice.
- SMS gateway connection test checks reachability of the configured gateway base URL. It does not send a test SMS.
- Talk connection test sends the literal message `Verbindungstest der Jugend-App.` to the configured Talk room.
- The app reports that the RouterOS API accepted an SMS request; it cannot confirm delivery to the handset.
- Authentik attribute names are read as `PhoneNumber` and `Sms` (lowercase variants are also accepted). Confirm these match the actual group payload before production use.
- No live credentials, network access, Nextcloud instance or MikroTik device were available during implementation. Connection and end-to-end tests must be performed in the target environment.

## Configuration

Configure the following under the app's administrator-only settings page:

| Service | Settings |
|---|---|
| Authentik | Base URL, group ID, API token |
| Nextcloud Talk | Nextcloud base URL, room token, bot secret |
| MikroTik SMS | Gateway base URL, RouterOS username/password, LTE interface (default `lte1`) |

The Nextcloud server must be able to reach both Authentik and the MikroTik gateway. Never commit credentials, tokens, phone numbers or production configuration to this repository.

## Deliberately out of scope

- Matrix integration (not required for the replacement).
- Separate Blazor/Authentik login.
- App-specific roles and group-to-role mapping.
- Native mobile client. The API is versioned at `/api/v1` to leave room for one later.
