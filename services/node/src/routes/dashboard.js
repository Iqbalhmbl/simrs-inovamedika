const express = require('express');
const pool = require('../db');
const ApiError = require('../utils/ApiError');

const router = express.Router();

/**
 * All dashboard numbers accept ?from=YYYY-MM-DD&to=YYYY-MM-DD (defaults to
 * today). Queries are plain read-only aggregates against the same Postgres
 * database Yii2 writes to - this is deliberately the ONE place Node reads
 * directly from business tables (see docs/architecture.md: "why dashboard
 * reads bypass the Yii2 API"), since it is pure reporting with no business
 * rules to duplicate, and keeps the heavy aggregate queries off the request
 * path that handles transactional writes.
 */
function dateRange(req) {
  const today = new Date().toISOString().slice(0, 10);
  const from = req.query.from || today;
  const to = req.query.to || today;
  return [from, to];
}

router.get('/visits-summary', async (req, res, next) => {
  try {
    const [from, to] = dateRange(req);
    const { rows } = await pool.query(
      `SELECT count(*)::int AS total_visits,
              count(*) FILTER (WHERE status = 'completed')::int AS completed,
              count(*) FILTER (WHERE status = 'waiting')::int AS waiting,
              count(*) FILTER (WHERE status = 'cancelled')::int AS cancelled
       FROM visits WHERE visit_date BETWEEN $1 AND $2`,
      [from, to]
    );
    res.json({ success: true, data: { from, to, ...rows[0] } });
  } catch (err) {
    next(err);
  }
});

router.get('/visits-by-polyclinic', async (req, res, next) => {
  try {
    const [from, to] = dateRange(req);
    const { rows } = await pool.query(
      `SELECT p.name AS polyclinic, count(v.id)::int AS total
       FROM polyclinics p
       LEFT JOIN visits v ON v.polyclinic_id = p.id AND v.visit_date BETWEEN $1 AND $2
       GROUP BY p.name ORDER BY total DESC`,
      [from, to]
    );
    res.json({ success: true, data: { from, to, items: rows } });
  } catch (err) {
    next(err);
  }
});

router.get('/procedures-summary', async (req, res, next) => {
  try {
    const [from, to] = dateRange(req);
    const { rows } = await pool.query(
      `SELECT mp.name AS procedure, count(vp.id)::int AS total, sum(vp.tariff_at_time * vp.qty)::numeric AS revenue
       FROM visit_procedures vp
       JOIN medical_procedures mp ON mp.id = vp.medical_procedure_id
       JOIN visits v ON v.id = vp.visit_id
       WHERE v.visit_date BETWEEN $1 AND $2 AND vp.is_voided = false
       GROUP BY mp.name ORDER BY total DESC`,
      [from, to]
    );
    res.json({ success: true, data: { from, to, items: rows } });
  } catch (err) {
    next(err);
  }
});

router.get('/revenue-summary', async (req, res, next) => {
  try {
    const [from, to] = dateRange(req);
    const { rows } = await pool.query(
      `SELECT coalesce(sum(p.amount), 0)::numeric AS total_collected,
              coalesce(sum(i.total_amount) FILTER (WHERE i.status != 'paid'), 0)::numeric AS outstanding
       FROM invoices i
       LEFT JOIN payments p ON p.invoice_id = i.id AND p.paid_at::date BETWEEN $1 AND $2
       JOIN visits v ON v.id = i.visit_id
       WHERE v.visit_date BETWEEN $1 AND $2`,
      [from, to]
    );
    res.json({ success: true, data: { from, to, ...rows[0] } });
  } catch (err) {
    next(err);
  }
});

router.get('/medicine-usage', async (req, res, next) => {
  try {
    const [from, to] = dateRange(req);
    const { rows } = await pool.query(
      `SELECT m.name AS medicine, m.unit, sum(pi.qty)::int AS total_dispensed
       FROM prescription_items pi
       JOIN medicines m ON m.id = pi.medicine_id
       JOIN prescriptions pr ON pr.id = pi.prescription_id
       JOIN visits v ON v.id = pr.visit_id
       WHERE pi.dispensed = true AND v.visit_date BETWEEN $1 AND $2
       GROUP BY m.name, m.unit ORDER BY total_dispensed DESC`,
      [from, to]
    );
    res.json({ success: true, data: { from, to, items: rows } });
  } catch (err) {
    next(err);
  }
});

router.get('/payment-status', async (req, res, next) => {
  try {
    const [from, to] = dateRange(req);
    const { rows } = await pool.query(
      `SELECT i.status, count(*)::int AS total
       FROM invoices i
       JOIN visits v ON v.id = i.visit_id
       WHERE v.visit_date BETWEEN $1 AND $2
       GROUP BY i.status`,
      [from, to]
    );
    res.json({ success: true, data: { from, to, items: rows } });
  } catch (err) {
    next(err);
  }
});

module.exports = router;
