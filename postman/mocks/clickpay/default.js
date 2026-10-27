// Local-only contract stand-in. __test routes control remote status/failures.
const http = require('http');
const { randomUUID } = require('crypto');
const provider = 'clickpay';
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
  if (req.method === 'POST' && path === '/payment/request') {
    if (!body.profile_id || !body.cart_id || !body.return || !body.callback || body.tran_type !== 'sale' || !(body.cart_amount > 0)) {
      return send(400, { error: 'Invalid payment contract' });
    }
    const id = `TST_${randomUUID()}`;
    const remote = { tran_ref: id, tran_type: 'Sale', cart_id: body.cart_id, cart_amount: body.cart_amount,
      cart_currency: body.cart_currency, payment_result: { response_status: 'H' } };
    await save(id, { remote, request: body, queries: 0 });
    return send(200, { tran_ref: id, redirect_url: `https://secure.clickpay.com.sa/checkout/${id}` });
  }
  if (req.method === 'POST' && path === '/payment/query') {
    if (!body.profile_id || !body.tran_ref) return send(400, { error: 'Invalid query contract' });
    return queryResponse(body.tran_ref, await load(body.tran_ref));
  }
  return send(501, { error: 'This payment mock does not model this endpoint' });
}).listen(process.env.PORT || 4500);
