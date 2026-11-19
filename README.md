# Nextcloud Jugend

Native Nextcloud app replacing the existing JugendSendClient.

## Current scope

The first release is a focused replacement of the existing message-sending application. Every user who can access the app has the same permissions in this initial phase.

The initial feature set is intended to cover:
- Compose a message for the youth group.
- Send the message to the configured Nextcloud Talk room.
- Send SMS through the MikroTik RouterOS REST SMS gateway.
- Show a clear per-channel result and failed recipients.

Matrix is not a prerequisite for the new app. Its future removal must not affect the SMS or Talk integrations.

## Architecture direction

- Native Nextcloud PHP AppFramework backend and Nextcloud web UI.
- Nextcloud authentication and app access control; no separate Blazor/Authentik login.
- Channel adapters kept separate from message orchestration.
- Recipient lookup isolated behind a provider interface (Authentik can remain the initial provider).
- Longer-running sends handled as background jobs with persisted status/history.
- A versioned app API kept suitable for a possible future mobile client.
- Future modules can be added without coupling them to the message-sending module.

## Development status

This repository currently contains the initial app shell and message composition UI. The send action remains disabled until the server-side dispatch pipeline and its channel configuration are implemented. This is intentional: the app must never report a send as successful when no channel has actually processed it.

The first settings page is restricted to Nextcloud administrators. All users who can access the app retain the same messaging permissions. API tokens and passwords are stored as sensitive Nextcloud app-config values (excluded from filtered config listings); this flag is **not encryption**. The settings page never returns stored secrets to the browser.

## Configuration still required

Before enabling sending, implementation and deployment must establish:
- Supported Nextcloud and PHP versions.
- Talk bot credentials, room token, and webhook secret (if incoming Talk events are retained).
- MikroTik gateway URL and credentials, plus confirmation that the Nextcloud host can reach it.
- Initial recipient source and the mapping of phone numbers/SMS opt-in.
- Background-job execution and retention policy for send history.

Never commit credentials, tokens, phone numbers, or production configuration to this repository.
