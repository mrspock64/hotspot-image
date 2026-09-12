# dashboard-v2 modulkoncept för hotspot-image

**Mål:** Bygga en ny, modulbaserad dashboard-frontend för RF.Guru Analog-HotSPOT-SVXLink-noden `svxlinkuhf`, utan att röra den befintliga produktionsdashboarden i `dashboard/`. Körs parallellt på port `:8081` på svxlinkuhf (samma mönster som projektets tidigare `:8081`-preview innan dashboard-fork-cutovern).

**Branch:** `dashboard-v2` (redan pushad till origin) — jobba här, inte på `main`. `main` är produktionskoden och rörs inte. (Ersätter den ursprungliga `signal-monitor`-branchen, som av misstag även rörde `events.d/Logic.tcl`, `setup.sh` och `dashboard/include/site_header.php` — utanför scope. `dashboard-v2` skapades ren från `main` med bara de commits som höll sig till `dashboard-v2/` + `lib/dashboard-v2/`.)

**Design-referens:** `docs/dashboard-redesign-concept.html` — en Artifact-mockup (mörk cockpit-stil, kopparaccent, IBM Plex Mono/Oswald/IBM Plex Sans) byggd i en tidigare session. Den är bara en visuell skiss, inte kopplad till riktig backend — men **designtokens** (färger, typsnitt, panel-layout) i den filens `<style>`-block är facit att bygga vidare på.

## Arkitektur — modulkontrakt

Varje panel (Signal, Node, QSO Log, Vitals, RX Monitor, …) ska vara ett självständigt paket bestående av tre delar:

1. En JSON-endpoint (`api/<modul>.php`) — en **tunn wrapper** runt befintlig PHP-logik i `dashboard/include/*.php` (t.ex. `qso_recorder.php`, `inisync.php`). Skriv INTE om den logiken, återanvänd den.
2. En JS-fil som renderar panelen (Web Component, `customElements.define`, inga ramverk).
3. En liten manifest (titel, refresh-intervall, storlek).

Huvuddashboarden blir bara en lista: "visa dessa moduler". Att lägga till en ny modul senare (t.ex. reflektor-info) ska vara att lägga till tre nya filer, inte att ändra huvudsidan.

**Flytta/stänga av moduler:** varje **sida** (Dashboard, QSO Log, …) har sin egen `dashboard-v2/pages/<sida>/layout.json` — den bestämmer vilka moduler som visas på just den sidan, i vilken kolumn (1/2/3), och i vilken ordning inom kolumnen (ordningen i arrayen = ordningen på skärmen):

```json
{ "id": "frequency", "col": 1, "enabled": true }
```

- **Flytta en modul**: ändra `col` (1/2/3) eller flytta raden till en annan plats i arrayen — eller dra kortet i `/settings/?page=<sida>`.
- **Stänga av/på en modul**: sätt `enabled` till `false`/`true` (eller kryssa ur i inställningssidan) — modulens filer rörs aldrig, den bara laddas inte.
- En moduls egen `manifest.json` vet aldrig var den bor — bara vad den heter/behöver (API-endpoint, refresh-intervall, extra inställningar). Det håller modulerna genuint flyttbara **mellan sidor också**, inte bara inom en sida.

**Inställningssida med drag-and-drop finns:** `/settings/?page=dashboard` (eller `?page=qsolog` osv.) — dra kort mellan kolumner, kryssa av/på, spara. Sparas till `/var/cache/hotspot-image/dashboard-v2-layout[-<sida>].json` (aldrig till git-checkouten, se `api/layout.php`:s egen kommentar för varför).

### Flera sidor

`dashboard-v2/js/pages-nav.js` har listan över sidor (delas av alla sidors topbar). Att lägga till en ny sida:

1. Skapa `dashboard-v2/<sida>/index.html` (kopiera `qsolog/index.html`, byt titel/`data-page`).
2. Skapa `dashboard-v2/pages/<sida>/layout.json` (standardval av moduler för den sidan).
3. Lägg till sidan i `js/pages-nav.js`:s `DASHBOARD_V2_PAGES`-lista och i `settings/settings.js`:s `PAGE_META`.

Alla sökvägar i manifests/HTML är **rot-absoluta** (`/api/...`, `/modules/...`, `/css/...`) med flit — en sida en katalognivå ner (t.ex. `/qsolog/`) skulle annars få relativa sökvägar att peka fel.

**Byggda sidor hittills:** Dashboard (`/`), QSO Log (`/qsolog/`), RX Monitor (`/rxmonitor/`), Setup (`/setup/`, **read-only** — se nedan).

### Setup-sidan är medvetet read-only

