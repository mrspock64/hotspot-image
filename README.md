# hotspot-image

**Stock SvxLink + a rebuilt dashboard for RF.Guru Analog-HotSPOT-SVXLink nodes.**

An upgrade path for an [RF.Guru Analog-HotSPOT-SVXLink](https://github.com/Guru-RF/Analog-HotSPOT-SVXLink) node: stock SvxLink instead of RF.Guru's own buggy `Logic.tcl` (root cause of a cluster of bugs — see [issue #2](https://github.com/Guru-RF/Analog-HotSPOT-SVXLink/issues/2) upstream), plus a rebuilt, secured, modernized dashboard fork.

<!-- Drop a screenshot of the Dashboard page here, e.g.: ![Dashboard](docs/dashboard.png) -->

## Highlights

- **Fixes the root cause** of a cluster of SvxLink bugs (crashes, missing periodic ID, silent DTMF commands) inherited from RF.Guru's own `Logic.tcl` — traced to a missing `locale.tcl` sourcing step, not the individual symptoms
- **A real security pass**: fixed command-injection bugs across WiFi/Network/EchoLink/Buttons, removed several unauthenticated/orphaned pages including a root-level remote code execution vulnerability in the stock dashboard
- **RX Monitor** — listen live, in real time, in the browser, to whatever the node is currently receiving or relaying (local RX *and* reflector traffic, including bursty digital-voice-relay traffic), backed by SvxLink's own QSO Recorder rather than a raw ALSA hack
- **QSO Log** — record transmissions on demand, browsable/playable/downloadable, with on/off, disk-limit, and max-recordings-to-keep controls in the dashboard itself (off by default — a per-node decision to opt into, not something this project turns on for you); each recording auto-tagged with talkgroup/callsign when known
- **Bluetooth companion-app support** — lets [svxlink-hotspot.app](https://svxlink-hotspot.app) (iOS/Android) drive the node without SSH, off by default with an optional "require pairing" mode
- **Power page** — start/stop/restart SvxLink independently of restarting/powering off the whole device, with live status
- **Updater** — checks/upgrades for the OS, SvxLink, and this dashboard itself from the browser, each upgrade confirmed first; upgrading the OS snapshots installed package versions beforehand and cleans up old kernels automatically
- **Editable Buttons and Talk Group names** — no more SSH + text editor to relabel the quick-DTMF buttons or the Talk Groups table
- **One-click Backup/Restore** — reflector certificate, node config, buttons, and TG names bundled into a single downloadable zip, with configurable retention on old backups
- **Load & temperature watchdog** — background service watching load/I-O-wait/memory and CPU temperature, with header badges and optional (off by default) auto-responses: pause QSO Recorder under load, stop SvxLink or transmit a spoken alert under sustained heat
- **Guru / Turbo performance mode** — a Power-page toggle between RF.Guru's own stock underclocked "Temperature Tuning" and full-speed Turbo (all 4 real cores) — confirmed live, no thermal issue in open air; hardware-guarded to Pi Zero 2 W only
- **Radio Test + Sound Library** — simulate a real QSO's rhythm to exercise the radio module itself (e.g. checking whether it's the actual source of thermal throttling), using SvxLink's own DTMF-command mechanism so it coexists safely with real traffic; save named custom/alert messages from text-to-speech or a real uploaded recording and pick which is active
- **Selectable voice language** — which SvxLink sound-clip pack is used for stock announcements is a Setup-page dropdown instead of an svxlink.conf edit
- **A modernized dashboard UI** on top of all of the above, without touching the underlying page logic more than the security/correctness fixes required

## Getting started (step by step)

**Before you start, you need:**

1. An RF.Guru Analog-HotSPOT-SVXLink node already booted on RF.Guru's own stock image, reachable over SSH (e.g. `ssh hotspot@<yourhostname>.local`)
2. That node already taken through RF.Guru's own `hotspot-config` (or equivalent) far enough that `/etc/svxlink/svxlink.conf` exists and you have a reflector certificate signed by your reflector network's admin — this project can't automate that registration step, see "What this is — and isn't" below
3. This repo is currently **private**, so cloning it needs a token. On github.com: **Settings → Developer settings → Personal access tokens → Generate new token** (a fine-grained token scoped to just this repo, read-only, is enough)

**Then, on the node itself:**

```bash
# 1. SSH into the node
ssh hotspot@<yourhostname>.local

# 2. Clone this repo -- paste your token in place of YOUR_TOKEN.
#    Clone into your home directory, NOT /opt/hotspot-image: setup.sh
#    manages that path itself (see the note below) and it's also
#    root-owned, so a plain `git clone` there fails with "Permission
#    denied" anyway.
git clone https://YOUR_TOKEN@github.com/mrspock64/hotspot-image.git ~/hotspot-image
cd ~/hotspot-image

# 3. Run the installer -- takes a while (SvxLink is built from source), and
#    does not ask any interactive questions
sudo bash setup.sh

# 4. Reboot once it finishes, so every config.txt change takes effect cleanly
sudo reboot
```

**After the reboot:**

1. Open `http://<yourhostname>.local/` in a browser
2. If you're not already on your real WiFi network, join it from the **WiFi** page
3. Go to the **Setup** page and fill in callsign, reflector domain/email, radio frequency, and location
4. Restart SvxLink from the **Power** page for the Setup changes to take effect
5. The dashboard's own **Help** page (`/help/`) explains what every other page does

That's it — no interactive prompts, no manual config-file editing required for a normal install.

**Note on the Update page:** `setup.sh` re-clones the repo into `/opt/hotspot-image` itself (that's the copy the dashboard actually serves and where its Update page looks for new commits — see `lib/install-dashboard.sh`). While this repo is private, that step can't authenticate on its own, so it just copies your local checkout there instead of doing a real `git clone` — the install itself works fine, but the Update page's "Dashboard" check/upgrade won't be able to fetch new commits afterward. Set `DASHBOARD_REPO_URL` (a `git@github.com:...` SSH URL, using a read-only deploy key added under this repo's **Settings → Deploy keys**) before running `setup.sh` if you want the Update page to work right away:

```bash
DASHBOARD_REPO_URL="git@github.com:mrspock64/hotspot-image.git" sudo -E bash setup.sh
```

Otherwise, add a deploy key after the fact and point `/opt/hotspot-image`'s `origin` at it manually — this is what `svxlinkuhf` (the project's own test node) does.

## What this is — and isn't

**This upgrades an existing RF.Guru node.** It is not a from-scratch Raspberry Pi OS installer. `setup.sh` relies on files RF.Guru's own image already provides and this project has never needed to generate itself:

- `/etc/svxlink/svxlink.conf`
- `/etc/asound.conf` (the ALSA dmix/dsnoop setup RX Monitor/QSO Log build on)
- `/etc/svxlink/node_info.json`
- A signed reflector certificate under `/var/lib/svxlink/pki/` — this specifically requires registering with your reflector network's admin; nothing here can automate that step.

To provision a **new** node: boot RF.Guru's own stock image first (following their own setup), get it far enough to have a working `svxlink.conf` and a signed certificate, then run this project's `setup.sh` on top of it. `setup.sh` refuses to run (with a clear message) if `/etc/svxlink/svxlink.conf` doesn't exist yet, rather than failing partway through in a confusing way.

A true blank-Pi installer (generating `svxlink.conf`/`asound.conf`/`node_info.json` from a template, automating the CSR half of certificate signing) is future work, not something this does today.

This exact path is what **svxlinkuhf** (the project's test node) went through — verified live on real hardware, not just reviewed.

## What `setup.sh` actually does

Doesn't ask interactive questions (unlike RF.Guru's own `hotspot-config`) — callsign, reflector, radio frequency, and location are all configured afterwards from the dashboard's **Setup** page once the device is reachable on the network. In order:

1. Base packages (avahi, NetworkManager)
2. Persistent crash logging (RF.Guru's stock image only logs to memory — a crash mid-investigation otherwise leaves zero forensic trail)
3. Audio (WM8960 codec) + GPIO PTT — RF.Guru's own proven steps, unchanged
4. Builds stock SvxLink from source and installs our own `Logic.tcl` (never RF.Guru's) — a full copy of stock content with hotspot-image's D911#/D920#/D921# additions merged in, not a from-scratch subset (RF.Guru's image has no real events.d/local override layer — `local` is a symlink to `events.d` itself)
5. Installs a Swedish sound-clip pack (`sv_SE`, real human voice) for stock SvxLink announcements alongside the English one RF.Guru ships — selectable afterwards on the Setup page
6. Installs the dashboard fork
7. Installs the watchdog
8. Installs the load/I-O-wait/memory/temperature monitor, with two built-in (callsign-free) temperature-alert voice clips (Swedish + English) ready to use
9. Sets up RX Monitor (live audio streaming) and enables SvxLink's built-in QSO Recorder
10. Bluetooth companion-app support — skipped automatically if already installed; self-gating, so it's safe to re-run even if the OS needs an `apt upgrade` + reboot first

See "Getting started" above for what to do next once it finishes.

## Project layout

- `setup.sh` — the upgrade script described above
- `lib/` — the individual steps `setup.sh` runs:
  - `lib/rx-monitor/` — the RX Monitor/QSO Log backend: a vendored [DVSwitch Web_Proxy](https://github.com/DVSwitch/DVSwitch-Dashboard), a Python watcher over SvxLink's QSO Recorder output (tails the recorder's own in-progress file directly, not the finished/renamed one), and the systemd units for both
  - `lib/load-monitor/` — the load/I-O-wait/memory/temperature watchdog (`monitor.sh`, systemd unit), the Radio Test page's QSO-simulation script (`qso_simulate.sh`), and the two built-in temperature-alert voice clips under `alerts/`
  - `lib/install-language-pack.sh` — installs the Swedish sound-clip pack and sets it as SvxLink's default announcement language
  - `lib/install-bluetooth.sh` — a vendored, reviewed copy of RF.Guru's own `install-bluetooth.sh` (deliberately not fetched fresh on every run, unlike their `hotspot-config` — see the script's own header comment for why that distinction matters); sets up the BLE GATT service the companion app talks to
- `events.d/Logic.tcl` — our replacement for RF.Guru's `Logic.tcl`: correct `locale.tcl` sourcing (the actual root cause fix), a full copy of stock content (not a from-scratch subset — RF.Guru's image has no real override layer), plus `D911#`/`D920#`/`D921#` DTMF-command additions
- `dashboard/` — the dashboard fork itself (forked from [Guru-RF/SVXLink-Dash-V2](https://github.com/Guru-RF/SVXLink-Dash-V2)); notably `dashboard/soundlib/` (named custom/alert messages) and `dashboard/radiotest/` (QSO simulation)

## License

[MIT](LICENSE) for this project's own original work. The forked dashboard base and the vendored `Web_Proxy` file keep their own upstream terms — see the LICENSE file for the details.

## Credits

SA0LEK, Claude — plus the dashboard's original lineage: ON3URE, G4NAB, SP2ONG, SP0DZ.
