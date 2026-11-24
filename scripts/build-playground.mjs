/**
 * Build the WordPress Playground blueprint and the theme/plugin zips.
 *
 * WordPress Playground runs real WordPress (PHP compiled to WebAssembly) in the
 * browser. The blueprint installs this theme and plugin, activates them and
 * seeds the demo data, so the full public site and /estora-admin/ can be tried
 * without a server or an account.
 *
 * Output: playground/estora-theme.zip, playground/estora-core.zip,
 *         playground/blueprint.json
 */
import fs from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import JSZip from 'jszip';

const root = path.resolve('.');
const out = path.join(root, 'playground');
const base = (process.env.ESTORA_PLAYGROUND_BASE || 'https://mindenry.github.io/playground').replace(/\/$/, '');
const skip = new Set(['.git', 'node_modules', 'tests', 'vendor', '.DS_Store', 'playground', 'dist']);

async function addDirectory(zip, dir, prefix) {
  const entries = await fs.readdir(dir, { withFileTypes: true });
  for (const entry of entries) {
    if (skip.has(entry.name)) continue;
    const full = path.join(dir, entry.name);
    const name = `${prefix}/${entry.name}`;
    if (entry.isDirectory()) {
      await addDirectory(zip, full, name);
    } else if (entry.isFile()) {
      zip.file(name, await fs.readFile(full));
    }
  }
}

async function zipDirectory(source, filename, prefix) {
  const zip = new JSZip();
  await addDirectory(zip, source, prefix);
  const buffer = await zip.generateAsync({ type: 'nodebuffer', compression: 'DEFLATE', compressionOptions: { level: 6 } });
  await fs.writeFile(path.join(out, filename), buffer);
  console.log(`  • ${filename}: ${(buffer.length / 1024 / 1024).toFixed(1)} MB`);
}

await fs.rm(out, { recursive: true, force: true });
await fs.mkdir(out, { recursive: true });
console.log('WordPress Playground build');

await zipDirectory(
  path.join(root, 'wp-content/themes/estora-theme'),
  'estora-theme.zip',
  'estora-theme'
);
await zipDirectory(
  path.join(root, 'wp-content/plugins/estora-core'),
  'estora-core.zip',
  'estora-core'
);

const blueprint = {
  $schema: 'https://playground.wordpress.net/blueprint-schema.json',
  preferredVersions: { php: '8.3', wp: '6.7' },
  login: true,
  landingPage: '/',
  steps: [
    {
      step: 'installTheme',
      themeZipFile: { resource: 'url', url: `${base}/estora-theme.zip` },
      options: { activate: true }
    },
    {
      step: 'installPlugin',
      pluginZipFile: { resource: 'url', url: `${base}/estora-core.zip` },
      options: { activate: true }
    },
    {
      step: 'setSiteOptions',
      options: {
        blogname: 'ESTORA',
        blogdescription: 'Residential property, handled properly.',
        permalink_structure: '/%postname%/',
        posts_per_page: 7
      }
    },
    {
      step: 'runPHP',
      code: `<?php
if ( ! defined( 'ABSPATH' ) ) {
	require_once '/wordpress/wp-load.php';
}
if ( class_exists( '\\Estora\\Core\\Support\\Capabilities' ) ) {
	\\Estora\\Core\\Support\\Capabilities::install();
}
if ( class_exists( '\\Estora\\Core\\Integrations\\Seeder' ) ) {
	$seeder = new \\Estora\\Core\\Integrations\\Seeder();
	$seeder->run( true );
}
update_option( 'blogname', 'ESTORA' );
update_option( 'blogdescription', 'Residential property, handled properly.' );
update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules( true );
echo 'ESTORA demo seeded';
`
    }
  ]
};

await fs.writeFile(path.join(out, 'blueprint.json'), JSON.stringify(blueprint, null, 2) + '\n');
console.log('  • blueprint.json');
console.log(`Open: https://playground.wordpress.net/?blueprint-url=${base}/blueprint.json`);
