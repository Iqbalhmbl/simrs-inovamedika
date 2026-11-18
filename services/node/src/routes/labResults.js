const express = require('express');
const ApiError = require('../utils/ApiError');
const { postLabResult } = require('../utils/yiiClient');
const pool = require('../db');

const router = express.Router();

/**
 * Integration endpoint for pemeriksaan penunjang (spec item 6): an external
 * lab system (or a lab instrument's middleware) pushes a result here.
 * Node validates the payload's shape and that the referenced lab_request
 * actually exists and is still open, then relays it to the Yii2 API, which
 * owns the actual business logic (marking the request done, making the
 * result visible in the patient's visit history, recalculating billing).
 *
 * Problem D5 (Gangguan Integrasi): if the Yii2 API is unreachable when this
 * endpoint is called, we do NOT silently drop the result or write it
 * straight to the database (that would duplicate business logic AND risk
 * writing something Yii2's validation would have rejected). Instead:
 *   - The failure is surfaced to the caller as a 502 with a retryable=true
 *     flag, so the external system's own retry logic (which every lab
 *     interface engine already has) can resubmit later without data loss.
 *   - The attempt is logged to `integration_failures` so an operator can
 *     reconcile manually if the external system does not retry.
 */
router.post('/', async (req, res, next) => {
  const { lab_request_id: labRequestId, result_text: resultText, result_data: resultData } = req.body;

  if (!labRequestId) {
    return next(new ApiError(400, 'lab_request_id is required.'));
  }

  try {
    const response = await postLabResult(
      { lab_request_id: labRequestId, result_text: resultText, result_data: resultData },
      req.headers.authorization ? req.headers.authorization.replace('Bearer ', '') : undefined
    );
    return res.status(201).json({ success: true, data: response.data });
  } catch (err) {
    if (err.response) {
      // Yii2 reached and rejected it (validation, 404, etc.) - relay as-is.
      return res.status(err.response.status).json(err.response.data);
    }

    // Yii2 unreachable/timeout: problem D5 handling (see doc comment above).
    try {
      await pool.query(
        `INSERT INTO integration_failures (source, payload, error_message)
         VALUES ($1, $2, $3)`,
        ['node_lab_integration', JSON.stringify(req.body), err.message]
      );
    } catch (logErr) {
      // eslint-disable-next-line no-console
      console.error('Failed to log integration failure:', logErr.message);
    }

    return next(new ApiError(502, 'Backend utama (Yii) sedang tidak dapat diakses. Silakan coba lagi.', {
      retryable: true,
    }));
  }
});

module.exports = router;
