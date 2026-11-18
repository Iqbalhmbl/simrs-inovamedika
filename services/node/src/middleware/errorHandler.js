const ApiError = require('../utils/ApiError');

/**
 * Consistent JSON error envelope, deliberately matching the shape produced
 * by app\components\ErrorResponseFormatter on the Yii2 side:
 *   {"success": false, "error": {"code", "message", "details"}}
 * so API consumers (React, Postman) never have to branch on which backend
 * answered.
 */
function notFoundHandler(req, res) {
  res.status(404).json({
    success: false,
    error: { code: 404, message: `Route not found: ${req.method} ${req.originalUrl}`, details: null },
  });
}

// eslint-disable-next-line no-unused-vars
function errorHandler(err, req, res, next) {
  if (err instanceof ApiError) {
    return res.status(err.statusCode).json({
      success: false,
      error: { code: err.statusCode, message: err.message, details: err.details },
    });
  }

  // eslint-disable-next-line no-console
  console.error('Unhandled error:', err);
  return res.status(500).json({
    success: false,
    error: { code: 500, message: 'Internal server error.', details: null },
  });
}

module.exports = { notFoundHandler, errorHandler };
