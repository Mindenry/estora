/* Minimal static server for the exported static demo. */
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(process.argv[2] || 'dist');
const port = Number(process.argv[3] || 8090);
const types = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.png': 'image/png',
  '.webp': 'image/webp',
  '.svg': 'image/svg+xml',
  '.woff2': 'font/woff2',
  '.ico': 'image/x-icon',
  '.txt': 'text/plain; charset=utf-8'
};

http.createServer((request, response) => {
  const url = new URL(request.url, 'http://localhost');
  let file = path.join(root, decodeURIComponent(url.pathname));
  try {
    if (fs.existsSync(file) && fs.statSync(file).isDirectory()) file = path.join(file, 'index.html');
    if (!fs.existsSync(file)) {
      file = path.join(root, '404.html');
      response.statusCode = 404;
    }
    const ext = path.extname(file).toLowerCase();
    response.setHeader('Content-Type', types[ext] || 'application/octet-stream');
    fs.createReadStream(file).pipe(response);
  } catch (error) {
    response.statusCode = 500;
    response.end('Server error');
  }
}).listen(port, () => console.log('Static demo on http://localhost:' + port));
