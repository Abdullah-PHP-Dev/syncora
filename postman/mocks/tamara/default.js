// Local-only contract stand-in. __test routes control remote status/failures.
const http = require('http');
const { randomUUID } = require('crypto');
const provider = 'tamara';
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
  if (req.method === 'POST' && path === '/checkout') {
    if (!body.order_reference_id || !body.consumer?.phone_number || !body.merchant_url?.notification || !(body.total_amount?.amount > 0)) {
      return send(400, { error: 'Invalid checkout contract' });
    }
    const id = `tamara_${randomUUID()}`;
    const remote = { order_id: id, order_reference_id: body.order_reference_id, status: 'new', total_amount: body.total_amount };
    await save(id, { remote, request: body, queries: 0 });
    return send(200, { order_id: id, checkout_id: id, status: 'new', checkout_url: `https://checkout-sandbox.tamara.co/${id}` });
  }
  if (path.startsWith('/orders/')) {
    const id = path.split('/')[2];
    const record = await load(id);
    if (req.method === 'GET') return queryResponse(id, record);
    if (req.method === 'POST' && path.endsWith('/authorise')) {
      if (!record) return send(404, { error: 'Unknown order' });
      record.remote.status = 'authorised';
      await save(id, record);
      return send(200, { status: 'authorised' });
    }
  }
  if (req.method === 'POST' && path === '/payments/capture') {
    const record = await load(body.order_id);
    if (!record) return send(404, { error: 'Unknown order' });
    if (record.capture_http_status) return send(record.capture_http_status, { error: 'Capture unavailable' });
    if (JSON.stringify(body.total_amount) !== JSON.stringify(record.remote.total_amount)) return send(400, { error: 'Capture amount mismatch' });
    record.remote.status = 'fully_captured';
    await save(body.order_id, record);
    return send(200, { capture_id: `capture_${body.order_id}`, status: 'fully_captured' });
  }
  return send(501, { error: 'This payment mock does not model this endpoint' });
}).listen(process.env.PORT || 4500);
