// dashboard-v2 QSO Log module. Layout matches docs/dashboard-redesign-
// concept.html's qso-list markup closely (time/who/duration grid rows) --
// see css/tokens.css for the .qso-row/.qso-list rules this relies on.
//
// Play/delete reuse production's own machinery directly: play streams
// through api/qso-play.php, a pure pass-through of dashboard/qsolog/
// play.php (Range support and all, no reimplementation); delete calls
// api/qsolog.php's ?action=delete/?action=delete_all, which are thin
// wrappers around dashboard/include/qso_recorder.php's own
// deleteQsoRecording()/deleteAllQsoRecordings() -- same filename
// validation and sudo-rm path that file's production page already uses.
//
// One playback Audio object, not an <audio> element baked into the
// rendered HTML -- this.innerHTML gets rebuilt every poll (see render()),
// which would tear down and restart any <audio> tag mid-playback. A
// detached JS Audio instance survives rebuilds untouched since it's never
// part of what gets replaced; only the row-level "is this file playing"
// visual state needs reapplying after a rebuild, handled in render().
class QsologPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/qsolog.php';
    this.refreshMs = parseInt(this.getAttribute('refresh-ms'), 10) || 15000;
    this.audio = new Audio();
    this.playingFile = null;
    this.audio.addEventListener('ended', () => this.setPlaying(null));
    this.audio.addEventListener('pause', () => {
      // Fires on both a real pause and .src reassignment -- only clear
      // the "now playing" row if playback actually stopped, not mid-swap
      // to a different file (that's immediately followed by .play()).
      if (this.audio.ended || this.audio.currentTime === 0) this.setPlaying(null);
    });
    this.render({ loading: true });
    this.poll();
    this._timer = setInterval(() => this.poll(), this.refreshMs);
    this.addEventListener('click', (e) => this.onClick(e));
  }

  disconnectedCallback() {
    clearInterval(this._timer);
    this.audio.pause();
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

  onClick(e) {
    const playBtn = e.target.closest('.qso-play-btn');
    if (playBtn) {
      this.togglePlay(playBtn.getAttribute('data-file'));
      return;
    }
    const delBtn = e.target.closest('.qso-delete-btn');
    if (delBtn) {
      this.deleteOne(delBtn.getAttribute('data-file'));
      return;
    }
    const delAllBtn = e.target.closest('#qso-delete-all-btn');
    if (delAllBtn) {
      this.deleteAll();
    }
  }

  togglePlay(file) {
    if (!file) return;
    if (this.playingFile === file) {
      this.audio.pause();
      this.setPlaying(null);
      return;
    }
    this.audio.src = this.apiUrl.replace(/qsolog\.php$/, 'qso-play.php') + '?file=' + encodeURIComponent(file);
    this.audio.play().catch(() => {});
    this.setPlaying(file);
  }

  setPlaying(file) {
    this.playingFile = file;
    this.querySelectorAll('.qso-play-btn').forEach((btn) => {
      const isThis = btn.getAttribute('data-file') === file;
      btn.textContent = isThis ? '⏸' : '▶';
      btn.classList.toggle('active', isThis);
    });
  }

  async deleteOne(file) {
    if (!file) return;
    if (!window.confirm('Delete this recording?')) return;
    if (this.playingFile === file) {
      this.audio.pause();
      this.setPlaying(null);
    }
    try {
      const res = await fetch(this.apiUrl + '?action=delete&file=' + encodeURIComponent(file), { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
    } catch (e) {
      // Fall through to the next poll either way -- it reflects whatever
      // the recordings directory actually holds now.
    }
    this.poll();
  }

  async deleteAll() {
    if (!window.confirm('Delete ALL QSO recordings? This cannot be undone.')) return;
    this.audio.pause();
    this.setPlaying(null);
    try {
      const res = await fetch(this.apiUrl + '?action=delete_all', { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
    } catch (e) {
      // Same as deleteOne -- next poll shows the true state regardless.
    }
    this.poll();
  }

  render({ loading, error, data }) {
    if (loading || error || !data || data.error) {
      const msg = loading ? 'Loading&hellip;' : (data && data.error ? data.error : 'QSO log unavailable');
      this.innerHTML = this.shell('', '<p style="padding:16px; color:var(--text-faint); font-size:12.5px;">' + msg + '</p>', false);
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
        file: null,
      });
    }
    for (const rec of data.recent) {
      rows += this.row({
        time: this.timeOf(rec.when),
        call: rec.callsign,
        tg: rec.tg,
        live: false,
        durLabel: this.formatDuration(rec.duration_sec),
        file: rec.file || null,
      });
    }
    if (!rows) {
      rows = '<p style="padding:16px; color:var(--text-faint); font-size:12.5px;">No recordings yet.</p>';
    }

    // Same scrollTop-reset issue the other list modules had -- every poll
    // rebuilds this.innerHTML wholesale, which would silently reset
    // .qso-list's scrollTop to 0 otherwise.
    const prevList = this.querySelector('.qso-list');
    const scrollTop = prevList ? prevList.scrollTop : 0;

    this.innerHTML = this.shell(data.total + ' recordings', rows, data.recent.length > 0);

    if (scrollTop) {
      this.querySelector('.qso-list').scrollTop = scrollTop;
    }
    window.dv2PersistResizableList(this, 'dv2ListHeight-qsolog', '.qso-list');
    if (this.playingFile) this.setPlaying(this.playingFile);
  }

  shell(countLabel, bodyHtml, showDeleteAll) {
    return (
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">QSO log</div>' +
          '<div style="display:flex; align-items:center; gap:8px;">' +
            '<span style="font-family:var(--mono); font-size:11px; color:var(--text-faint);">' + countLabel + '</span>' +
            (showDeleteAll ? '<button type="button" class="btn" id="qso-delete-all-btn" title="Delete all recordings">Delete all</button>' : '') +
          '</div>' +
        '</div>' +
        '<div class="qso-list">' + bodyHtml + '</div>' +
      '</div>'
    );
  }

  row({ time, call, tg, live, durLabel, file }) {
    const who = live
      ? '<span class="call">' + '&hellip;' + '<span class="live-tag"><span class="dot ok" style="background:#1a0f04; box-shadow:none;"></span>REC</span></span>'
      : '<span class="call">' + (call || '&mdash;') + '</span>';
    const tgHtml = tg ? '<span class="tg">TG <span class="tg-num">' + tg + '</span></span>' : '';
    const controls = file
      ? '<button type="button" class="btn qso-play-btn" data-file="' + this.esc(file) + '" style="padding:3px 9px;" title="Play">&#9654;</button>' +
        '<button type="button" class="btn qso-delete-btn" data-file="' + this.esc(file) + '" style="padding:3px 9px;" title="Delete">&times;</button>'
      : '';
    return (
      '<div class="qso-row' + (live ? ' live' : '') + '">' +
        '<span class="time tabular">' + time + '</span>' +
        '<span class="who">' + who + tgHtml + '</span>' +
        '<span class="dur tabular">' + durLabel + '</span>' +
        '<span style="display:flex; gap:6px;">' + controls + '</span>' +
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

  esc(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }
}

customElements.define('qsolog-panel', QsologPanel);
