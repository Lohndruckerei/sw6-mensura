// Statische Testseite des Konfigurators (ohne Shop, gegen docs/contract/fixtures).
// Aufruf im Plugin-Verzeichnis:  node tests/Frontend/static/serve.mjs [port]   → http://localhost:<port>/
// Im Docker-Container „shopware“ ist Port 8888 nach außen freigegeben (Dockware-Watcher-Port, sonst ungenutzt).
import { createServer } from 'node:http';
import { readFile, readdir } from 'node:fs/promises';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(fileURLToPath(new URL('.', import.meta.url)), '..', '..', '..');
const PORT = Number(process.argv[2] || 8888);
const TYPES = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.mjs': 'text/javascript; charset=utf-8', '.json': 'application/json; charset=utf-8', '.scss': 'text/css; charset=utf-8', '.css': 'text/css; charset=utf-8', '.svg': 'image/svg+xml' };
// nur diese Ordner werden ausgeliefert
const ALLOWED = ['src/Resources/app/storefront/src/', 'src/Resources/snippet/', 'docs/contract/fixtures/', 'tests/Frontend/'];

async function fixtureIndex() {
    const dir = join(ROOT, 'docs/contract/fixtures');
    const configurators = (await readdir(join(dir, 'configurator'))).filter((f) => f.endsWith('.json')).map((f) => f.slice(0, -5));
    const cases = (await readdir(join(dir, 'calculate'))).filter((f) => f.endsWith('.request.json')).map((f) => f.replace('.request.json', ''));
    return { configurators, cases };
}

createServer(async (req, res) => {
    try {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/' || url.pathname === '/index.html') {
            res.writeHead(302, { Location: '/tests/Frontend/static/index.html' });
            return res.end();
        }
        if (url.pathname === '/docs/contract/fixtures/index.json') {
            res.writeHead(200, { 'Content-Type': TYPES['.json'] });
            return res.end(JSON.stringify(await fixtureIndex()));
        }
        const rel = normalize(decodeURIComponent(url.pathname)).replace(/^[/\\]+/, '').replace(/\\/g, '/');
        if (rel.includes('..') || !ALLOWED.some((p) => rel.startsWith(p))) {
            res.writeHead(404);
            return res.end('nicht freigegeben');
        }
        const body = await readFile(join(ROOT, rel));
        res.writeHead(200, { 'Content-Type': TYPES[extname(rel)] || 'application/octet-stream', 'Cache-Control': 'no-store' });
        return res.end(body);
    } catch (e) {
        res.writeHead(404);
        return res.end('nicht gefunden');
    }
}).listen(PORT, '0.0.0.0', () => console.log(`Mensura-Testseite: http://localhost:${PORT}/`));
