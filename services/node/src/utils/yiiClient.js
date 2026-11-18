const axios = require('axios');
const config = require('../config');

/**
 * Forwards business-logic writes to the Yii2 API instead of writing to the
 * database directly, so pemeriksaan-penunjang business rules (e.g. marking
 * the lab_request done, recalculating the invoice) live in exactly one
 * place (requirement E.10: no duplicated business logic between Yii and
 * Node). Node's job here is only: receive the external payload, validate
 * its shape, authenticate the caller, and relay it.
 */
const client = axios.create({
  baseURL: config.yiiApiBaseUrl,
  timeout: 5000,
});

async function postLabResult(payload, bearerToken) {
  const token = bearerToken || config.yiiServiceToken;
  return client.post('/api/lab-results', payload, {
    headers: { Authorization: `Bearer ${token}` },
  });
}

module.exports = { postLabResult };
