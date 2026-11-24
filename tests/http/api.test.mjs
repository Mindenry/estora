/**
 * ESTORA HTTP integration suite.
 *
 * Runs against the Docker stack with seeded data. Uses real cookie sessions,
 * REST nonces and the actual MySQL rows. Created fixtures use the
 * `@estora-qa.test` / `QA ` markers so tests/wp/cleanup.php can remove them.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { Session, assertOk, assertStatus, nextOpenDate, BASE } from './helpers.mjs';

const ADMIN_PASSWORD = 'Estora!Admin!2026';
const STAFF_PASSWORD = process.env.ESTORA_TEST_AGENT_PASSWORD || 'Estora!Agent!2026';
const suffix = String(Date.now()).slice(-6);
const marker = `QA ${suffix}`;
const email = `qa+${suffix}@estora-qa.test`;
const phone = `+66 99 88${suffix.slice(0, 4)}`;

test('ESTORA API', async (t) => {
  const admin = await new Session('admin').login('admin', ADMIN_PASSWORD);
  const manager = await new Session('manager').login('manager@estora.test', STAFF_PASSWORD);
  const agentA = await new Session('agentA').login('agent@estora.test', STAFF_PASSWORD);
  const agentB = await new Session('agentB').login('mai@estora.test', STAFF_PASSWORD);
  const marketing = await new Session('marketing').login('marketing@estora.test', STAFF_PASSWORD);
  const guest = new Session('guest');
  guest.nonce = await guest.getNonce();
  const config = (await (await fetch(`${BASE}/wp-json/estora/v1/public/config`)).json()).data;

  let createdProperty = 0;
  let soldProperty = 0;
  const createdLeads = [];
  let createdAppointment = 0;
  let createdCampaign = 0;
  let createdLeadForAgent = 0;
  let agentAId = 0;
  let agentBId = 0;

  await t.test('health reports all tables', async () => {
    const result = await admin.api('/health');
    const body = assertOk(result, 'health');
    assert.equal(body.data.db_version, '1.0.0');
    assert.ok(Object.values(body.data.tables).every(Boolean), 'all tables exist');
  });

  await t.test('public property listing, filters, sort and pagination', async () => {
    const all = assertOk(await guest.api('/properties?per_page=100'), 'list');
    assert.ok(all.data.length > 0);
    assert.ok(all.data.every((p) => p.status === 'publish'));
    assert.ok(all.meta.pagination.total >= all.data.length);

    const sale = assertOk(await guest.api('/properties?purpose=sale&per_page=100'), 'sale');
    assert.ok(sale.data.every((p) => p.purpose === 'sale'));

    const cheap = assertOk(await guest.api('/properties?max_price=10000000&per_page=100'), 'max price');
    assert.ok(cheap.data.every((p) => p.price <= 10000000));

    const beds = assertOk(await guest.api('/properties?bedrooms=3&per_page=100'), 'beds');
    assert.ok(beds.data.every((p) => p.bedrooms >= 3));

    const sorted = assertOk(await guest.api('/properties?sort=price_asc&per_page=100'), 'sort');
    const prices = sorted.data.map((p) => p.price);
    assert.deepEqual(prices, [...prices].sort((a, b) => a - b));

    const paged = assertOk(await guest.api('/properties?per_page=3&page=2'), 'pagination');
    assert.equal(paged.data.length <= 3, true);
    assert.equal(paged.meta.pagination.page, 2);

    const none = assertOk(await guest.api('/properties?q=zzzz-no-match-zzzz'), 'empty');
    assert.equal(none.data.length, 0);
    assert.equal(none.meta.pagination.total, 0);
  });

  await t.test('property CRUD, publish gate and availability rule', async () => {
    const rejected = await admin.api('/properties', {
      method: 'POST',
      body: { title: `${marker} invalid`, purpose: 'sale', type: 'house', price: 0 }
    });
    assertStatus(rejected, 422, 'zero price');
    assert.ok(rejected.body.errors.price, 'price error present');

    const created = assertOk(await admin.api('/properties', {
      method: 'POST',
      body: {
        title: `${marker} Loft`,
        description: '<p>Integration test listing.</p>',
        purpose: 'rent',
        type: 'condo',
        price: 31000,
        rent_period: 'month',
        location: 'suthep',
        address: '1 QA Road',
        province: 'Chiang Mai',
        district: 'Suthep',
        bedrooms: 2,
        bathrooms: 2,
        usable_area: 66,
        land_area: 0,
        amenities: ['Pool', 'Parking'],
        status: 'draft',
        featured: false
      }
    }), 'create property');
    createdProperty = created.data.id;
    assert.equal(created.data.status, 'draft');
    assert.ok(created.data.reference.startsWith('EST-'));

    assertStatus(await guest.api(`/properties/${createdProperty}`), 404, 'draft hidden');

    const published = assertOk(await admin.api(`/properties/${createdProperty}`, {
      method: 'PATCH',
      body: { status: 'published' }
    }), 'publish');
    assert.equal(published.data.status, 'publish');

    const publicView = assertOk(await guest.api(`/properties/${createdProperty}`), 'public detail');
    assert.equal(publicView.data.title, `${marker} Loft`);
    assert.equal(publicView.data.amenities.length, 2);

    const search = assertOk(await guest.api(`/properties?q=${encodeURIComponent(marker)}`), 'search marker');
    assert.ok(search.data.some((p) => p.id === createdProperty));

    const sold = assertOk(await admin.api(`/properties/${createdProperty}`, {
      method: 'PATCH',
      body: { availability: 'sold' }
    }), 'availability');
    assert.equal(sold.data.availability, 'sold');

    const blocked = await guest.api('/inquiries', {
      method: 'POST',
      body: {
        property_id: createdProperty,
        name: 'Blocked Buyer',
        phone: `+66 99 77${suffix.slice(0, 4)}`,
        message: 'Is it available?',
        consent: true
      }
    });
    assertStatus(blocked, 409, 'sold property inquiry');

    soldProperty = createdProperty;
    assertStatus(await admin.api(`/properties/${createdProperty}?force=true`, { method: 'DELETE' }), 204, 'delete property');
    assertStatus(await guest.api(`/properties/${createdProperty}`), 404, 'deleted property gone');
    createdProperty = 0;
  });

  await t.test('lead lifecycle, scoping and invalid transition', async () => {
    const created = assertOk(await agentA.api('/leads', {
      method: 'POST',
      body: {
        full_name: `${marker} Buyer`,
        email,
        phone,
        message: 'Looking for a two-bedroom with parking.',
        priority: 'high',
        budget_max: 8000000
      }
    }), 'create lead');
    const leadId = created.data.id;
    createdLeads.push(leadId);
    assert.equal(created.data.status, 'new');

    const agents = assertOk(await manager.api('/agents'), 'agents').data;
    agentAId = agents.find((a) => a.email === 'agent@estora.test').id;
    agentBId = agents.find((a) => a.email === 'mai@estora.test').id;

    const invalidAssign = await manager.api(`/leads/${leadId}/assign`, {
      method: 'POST',
      body: { agent_id: 999999 }
    });
    assertStatus(invalidAssign, 422, 'assign unknown agent');

    assertOk(await manager.api(`/leads/${leadId}/assign`, { method: 'POST', body: { agent_id: agentBId, reason: 'QA assignment' } }), 'assign');

    assertStatus(await agentA.api(`/leads/${leadId}`), 404, 'agent A cannot read B lead');
    assertOk(await agentB.api(`/leads/${leadId}`), 'agent B reads own lead');

    assertOk(await agentB.api(`/leads/${leadId}/notes`, {
      method: 'POST',
      body: { body: 'Called the customer; viewing requested.', note_type: 'call', is_internal: true }
    }), 'note');

    for (const status of ['contacted', 'qualified', 'negotiation']) {
      const moved = assertOk(await agentB.api(`/leads/${leadId}/status`, { method: 'POST', body: { status } }), `status ${status}`);
      assert.equal(moved.data.status, status);
    }
    const won = assertOk(await agentB.api(`/leads/${leadId}/status`, {
      method: 'POST',
      body: { status: 'won', won_value: 7800000, note: 'Reservation signed.' }
    }), 'won');
    assert.equal(won.data.status, 'won');
    assert.equal(won.data.won_value, 7800000);

    const invalid = await agentB.api(`/leads/${leadId}/status`, { method: 'POST', body: { status: 'lost' } });
    assertStatus(invalid, 422, 'won to lost');

    const detail = assertOk(await manager.api(`/leads/${leadId}`), 'lead detail');
    assert.ok(detail.data.notes.length >= 2);
    assert.ok(detail.data.assignments.length >= 1);
    assert.equal(detail.data.allowed_next.length, 0);

    const projectLead = assertOk(await agentA.api('/leads', {
      method: 'POST',
      body: { full_name: `${marker} Agent A`, phone: `+66 99 66${suffix.slice(0, 4)}`, message: 'Mine only.' }
    }), 'agent A lead');
    createdLeadForAgent = projectLead.data.id;
    createdLeads.push(createdLeadForAgent);
    assert.equal(assertOk(await agentA.api('/leads?per_page=100'), 'agent list').data.some((l) => l.id === leadId), false, 'scope excludes B lead');
    assert.equal(assertOk(await manager.api('/leads?per_page=100'), 'manager list').data.some((l) => l.id === leadId), true, 'manager sees all');
  });

  await t.test('appointments: availability, conflict, confirm, reschedule, cancel', async () => {
    const properties = assertOk(await guest.api('/properties?per_page=20'), 'properties').data;
    const visible = properties.find((p) => p.availability === 'available');
    assert.ok(visible, 'an available property exists');
    const date = nextOpenDate(config.business.closed_days, 2);
    const availability = assertOk(await guest.api(`/appointments/availability?date=${date}&property_id=${visible.id}`), 'slots');
    const slot = availability.data.slots.find((entry) => entry.available);
    assert.ok(slot, 'an open slot exists');

    const created = assertOk(await guest.api('/appointments', {
      method: 'POST',
      body: {
        property_id: visible.id,
        date,
        time: slot.start,
        name: `${marker} Viewer`,
        email,
        phone: `${phone}1`,
        message: 'Would like to see the kitchen.',
        consent: true
      }
    }), 'public booking');
    createdAppointment = created.data.id;
    assert.equal(created.data.status, 'pending');
    assert.ok(created.data.public_ref.startsWith('AP-'));

    const conflict = await guest.api('/appointments', {
      method: 'POST',
      body: {
        property_id: visible.id,
        date,
        time: slot.start,
        name: `${marker} Viewer 2`,
        email,
        phone: `${phone}2`,
        consent: true
      }
    });
    assertStatus(conflict, 409, 'double booking');

    const past = await guest.api('/appointments', {
      method: 'POST',
      body: { property_id: visible.id, date: '2020-01-02', time: '10:00', name: 'Past', phone: `${phone}3`, consent: true }
    });
    assertStatus(past, 422, 'past date');

    const detail = assertOk(await manager.api(`/appointments/${createdAppointment}`), 'appointment detail');
    const assignedAgent = detail.data.agent_id;
    const otherAgentSession = assignedAgent === agentAId ? agentB : agentA;
    assertStatus(await otherAgentSession.api(`/appointments/${createdAppointment}/status`, { method: 'PATCH', body: { status: 'confirmed' } }), 404, 'appointment IDOR');

    const confirmed = assertOk(await manager.api(`/appointments/${createdAppointment}/status`, {
      method: 'PATCH',
      body: { status: 'confirmed', internal_notes: 'Confirmed from the QA suite.' }
    }), 'confirm');
    assert.equal(confirmed.data.status, 'confirmed');

    const nextAvailability = assertOk(await guest.api(`/appointments/availability?date=${date}&property_id=${visible.id}&exclude=${createdAppointment}`), 'slots again');
    const second = nextAvailability.data.slots.find((entry) => entry.available && entry.start !== slot.start);
    assert.ok(second, 'a second slot exists');
    const rescheduled = assertOk(await manager.api(`/appointments/${createdAppointment}/reschedule`, {
      method: 'POST',
      body: { date, time: second.start, reason: 'Customer request' }
    }), 'reschedule');
    assert.equal(rescheduled.data.status, 'rescheduled');
    assert.equal(rescheduled.data.time, second.start);

    const cancelled = assertOk(await manager.api(`/appointments/${createdAppointment}/status`, { method: 'PATCH', body: { status: 'cancelled' } }), 'cancel');
    assert.equal(cancelled.data.status, 'cancelled');

    const sold = assertOk(await guest.api('/properties?availability=sold&per_page=1'), 'sold lookup').data[0];
    if (sold) {
      const soldSlot = assertOk(await guest.api(`/appointments/availability?date=${date}&property_id=${sold.id}`), 'sold slots').data.slots.find((entry) => entry.available);
      if (soldSlot) {
        const soldBooking = await guest.api('/appointments', {
          method: 'POST',
          body: { property_id: sold.id, date, time: soldSlot.start, name: 'Sold property', phone: `${phone}4`, consent: true }
        });
        assertStatus(soldBooking, 409, 'sold property booking');
      }
    }
  });

  await t.test('guest favorites are idempotent and scoped by token', async () => {
    const property = assertOk(await guest.api('/properties?per_page=1'), 'one property').data[0];
    const first = assertOk(await guest.api('/favorites', { method: 'POST', body: { property_id: property.id } }), 'favorite');
    assert.equal(first.data.favorited, true);
    const second = assertOk(await guest.api('/favorites', { method: 'POST', body: { property_id: property.id } }), 'favorite twice');
    assert.equal(second.data.count, 1, 'no duplicate row');
    const list = assertOk(await guest.api('/favorites'), 'favorites list');
    assert.ok(list.data.ids.includes(property.id));

    const otherGuest = new Session('guest2');
    otherGuest.nonce = await otherGuest.getNonce();
    const otherList = assertOk(await otherGuest.api('/favorites'), 'other guest');
    assert.equal(otherList.data.ids.includes(property.id), false, 'guest tokens are isolated');

    assertOk(await guest.api(`/favorites/${property.id}`, { method: 'DELETE' }), 'unfavorite');
    const after = assertOk(await guest.api('/favorites'), 'after delete');
    assert.equal(after.data.ids.includes(property.id), false);
  });

  await t.test('inquiries create leads, sanitise markup, enforce rate limits', async () => {
    const property = assertOk(await guest.api('/properties?purpose=sale&availability=available&per_page=1'), 'sale property').data[0];
    const payload = {
      property_id: property.id,
      name: `${marker} Inquiry`,
      email,
      phone,
      message: 'Please send the floor plan.',
      consent: true
    };
    const first = assertOk(await guest.api('/inquiries', { method: 'POST', body: payload }), 'inquiry');
    assert.ok(first.data.lead_ref.startsWith('LD-'));
    const duplicate = assertOk(await guest.api('/inquiries', { method: 'POST', body: payload }), 'duplicate inquiry');
    assert.equal(duplicate.data.duplicate, true);
    assert.equal(duplicate.data.lead_ref, first.data.lead_ref);

    const xss = await guest.api('/inquiries', {
      method: 'POST',
      body: {
        property_id: 0,
        name: `QA <script>alert(1)</script> Buyer ${suffix}`,
        phone: `+66 99 55${suffix.slice(0, 4)}`,
        message: '<img src=x onerror=alert(1)>',
        consent: true
      }
    });
    assertOk(xss, 'xss payload accepted');
    const search = assertOk(await manager.api(`/leads?q=${encodeURIComponent('script')}&per_page=50`), 'xss search');
    assert.ok(!JSON.stringify(search).includes('<script>'), 'script tag is not stored raw');

    const sold = assertOk(await guest.api('/properties?availability=sold&per_page=1'), 'sold').data[0];
    if (sold) {
      const blocked = await guest.api('/inquiries', {
        method: 'POST',
        body: { property_id: sold.id, name: 'Sold inquiry', phone: `+66 99 44${suffix.slice(0, 4)}`, message: 'Still available?', consent: true }
      });
      assertStatus(blocked, 409, 'sold property inquiry');
    }

    const limitPhone = `+66 99 33${suffix.slice(0, 4)}`;
    let limited = false;
    for (let i = 0; i < 7; i++) {
      const attempt = await guest.api('/inquiries', {
        method: 'POST',
        body: { property_id: 0, name: `Rate ${i}`, phone: limitPhone, message: 'Rate limit probe', consent: true }
      });
      if (attempt.status === 429) { limited = true; break; }
    }
    assert.ok(limited, 'sixth request from one contact is throttled');
  });

  await t.test('security: auth, nonce, capabilities and SQL injection', async () => {
    assertStatus(await (new Session('anon')).api('/leads'), 401, 'anonymous leads');
    assertStatus(await admin.api('/leads', { nonce: false }), 401, 'missing nonce is not authenticated');
    assertStatus(await agentA.api('/leads', { method: 'POST', body: { full_name: 'Nope' } }).then((r) => r), 422, 'lead without contact rejected');
    assertStatus(await agentA.api('/leads/1/assign', { method: 'POST', body: { agent_id: 1 } }), 403, 'agent assign');
    assertStatus(await marketing.api('/settings'), 403, 'marketing settings');
    assertStatus(await manager.api('/settings', { method: 'POST', body: { company_name: 'Hacked' } }), 403, 'manager settings');
    assertStatus(await agentA.api('/campaigns'), 403, 'agent campaigns');

    const injection = await guest.api(`/properties?q=${encodeURIComponent("%' OR 1=1 -- ")}`);
    assertOk(injection, 'sqli returns empty');
    assert.ok(!JSON.stringify(injection).toLowerCase().includes('sql syntax'));
  });

  await t.test('campaign attribution and CSV export', async () => {
    const created = assertOk(await marketing.api('/campaigns', {
      method: 'POST',
      body: { name: `${marker} Campaign`, channel: 'meta', utm_source: 'facebook', utm_medium: 'cpc', utm_campaign: `qa-${suffix}`, status: 'active', budget: 1200 }
    }), 'campaign create');
    createdCampaign = created.data.id;

    const campaignEmail = `qa-campaign-${suffix}@estora-qa.test`;
    assertOk(await guest.api('/inquiries', {
      method: 'POST',
      body: {
        property_id: 0,
        name: `${marker} Campaign Lead`,
        email: campaignEmail,
        phone: `+66 99 22${suffix.slice(0, 4)}`,
        message: 'Attribution probe.',
        consent: true,
        utm_source: 'facebook',
        utm_medium: 'cpc',
        utm_campaign: `qa-${suffix}`
      }
    }), 'attributed inquiry');

    const attributed = assertOk(await marketing.api('/campaigns?per_page=50'), 'campaign attribution');
    const row = attributed.data.find((c) => c.id === createdCampaign);
    assert.ok(row && row.attribution_count >= 1, 'lead counted against the campaign');

    const paused = assertOk(await marketing.api(`/campaigns/${createdCampaign}`, { method: 'PATCH', body: { status: 'paused' } }), 'campaign update');
    assert.equal(paused.data.status, 'paused');
    assertStatus(await manager.api(`/campaigns/${createdCampaign}`, { method: 'DELETE' }), 403, 'manager cannot delete campaign');

    const campaignLeads = assertOk(await manager.api(`/leads?q=${encodeURIComponent(campaignEmail)}&per_page=5`), 'campaign lead lookup');
    for (const lead of campaignLeads.data) {
      await manager.api(`/leads/${lead.id}`, { method: 'DELETE' });
    }

    const reportsPage = await admin.fetch('/wp-admin/admin.php?page=estora-reports');
    assert.equal(reportsPage.status, 200);
    const html = await reportsPage.text();
    const match = html.match(/report=properties(?:&amp;|&#038;|&)_wpnonce=([a-f0-9]+)/);
    assert.ok(match, 'export URL with nonce on reports page');
    const csv = await admin.fetch(`/wp-admin/admin-post.php?action=estora_export&report=properties&_wpnonce=${match[1]}`);
    assert.equal(csv.status, 200);
    assert.match(csv.headers.get('content-type') || '', /text\/csv/);
    const body = await csv.text();
    const lines = body.trim().split('\n');
    assert.ok(lines.length > 1, 'CSV has a header and rows');

    assertOk(await marketing.api(`/campaigns/${createdCampaign}`, { method: 'DELETE' }), 'campaign delete');
    createdCampaign = 0;
  });

  await t.test('settings are readable and writable by administrators only', async () => {
    const current = assertOk(await admin.api('/settings'), 'settings read');
    assert.ok(current.data.company_name);
    const saved = assertOk(await admin.api('/settings', {
      method: 'POST',
      body: { company_tagline: current.data.company_tagline, slot_minutes: current.data.slot_minutes }
    }), 'settings write');
    assert.equal(saved.data.company_name, current.data.company_name);
    assertStatus(await manager.api('/settings', { method: 'POST', body: { company_name: 'Nope' } }), 403, 'manager cannot write settings');
  });

  await t.test('media upload rejects executables and accepts images', async () => {
    const php = new FormData();
    php.append('file', new Blob(['<?php echo "x"; ?>'], { type: 'application/x-php' }), 'shell.php');
    const rejected = await admin.upload(php);
    assert.ok(rejected.status >= 400, `php upload rejected (${rejected.status})`);
    assert.match(JSON.stringify(rejected.body), /(not allowed to upload this file type|file type is not allowed)/i);

    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
    const image = new FormData();
    image.append('file', new Blob([png], { type: 'image/png' }), `qa-${suffix}.png`);
    const accepted = await admin.upload(image);
    assert.equal(accepted.status, 201, `png accepted: ${JSON.stringify(accepted.body)}`);
    if (accepted.body && accepted.body.id) {
      await admin.fetch(`/wp-json/wp/v2/media/${accepted.body.id}?force=true`, { method: 'DELETE', headers: { 'x-wp-nonce': admin.nonce } });
    }
  });

  await t.test('dashboard aggregates match the API totals', async () => {
    const summary = assertOk(await admin.api('/dashboard/summary?refresh=1'), 'summary');
    const properties = assertOk(await guest.api('/properties?per_page=1'), 'property total');
    assert.equal(summary.data.properties.published, properties.meta.pagination.total);
    assert.ok(summary.data.leads.total >= 1);
    assert.equal(typeof summary.data.leads.conversion_rate, 'number');
    assert.ok(Array.isArray(summary.data.recent_activity));
    assert.ok(Array.isArray(summary.data.lead_trend));

    const leadStats = assertOk(await admin.api('/dashboard/lead-statistics?days=14'), 'lead stats');
    assert.ok(Array.isArray(leadStats.data.daily));
    const appointmentStats = assertOk(await admin.api('/dashboard/appointment-statistics'), 'appointment stats');
    assert.ok(typeof appointmentStats.data.completion_rate === 'number');
  });

  await t.test('deactivated staff cannot sign in', async (t2) => {
    let agentBId = 0;
    try {
      const agents = assertOk(await admin.api('/agents'), 'agents for deactivation').data;
      agentBId = agents.find((a) => a.email === 'mai@estora.test').id;
      execFileSync('docker', ['compose', 'run', '--rm', '-T', 'cli', 'user', 'meta', 'update', String(agentBId), 'estora_active', '0'], { cwd: process.cwd(), stdio: 'ignore' });
    } catch (error) {
      t2.skip('docker CLI unavailable for user meta update');
      return;
    }

    try {
      const session = new Session('inactive');
      await session.fetch('/wp-login.php');
      const response = await session.fetch('/wp-login.php', {
        method: 'POST',
        headers: { 'content-type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ log: 'mai@estora.test', pwd: STAFF_PASSWORD, 'wp-submit': 'Log In', testcookie: '1' })
      });
      const html = await response.text();
      assert.equal(response.status, 200, 'failed login stays on the form');
      assert.ok(/deactivated|cannot|invalid|incorrect/i.test(html), 'login refused with a message');
    } finally {
      execFileSync('docker', ['compose', 'run', '--rm', '-T', 'cli', 'user', 'meta', 'update', String(agentBId), 'estora_active', '1'], { cwd: process.cwd(), stdio: 'ignore' });
    }
  });

  await t.test('cleanup fixtures through the API', async () => {
    for (const id of createdLeads) {
      await manager.api(`/leads/${id}`, { method: 'DELETE' });
    }
    if (createdProperty) {
      await admin.api(`/properties/${createdProperty}?force=true`, { method: 'DELETE' });
    }
    if (createdCampaign) {
      await marketing.api(`/campaigns/${createdCampaign}`, { method: 'DELETE' });
    }
  });
});
