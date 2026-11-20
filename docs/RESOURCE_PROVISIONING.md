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
- Einladen der aktuellen Mitglieder der jeweiligen Arbeitsgruppe in den Raum.
- Wiederholungen verwenden vorhandene Raum-Tokens und gleichen fehlende Gruppenmitglieder ab.
- Teilergebnisse und fehlgeschlagene Einladungen werden pro Gruppe zurückgegeben.
- Audit-Ereignis bei erfolgreicher Raumregistrierung.

Die Bereitstellung wird bewusst als separater, autorisierter Schritt nach der Aktivierung ausgeführt. Die Periodenaktivierung selbst führt keine externen API-Aufrufe aus und bleibt damit unabhängig von temporären Nextcloud-/Talk-Ausfällen.

## Noch nicht umgesetzt

- Entfernung ausgeschiedener Mitglieder aus Talk-Räumen sowie Zugriffsgewährung für alle aktuellen Abteilungsmitglieder auf archivierte Talk-Inhalte.
- Groupfolders und Berechtigungen für aktive sowie archivierte Gruppen.
- Kalenderanlage und Kalenderberechtigungen.
- Persistente Job-Warteschlange mit Retry/Backoff und explizitem Fehlerstatus.
- Vollständige Idempotenz bei parallelen Provisioning-Aufrufen und Kompensation verwaister Räume nach einem DB-Fehler.

Vor einem produktiven Einsatz müssen diese Punkte ergänzt und die Talk-API gegen die konkrete Nextcloud-/Talk-Version getestet werden. Insbesondere ist die tatsächliche Berechtigungsanforderung des Dienstkontos installationsabhängig.
