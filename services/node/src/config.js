require('dotenv').config();

module.exports = {
  port: process.env.PORT || 4000,
  db: {
    host: process.env.DB_HOST || 'localhost',
    port: Number(process.env.DB_PORT || 5432),
    database: process.env.DB_NAME || 'simrs',
    user: process.env.DB_USER || 'postgres',
    password: process.env.DB_PASSWORD || 'workingspace',
  },
  jwtSecret: process.env.JWT_SECRET || 'dev-only-change-me-in-production-simrs-2026',
  yiiApiBaseUrl: process.env.YII_API_BASE_URL || 'http://localhost:8080',
  yiiServiceToken: process.env.YII_SERVICE_TOKEN || '',
};
