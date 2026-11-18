import { useState, useEffect } from 'react';
import { yiiApi } from '../api/client';
import { useAlert } from '../context/AlertContext';

const DAY_NAMES = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

export default function SchedulePage() {
  const { error: alertError } = useAlert();
  const [polyclinics, setPolyclinics] = useState([]);
  const [schedules, setSchedules] = useState([]);
  const [polyclinicId, setPolyclinicId] = useState('');

  useEffect(() => {
    yiiApi.get('/polyclinics').then((res) => setPolyclinics(res.data.items || res.data));
  }, []);

  useEffect(() => {
    const params = {};
    if (polyclinicId) params.polyclinic_id = polyclinicId;
    yiiApi
      .get('/doctor-schedules', { params })
      .then((res) => setSchedules(res.data.items || res.data))
      .catch((err) => alertError(err));
  }, [polyclinicId]);

  // Group by day of week (0=Sunday..6=Saturday), each day's rows sorted by start time.
  const byDay = DAY_NAMES.map((_, dow) =>
    schedules
      .filter((s) => s.day_of_week === dow)
      .sort((a, b) => a.start_time.localeCompare(b.start_time))
  );

  return (
    <div>
      <h1>Jadwal Dokter</h1>
      <div className="card">
        <div className="form-row">
          <div>
            <label>Poli</label>
            <select value={polyclinicId} onChange={(e) => setPolyclinicId(e.target.value)}>
              <option value="">-- Semua Poli --</option>
              {polyclinics.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </div>
        </div>

        {DAY_NAMES.map((dayName, dow) => (
          <div key={dow} style={{ marginTop: 20 }}>
            <h3>{dayName}</h3>
            {byDay[dow].length === 0 ? (
              <p style={{ color: '#888', fontSize: 14 }}>Tidak ada jadwal.</p>
            ) : (
              <table>
                <thead>
                  <tr>
                    <th>Jam</th>
                    <th>Dokter</th>
                    <th>Spesialisasi</th>
                    <th>Poli</th>
                    <th>Kuota</th>
                  </tr>
                </thead>
                <tbody>
                  {byDay[dow].map((s) => (
                    <tr key={s.id}>
                      <td>{s.start_time} - {s.end_time}</td>
                      <td>{s.doctor?.name}</td>
                      <td>{s.doctor?.specialization || '-'}</td>
                      <td>{s.polyclinic?.name}</td>
                      <td>{s.quota}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