Till skillnad från övriga sidor **skriver** produktionens Setup-sida till `svxlink.conf`/`node_info.json`. `dashboard-v2/api/setup.php` visar bara nuvarande värden (callsign, reflektor, frekvens, plats, m.m.) — ingen spara-knapp, ingen skrivning. Länkar till den riktiga Setup-sidan (`http://<host>/setup/`, port 80) för faktisk redigering.

Läslogiken är **duplicerad** från `dashboard/setup/index.php`:s `readCurrent()`, inte `require()`:ad — den funktionen ligger inline i en sida (blandad med sidans egen POST-hantering och HTML), inte i en separat `dashboard/include/*.php`-fil som `qso_recorder.php`/`inisync.php`. Håll den i synk för hand om produktionens `readCurrent()` ändras.

Ett riktigt läs+skriv-Setup (återanvänder produktionens egen `iniSyncUpdateSection()`-skrivväg) är nästa steg **om/när** det efterfrågas — egen, försiktigare verifiering då (en skarp sparning mot `svxlink.conf` måste testas live innan det kallas klart).

## Första riktiga modulen

Signal/WiFi-kortet — verklig data från `/proc/net/wireless` på svxlinkuhf (dBm, paketförlust). Det här är redan bevisad, verklig data: svxlinkuhf:s WiFi gick från -82dBm till -74dBm genom att flytta noden fysiskt (löste QSO-uppspelningens svarsträghet, se `dashboard/qsolog/play.php`:s Range-stöd-commit samma dag). Använd riktiga siffror, inte fejkdata.

## Byggda moduler hittills

Frequency, Radio Status, Talkgroup, Node/Vitals, WiFi Signal, RX Monitor, Reflector Activity, Monitored Talkgroups, Auto-update, QSO Log, Setup (read-only) — alla live på svxlinkuhf:8081 med riktig data.

Talkgroup/Reflector Activity/Monitored Talkgroups/Radio Status delar alla samma källa (`/var/log/svxlink`): `Selecting TG #<n>`, `Talker start/stop on TG #<n>: <call>`, `Node joined/left: <call>`, `Tx1: Turning the transmitter ON/OFF`, `Rx1: The squelch is OPEN/CLOSED` loggas redan verbatim av SvxLink självt — samma logg `lib/rx-monitor/tag_and_encode.py` redan tailar. Ingen ny backend-daemon behövdes för nån av dem. OBS tidszonsfälla löst i alla fyra: SvxLink:s loggtider är `localtime()` utan tidszon i strängen — PHP måste sättas till samma tidszon som `/etc/timezone` innan `strtotime()`, annars blir allt "0s ago" om PHP:s standard (ofta UTC) skiljer sig från systemets faktiska zon.

## Övriga funktioner

- **Döpa om sidor + kolumnantal (1–4)**: `dashboard-v2/pages/<sida>/meta.json` (standard) + sparad override, redigerbart i `/settings/?page=<sida>`. Samma mbstring-fälla som andra API:er kan råka ut för — se `api/page-meta.php`:s kommentar.
- **Detacha modul till eget fönster**: hover-knapp (↗) på varje panel, öppnar `/module/?id=<id>` (`js/module.js`) — exakt samma manifest/panel.js/api, ingen modul behöver veta att den är lösgjord. `popup=yes` krävs i `window.open`-featuresträngen, annars öppnas det som en vanlig flik (hittat live).
- **App-ikon/namn**: `manifest.webmanifest` + `icons/` (kopparfärgad "HS", matchar topbaren) för Chromes "Skapa genväg". **OBS:** ger bara rätt namn/ikon på genvägen — Chromes riktiga "Öppna som fönster"-läge (och install-ikonen i adressfältet) kräver HTTPS/`localhost`, funkar inte över vanlig HTTP. Se backlog nedan.

## Backlog

- **HTTPS för dashboard-v2:8081** — enda sättet att få Chromes riktiga "Installera app"/"Öppna som fönster" att fungera (bekräftat live: utan HTTPS visas inte kryssrutan alls, genvägen öppnar bara en vanlig flik). Kräver självsignerat cert (eller mkcert) för svxlinkuhf + antingen TLS-stöd i PHP:s inbyggda server eller en nginx/Apache-proxy framför `:8081`, plus att godkänna certifikatvarningen en gång per enhet. Medvetet pausad 2026-09-12, inte bortglömd.

## Viktiga ramar

- Rör aldrig `dashboard/` (produktion) eller `main`-branchen.
- Testnod: bara `svxlinkuhf` — svxlinkmobile och den orelaterade "svxlink"-noden (10.10.0.206, ett annat, orelaterat Pi-projekt) är inte inblandade.
- Verifiera live på svxlinkuhf:8081, inte bara lokalt/syntetiskt, enligt projektets vanliga arbetssätt (lint → deploy → curl/live-test → commit → push).
