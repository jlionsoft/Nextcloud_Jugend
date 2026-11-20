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

- Verbindungstest gegen `cloud/capabilities` mit den gespeicherten Zugangsdaten.
- Erstellung privater Talk-Gruppenräume über die Talk OCS API v4.
- Bereitstellung eines Talk-Raums je Gruppe der aktiven Organisationsperiode.
- Aktive Talk-Räume werden mit den aktuellen Mitgliedern der jeweiligen Arbeitsgruppe abgeglichen.
- In archivierten Talk-Räumen erhalten alle aktuellen Abteilungsmitglieder Zugriff; nicht mehr aktuelle Teilnehmer werden entfernt.
- Je Arbeitsgruppe wird eine dedizierte Authentik-Gruppe `jugend-{period_key}-{group_slug}` angelegt und mit der geplanten Mitgliedschaft synchronisiert. Diese Gruppe ist die Zugriffsgruppe für die aktiven Dateiordner und Kalender.
- Die Authentik-Mitgliedschaften werden durch einen Nextcloud-Hintergrundjob alle 15 Minuten abgeglichen. Damit werden ausgeschiedene Mitglieder aus den aktiven Arbeitsgruppen-Gruppen entfernt; die tatsächliche Übernahme in Nextcloud hängt zusätzlich vom konfigurierten Authentik-Gruppensync ab.
- Groupfolder-Unterordner werden unter dem vorkonfigurierten Groupfolder-Stamm per WebDAV angelegt. Aktive Gruppen erhalten Lese-/Schreibzugriff über ihre Authentik-synchronisierte Nextcloud-Gruppe.
- Archivierte Dateiordner werden mit der aktuellen Abteilungsgruppe geteilt und sind schreibgeschützt.
- Pro Arbeitsgruppe wird ein CalDAV-Kalender im Dienstkonto angelegt. Aktive Kalender werden mit der Arbeitsgruppen-Gruppe geteilt; archivierte Kalender werden schreibgeschützt mit der aktuellen Abteilungsgruppe geteilt.
- Bei Archivierung werden die früheren Authentik-Arbeitsgruppen geleert; historische Inhalte werden stattdessen für alle aktuellen Abteilungsmitglieder freigegeben.
- Der Provisionierungsstatus wird je Periode und Arbeitsgruppe dauerhaft gespeichert (nicht begonnen, läuft, erfolgreich, fehlgeschlagen).
- Fehlgeschlagene Einladungen/Freigaben und letzte Fehlermeldung bleiben sichtbar. App-Admins können den Abgleich manuell erneut starten.
- Audit-Ereignisse werden bei erfolgreicher Ressourcenregistrierung geschrieben.

Die Bereitstellung wird bewusst als separater Schritt nach der Aktivierung ausgeführt. Die Periodenaktivierung selbst führt keine externen API-Aufrufe aus und bleibt damit unabhängig von temporären Nextcloud-/Authentik-Ausfällen. Der Hintergrundjob führt denselben Abgleich regelmäßig erneut aus.

## Noch nicht umgesetzt

- Automatische Kompensation verwaister Remote-Ressourcen, falls ein externer Dienst eine Ressource erstellt, aber die lokale Datenbankregistrierung fehlschlägt.
- Vollständige Laufzeit-Integrationstests gegen die konkret installierten Nextcloud-, Groupfolders-, Calendar- und Talk-Versionen.
- Überwachung und sichtbarer Gesamtstatus des Hintergrundjobs einschließlich des letzten erfolgreichen Laufs.

Vor einem produktiven Einsatz müssen die einmalige Groupfolder-Einrichtung, Authentik-Gruppensynchronisierung und die CalDAV-/Files-Sharing-API gegen die konkrete Nextcloud-Installation getestet werden. Für zuverlässige 15-Minuten-Synchronisierung muss Nextcloud auf dem Cron-Modus laufen.
