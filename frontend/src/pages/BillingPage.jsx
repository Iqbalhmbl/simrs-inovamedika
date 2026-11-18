import { useState, useEffect, useRef } from 'react';
import { useParams } from 'react-router-dom';
import { yiiApi } from '../api/client';
import { useAlert } from '../context/AlertContext';

function formatRp(value) {
  return `Rp ${Number(value || 0).toLocaleString('id-ID')}`;
}

export default function BillingPage() {
  const { visitId } = useParams();
  const { success, error: alertError } = useAlert();
  const [invoice, setInvoice] = useState(null);
  const [amount, setAmount] = useState('');
  const [method, setMethod] = useState('cash');

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
      idempotencyKeyRef.current = null; // success: free to generate a new key for the next payment
      setAmount('');
      load();
      success('Pembayaran berhasil dicatat.');
    } catch (err) {
      // Leave idempotencyKeyRef as-is so a manual retry reuses the same key.
      alertError(err);
    }
  }

  if (!invoice) return <p>Memuat tagihan...</p>;

  const remaining = invoice.total_amount - invoice.paid_amount;
  const visit = invoice.visit;
  const payments = invoice.payments || [];

  const STATUS_LABEL = { unpaid: 'Belum Lunas', partial: 'Sebagian', paid: 'Lunas' };
  const statusBadgeClass = invoice.status === 'paid' ? 'completed' : invoice.status === 'partial' ? 'examined' : 'waiting';

  return (
    <div>
      <h1 className="no-print">Billing - Kunjungan #{visitId}</h1>

      <div className="card" id="invoice-print">
        <div className="invoice-head">
          <div>
            <h2>PT Inova Medika Solusindo</h2>
            <p>Sistem Informasi Manajemen Rumah Sakit</p>
            <p>Jl. Contoh No. 1, Jakarta</p>
          </div>
          <div className="invoice-meta">
            <div className="invoice-number">{invoice.invoice_number}</div>
            <p>Tanggal: {invoice.created_at ? invoice.created_at.slice(0, 10) : '-'}</p>
            <p>
              Status:{' '}
              <span className={`badge ${statusBadgeClass}`}>{STATUS_LABEL[invoice.status] || invoice.status}</span>
            </p>
          </div>
        </div>

        {visit && (
          <div className="invoice-patient">
            <div><span>Nama Pasien</span>: {visit.patient?.name}</div>
            <div><span>No. RM</span>: {visit.patient?.mrn}</div>
            <div><span>Poli</span>: {visit.polyclinic?.name}</div>
            <div><span>Dokter</span>: dr. {visit.doctor?.name}</div>
            <div><span>Tgl. Kunjungan</span>: {visit.visit_date}</div>
            <div><span>No. Antrean</span>: {visit.queue_number}</div>
          </div>
        )}

        <h3 style={{ marginBottom: 8 }}>Rincian Biaya</h3>
        <table>
          <tbody>
            <tr><td>Biaya Pendaftaran</td><td>{formatRp(invoice.registration_fee)}</td></tr>
            <tr><td>Biaya Tindakan Medis</td><td>{formatRp(invoice.procedure_fee)}</td></tr>
            <tr><td>Biaya Pemeriksaan Penunjang</td><td>{formatRp(invoice.lab_fee)}</td></tr>
            <tr><td>Biaya Obat</td><td>{formatRp(invoice.medicine_fee)}</td></tr>
            <tr className="invoice-total-row"><td><strong>Total Tagihan</strong></td><td><strong>{formatRp(invoice.total_amount)}</strong></td></tr>
            <tr><td>Sudah Dibayar</td><td>{formatRp(invoice.paid_amount)}</td></tr>
            <tr className="invoice-total-row"><td><strong>Sisa Tagihan</strong></td><td><strong>{formatRp(remaining)}</strong></td></tr>
          </tbody>
        </table>

        <h3 style={{ marginTop: 20, marginBottom: 8 }}>Riwayat Pembayaran</h3>
        {payments.length === 0 ? (
          <p style={{ color: '#888', fontSize: 14 }}>Belum ada pembayaran.</p>
        ) : (
          <table>
            <thead>
              <tr><th>No. Struk</th><th>Jumlah</th><th>Metode</th><th>Waktu</th></tr>
            </thead>
            <tbody>
              {payments.map((p) => (
                <tr key={p.id}>
                  <td>{p.receipt_number}</td>
                  <td>{formatRp(p.amount)}</td>
                  <td>{p.method === 'cash' ? 'Tunai' : 'Non-Tunai'}</td>
                  <td>{p.paid_at}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        <div className="invoice-footer">Dicetak dari SIMRS - {new Date().toLocaleString('id-ID')}</div>

        <button className="secondary no-print" onClick={() => window.print()} style={{ marginTop: 16 }}>
          Cetak Invoice
        </button>
      </div>

      {invoice.status !== 'paid' && (
        <form className="card no-print" onSubmit={handlePay}>
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
    </div>
  );
}
