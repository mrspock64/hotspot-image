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

## Första riktiga modulen

Signal/WiFi-kortet — verklig data från `/proc/net/wireless` på svxlinkuhf (dBm, paketförlust). Det här är redan bevisad, verklig data: svxlinkuhf:s WiFi gick från -82dBm till -74dBm genom att flytta noden fysiskt (löste QSO-uppspelningens svarsträghet, se `dashboard/qsolog/play.php`:s Range-stöd-commit samma dag). Använd riktiga siffror, inte fejkdata.

## Byggda moduler hittills

Frequency, Node/Vitals, WiFi Signal, RX Monitor, Auto-update, QSO Log — alla live på svxlinkuhf:8081 med riktig data.

## Backlog — Talkgroup/reflector-info (INTE byggd än)

Medvetet uteskjuten, inte bortglömd. Frequency-modulen (`dashboard-v2/api/frequency.php`) visar callsign/frekvens/nätverk men **ingen live "vilken talkgroup är aktiv just nu"** — den datan finns inte exponerad nånstans i kodbasen idag. `tg.php`:s Monitor/Activate-knappar är envägs-DTMF-triggers (se `events.d/Logic.tcl`), ingen readback av vad SvxLink/reflektorn faktiskt har länkat just nu.

För att bygga den här modulen på riktigt behövs först en riktig källa till reflektor-/TG-state — utred om SvxLink Reflector exponerar ett status-API, eller om det måste läsas ur loggar/en statusfil. Tills dess: inget att fejka här.

## Viktiga ramar

- Rör aldrig `dashboard/` (produktion) eller `main`-branchen.
- Testnod: bara `svxlinkuhf` — svxlinkmobile och den orelaterade "svxlink"-noden (10.10.0.206, ett annat, orelaterat Pi-projekt) är inte inblandade.
- Verifiera live på svxlinkuhf:8081, inte bara lokalt/syntetiskt, enligt projektets vanliga arbetssätt (lint → deploy → curl/live-test → commit → push).
