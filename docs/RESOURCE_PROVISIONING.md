# Ressourcenbereitstellung

## Dienstkonto

Die App verwendet für Provisioning-Aufgaben ein separates Nextcloud-Dienstkonto. Das Konto sollte nicht für interaktive Anmeldung verwendet werden. In den persönlichen Sicherheitseinstellungen des Kontos wird ein eigenes App-Passwort erstellt und ausschließlich in den Jugendeinstellungen hinterlegt.

- Basis-URL: kanonische HTTPS-URL der Nextcloud-Instanz
- Benutzername: UID des Dienstkontos
- App-Passwort: dediziertes Nextcloud-App-Passwort

Das App-Passwort wird über den verschlüsselten Nextcloud-App-Config-Speicher (IAppConfig, verschlüsselter Wert) gespeichert. Es wird nicht an den Browser zurückgegeben. Die Verbindung verwendet HTTP Basic Authentication über TLS und den OCS-Header OCS-APIRequest: true.

Das Dienstkonto benötigt für die Talk-Raumerstellung die Berechtigung, Gruppenräume anzulegen. Es sollten keine globalen Administratorrechte vergeben werden, sofern die eingesetzte Talk-Version die benötigten Aktionen ohne diese Rechte zulässt.

## Aktueller Umfang

- Verbindungstest gegen cloud/capabilities mit den gespeicherten Zugangsdaten.
- Erstellung privater Talk-Gruppenräume über die Talk OCS API v4.
- Bereitstellung eines Talk-Raums je Gruppe der aktiven Organisationsperiode.
- Speicherung des Raum-Tokens und der Raum-URL in jugend_resources.
- Aktive Talk-Räume werden mit den aktuellen Mitgliedern der jeweiligen Arbeitsgruppe abgeglichen.
- Mitglieder, die nicht mehr zur aktiven Abteilungsgruppe in Authentik gehören, werden aus aktiven Gruppenräumen entfernt.
- In archivierten Organisationsperioden werden alle vorhandenen Talk-Räume mit der aktuellen Abteilungsmitgliedschaft abgeglichen: aktuelle Mitglieder erhalten Zugriff, ausgeschiedene Mitglieder werden entfernt.
- Raum-Tokens werden bei Wiederholungen wiederverwendet; fehlende Teilnehmer werden ergänzt und nicht mehr berechtigte Teilnehmer entfernt.
- Der Provisionierungsstatus wird je Periode und Arbeitsgruppe dauerhaft gespeichert (nicht begonnen, läuft, erfolgreich, fehlgeschlagen).
- Fehlgeschlagene Einladungen/Entfernungen und letzte Fehlermeldung bleiben sichtbar. App-Admins können den Abgleich manuell erneut starten.
- Audit-Ereignis bei erfolgreicher Raumregistrierung.

Die Bereitstellung wird bewusst als separater, autorisierter Schritt nach der Aktivierung ausgeführt. Die Periodenaktivierung selbst führt keine externen API-Aufrufe aus und bleibt damit unabhängig von temporären Nextcloud-/Talk-Ausfällen.

## Noch nicht umgesetzt

- Groupfolders und Berechtigungen für aktive sowie archivierte Gruppen.
- Kalenderanlage und Kalenderberechtigungen.
- Automatische Hintergrundverarbeitung mit Retry/Backoff; der aktuelle Stand bietet persistente Status und manuell auslösbare Wiederholungen.
- Vollständige Exactly-once-Absicherung und Kompensation verwaister Räume, falls Talk einen Raum erstellt, aber die lokale Datenbankregistrierung fehlschlägt.

Vor einem produktiven Einsatz müssen Groupfolders und Kalender ergänzt und die Talk-API gegen die konkrete Nextcloud-/Talk-Version getestet werden. Insbesondere ist die tatsächliche Berechtigungsanforderung des Dienstkontos installationsabhängig.
