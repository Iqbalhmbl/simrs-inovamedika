const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');

const config = require('./config');
const { requireAuth, requireRole } = require('./middleware/auth');
const { notFoundHandler, errorHandler } = require('./middleware/errorHandler');
const dashboardRoutes = require('./routes/dashboard');
const labResultsRoutes = require('./routes/labResults');

const app = express();

app.use(helmet());
app.use(cors());
app.use(express.json());

// Middleware for request validation / rate limiting (spec item C.3).
app.use(
  rateLimit({
    windowMs: 60 * 1000,
    limit: 120,
    standardHeaders: true,
    legacyHeaders: false,
    message: { success: false, error: { code: 429, message: 'Too many requests.', details: null } },
  })
);

app.get('/health', (req, res) => res.json({ success: true, data: { status: 'ok' } }));

// Dashboard: any authenticated role can read it; fine-grained hiding of
// specific widgets per role is left to the React frontend.
app.use('/api/dashboard', requireAuth, dashboardRoutes);

// External lab-integration push: service-to-service call, so it is
// authenticated but not tied to a specific interactive role.
app.use(
  '/api/integrations/lab-results',
  requireAuth,
  requireRole('admin', 'petugas_laboratorium'),
  labResultsRoutes
);

app.use(notFoundHandler);
app.use(errorHandler);

if (require.main === module) {
  app.listen(config.port, () => {
    // eslint-disable-next-line no-console
    console.log(`SIMRS Node services listening on port ${config.port}`);
  });
}

module.exports = app;
