import { useState, useEffect } from 'react';
import { yiiApi } from '../api/client';

export default function LabPage() {
  const [requests, setRequests] = useState([]);
  const [resultText, setResultText] = useState({});
  const [error, setError] = useState(null);

  function load() {
    yiiApi.get('/lab-requests', { params: { status: 'requested' } }).then((res) => setRequests(res.data.items || res.data));
  }

  useEffect(load, []);

  async function submitResult(requestId) {
    setError(null);
    try {
      await yiiApi.post('/lab-results', { lab_request_id: requestId, result_text: resultText[requestId] || '' });
      load();
    } catch (err) {
      setError(err.message);
    }
  }

  return (
    <div>
      <h1>Laboratorium - Permintaan Pemeriksaan</h1>
      {error && <p className="error">{error}</p>}
      <div className="card">
        <table>
          <thead>
            <tr>
              <th>Kunjungan</th>
              <th>Jenis Pemeriksaan</th>
              <th>Hasil</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {requests.map((r) => (
              <tr key={r.id}>
                <td>{r.visit_id}</td>
                <td>{r.test_name}</td>
                <td>
                  <input
                    value={resultText[r.id] || ''}
                    onChange={(e) => setResultText({ ...resultText, [r.id]: e.target.value })}
                    placeholder="Input hasil..."
                  />
                </td>
                <td>
                  <button onClick={() => submitResult(r.id)}>Simpan Hasil</button>
                </td>
              </tr>
            ))}
            {requests.length === 0 && (
              <tr>
                <td colSpan={4}>Tidak ada permintaan pemeriksaan.</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
