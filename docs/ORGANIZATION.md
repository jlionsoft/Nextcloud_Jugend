# Organisationsmodell und Jahreswechsel

## Verbindliche Produktentscheidungen

- Authentik ist die Identitäts- und Anmeldequelle. Die bestehende Authentik-Zugriffsgruppe begrenzt den grundsätzlichen Zugang zur App.
- Die Jugend-App ist führend für Organisationsperioden, Arbeitsgruppen, Mitgliedschaften, zwei gleichberechtigte Gruppenleitungen pro Arbeitsgruppe, App-Rollen und Berechtigungen.
- Nextcloud-Gruppen sind eine technische Projektion der aktiven Arbeitsgruppen und dienen der Ressourcenfreigabe. Sie sind nicht die führende Organisationsdatenbank.
- Jede Organisationsperiode hat den Status `draft`, `active` oder `archived`. Es gibt höchstens eine aktive Periode.
- Planungen werden unabhängig von der aktiven Periode bearbeitet. Aktivierung archiviert die vorherige Periode und rollt die vorbereitete Periode aus.
- Jede Arbeitsgruppe erhält pro Periode einen eigenen Talk-Raum, einen Gruppenordner und einen Kalender.
- Archivierte Dateien und Kalender werden allen aktuellen Mitgliedern der App-Zugriffsgruppe lesend zugänglich gemacht.
- Talk erhält pro Organisationsperiode einen neuen Raum. Der alte Raum bleibt als historischer Raum erhalten; es wird keine Unterhaltung über Perioden hinweg weiterverwendet.
- Personen scheiden aus, indem sie aus der bestehenden Authentik-/Nextcloud-Zugriffsgruppe für die App entfernt werden. Dadurch verlieren sie den Zugang zur App und zu den für die Abteilung freigegebenen Archiven. Die historische Mitgliedschaft und Urheberschaft bleiben in der Jugend-App erhalten.

## Identitäts- und Zugriffsmodell

Authentik steuert den Eintritt in die App. Die App darf keine eigenen Benutzerkonten oder Passwörter anlegen. Benutzerreferenzen werden als Nextcloud-UID gespeichert. Die Jugend-App prüft bei jedem geschützten Request die aktuelle App-Zugriffsberechtigung; historische Mitgliedschaften verleihen keinen eigenständigen Login- oder Zugriffsanspruch.

Die Organisationsmitgliedschaft ist periodengebunden. Ein Benutzer kann mehreren Arbeitsgruppen angehören. Pro Arbeitsgruppe müssen genau zwei aktive Gruppenleiter festgelegt sein, bevor eine Periode aktiviert werden kann. Beide Leitungen sind gleichberechtigt. Ihre App-Rechte sind auf die jeweilige Arbeitsgruppe begrenzt.

Rollen bestehen aus Berechtigungen (Capability Keys). Zuweisungen besitzen einen Geltungsbereich: global, period oder group. Die Autorisierung prüft sowohl die Berechtigung als auch den Scope des angefragten Objekts. Rollenbezeichnungen allein dürfen niemals eine Autorisierungsentscheidung auslösen.

## Jahreswechsel als Workflow

1. Eine Leitung oder ein App-Admin erstellt eine neue Periode als Entwurf, optional durch Kopieren einer früheren Periode.
2. Gruppen, Mitglieder und jeweils zwei Gruppenleiter werden bearbeitet.
3. Eine Vorschau zeigt neue, entfernte und wechselnde Mitglieder sowie Validierungsfehler.
4. Ressourcen werden vorbereitet. Externe Nextcloud-Aufrufe werden als wiederholbare Schritte mit Status protokolliert; ein Fehler darf nicht zu einem halb aktivierten Zustand führen.
5. Die Aktivierung wird explizit bestätigt. Die bisher aktive Periode wird archiviert und die neue Periode aktiv.
6. Die App synchronisiert die aktive Gruppenmitgliedschaft zu Nextcloud. Die Zugriffsgruppe der Abteilung bleibt davon getrennt.

Die Datenbankmigration legt zunächst das persistente Schema an. Die externe Ressourcenorchestrierung wird danach als eigener Service/Background-Job implementiert. Talk, Files und Calendar sind optionale Integrationen und müssen über öffentliche Nextcloud-APIs angesprochen werden.

## Archiv- und Austrittsverhalten

