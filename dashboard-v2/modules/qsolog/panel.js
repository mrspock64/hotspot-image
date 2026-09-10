// dashboard-v2 QSO Log module. Layout matches docs/dashboard-redesign-
// concept.html's qso-list markup closely (time/who/duration grid rows) --
// see css/tokens.css for the .qso-row/.qso-list rules this relies on,
// which still need porting over from the mockup (see below).
class QsologPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/qsolog.php';
    this.refreshMs = parseInt(this.getAttribute('refresh-ms'), 10) || 15000;
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
    if (loading || error || !data || data.error) {
      const msg = loading ? 'Loading&hellip;' : (data && data.error ? data.error : 'QSO log unavailable');
      this.innerHTML = this.shell('', '<p style="padding:16px; color:var(--text-faint); font-size:12.5px;">' + msg + '</p>');
      return;
    }

    let rows = '';
    if (data.in_progress) {
      rows += this.row({
        time: this.timeOf(data.in_progress.since),
        call: null,
        tg: null,
        live: true,
        durLabel: '&hellip;',
      });
    }
    for (const rec of data.recent) {
      rows += this.row({
        time: this.timeOf(rec.when),
        call: rec.callsign,
        tg: rec.tg,
        live: false,
        durLabel: this.formatDuration(rec.duration_sec),
      });
    }
    if (!rows) {
      rows = '<p style="padding:16px; color:var(--text-faint); font-size:12.5px;">No recordings yet.</p>';
    }

    this.innerHTML = this.shell(data.total + ' recordings', rows);
  }

  shell(countLabel, bodyHtml) {
    return (
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">QSO log</div>' +
          '<span style="font-family:var(--mono); font-size:11px; color:var(--text-faint);">' + countLabel + '</span>' +
        '</div>' +
        '<div class="qso-list">' + bodyHtml + '</div>' +
      '</div>'
    );
  }

  row({ time, call, tg, live, durLabel }) {
    const who = live
      ? '<span class="call">' + '&hellip;' + '<span class="live-tag"><span class="dot ok" style="background:#1a0f04; box-shadow:none;"></span>REC</span></span>'
      : '<span class="call">' + (call || '&mdash;') + '</span>';
    const tgHtml = tg ? '<span class="tg">TG <span class="tg-num">' + tg + '</span></span>' : '';
    return (
      '<div class="qso-row' + (live ? ' live' : '') + '">' +
        '<span class="time tabular">' + time + '</span>' +
        '<span class="who">' + who + tgHtml + '</span>' +
        '<span class="dur tabular">' + durLabel + '</span>' +
      '</div>'
    );
  }

  timeOf(whenStr) {
    if (!whenStr) return '&mdash;';
    // qsoRecordingInfo()'s 'when' is 'Y-m-d H:i:s' in server-local time --
    // treated as local here too (new Date on a non-ISO, no-'T'/'Z' string
    // parses as local in every browser this targets), not re-interpreted
    // as UTC.
    const d = new Date(whenStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return whenStr;
    return d.toTimeString().slice(0, 5);
  }

  formatDuration(sec) {
    if (sec === null || sec === undefined) return '&mdash;';
    const m = Math.floor(sec / 60);
    const s = Math.round(sec % 60);
    return m + ':' + String(s).padStart(2, '0');
  }
}

customElements.define('qsolog-panel', QsologPanel);
