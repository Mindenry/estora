import test from 'node:test';
import assert from 'node:assert/strict';
import { Session, assertOk, assertStatus } from './helpers.mjs';

const STAFF = process.env.ESTORA_TEST_AGENT_PASSWORD || 'Estora!Agent!2026';

test('ESTORA back-office application', async (t) => {
  const admin = await new Session('app-admin').login('admin', 'Estora!Admin!2026');
  const operations = await new Session('app-operations').login('operations@estora.test', STAFF);
  const manager = await new Session('app-manager').login('manager@estora.test', STAFF);
  const agent = await new Session('app-agent').login('agent@estora.test', STAFF);
  const property = await new Session('app-property').login('property@estora.test', STAFF);
  const marketing = await new Session('app-marketing').login('marketing@estora.test', STAFF);

  const navFor = async (session) => {
    const res = await session.fetch('/estora-admin/');
    assert.equal(res.status, 200, 'app loads');
    const html = await res.text();
    assert.ok(html.includes('estora-shell'), 'shell present');
    return { html, links: [...html.matchAll(/class="estora-navlink[^"]*"[\s\S]*?href="([^"]+)"/g)].map((m) => m[1]) };
  };

  await t.test('every staff role lands on the ESTORA app after login', async () => {
    for (const session of [admin, operations, manager, agent, property, marketing]) {
      assert.ok(session.loginRedirect.includes('/estora-admin/'), `${session.name} redirect: ${session.loginRedirect}`);
      const res = await session.fetch('/wp-admin/');
      assert.ok([200, 302].includes(res.status));
      if (res.status === 302) {
        assert.ok((res.headers.get('location') || '').includes('/estora-admin/'), 'wp-admin index redirects staff');
      }
    }
  });

  await t.test('navigation is permission aware', async () => {
    const adminNav = await navFor(admin);
    for (const route of ['properties', 'leads', 'pipeline', 'appointments', 'agents', 'locations', 'campaigns', 'content', 'reports', 'notifications', 'activity', 'users', 'settings']) {
      assert.ok(adminNav.links.some((href) => href.includes(`/estora-admin/${route}/`)), `admin sees ${route}`);
    }
    assert.ok(adminNav.html.includes('/wp-admin/'), 'super admin has WP admin link');

    const operationsNav = await navFor(operations);
    assert.ok(operationsNav.links.some((href) => href.includes('/estora-admin/users/')), 'administrator sees users');
    assert.ok(!operationsNav.html.includes('WordPress admin'), 'administrator has no technical WP link');

    const managerNav = await navFor(manager);
    assert.ok(managerNav.links.some((href) => href.includes('/estora-admin/leads/')));
    assert.ok(managerNav.links.some((href) => href.includes('/estora-admin/agents/')));
    assert.ok(!managerNav.links.some((href) => href.includes('/estora-admin/settings/')), 'sales manager cannot see settings');
    assert.ok(!managerNav.links.some((href) => href.includes('/estora-admin/campaigns/')), 'sales manager cannot see campaigns');

    const agentNav = await navFor(agent);
    assert.ok(agentNav.links.some((href) => href.includes('/estora-admin/leads/')));
    assert.ok(!agentNav.links.some((href) => href.includes('/estora-admin/agents/')), 'agent has no agent management');
    assert.ok(!agentNav.links.some((href) => href.includes('/estora-admin/settings/')), 'agent has no settings');
    assert.ok(!agentNav.links.some((href) => href.includes('/estora-admin/reports/')), 'agent has no company reports');

    const propertyNav = await navFor(property);
    assert.ok(propertyNav.links.some((href) => href.includes('/estora-admin/properties/')));
    assert.ok(!propertyNav.links.some((href) => href.includes('/estora-admin/leads/')), 'property manager has no CRM');

    const marketingNav = await navFor(marketing);
    assert.ok(marketingNav.links.some((href) => href.includes('/estora-admin/content/')));
    assert.ok(marketingNav.links.some((href) => href.includes('/estora-admin/campaigns/')));
    assert.ok(!marketingNav.links.some((href) => href.includes('/estora-admin/leads/')), 'marketing has no CRM');
    assert.ok(!marketingNav.links.some((href) => href.includes('/estora-admin/agents/')), 'marketing has no agent module');
  });

  await t.test('direct URLs enforce capabilities and 404 state', async () => {
    assertStatus(await admin.fetch('/estora-admin/settings/'), 200, 'admin settings');
    assertStatus(await agent.fetch('/estora-admin/settings/'), 403, 'agent settings forbidden');
    assertStatus(await agent.fetch('/estora-admin/users/'), 403, 'agent users forbidden');
    assertStatus(await marketing.fetch('/estora-admin/leads/'), 403, 'marketing leads forbidden');
    assertStatus(await manager.fetch('/estora-admin/campaigns/'), 403, 'manager campaigns forbidden');
    assertStatus(await marketing.fetch('/estora-admin/agents/'), 403, 'marketing agents forbidden');
    assertStatus(await marketing.fetch('/estora-admin/content/'), 200, 'marketing content allowed');
    assertStatus(await operations.fetch('/estora-admin/users/'), 200, 'administrator users allowed');
    assertStatus(await property.fetch('/estora-admin/reports/'), 200, 'property manager reports allowed');
    assertStatus(await admin.fetch('/estora-admin/no-such-module/'), 404, 'unknown route 404');
    const forbidden = await agent.fetch('/estora-admin/settings/');
    assert.ok((await forbidden.text()).includes('do not have access'), 'forbidden shell explains');
  });

  await t.test('lead detail route renders the selected lead', async () => {
    const leads = assertOk(await manager.api('/leads?per_page=1'), 'lead list');
    assert.ok(leads.data.length > 0);
    const lead = leads.data[0];
    const res = await manager.fetch(`/estora-admin/leads/${lead.id}/`);
    assert.equal(res.status, 200);
    const html = await res.text();
    assert.ok(html.includes(`data-lead-id="${lead.id}"`), 'lead screen has the id');
    assert.ok(html.includes('Lead detail'), 'lead screen title');
  });

  await t.test('dashboard endpoints respect scope and range', async () => {
    const summary = assertOk(await admin.api('/dashboard/summary?days=7'), 'summary');
    assert.equal(typeof summary.data.properties.total, 'number');
    assert.equal(typeof summary.data.range.total, 'number');
    assert.ok(summary.data.leads);
    assert.equal(summary.data.range.to, new Date().toISOString().slice(0, 10));

    const marketingSummary = assertOk(await marketing.api('/dashboard/summary?days=7'), 'marketing summary');
    assert.ok(!marketingSummary.data.leads, 'marketing does not receive lead aggregates');
    assert.ok(!marketingSummary.data.appointments, 'marketing does not receive appointments');

    const propertySummary = assertOk(await property.api('/dashboard/summary?days=7'), 'property summary');
    assert.ok(!propertySummary.data.leads, 'property manager does not receive lead aggregates');
    assert.ok(propertySummary.data.appointments, 'property manager receives appointment aggregates');

    const me = assertOk(await agent.api('/dashboard/me?days=30'), 'agent dashboard');
    assert.equal(typeof me.data.leads.total, 'number');
    assert.ok(Array.isArray(me.data.lead_trend));
    assert.equal(typeof me.data.properties.assigned, 'number');
    assert.ok(me.data.appointments.next.length <= 6);

    const propertyMe = await property.api('/dashboard/me?days=30');
    assert.equal(propertyMe.status, 200, 'property manager may use the scoped dashboard endpoint');
  });

  await t.test('WordPress CMS is available to marketing but not to agents', async () => {
    assertStatus(await marketing.fetch('/wp-admin/edit.php'), 200, 'marketing posts screen');
    const pages = await marketing.fetch('/wp-admin/edit.php?post_type=page');
    assert.equal(pages.status, 200, 'marketing pages screen');
    const agentPosts = await agent.fetch('/wp-admin/edit.php');
    assert.ok([302, 403].includes(agentPosts.status), 'agent cannot use the post editor');
  });

  await t.test('legacy ESTORA admin pages redirect into the app', async () => {
    const legacy = await manager.fetch('/wp-admin/admin.php?page=estora-dashboard');
    assert.equal(legacy.status, 302);
    assert.ok((legacy.headers.get('location') || '').includes('/estora-admin/'));
  });

  await t.test('logout clears the app session', async () => {
    const shell = await manager.fetch('/estora-admin/');
    const html = await shell.text();
    const match = html.match(/href="([^"]*wp-login\.php\?action=logout[^"]*)"/);
    assert.ok(match, 'logout link present');
    const logout = await manager.fetch(match[1].replace(/&amp;|&#038;/g, '&'));
    assert.ok([302, 200].includes(logout.status), 'logout responds');
    const after = await manager.fetch('/estora-admin/');
    assert.equal(after.status, 302, 'app requires login after logout');
    assert.ok((after.headers.get('location') || '').includes('wp-login.php'), 'redirects to login');
  });
});
