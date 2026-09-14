// dashboard-v2 DTMF Dialer module. Combines production's two separate
// raw-DTMF UIs into one compact module -- dashboard/dtmf/index.php's
// numeric keypad (single digit per press) and dashboard/include/
// buttons.php's free-text "DTMF command" box (send a typed string as
// is) -- see api/dtmf.php's header comment for why this stays separate
// from Quick Buttons (a saved, reviewable macro list) rather than
// folding into it.
//
// No polling: this module has no state to fetch from the server, every
// action is a fire-and-forget shell_exec. connectedCallback renders
// once and wires up the keypad + text input, that's it.
class DtmfPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/dtmf.php';
    this.render();
  }

  render() {
    const keys = ['1', '2', '3', 'A', '4', '5', '6', 'B', '7', '8', '9', 'C', '*', '0', '#', 'D'];
    const keyButtons = keys
      .map((k) => '<button type="button" class="dtmf-key" data-digit="' + k + '">' + k + '</button>')
      .join('');

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">DTMF Dialer</div></div>' +
        '<div class="panel-body" style="display:flex; flex-direction:column; gap:12px; align-items:center;">' +
          '<div class="dtmf-keypad">' + keyButtons + '</div>' +
          '<div style="display:flex; gap:8px; width:100%; max-width:220px;">' +
            '<input type="text" id="dtmf-code-input" placeholder="Code, e.g. 91240#" style="flex:1; min-width:0;">' +
            '<button type="button" class="btn" id="dtmf-send-btn">Send</button>' +
          '</div>' +
          '<span id="dtmf-status" style="font-family:var(--mono); font-size:11px; color:var(--text-faint); min-height:14px;"></span>' +
        '</div>' +
        '<div class="panel-foot">Sends raw DTMF via the same relay production&#39;s dialer uses</div>' +
      '</div>';

    this.querySelectorAll('.dtmf-key').forEach((btn) => {
      btn.addEventListener('click', () => this.pressKey(btn));
    });
    const input = this.querySelector('#dtmf-code-input');
    this.querySelector('#dtmf-send-btn').addEventListener('click', () => this.sendCode());
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        this.sendCode();
      }
    });
  }

  async pressKey(btn) {
    const digit = btn.getAttribute('data-digit');
    btn.classList.add('pressed');
    setTimeout(() => btn.classList.remove('pressed'), 200);
    const status = this.querySelector('#dtmf-status');
    try {
      const res = await fetch(this.apiUrl + '?action=key&digit=' + encodeURIComponent(digit), { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      if (status) {
        status.textContent = 'Sent ' + digit;
        status.style.color = 'var(--text-faint)';
      }
    } catch (e) {
      if (status) {
        status.textContent = 'Failed to send';
        status.style.color = 'var(--crit)';
      }
    }
  }

  async sendCode() {
    const input = this.querySelector('#dtmf-code-input');
    const status = this.querySelector('#dtmf-status');
    const code = input.value.trim();
    if (!code) {
      return;
    }
    try {
      const res = await fetch(this.apiUrl + '?action=send&code=' + encodeURIComponent(code), { cache: 'no-store' });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'HTTP ' + res.status);
      status.textContent = 'Sent ' + data.sent;
      status.style.color = 'var(--ok)';
      input.value = '';
    } catch (e) {
      status.textContent = e.message || 'Failed to send';
      status.style.color = 'var(--crit)';
    }
  }
}

customElements.define('dtmf-panel', DtmfPanel);