- Alle aktuell zugriffsberechtigten Abteilungsmitglieder dürfen historische Dateien und Kalender lesen, auch wenn sie damals nicht Mitglied waren.
- Archivierte Gruppenordner und Kalender werden nicht gelöscht. Ihre Freigabe wird auf Lesen für die aktuelle App-Zugriffsgruppe gesetzt.
- Historische Talk-Räume bleiben getrennt pro Jahr erhalten. Neue Mitglieder werden nicht automatisch in alte Chats eingeladen; die App stellt den historischen Raum als Archivverweis dar. Ein späterer expliziter Beitritt muss separat entschieden und getestet werden.
- Beim Austritt wird der Benutzer aus der Authentik-/Nextcloud-Zugriffsgruppe entfernt. Die App löscht keine historischen Datensätze oder Ressourcen. Der Zugang zu App und Abteilungsarchiven endet durch den Entzug der übergeordneten Zugriffsgruppe.
- Die App darf die Authentik-Gruppe nicht selbst verändern, solange dafür keine ausdrücklich konfigurierte und autorisierte Provisioning-Integration existiert.

## Datenbankkonventionen

- Tabellennamen bleiben kurz und verwenden den Nextcloud-App-Präfix `jugend_`.
- Benutzeridentitäten werden als Nextcloud-UID gespeichert, nicht als numerische interne Datenbank-ID.
- Zeitpunkte werden als Unix-Zeitstempel gespeichert; Periodengrenzen als SQL-DATE.
- Externe Ressourcen-IDs werden opak gespeichert. Die App soll keine Talk-/Calendar-/Files-internen Tabellen direkt manipulieren.
- Audit-Einträge werden append-only behandelt.
- Die maximale Anzahl von zwei Gruppenleitern wird in der Service-Schicht innerhalb einer Transaktion validiert, nicht durch eine nicht portable Datenbank-Sonderfunktion.

## Implementierungsreihenfolge

1. Persistentes Schema und Repository-/Service-Schicht.
2. API und UI für Perioden, Gruppen, Mitgliedschaften und zwei Gruppenleitungen.
3. Rollen-/Berechtigungsverwaltung mit serverseitiger Scope-Autorisierung.
4. Entwurfsvergleich, Validierung und Aktivierungsworkflow.
5. Nextcloud-Gruppen-Synchronisierung.
6. Ressourcenadapter für Talk, Files und Calendar.
7. Archivierung, Audit und Fehler-/Wiederholungsbehandlung.


## Implementierungsstand

### Organisationsverwaltung und Rollen

- Organisationsperioden, Arbeitsgruppen und historische Mitgliedschaftsintervalle sind persistiert.
- Pro Arbeitsgruppe werden vor der Aktivierung genau zwei aktive Gruppenleitungen verlangt.
- Die App verwendet serverseitige Capability-Prüfungen mit globalen, Perioden- und Gruppen-Scopes.
- App-Administratoren und frei definierbare Rollen werden unterstützt. Die Systemrolle `app_admin` bleibt geschützt.
- Rollenänderungen und Organisationsaktionen werden im Audit-Log erfasst.

### Jahreswechsel

- Neue Perioden und Arbeitsgruppen können als Entwürfe unabhängig von der aktiven Periode vorbereitet werden.
- Die Aktivierungsvorschau vergleicht Gruppen anhand ihres technischen Slugs und zeigt neue/entfallende Gruppen sowie Mitgliedschafts- und Leitungsänderungen.
- Vor der Aktivierung werden alle Gruppen auf genau zwei aktive Leitungen und alle Mitglieder auf vorhandene Nextcloud-Konten innerhalb der aktuellen Authentik-Empfängermenge geprüft.
- Die Aktivierung archiviert die bisher aktive Periode und aktiviert die ausgewählte Entwurfsperiode in einer Datenbanktransaktion. Schlägt der Statuswechsel fehl, wird die Transaktion zurückgerollt.
- **Wichtige Grenze:** Die aktuelle Aktivierung schaltet nur den Organisationsstatus in der App um. Sie provisioniert noch keine Nextcloud-Gruppen, Talk-Räume, Gruppenordner oder Kalender. Bis die Ressourcenorchestrierung umgesetzt und getestet ist, darf die Aktivierung nicht als vollständiger produktiver Jahreswechsel verstanden werden.

### Noch ausstehende Schritte

1. Wiederholbare Provisionierungsjobs mit Status-/Fehlerprotokoll für Nextcloud-Gruppen.
2. Ressourcenadapter für Talk-Räume, Gruppenordner und Kalender.
3. Vorbereitung und Prüfung aller Ressourcen vor dem finalen Periodenwechsel.
4. Archivfreigaben für alle aktuellen Mitglieder der App-Zugriffsgruppe.
5. Eigener Gruppenleitungs-Arbeitsbereich mit ausschließlich gruppenbezogenen Verwaltungsaktionen.
6. Laufzeit- und Integrationstests auf der Zielinstanz mit Nextcloud Hub 26 / Server 35.

Die App verändert weder die Authentik-Zugriffsgruppe noch historische Mitgliedschafts- oder Ressourceninhalte automatisch beim Austritt einer Person.
