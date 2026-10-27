// Local-only contract stand-in. __test routes control remote status/failures.
const http = require('http');
const { randomUUID } = require('crypto');
const provider = 'tap';
http.createServer(async (req, res) => {
  const path = new URL(req.url, 'http://mock.local').pathname;
  const body = req.body || {};
  const send = (status, value) => {
    res.writeHead(status, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(value));
  };
  const load = id => pm.state.get(`${provider}:${id}`);
  const save = (id, value) => pm.state.set(`${provider}:${id}`, value);
  if (path.startsWith('/__test/')) {
    const id = path.slice('/__test/'.length);
    const record = await load(id);
    if (!record) return send(404, { error: 'Unknown payment' });
    if (req.method === 'POST') {
      Object.assign(record.remote, body.patch || {});
      for (const field of ['http_status', 'html', 'delay_ms', 'capture_http_status']) {
        if (field in body) record[field] = body[field];
      }
      await save(id, record);
    }
    return send(200, record);
  }
  if (req.headers.authorization !== 'Bearer simulation-key' && req.headers.authorization !== 'simulation-key') {
    return send(401, { error: 'Missing simulation credentials' });
  }
  const queryResponse = async (id, record) => {
    if (!record) return send(404, { error: 'Unknown payment' });
    record.queries = (record.queries || 0) + 1;
    await save(id, record);
    if (record.delay_ms) await new Promise(resolve => setTimeout(resolve, record.delay_ms));
    if (record.html) {
      res.writeHead(record.http_status || 502, { 'Content-Type': 'text/html' });
      return res.end('<html>Gateway unavailable</html>');
    }
    return send(record.http_status || 200, record.remote);
  };
  if (req.method === 'POST' && path === '/v2/charges') {
    if (!body.reference?.transaction || !body.redirect?.url || !body.post?.url || !body.customer?.email || !body.currency || !(body.amount > 0)) {
      return send(400, { error: 'Invalid charge contract' });
    }
    const id = `chg_${randomUUID()}`;
    const remote = { id, status: 'INITIATED', amount: body.amount, currency: body.currency, reference: body.reference };
    await save(id, { remote, request: body, queries: 0 });
    return send(200, { ...remote, transaction: { url: `https://checkout.tap.company/${id}` } });
  }
  if (req.method === 'GET' && path.startsWith('/v2/charges/')) {
    const id = path.slice('/v2/charges/'.length);
    return queryResponse(id, await load(id));
  }
  return send(501, { error: 'This payment mock does not model this endpoint' });
}).listen(process.env.PORT || 4500);
