import { useState, useEffect } from 'react';
import { yiiApi } from '../api/client';

export default function PharmacyPage() {
  const [prescriptions, setPrescriptions] = useState([]);
  const [error, setError] = useState(null);
  const [message, setMessage] = useState(null);

  function load() {
    yiiApi.get('/prescriptions', { params: { status: 'pending' } }).then((res) => setPrescriptions(res.data.items || res.data));
  }

  useEffect(load, []);

  async function dispense(id) {
    setError(null);
    setMessage(null);
    try {
      await yiiApi.post(`/prescriptions/${id}/dispense`);
      setMessage(`Resep #${id} berhasil diproses dan obat diserahkan.`);
      load();
    } catch (err) {
      // e.g. "Stok obat tidak mencukupi..." from PharmacyService (problem D2)
      setError(err.message);
    }
  }

  return (
    <div>
      <h1>Farmasi - Resep Menunggu</h1>
      {message && <p style={{ color: '#198754' }}>{message}</p>}
      {error && <p className="error">{error}</p>}
      <div className="card">
        <table>
          <thead>
            <tr>
              <th>ID Resep</th>
              <th>Kunjungan</th>
              <th>Item</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {prescriptions.map((p) => (
              <tr key={p.id}>
                <td>{p.id}</td>
                <td>{p.visit_id}</td>
                <td>
                  {(p.items || []).map((i) => (
                    <div key={i.id}>
                      {i.medicine?.name} x{i.qty} ({i.dose}, {i.frequency})
                    </div>
                  ))}
                </td>
                <td>
                  <button onClick={() => dispense(p.id)}>Proses & Serahkan</button>
                </td>
              </tr>
            ))}
            {prescriptions.length === 0 && (
              <tr>
                <td colSpan={4}>Tidak ada resep menunggu.</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
