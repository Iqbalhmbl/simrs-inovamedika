import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { yiiApi } from '../api/client';

export default function QueuePage() {
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [visits, setVisits] = useState([]);

  function load() {
    yiiApi.get('/visits', { params: { date } }).then((res) => setVisits(res.data.items || res.data));
  }

  useEffect(() => {
    load();
    const t = setInterval(load, 10000); // simple polling so the queue screen stays current
    return () => clearInterval(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [date]);

  return (
    <div>
      <h1>Antrean & Kunjungan</h1>
      <div className="card">
        <div className="form-row">
          <div>
            <label>Tanggal</label>
            <input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
          </div>
        </div>
        <table>
          <thead>
            <tr>
              <th>No. Antrean</th>
              <th>Pasien</th>
              <th>Poli</th>
              <th>Dokter</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {visits.map((v) => (
              <tr key={v.id}>
                <td>{v.queue_number}</td>
                <td>{v.patient?.name}</td>
                <td>{v.polyclinic?.name}</td>
                <td>{v.doctor?.name}</td>
                <td>
                  <span className={`badge ${v.status}`}>{v.status}</span>
                </td>
                <td>
                  <Link to={`/examination/${v.id}`}>Pemeriksaan</Link>{' '}
                  | <Link to={`/billing/${v.id}`}>Billing</Link>
                </td>
              </tr>
            ))}
            {visits.length === 0 && (
              <tr>
                <td colSpan={6}>Tidak ada kunjungan untuk tanggal ini.</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
