const test = require('node:test');
const assert = require('node:assert');
const http = require('node:http');
const jwt = require('jsonwebtoken');
const config = require('../src/config');
const app = require('../src/server');

/**
 * Integration tests against the live app + whatever Postgres is configured
 * in .env. Run with: npm test (requires the `simrs` database to exist and
 * be migrated - see docs/installation.md).
 */
function withServer(fn) {
  return new Promise((resolve, reject) => {
    const server = app.listen(0, async () => {
      try {
        const { port } = server.address();
        await fn(`http://127.0.0.1:${port}`);
        resolve();
      } catch (err) {
        reject(err);
      } finally {
        server.close();
      }
    });
  });
}

function request(url, options = {}) {
  return new Promise((resolve, reject) => {
    const req = http.request(url, options, (res) => {
      let body = '';
      res.on('data', (chunk) => (body += chunk));
      res.on('end', () => resolve({ status: res.statusCode, body: JSON.parse(body || '{}') }));
    });
    req.on('error', reject);
    if (options.body) req.write(options.body);
    req.end();
  });
}

test('GET /health returns ok', async () => {
  await withServer(async (base) => {
    const res = await request(`${base}/health`);
    assert.strictEqual(res.status, 200);
    assert.strictEqual(res.body.data.status, 'ok');
  });
});

test('dashboard route rejects missing auth', async () => {
  await withServer(async (base) => {
    const res = await request(`${base}/api/dashboard/visits-summary`);
    assert.strictEqual(res.status, 401);
    assert.strictEqual(res.body.success, false);
  });
});

test('dashboard route accepts a valid JWT and returns the expected shape', async () => {
  await withServer(async (base) => {
    const token = jwt.sign({ sub: 1, role: 'admin' }, config.jwtSecret, { algorithm: 'HS256' });
    const res = await request(`${base}/api/dashboard/visits-summary`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    assert.strictEqual(res.status, 200);
    assert.ok('total_visits' in res.body.data);
  });
});

test('lab-integration route is forbidden for roles other than admin/lab', async () => {
  await withServer(async (base) => {
    const token = jwt.sign({ sub: 2, role: 'kasir' }, config.jwtSecret, { algorithm: 'HS256' });
    const res = await request(`${base}/api/integrations/lab-results`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ lab_request_id: 1 }),
    });
    assert.strictEqual(res.status, 403);
  });
});

test('unknown route returns 404 with the standard error envelope', async () => {
  await withServer(async (base) => {
    const res = await request(`${base}/api/does-not-exist`);
    assert.strictEqual(res.status, 404);
    assert.strictEqual(res.body.success, false);
  });
});
