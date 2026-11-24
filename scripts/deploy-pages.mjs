/**
 * Publish the exported static demo to GitHub Pages.
 *
 * The Pages repository (Mindenry/Mindenry.github.io) is a build artefact: every
 * run exports a fresh dist/, commits it and force-pushes to main. GitHub Pages
 * then serves it from the repository root.
 *
 *   npm run export:static
 *   npm run deploy:pages
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const dist = path.resolve('dist');
const remote = process.env.ESTORA_PAGES_REMOTE || 'https://github.com/Mindenry/Mindenry.github.io.git';

if (!fs.existsSync(path.join(dist, 'index.html'))) {
  console.error('dist/index.html is missing. Run `npm run export:static` first.');
  process.exit(1);
}

const git = (args) => execFileSync('git', args, { cwd: dist, stdio: 'inherit' });

if (!fs.existsSync(path.join(dist, '.git'))) {
  git(['init', '-b', 'main']);
  git(['config', 'user.name', 'Peerawit Lakhampa']);
  git(['config', 'user.email', 'mindenrymmd@gmail.com']);
  git(['remote', 'add', 'origin', remote]);
}

git(['add', '-A']);
try {
  git(['commit', '-m', 'deploy: ESTORA static demo ' + new Date().toISOString()]);
} catch (error) {
  console.log('Nothing new to commit.');
}
git(['push', '--force', 'origin', 'main']);
console.log('Published to GitHub Pages. The build usually finishes in under a minute.');
