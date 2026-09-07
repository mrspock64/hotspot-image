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
- **One-click Backup/Restore** — reflector certificate, node config, buttons, and TG names bundled into a single downloadable zip
- **A modernized dashboard UI** on top of all of the above, without touching the underlying page logic more than the security/correctness fixes required

## What this is — and isn't

**This upgrades an existing RF.Guru node.** It is not a from-scratch Raspberry Pi OS installer. `setup.sh` relies on files RF.Guru's own image already provides and this project has never needed to generate itself:

- `/etc/svxlink/svxlink.conf`
- `/etc/asound.conf` (the ALSA dmix/dsnoop setup RX Monitor/QSO Log build on)
- `/etc/svxlink/node_info.json`
- A signed reflector certificate under `/var/lib/svxlink/pki/` — this specifically requires registering with your reflector network's admin; nothing here can automate that step.

To provision a **new** node: boot RF.Guru's own stock image first (following their own setup), get it far enough to have a working `svxlink.conf` and a signed certificate, then run this project's `setup.sh` on top of it. `setup.sh` refuses to run (with a clear message) if `/etc/svxlink/svxlink.conf` doesn't exist yet, rather than failing partway through in a confusing way.

A true blank-Pi installer (generating `svxlink.conf`/`asound.conf`/`node_info.json` from a template, automating the CSR half of certificate signing) is future work, not something this does today.

This exact path is what **svxlinkuhf** (the project's test node) went through — verified live on real hardware, not just reviewed.

## Running it

```bash
sudo bash setup.sh
```

Doesn't ask interactive questions (unlike RF.Guru's own `hotspot-config`) — callsign, reflector, radio frequency, and location are all configured afterwards from the dashboard's **Setup** page once the device is reachable on the network.

What it does, in order:

1. Base packages (avahi, NetworkManager)
2. Persistent crash logging (RF.Guru's stock image only logs to memory — a crash mid-investigation otherwise leaves zero forensic trail)
3. Audio (WM8960 codec) + GPIO PTT — RF.Guru's own proven steps, unchanged
4. Builds stock SvxLink from source and installs our own `Logic.tcl` (never RF.Guru's)
5. Installs the dashboard fork
6. Installs the watchdog
7. Sets up RX Monitor (live audio streaming) and enables SvxLink's built-in QSO Recorder
8. Bluetooth companion-app support — skipped automatically if already installed; self-gating, so it's safe to re-run even if the OS needs an `apt upgrade` + reboot first

After it finishes:

1. Join WiFi (or connect via ethernet) and open `http://<hostname>.local/`
2. Use the **WiFi** page to join your real network if not already on it
3. Use the **Setup** page to configure callsign, reflector, radio, and location
4. Restart SvxLink from the **Power** page
5. See the dashboard's own **Help** page (`/help/`) for what every page does

## Project layout

- `setup.sh` — the upgrade script described above
- `lib/` — the individual steps `setup.sh` runs:
  - `lib/rx-monitor/` — the RX Monitor/QSO Log backend: a vendored [DVSwitch Web_Proxy](https://github.com/DVSwitch/DVSwitch-Dashboard), a Python watcher over SvxLink's QSO Recorder output (tails the recorder's own in-progress file directly, not the finished/renamed one), and the systemd units for both
  - `lib/install-bluetooth.sh` — a vendored, reviewed copy of RF.Guru's own `install-bluetooth.sh` (deliberately not fetched fresh on every run, unlike their `hotspot-config` — see the script's own header comment for why that distinction matters); sets up the BLE GATT service the companion app talks to
- `events.d/Logic.tcl` — our replacement for RF.Guru's `Logic.tcl`: correct `locale.tcl` sourcing (the actual root cause fix) plus a clean `D911#` IP-readout implementation
- `dashboard/` — the dashboard fork itself (forked from [Guru-RF/SVXLink-Dash-V2](https://github.com/Guru-RF/SVXLink-Dash-V2))

## License

[MIT](LICENSE) for this project's own original work. The forked dashboard base and the vendored `Web_Proxy` file keep their own upstream terms — see the LICENSE file for the details.

## Credits

SA0LEK, Claude — plus the dashboard's original lineage: ON3URE, G4NAB, SP2ONG, SP0DZ.
