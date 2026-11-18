import { useState, useEffect, useRef } from 'react';
import { useParams } from 'react-router-dom';
import { yiiApi } from '../api/client';

function formatRp(value) {
  return `Rp ${Number(value || 0).toLocaleString('id-ID')}`;
}

export default function BillingPage() {
  const { visitId } = useParams();
  const [invoice, setInvoice] = useState(null);
  const [amount, setAmount] = useState('');
  const [method, setMethod] = useState('cash');
  const [error, setError] = useState(null);
  const [message, setMessage] = useState(null);

  // Problem D3 (Pembayaran Ganda): generated ONCE per "Bayar" click and
  // reused on every retry of that same attempt, so a network blip followed
  // by the user/browser retrying can never double-charge - the server
  // recognizes the repeated idempotency_key and returns the original
  // payment instead of creating a second one (see BillingService::pay()).
  const idempotencyKeyRef = useRef(null);

  function load() {
    yiiApi.get(`/invoices/by-visit/${visitId}`).then((res) => setInvoice(res.data));
  }

  useEffect(load, [visitId]);

  async function handlePay(e) {
    e.preventDefault();
    setError(null);
    setMessage(null);

    if (!idempotencyKeyRef.current) {
      idempotencyKeyRef.current =
        (crypto.randomUUID && crypto.randomUUID()) || `${Date.now()}-${Math.random()}`;
    }

    try {
      await yiiApi.post(`/invoices/${invoice.id}/pay`, {
        amount: Number(amount),
        method,
        idempotency_key: idempotencyKeyRef.current,
      });
      setMessage('Pembayaran berhasil dicatat.');
      idempotencyKeyRef.current = null; // success: free to generate a new key for the next payment
      setAmount('');
      load();
    } catch (err) {
      // Leave idempotencyKeyRef as-is so a manual retry reuses the same key.
      setError(err.message);
    }
  }

  if (!invoice) return <p>Memuat tagihan...</p>;

  const remaining = invoice.total_amount - invoice.paid_amount;

  return (
    <div>
      <h1>Billing - Kunjungan #{visitId}</h1>
      <div className="card" id="invoice-print">
        <h3>Invoice {invoice.invoice_number}</h3>
        <table>
          <tbody>
            <tr><td>Biaya Pendaftaran</td><td>{formatRp(invoice.registration_fee)}</td></tr>
            <tr><td>Biaya Tindakan Medis</td><td>{formatRp(invoice.procedure_fee)}</td></tr>
            <tr><td>Biaya Pemeriksaan Penunjang</td><td>{formatRp(invoice.lab_fee)}</td></tr>
            <tr><td>Biaya Obat</td><td>{formatRp(invoice.medicine_fee)}</td></tr>
            <tr><td><strong>Total Tagihan</strong></td><td><strong>{formatRp(invoice.total_amount)}</strong></td></tr>
            <tr><td>Sudah Dibayar</td><td>{formatRp(invoice.paid_amount)}</td></tr>
            <tr><td><strong>Sisa Tagihan</strong></td><td><strong>{formatRp(remaining)}</strong></td></tr>
            <tr><td>Status</td><td><span className={`badge ${invoice.status === 'paid' ? 'completed' : 'waiting'}`}>{invoice.status}</span></td></tr>
          </tbody>
        </table>
        <button className="secondary" onClick={() => window.print()} style={{ marginTop: 12 }}>
          Cetak Invoice
        </button>
      </div>

      {invoice.status !== 'paid' && (
        <form className="card" onSubmit={handlePay}>
          <h3>Catat Pembayaran</h3>
          <div className="form-row">
            <div>
              <label>Jumlah</label>
              <input type="number" min="1" max={remaining} value={amount} onChange={(e) => setAmount(e.target.value)} required />
            </div>
            <div>
              <label>Metode</label>
              <select value={method} onChange={(e) => setMethod(e.target.value)}>
                <option value="cash">Tunai</option>
                <option value="non_cash">Non-Tunai</option>
              </select>
            </div>
          </div>
          <button type="submit">Bayar</button>
        </form>
      )}

      {message && <p style={{ color: '#198754' }}>{message}</p>}
      {error && <p className="error">{error}</p>}

      <div className="card">
        <h3>Riwayat Pembayaran</h3>
        <table>
          <thead>
            <tr><th>No. Struk</th><th>Jumlah</th><th>Metode</th><th>Waktu</th></tr>
          </thead>
          <tbody>
            {(invoice.payments || []).map((p) => (
              <tr key={p.id}>
                <td>{p.receipt_number}</td>
                <td>{formatRp(p.amount)}</td>
                <td>{p.method}</td>
                <td>{p.paid_at}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
