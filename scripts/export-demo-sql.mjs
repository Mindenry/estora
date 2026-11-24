/**
 * Dump the seeded demo database for a manual WordPress install.
 *
 * Shared hosts such as InfinityFree have no WP-CLI or SSH. This writes a SQL
 * file next to the deployment guide, which can be imported with phpMyAdmin.
 * After importing, replace http://localhost:8080 with the new URL using the
 * Better Search Replace plugin (it handles serialized values).
 *
 *   npm run export:demo-sql
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const envFile = fs.existsSync('.env') ? fs.readFileSync('.env', 'utf8') : '';
const read = (key, fallback) => {
  const match = envFile.match(new RegExp('^' + key + '=(.*)$', 'm'));
  return match ? match[1].trim().replace(/^["']|["']$/g, '') : fallback;
};

const database = read('MYSQL_DATABASE', 'estora');
const password = read('MYSQL_ROOT_PASSWORD', 'estora_local_root');
const out = path.resolve('deploy/wordpress-free/estora-demo.sql');

const sql = execFileSync(
  'docker',
  [
    'compose', 'exec', '-T', 'db',
    'mysqldump', '-u', 'root', `--password=${password}`,
    '--single-transaction', '--quick', '--skip-comments',
    '--default-character-set=utf8mb4', database
  ],
  { maxBuffer: 256 * 1024 * 1024 }
);

fs.writeFileSync(out, sql);
console.log(`Wrote ${out} (${(sql.length / 1024 / 1024).toFixed(1)} MB)`);
console.log('Import it with phpMyAdmin, then run Better Search Replace:');
console.log('  http://localhost:8080  ->  https://your-domain.example');
