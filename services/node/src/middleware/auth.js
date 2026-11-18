const jwt = require('jsonwebtoken');
const config = require('../config');
const ApiError = require('../utils/ApiError');

/**
 * Validates the same JWT format Yii2 issues (components/JwtHttpBearerAuth.php):
 * HS256, shared secret, 'sub' = user id, 'role' = Yii2 role name. This lets a
 * single login (POST /api/auth/login on the Yii2 side) authenticate against
 * both backends.
 */
function requireAuth(req, res, next) {
  const header = req.headers.authorization;
  if (!header || !header.startsWith('Bearer ')) {
    return next(new ApiError(401, 'Missing or invalid Authorization header.'));
  }

  const token = header.slice('Bearer '.length);
  try {
    req.user = jwt.verify(token, config.jwtSecret, { algorithms: ['HS256'] });
    return next();
  } catch (err) {
    return next(new ApiError(401, `Invalid or expired token: ${err.message}`));
  }
}

/** Restricts a route to specific Yii2 roles (same role names as app\models\User::ROLE_*). */
function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.user || !roles.includes(req.user.role)) {
      return next(new ApiError(403, 'Anda tidak memiliki akses untuk endpoint ini.'));
    }
    return next();
  };
}

module.exports = { requireAuth, requireRole };
