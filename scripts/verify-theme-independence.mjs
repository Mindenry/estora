import { Session, BASE } from '../tests/http/helpers.mjs';

const manager = await new Session('theme-check').login('manager@estora.test', process.env.ESTORA_TEST_AGENT_PASSWORD || 'Estora!Agent!2026');
const leads = await manager.api('/leads?per_page=1');
const properties = await manager.api('/properties?per_page=1');
const health = await fetch(BASE + '/wp-json/estora/v1/health').then((r) => r.json());
console.log(JSON.stringify({
  leads: leads.status,
  properties: properties.status,
  tables: Object.values(health.data.tables).every(Boolean)
}));
if (leads.status !== 200 || properties.status !== 200) process.exit(1);
