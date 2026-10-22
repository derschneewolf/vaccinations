# Entwicklungsinstanz auf der Synology DiskStation

Nextcloud + PostgreSQL + Cron + Frontend-Build als Container-Manager-Projekt. Das Repo selbst wird als App eingehängt – Änderungen nach `git pull` sind sofort aktiv, ohne Neubau.

| Container | Aufgabe |
|---|---|
| `app` | Nextcloud (Apache) auf Port `NC_PORT` |
| `db` | PostgreSQL 17 |
| `cron` | Nextcloud-Hintergrundjobs alle 5 Minuten |
| `frontend` | baut die Oberfläche nach `js/` und `css/` und baut bei jeder Änderung automatisch neu (`npm run watch`) |
| `init-permissions` | läuft einmal beim Start und gibt `custom_apps` an `www-data` |

Nur fürs LAN gedacht: kein HTTPS, Debug-Modus an.

## Einmalig einrichten

1. **SSH auf die DiskStation** (Systemsteuerung → Terminal & SNMP → SSH aktivieren) und Git bereitstellen, z. B. über das Paket *Git Server* im Paket-Zentrum.

2. **Repo klonen:**
   ```sh
   cd /volume2/docker
   git clone https://github.com/derschneewolf/vaccinations.git
   cd vaccinations/dev
   cp .env.example .env
   ```
   In `.env` die drei Passwörter setzen. Port (Standard `8090`) und `NC_TRUSTED_DOMAINS` bei Bedarf anpassen.

3. **Container Manager → Projekt → Erstellen**
   - Projektname: `vaccinations-dev`
   - Pfad: `/volume2/docker/vaccinations/dev`
   - Quelle: *Vorhandene compose.yaml verwenden*
   - Webportal-Einstellungen überspringen, dann *Fertig*.

4. **Erster Start dauert 1–2 Minuten** (Images laden, Nextcloud installieren). Danach: <http://ds925p:8090>

## Was die Installation automatisch einrichtet

`hooks/post-installation/10-dev-setup.sh` läuft genau einmal nach der Erstinstallation:

| Benutzer | Rolle |
|---|---|
| `admin` | Nextcloud-Admin (Passwort `NC_ADMIN_PASSWORD`) |
| `frank` | normaler User, **Betreuer von `kind`** |
| `kind` | normaler User |

Beide Test-User haben das Passwort `DEV_USER_PASSWORD`. Außerdem: Debug-Modus, Hintergrundjobs per Cron, App `vaccinations` aktiviert (dabei läuft die DB-Migration).

> Wird der Hook nicht ausgeführt (im Log steht „lacks the executable flag“), wurde das Repo ohne Dateirechte kopiert. Dann: `chmod +x hooks/post-installation/*.sh` und die Instanz neu aufsetzen (siehe unten).

## Fehlerbehebung

**„Cannot write into "apps" directory“ auf der App-Seite:** Der Ordner `custom_apps` gehört `root`. Seit dem Init-Container `init-permissions` passiert das bei neuen Instanzen nicht mehr; bei einer älteren Instanz einmalig:
```sh
sudo docker exec vaccinations-dev-app-1 chown www-data:www-data /var/www/html/custom_apps
```

**Oberfläche zeigt nach `git pull` noch den alten Stand:** Der `frontend`-Container hat die Änderung nicht mitbekommen. Neu bauen lassen:
```sh
sudo docker restart vaccinations-dev-frontend-1
sudo docker logs -f vaccinations-dev-frontend-1   # warten auf „built in …“, dann Strg+C
```

## Im Alltag

**Neuen Stand holen:**
```sh
cd /volume2/docker/vaccinations && git pull
cd dev && sudo docker compose -p vaccinations-dev up -d
sudo docker exec -u www-data vaccinations-dev-app-1 php occ upgrade
```
- `git pull` holt den Code; PHP-Änderungen sind damit sofort aktiv.
- `docker compose up -d` übernimmt Änderungen an `compose.yaml` (z. B. neue Container). Ohne Änderungen passiert nichts.
- `occ upgrade` ist nötig, wenn sich die App-Version in `appinfo/info.xml` geändert hat (neue Migrationen, Befehle, Navigation). Sonst meldet es nur „Nextcloud is already latest version“.
- Die Oberfläche baut der `frontend`-Container automatisch neu (ca. 20–60 s). Fortschritt im Log des Containers `vaccinations-dev-frontend-1`.
- Danach im Browser **Strg + F5** (bzw. Strg + Umschalt + R). Ein normales Neuladen reicht nicht: Nextcloud erlaubt dem Browser, die Skripte lange zu cachen.

**occ aufrufen** – Container Manager → Container → `vaccinations-dev-app-1` → Terminal → *Erstellen* mit Befehl `bash`, dann:
```sh
su -s /bin/sh www-data -c "php occ vaccinations:guardian:list"
```
oder per SSH:
```sh
sudo docker exec -u www-data vaccinations-dev-app-1 php occ vaccinations:guardian:list
```

Betreuer-Befehle: `vaccinations:guardian:add <betreuer> <person>`, `:remove`, `:list`.

Benachrichtigungen sofort prüfen statt auf den täglichen Hintergrundjob zu warten: `vaccinations:notify` (optional mit User-ID).

## Neu aufsetzen / andere Nextcloud-Version

Per SSH Container **und** Volumes (Datenbank, Nextcloud-Daten) löschen:
```sh
cd /volume2/docker/vaccinations/dev
sudo docker compose -p vaccinations-dev down -v
```
Danach ggf. `NC_VERSION` in `.env` ändern und das Projekt im Container Manager wieder *Starten* bzw. *Erstellen*.

Ein Downgrade auf eine ältere Nextcloud-Version geht nur mit frischen Volumes; ein Upgrade (z. B. 34 → 35) klappt auch über bestehende.
