# hotspot-image

An upgrade path for an [RF.Guru Analog-HotSPOT-SVXLink](https://github.com/Guru-RF/Analog-HotSPOT-SVXLink) node: stock SvxLink instead of RF.Guru's own buggy `Logic.tcl` (root cause of a cluster of bugs — see [issue #2](https://github.com/Guru-RF/Analog-HotSPOT-SVXLink/issues/2) upstream), plus a rebuilt, secured, modernized dashboard fork.

## What this is — and isn't

**This upgrades an existing RF.Guru node.** It is not a from-scratch Raspberry Pi OS installer. `setup.sh` relies on files RF.Guru's own image already provides and this project has never needed to generate itself:

- `/etc/svxlink/svxlink.conf`
- `/etc/asound.conf` (the ALSA dmix/dsnoop setup RX Monitor/QSO Log build on)
- `/etc/svxlink/node_info.json`
- A signed reflector certificate under `/var/lib/svxlink/pki/` — this specifically requires registering with your reflector network's admin; nothing here can automate that step.

To provision a **new** node: boot RF.Guru's own stock image first (following their own setup), get it far enough to have a working `svxlink.conf` and a signed certificate, then run this project's `setup.sh` on top of it. `setup.sh` refuses to run (with a clear message) if `/etc/svxlink/svxlink.conf` doesn't exist yet, rather than failing partway through in a confusing way.

A true blank-Pi installer (generating `svxlink.conf`/`asound.conf`/`node_info.json` from a template, automating the CSR half of certificate signing) is future work, not something this does today.

This exact path is what [svxlinkuhf](https://github.com/mrspock64/hotspot-image) (the project's test node) went through — verified live on real hardware, not just reviewed.

## Running it

```bash
sudo bash setup.sh
```

Doesn't ask interactive questions (unlike RF.Guru's own `hotspot-config`) — callsign, reflector, radio frequency, and location are all configured afterwards from the dashboard's **Setup** page once the device is reachable on the network.

What it does, in order:

1. Base packages (avahi, NetworkManager)
2. Audio (WM8960 codec) + GPIO PTT — RF.Guru's own proven steps, unchanged
3. Builds stock SvxLink from source and installs our own `Logic.tcl` (never RF.Guru's)
4. Installs the dashboard fork
5. Installs the watchdog
6. Sets up RX Monitor (live audio streaming) and enables SvxLink's built-in QSO Recorder

After it finishes:

1. Join WiFi (or connect via ethernet) and open `http://<hostname>.local/`
2. Use the **WiFi** page to join your real network if not already on it
3. Use the **Setup** page to configure callsign, reflector, radio, and location
4. Restart SvxLink from the **Power** page
5. See the dashboard's own **Help** page (`/help/`) for what every page does

## Project layout

- `setup.sh` — the upgrade script described above
- `lib/` — the individual steps `setup.sh` runs, plus `lib/rx-monitor/` (the RX Monitor/QSO Log backend: a vendored [DVSwitch Web_Proxy](https://github.com/DVSwitch/DVSwitch-Dashboard), a Python watcher over SvxLink's QSO Recorder output, and the systemd units for both)
- `events.d/Logic.tcl` — our replacement for RF.Guru's `Logic.tcl`: correct `locale.tcl` sourcing (the actual root cause fix) plus a clean `D911#` IP-readout implementation
- `dashboard/` — the dashboard fork itself (forked from [Guru-RF/SVXLink-Dash-V2](https://github.com/Guru-RF/SVXLink-Dash-V2))
