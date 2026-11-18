import { useState, useEffect } from 'react';
import { yiiApi } from '../api/client';
import Modal from '../components/Modal';

const EMPTY_PATIENT = { name: '', birth_date: '', gender: 'L', nik: '', phone: '' };

export default function RegistrationPage() {
  const [search, setSearch] = useState('');
  const [patients, setPatients] = useState([]);
  const [selectedPatient, setSelectedPatient] = useState(null);
  const [showNewPatientModal, setShowNewPatientModal] = useState(false);
  const [newPatient, setNewPatient] = useState(EMPTY_PATIENT);
  const [polyclinics, setPolyclinics] = useState([]);
  const [schedules, setSchedules] = useState([]);
  const [form, setForm] = useState({ polyclinic_id: '', doctor_id: '', visit_date: today() });
  const [message, setMessage] = useState(null);
  const [error, setError] = useState(null);

  function today() {
    return new Date().toISOString().slice(0, 10);
  }

  useEffect(() => {
    yiiApi.get('/polyclinics').then((res) => setPolyclinics(res.data.items || res.data));
  }, []);

  useEffect(() => {
    if (!form.polyclinic_id) return;
    const dow = new Date(form.visit_date).getDay();
    yiiApi
      .get('/doctor-schedules', { params: { polyclinic_id: form.polyclinic_id, day_of_week: dow } })
      .then((res) => setSchedules(res.data.items || res.data));
  }, [form.polyclinic_id, form.visit_date]);

  async function handleSearch(e) {
    e.preventDefault();
    const res = await yiiApi.get('/patients', { params: { search } });
    setPatients(res.data.items || res.data);
  }

  async function handleCreatePatient(e) {
    e.preventDefault();
    setError(null);
    try {
      const res = await yiiApi.post('/patients', newPatient);
      setSelectedPatient(res.data);
      setMessage(`Pasien baru terdaftar dengan No. RM: ${res.data.mrn}`);
      setShowNewPatientModal(false);
      setNewPatient(EMPTY_PATIENT);
    } catch (err) {
      setError(err.message);
    }
  }

  function closeNewPatientModal() {
    setShowNewPatientModal(false);
    setNewPatient(EMPTY_PATIENT);
    setError(null);
  }

  async function handleRegisterVisit(e) {
    e.preventDefault();
    setError(null);
    setMessage(null);
    try {
      const res = await yiiApi.post('/visits/register', {
        patient_id: selectedPatient.id,
        doctor_id: form.doctor_id,
        polyclinic_id: form.polyclinic_id,
        visit_date: form.visit_date,
      });
      setMessage(`Berhasil! Nomor antrean: ${res.data.queue_number}`);
      setSelectedPatient(null);
    } catch (err) {
      setError(err.message);
    }
  }

  return (
    <div>
      <h1>Pendaftaran Pasien</h1>

      {!selectedPatient && (
        <div className="card">
          <h3>1. Cari Pasien (No. RM / Nama / NIK)</h3>
          <form onSubmit={handleSearch} className="form-row">
            <div>
              <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Cari..." />
            </div>
            <div style={{ flex: 'none' }}>
              <button type="submit">Cari</button>
            </div>
          </form>
          <table>
            <thead>
              <tr>
                <th>No. RM</th>
                <th>Nama</th>
                <th>Tgl Lahir</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {patients.map((p) => (
                <tr key={p.id}>
                  <td>{p.mrn}</td>
                  <td>{p.name}</td>
                  <td>{p.birth_date}</td>
                  <td>
                    <button onClick={() => setSelectedPatient(p)}>Pilih</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          <div style={{ marginTop: 20 }}>
            <button type="button" onClick={() => setShowNewPatientModal(true)}>
              + Daftarkan Pasien Baru
            </button>
          </div>
        </div>
      )}

      {showNewPatientModal && (
        <Modal title="Daftarkan Pasien Baru" onClose={closeNewPatientModal}>
          <form onSubmit={handleCreatePatient}>
            <div className="form-row">
              <div>
                <label>Nama</label>
                <input
                  required
                  value={newPatient.name}
                  onChange={(e) => setNewPatient({ ...newPatient, name: e.target.value })}
                />
              </div>
              <div>
                <label>Tanggal Lahir</label>
                <input
                  required
                  type="date"
                  value={newPatient.birth_date}
                  onChange={(e) => setNewPatient({ ...newPatient, birth_date: e.target.value })}
                />
              </div>
            </div>
            <div className="form-row">
              <div>
                <label>Jenis Kelamin</label>
                <select
                  value={newPatient.gender}
                  onChange={(e) => setNewPatient({ ...newPatient, gender: e.target.value })}
                >
                  <option value="L">Laki-laki</option>
                  <option value="P">Perempuan</option>
                </select>
              </div>
              <div>
                <label>NIK</label>
                <input
                  value={newPatient.nik}
                  onChange={(e) => setNewPatient({ ...newPatient, nik: e.target.value })}
                />
              </div>
              <div>
                <label>No. Telepon</label>
                <input
                  value={newPatient.phone}
                  onChange={(e) => setNewPatient({ ...newPatient, phone: e.target.value })}
                />
              </div>
            </div>
            {error && <p className="error">{error}</p>}
            <button type="submit">Simpan Pasien</button>{' '}
            <button type="button" className="secondary" onClick={closeNewPatientModal}>
              Batal
            </button>
          </form>
        </Modal>
      )}

      {selectedPatient && (
        <div className="card">
          <h3>2. Pendaftaran Kunjungan untuk {selectedPatient.name} ({selectedPatient.mrn})</h3>
          <form onSubmit={handleRegisterVisit}>
            <div className="form-row">
              <div>
                <label>Tanggal Kunjungan</label>
                <input
                  type="date"
                  value={form.visit_date}
                  onChange={(e) => setForm({ ...form, visit_date: e.target.value })}
                />
              </div>
              <div>
                <label>Poli</label>
                <select
                  required
                  value={form.polyclinic_id}
                  onChange={(e) => setForm({ ...form, polyclinic_id: e.target.value })}
                >
                  <option value="">-- Pilih Poli --</option>
                  {polyclinics.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label>Dokter (sesuai jadwal)</label>
                <select
                  required
                  value={form.doctor_id}
                  onChange={(e) => setForm({ ...form, doctor_id: e.target.value })}
                >
                  <option value="">-- Pilih Dokter --</option>
                  {schedules.map((s) => (
                    <option key={s.id} value={s.doctor_id}>
                      {s.doctor?.name} ({s.start_time}-{s.end_time})
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <button type="submit">Daftarkan Kunjungan</button>{' '}
            <button type="button" className="secondary" onClick={() => setSelectedPatient(null)}>
              Batal
            </button>
          </form>
        </div>
      )}

      {message && <p className="card" style={{ color: '#198754' }}>{message}</p>}
      {error && <p className="error">{error}</p>}
    </div>
  );
}
