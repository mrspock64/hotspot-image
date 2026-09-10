// dashboard-v2 Talkgroup module. The "which talkgroup is active right
// now" field the Frequency module's own comment said didn't exist yet --
// see api/talkgroup.php's header comment for the real log source this
// turned out to have all along.
class TalkgroupPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/talkgroup.php';
    this.refreshMs = parseInt(this.getAttribute('refresh-ms'), 10) || 10000;
    this.render({ loading: true });
    this.poll();
    this._timer = setInterval(() => this.poll(), this.refreshMs);
  }

  disconnectedCallback() {
    clearInterval(this._timer);
  }

  async poll() {
    try {
      const res = await fetch(this.apiUrl, { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      this.render({ data: await res.json() });
    } catch (e) {
      this.render({ error: true });
    }
  }

  render({ loading, error, data }) {
    if (loading || error || !data) {
      const msg = loading ? 'Loading&hellip;' : 'Talkgroup data unavailable';
      this.innerHTML =
        '<div class="panel"><div class="panel-head"><div class="panel-title">Talkgroup</div></div>' +
        '<div class="panel-body"><div class="kv-row"><span class="k">' + msg + '</span></div></div></div>';
      return;
    }

    const linkedChip = data.selected_tg
      ? '<span class="status-chip" style="padding:3px 9px;"><span class="dot ok"></span>TG ' + data.selected_tg + ' linked</span>'
      : '<span class="status-chip" style="padding:3px 9px;"><span class="dot warn"></span>unlinked</span>';

    let talkerBlock;
    if (data.talker && data.talker.active) {
      talkerBlock =
        '<div class="freq-readout" style="font-size:22px; color:var(--copper);">' +
          '&#9679; ' + this.escapeHtml(data.talker.callsign) +
        '</div>' +
        this.kvRow('On', 'TG ' + data.talker.tg) +
        this.kvRow('Talking for', this.formatDuration(data.talker.since_seconds));
    } else if (data.talker) {
      talkerBlock =
        '<div class="kv-row"><span class="k">Last talker</span><span class="v">' + this.escapeHtml(data.talker.callsign) + '</span></div>' +
        this.kvRow('On', 'TG ' + data.talker.tg) +
        this.kvRow('Ended', this.formatDuration(data.talker.since_seconds) + ' ago');
    } else {
      talkerBlock = '<div class="kv-row"><span class="k">No recent talker activity</span></div>';
    }

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Talkgroup</div>' + linkedChip + '</div>' +
        '<div class="panel-body"><div class="kv">' + talkerBlock + '</div></div>' +
        '<div class="panel-foot">' + data.nodes_online_approx + ' node(s) seen recently on the reflector &middot; parsed from /var/log/svxlink</div>' +
      '</div>';
  }

  kvRow(label, value) {
    return '<div class="kv-row"><span class="k">' + label + '</span><span class="v">' + value + '</span></div>';
  }

  formatDuration(sec) {
    if (sec < 60) return sec + 's';
    const min = Math.floor(sec / 60);
    if (min < 60) return min + 'm ' + (sec % 60) + 's';
    const hr = Math.floor(min / 60);
    return hr + 'h ' + (min % 60) + 'm';
  }

  escapeHtml(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }
}

customElements.define('talkgroup-panel', TalkgroupPanel);
