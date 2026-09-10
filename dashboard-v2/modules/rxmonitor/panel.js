// dashboard-v2 RX Monitor module. No collector, no new backend at all --
// this is a *client* of the already-running production audio proxy
// (lib/rx-monitor/proxy.js on ws-port, see manifest.json), the exact same
// ws://host:8080 stream the existing dashboard's RX Monitor button plays
// through pcm-player.min.js. That file's own SVXPlayer sets the ground
// truth for the real wire format -- 16-bit signed mono PCM at 16000 Hz
// (this.sampleRate=16000 in dashboard/scripts/pcm-player.min.js) -- used
// below for the FFT rather than assumed.
//
// The waterfall is real: an actual FFT run on the live incoming PCM
// samples, not a decorative animation. No playback, no AudioContext --
// this module never makes sound, only visualizes the same bytes the
// production Play button would feed to the speaker.
class RxmonitorPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/rxmonitor.php';
    this.refreshMs = parseInt(this.getAttribute('refresh-ms'), 10) || 5000;
    this.wsPort = parseInt(this.getAttribute('ws-port'), 10) || 8080;
    this.lastAudioAt = 0;
    this.sampleBuf = new Float32Array(0);

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">RX monitor</div>' +
          '<span class="status-chip" style="padding:3px 9px;"><span id="rxm-dot" class="dot warn"></span><span id="rxm-status">connecting&hellip;</span></span>' +
        '</div>' +
        '<div class="panel-body" style="padding-top:14px;">' +
          '<div class="waterfall-wrap"><canvas class="waterfall-canvas"></canvas></div>' +
        '</div>' +
        '<div class="panel-foot">Live FFT of the same ws://&lt;host&gt;:' + this.wsPort + ' stream the dashboard\'s own RX Monitor button plays</div>' +
      '</div>';

    this.dot = this.querySelector('#rxm-dot');
    this.statusEl = this.querySelector('#rxm-status');
    this.canvas = this.querySelector('canvas');

    this.poll();
    this._statusTimer = setInterval(() => this.poll(), this.refreshMs);
    this.connectWs();
    this._drawTimer = setInterval(() => this.drawRow(), 120);
  }

  disconnectedCallback() {
    clearInterval(this._statusTimer);
    clearInterval(this._drawTimer);
    if (this.ws) this.ws.close();
  }

  async poll() {
    try {
      const res = await fetch(this.apiUrl, { cache: 'no-store' });
      const data = await res.json();
      if (!data.proxy_reachable) {
        this.setStatus('warn', 'proxy offline');
      }
      // 'recording' (QSO Recorder has a file open) vs the WS actually
      // delivering bytes (checked in drawRow(), via lastAudioAt) are two
      // different signals worth keeping separate -- the API call alone
      // can't tell us the WS is truly flowing, only that a recording
      // exists to flow from.
      this._recording = !!data.recording;
    } catch (e) {
      this.setStatus('warn', 'status unavailable');
    }
  }

  connectWs() {
    let url;
    try {
      url = 'ws://' + window.location.hostname + ':' + this.wsPort;
      this.ws = new WebSocket(url);
      this.ws.binaryType = 'arraybuffer';
    } catch (e) {
      this.setStatus('warn', 'unavailable');
      return;
    }
    this.ws.onopen = () => this.setStatus('warn', 'idle');
    this.ws.onclose = () => {
      this.setStatus('warn', 'disconnected');
      // One reconnect attempt after a few seconds -- the proxy only ever
      // sends bytes while a QSO is actually open, so a long-lived idle
      // connection is normal and not itself a failure worth giving up on.
      setTimeout(() => { if (this.isConnected) this.connectWs(); }, 4000);
    };
    this.ws.onerror = () => {};
    this.ws.onmessage = (evt) => this.onAudio(evt.data);
  }

  onAudio(arrayBuffer) {
    this.lastAudioAt = Date.now();
    this.setStatus('ok', 'streaming');
    const int16 = new Int16Array(arrayBuffer);
    const floats = new Float32Array(int16.length);
    for (let i = 0; i < int16.length; i++) floats[i] = int16[i] / 32768;
    // Keep only the most recent FFT_SIZE*2 samples -- this is a live
    // visualization, not a buffer that needs to retain history.
    const merged = new Float32Array(this.sampleBuf.length + floats.length);
    merged.set(this.sampleBuf, 0);
    merged.set(floats, this.sampleBuf.length);
    const keep = FFT_SIZE * 2;
    this.sampleBuf = merged.length > keep ? merged.slice(merged.length - keep) : merged;
  }

  setStatus(level, text) {
    if (this._lastStatusText === text) return;
    this._lastStatusText = text;
    this.dot.className = 'dot ' + level;
    this.statusEl.textContent = text;
  }

  drawRow() {
    // No audio for >2s (same freshness window the production dashboard's
    // own header RX meter uses, dashboard/include/rx_level.php) -- back to
    // idle rather than leaving a stale "streaming" label up.
    if (this.lastAudioAt && Date.now() - this.lastAudioAt > 2000) {
      this.setStatus('warn', this._recording ? 'idle (no audio)' : 'idle');
    }

    const canvas = this.canvas;
    const wrap = canvas.parentElement;
    const w = wrap.clientWidth || 400;
    const h = 76;
    if (canvas.width !== w || canvas.height !== h) {
      canvas.width = w;
      canvas.height = h;
    }
    const ctx = canvas.getContext('2d');

    // Scroll the existing image down 1px, then draw this tick's spectrum
    // as a fresh 1px row at the top -- classic top-down waterfall.
    ctx.drawImage(canvas, 0, 0, w, h - 1, 0, 1, w, h - 1);

    if (this.sampleBuf.length < FFT_SIZE || Date.now() - this.lastAudioAt > 2000) {
      ctx.fillStyle = '#050709';
      ctx.fillRect(0, 0, w, 1);
      return;
    }

    const frame = this.sampleBuf.slice(this.sampleBuf.length - FFT_SIZE);
    const mags = magnitudeSpectrumDb(frame);
    const bins = mags.length; // FFT_SIZE/2, i.e. DC..Nyquist (8kHz at 16kHz sample rate)
    for (let x = 0; x < w; x++) {
      const bin = Math.min(bins - 1, Math.floor((x / w) * bins));
      ctx.fillStyle = dbToColor(mags[bin]);
      ctx.fillRect(x, 0, 1, 1);
    }
  }
}

