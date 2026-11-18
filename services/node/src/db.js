const { Pool } = require('pg');
const config = require('./config');

/**
 * Read-only pool for dashboard aggregation. Node never INSERTs/UPDATEs
 * business tables directly - see docs/architecture.md for the full
 * responsibility split between Yii2 and Node.js.
 */
const pool = new Pool(config.db);

pool.on('error', (err) => {
  // eslint-disable-next-line no-console
  console.error('Unexpected Postgres pool error', err);
});

module.exports = pool;
