import { useState, useEffect } from 'react';
import { nodeApi } from '../api/client';

export default function DashboardPage() {
  const [from, setFrom] = useState(new Date().toISOString().slice(0, 10));
  const [to, setTo] = useState(new Date().toISOString().slice(0, 10));
  const [summary, setSummary] = useState(null);
  const [byPolyclinic, setByPolyclinic] = useState([]);
  const [revenue, setRevenue] = useState(null);
  const [medicineUsage, setMedicineUsage] = useState([]);
  const [paymentStatus, setPaymentStatus] = useState([]);

  function load() {
    const params = { from, to };
    nodeApi.get('/dashboard/visits-summary', { params }).then((res) => setSummary(res.data));
    nodeApi.get('/dashboard/visits-by-polyclinic', { params }).then((res) => setByPolyclinic(res.data.items));
    nodeApi.get('/dashboard/revenue-summary', { params }).then((res) => setRevenue(res.data));
    nodeApi.get('/dashboard/medicine-usage', { params }).then((res) => setMedicineUsage(res.data.items));
    nodeApi.get('/dashboard/payment-status', { params }).then((res) => setPaymentStatus(res.data.items));
  }

  useEffect(load, [from, to]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div>
      <h1>Dashboard Operasional</h1>
      <div className="card form-row">
        <div>
          <label>Dari</label>
          <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
        </div>
        <div>
          <label>Sampai</label>
          <input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
        </div>
      </div>

      {summary && (
        <div className="card">
          <h3>Ringkasan Kunjungan</h3>
          <p>
            Total: {summary.total_visits} | Menunggu: {summary.waiting} | Selesai: {summary.completed} | Batal:{' '}
            {summary.cancelled}
          </p>
        </div>
      )}

      <div className="card">
        <h3>Kunjungan per Poli</h3>
        <table>
          <thead><tr><th>Poli</th><th>Jumlah</th></tr></thead>
          <tbody>
            {byPolyclinic.map((r) => (
              <tr key={r.polyclinic}><td>{r.polyclinic}</td><td>{r.total}</td></tr>
            ))}
          </tbody>
        </table>
      </div>

      {revenue && (
        <div className="card">
          <h3>Ringkasan Pendapatan</h3>
          <p>Terkumpul: Rp {Number(revenue.total_collected).toLocaleString('id-ID')}</p>
          <p>Belum Lunas: Rp {Number(revenue.outstanding).toLocaleString('id-ID')}</p>
        </div>
      )}

      <div className="card">
        <h3>Rekap Penggunaan Obat</h3>
        <table>
          <thead><tr><th>Obat</th><th>Jumlah Terpakai</th></tr></thead>
          <tbody>
            {medicineUsage.map((r) => (
              <tr key={r.medicine}><td>{r.medicine}</td><td>{r.total_dispensed} {r.unit}</td></tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="card">
        <h3>Status Pembayaran</h3>
        <table>
          <thead><tr><th>Status</th><th>Jumlah</th></tr></thead>
          <tbody>
            {paymentStatus.map((r) => (
              <tr key={r.status}><td>{r.status}</td><td>{r.total}</td></tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
