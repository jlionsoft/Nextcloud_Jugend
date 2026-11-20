# Ressourcenbereitstellung

## Dienstkonto und einmalige Groupfolder-Einrichtung

Die App verwendet für Provisioning-Aufgaben ein separates Nextcloud-Dienstkonto. Das Konto sollte nicht für interaktive Anmeldung verwendet werden. In den persönlichen Sicherheitseinstellungen des Kontos wird ein eigenes App-Passwort erstellt und ausschließlich in den Jugendeinstellungen hinterlegt.

- Basis-URL: kanonische HTTPS-URL der Nextcloud-Instanz
- Benutzername: UID des Dienstkontos
- App-Passwort: dediziertes Nextcloud-App-Passwort
- Dienstgruppe: lokale Nextcloud-Gruppe, in der ausschließlich das Dienstkonto Mitglied ist
- Groupfolder-Stamm: Name eines bereits angelegten Groupfolders, standardmäßig `Jugend`
- Abteilungsgruppe: Authentik-synchronisierte Nextcloud-Gruppe, die den App-Zugriff begrenzt

Das App-Passwort wird über den verschlüsselten Nextcloud-App-Config-Speicher (IAppConfig, verschlüsselter Wert) gespeichert. Es wird nicht an den Browser zurückgegeben. Die Verbindung verwendet HTTP Basic Authentication über TLS und den OCS-Header `OCS-APIRequest: true`.

**Einmalige Einrichtung durch einen Nextcloud-Administrator:**

1. Nextcloud-Gruppe `jugend-provisioner` anlegen und ausschließlich das Dienstkonto aufnehmen.
2. In der Groupfolders-App einen Groupfolder mit dem in den Jugendeinstellungen eingetragenen Namen (standardmäßig `Jugend`) anlegen.
3. Dem Stamm-Groupfolder ausschließlich die Gruppe `jugend-provisioner` mit Lese-, Schreib-, Erstell-, Lösch- und Freigaberechten zuweisen.
4. Die Authentik-synchronisierte Abteilungsgruppe in den Jugendeinstellungen eintragen.
5. Sicherstellen, dass Authentik-Gruppen in Nextcloud synchronisiert werden und Gruppen-IDs/Namen unverändert übernommen werden.

Die App legt darunter per WebDAV Unterordner für Organisationsperiode und Arbeitsgruppe an. Sie ruft absichtlich nicht die Groupfolders-Schreib-OCS-Endpunkte auf: Diese sind mit `PasswordConfirmationRequired` geschützt und erfordern eine interaktive Passwortbestätigung, die ein App-Passwort nicht bereitstellen kann. Der Dienstaccount benötigt daher keine Groupfolders-Administratorrechte.

Für das Anlegen und Verwalten der automatisch erzeugten Authentik-Arbeitsgruppen benötigt das Authentik-API-Token mindestens die Berechtigungen `view_group`, `add_group`, `view_user`, `add_user_to_group` und `remove_user_from_group`. Die App markiert selbst angelegte Gruppen mit den Attributen `managed_by=nextcloud-jugend`, `period_id` und `group_id`; fremde Gruppen gleichen Namens werden nicht übernommen.

## Aktueller Umfang

- Verbindungstest gegen cloud/capabilities mit den gespeicherten Zugangsdaten.
- Erstellung privater Talk-Gruppenräume über die Talk OCS API v4.
- Bereitstellung eines Talk-Raums je Gruppe der aktiven Organisationsperiode.
- Speicherung des Raum-Tokens und der Raum-URL in jugend_resources.
- Aktive Talk-Räume werden mit den aktuellen Mitgliedern der jeweiligen Arbeitsgruppe abgeglichen.
- Mitglieder, die nicht mehr zur aktiven Abteilungsgruppe in Authentik gehören, werden aus aktiven Gruppenräumen entfernt.
- In archivierten Organisationsperioden werden alle vorhandenen Talk-Räume mit der aktuellen Abteilungsmitgliedschaft abgeglichen: aktuelle Mitglieder erhalten Zugriff, ausgeschiedene Mitglieder werden entfernt.
- Dieser Abgleich läuft aktuell bei manueller Ausführung durch einen App-Admin. Änderungen an der Authentik-Gruppe werden daher nicht sofort oder automatisch in Talk gespiegelt.
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
