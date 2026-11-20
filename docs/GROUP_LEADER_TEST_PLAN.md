# Testplan: Gruppenleitungsbereich

## Voraussetzungen

- Nextcloud Server 35 mit aktivierter Jugend-App.
- Authentik-App-Zugriffsgruppe ist mit Nextcloud synchronisiert.
- Mindestens eine aktive Organisationsperiode mit zwei Gruppen.
- Gruppe A und Gruppe B haben jeweils genau zwei aktive Gruppenleitungen.
- Mindestens ein App-Admin ohne globale Nextcloud-Administratorrechte.
- Mindestens ein normaler Gruppenleiter, der kein App-Admin ist.
- Nextcloud-Cron läuft im System-Cron-Modus.

## Funktionstests

### 1. App-Admin kann Entwürfe bearbeiten

1. Als App-Admin ohne globale Nextcloud-Adminrechte anmelden.
2. Organisationsseite öffnen und eine Entwurfsperiode auswählen.
3. Mitglieder und genau zwei Gruppenleitungen einer Gruppe speichern.
4. Erwartung: Speichern erfolgreich; die Mitgliedschaftsintervalle werden historisiert.

Dieser Test prüft insbesondere, dass globale App-Admin-Berechtigungen nicht durch die Aktivitätsprüfung eines Draft-Scopes blockiert werden.

### 2. Gruppenleitung sieht nur berechtigte Gruppen

1. Als Gruppenleitung von Gruppe A anmelden.
2. Auf der Hauptseite „Meine Arbeitsgruppen“ öffnen.
3. Erwartung: Gruppe A wird angezeigt, Gruppe B nicht.
4. Eine API-Anfrage auf die Gruppenliste muss dasselbe Ergebnis liefern.

### 3. Geplante Gruppe für die nächste Periode bearbeiten

1. Eine Entwurfsperiode aus der aktiven Periode kopieren.
2. Als aktuelle Gruppenleitung die passende Gruppe mit identischem technischem Slug im Gruppenleitungsbereich auswählen.
3. Mitglieder und die beiden Leitungsrollen für den Entwurf ändern.
4. Erwartung: Die Änderung ist möglich und wird historisiert. Die aktuelle aktive Gruppe bleibt unverändert; externe Ressourcen werden für den Entwurf noch nicht freigegeben.

### 4. Fremde Gruppe bleibt serverseitig geschützt

1. Als Gruppenleitung von Gruppe A die Gruppen-ID von Gruppe B verwenden.
2. Den Endpunkt zum Speichern der Mitgliedschaften direkt mit dieser Gruppen-ID aufrufen.
3. Erwartung: HTTP 403; keine Datenbankänderung.

### 5. Aktive Mitgliedschaften ändern

1. Als Gruppenleitung von Gruppe A ein aktuelles Mitglied hinzufügen.
2. Genau zwei Personen als Gruppenleitung markieren.
3. Speichern.
4. Erwartung: Änderung wird in der Tabelle jugend_members als neues Mitgliedschaftsintervall gespeichert.
5. Innerhalb des nächsten 15-Minuten-Laufs müssen Authentik-Mitgliedschaft, Talk-Teilnehmer, Groupfolder-Freigabe und Kalenderfreigabe abgeglichen werden.

### 6. Nicht zugelassene Personen

1. Eine Person verwenden, die nicht in der aktuellen Authentik-App-Zugriffsgruppe ist.
2. Diese Person über den API-Endpunkt als Mitglied übermitteln.
3. Erwartung: HTTP 400 und keine Änderung an der Mitgliedschaft.

### 7. Gruppenleitung entziehen

1. Als berechtigte Person die bisherige Gruppenleitung durch eine andere Person ersetzen.
2. Speichern.
3. Erwartung: Die bisherige Gruppenleitung verliert beim nächsten Request die dynamische Berechtigung für diese Gruppe.

### 8. Ressourcenkurzlinks

1. Für eine aktive Gruppe Talk, Groupfolder und Kalender bereitstellen.
2. Gruppenleitungsbereich neu laden.
3. Erwartung: Die drei Kurzlinks öffnen die zugehörigen Ressourcen.
4. Nicht bereitgestellte Ressourcen werden als solche angezeigt; es dürfen keine erfundenen Links entstehen.

### 9. Benutzer ohne Gruppenberechtigung

1. Als normaler Benutzer ohne Gruppenleitung und ohne Gruppenberechtigung anmelden.
2. /apps/jugend/groups direkt aufrufen und API-Endpunkte testen.
3. Erwartung: Die Seite wird verweigert; die Benutzerliste und Mitgliedschaftsänderung liefern HTTP 403.

## Integrations- und Regressionstests

- Authentik-Gruppen-Synchronisierung in Nextcloud.
- Talk-Teilnehmer hinzufügen und entfernen.
- Groupfolder-Freigaben mit Vollzugriff für aktive Gruppen.
- Kalenderfreigaben mit Schreibrechten für aktive Gruppen.
- Archivzugriff nach Periodenwechsel.
- App-Admin-Rollen, globale/perioden-/gruppengebundene Berechtigungen.
- Austritt aus der übergeordneten App-Zugriffsgruppe.
