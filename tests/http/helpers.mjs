/**
 * ESTORA HTTP test helpers: cookie session, login, nonce, API calls.
 */
import assert from 'node:assert/strict';

export const BASE = process.env.ESTORA_BASE_URL || 'http://localhost:8080';

export class Session {
  constructor(name) {
    this.name = name;
    this.cookies = new Map();
    this.visitor = Array.from(crypto.getRandomValues(new Uint8Array(24)), (byte) => byte.toString(16).padStart(2, '0')).join('');
  }

  cookieHeader() {
    return [...this.cookies.entries()].map(([key, value]) => `${key}=${value}`).join('; ');
  }

  store(response) {
    const setCookies = typeof response.headers.getSetCookie === 'function'
      ? response.headers.getSetCookie()
      : (response.headers.get('set-cookie') ? [response.headers.get('set-cookie')] : []);
    for (const raw of setCookies) {
      const [pair] = raw.split(';');
      const index = pair.indexOf('=');
      if (index > 0) {
        this.cookies.set(pair.slice(0, index).trim(), pair.slice(index + 1));
      }
    }
  }

  async fetch(path, options = {}) {
    const headers = new Headers(options.headers || {});
    if (this.cookies.size) headers.set('cookie', this.cookieHeader());
    const response = await fetch(path.startsWith('http') ? path : BASE + path, {
      redirect: 'manual',
      ...options,
      headers
    });
    this.store(response);
    return response;
  }

  async login(username, password) {
    await this.fetch('/wp-login.php');
    const body = new URLSearchParams({
      log: username,
      pwd: password,
      'wp-submit': 'Log In',
      redirect_to: `${BASE}/wp-admin/`,
      testcookie: '1'
    });
    const response = await this.fetch('/wp-login.php', {
      method: 'POST',
      headers: { 'content-type': 'application/x-www-form-urlencoded' },
      body
    });
    assert.ok([302, 303].includes(response.status), `${username}: login should redirect, got ${response.status}`);
    const loginLocation = response.headers.get('location') || '';
    let admin = await this.fetch('/wp-admin/');
    if (admin.status >= 300 && admin.status < 400) {
      admin = await this.fetch(admin.headers.get('location') || '/estora-admin/');
    }
    assert.equal(admin.status, 200, `${username}: admin should load`);
    const html = await admin.text();
    assert.ok(!html.includes('name="log"'), `${username}: still on the login form`);
    this.loginRedirect = loginLocation;
    this.nonce = await this.getNonce();
    return this;
  }

  async getNonce() {
    const response = await this.fetch('/wp-json/estora/v1/public/config');
    assert.equal(response.status, 200);
    const payload = await response.json();
    return payload.data.nonce;
  }

  /**
   * Call the ESTORA REST API.
   * @returns {Promise<{status:number, body:any, headers:Headers}>}
   */
  async api(path, { method = 'GET', body, nonce = true, raw = false } = {}) {
    const headers = { 'content-type': 'application/json', 'x-estora-visitor': this.visitor };
    if (nonce && this.nonce) headers['x-wp-nonce'] = this.nonce;
    const options = { method, headers };
    if (body !== undefined) options.body = JSON.stringify(body);
    const response = await this.fetch(`/wp-json/estora/v1${path}`, options);
    const text = await response.text();
    let parsed = null;
    if (!raw) {
      try { parsed = JSON.parse(text); } catch { parsed = { raw: text }; }
    }
    return { status: response.status, body: parsed, text, headers: response.headers };
  }

  async upload(formData) {
    const response = await this.fetch('/wp-json/wp/v2/media', {
      method: 'POST',
      headers: { 'x-wp-nonce': this.nonce },
      body: formData
    });
    const text = await response.text();
    let parsed = null;
    try { parsed = JSON.parse(text); } catch { parsed = { raw: text }; }
    return { status: response.status, body: parsed };
  }
}

export function assertOk(result, label = '') {
  assert.ok(result.status >= 200 && result.status < 300, `${label}: expected 2xx, got ${result.status} ${JSON.stringify(result.body)}`);
  assert.equal(result.body.success, true, `${label}: success envelope`);
  return result.body;
}

export function assertStatus(result, expected, label = '') {
  assert.equal(result.status, expected, `${label}: expected ${expected}, got ${result.status} ${JSON.stringify(result.body)}`);
  return result.body;
}

/** Next open date (Mon–Sat by default) at least `skip` days ahead. */
export function nextOpenDate(closedDays = [0], skip = 1) {
  const date = new Date();
  date.setUTCDate(date.getUTCDate() + skip);
  for (let i = 0; i < 21; i++) {
    if (!closedDays.includes(date.getUTCDay())) {
      return date.toISOString().slice(0, 10);
    }
    date.setUTCDate(date.getUTCDate() + 1);
  }
  throw new Error('No open date found');
}