const FFT_SIZE = 256;

// Hann window, precomputed once.
const HANN = (() => {
  const w = new Float32Array(FFT_SIZE);
  for (let i = 0; i < FFT_SIZE; i++) w[i] = 0.5 - 0.5 * Math.cos((2 * Math.PI * i) / (FFT_SIZE - 1));
  return w;
})();

/** In-place iterative radix-2 Cooley-Tukey FFT. re/im must be Float64Array of length a power of 2. */
function fft(re, im) {
  const n = re.length;
  for (let i = 1, j = 0; i < n; i++) {
    let bit = n >> 1;
    for (; j & bit; bit >>= 1) j ^= bit;
    j ^= bit;
    if (i < j) {
      [re[i], re[j]] = [re[j], re[i]];
      [im[i], im[j]] = [im[j], im[i]];
    }
  }
  for (let len = 2; len <= n; len <<= 1) {
    const ang = (-2 * Math.PI) / len;
    const wr = Math.cos(ang), wi = Math.sin(ang);
    for (let i = 0; i < n; i += len) {
      let curWr = 1, curWi = 0;
      for (let k = 0; k < len / 2; k++) {
        const uRe = re[i + k], uIm = im[i + k];
        const vRe = re[i + k + len / 2] * curWr - im[i + k + len / 2] * curWi;
        const vIm = re[i + k + len / 2] * curWi + im[i + k + len / 2] * curWr;
        re[i + k] = uRe + vRe;
        im[i + k] = uIm + vIm;
        re[i + k + len / 2] = uRe - vRe;
        im[i + k + len / 2] = uIm - vIm;
        const nextWr = curWr * wr - curWi * wi;
        curWi = curWr * wi + curWi * wr;
        curWr = nextWr;
      }
    }
  }
}

function magnitudeSpectrumDb(frame) {
  const re = new Float64Array(FFT_SIZE);
  const im = new Float64Array(FFT_SIZE);
  for (let i = 0; i < FFT_SIZE; i++) re[i] = frame[i] * HANN[i];
  fft(re, im);
  const bins = FFT_SIZE / 2;
  const out = new Float32Array(bins);
  for (let i = 0; i < bins; i++) {
    const mag = Math.sqrt(re[i] * re[i] + im[i] * im[i]) / FFT_SIZE;
    out[i] = 20 * Math.log10(mag + 1e-9);
  }
  return out;
}

// -90dB (near silence) to -20dB (strong signal) mapped onto the same
// bg -> cyan -> copper ramp the rest of this module set uses for
// intensity, dark background matching --bg-deep so a quiet waterfall
// reads as "nothing happening", not as unstyled noise.
function dbToColor(db) {
  const t = Math.max(0, Math.min(1, (db + 90) / 70));
  if (t < 0.5) {
    const u = t / 0.5;
    return lerpColor([7, 9, 13], [44, 130, 138], u); // bg-deep -> cyan-dim
  }
  const u = (t - 0.5) / 0.5;
  return lerpColor([44, 130, 138], [255, 157, 77], u); // cyan-dim -> copper
}

function lerpColor(a, b, t) {
  const r = Math.round(a[0] + (b[0] - a[0]) * t);
  const g = Math.round(a[1] + (b[1] - a[1]) * t);
  const bl = Math.round(a[2] + (b[2] - a[2]) * t);
  return 'rgb(' + r + ',' + g + ',' + bl + ')';
}

customElements.define('rxmonitor-panel', RxmonitorPanel);
