import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { yiiApi } from '../api/client';

export default function ExaminationPage() {
  const { visitId } = useParams();
  const [record, setRecord] = useState({
    anamnesis: '', complaint: '', physical_exam: '',
    primary_diagnosis: '', secondary_diagnosis: '',
    vital_signs: { td: '', nadi: '', suhu: '', rr: '' },
  });
  const [procedures, setProcedures] = useState([]);
  const [medicines, setMedicines] = useState([]);
  const [visitProcedures, setVisitProcedures] = useState([]);
  const [prescriptionItems, setPrescriptionItems] = useState([{ medicine_id: '', dose: '', frequency: '', qty: 1 }]);
  const [message, setMessage] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    yiiApi.get('/medical-procedures').then((res) => setProcedures(res.data.items || res.data));
    yiiApi.get('/medicines').then((res) => setMedicines(res.data.items || res.data));
    yiiApi
      .get(`/medical-records/by-visit/${visitId}`)
      .then((res) => setRecord({ ...record, ...res.data, vital_signs: res.data.vital_signs || record.vital_signs }))
      .catch(() => {}); // no record yet is fine
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visitId]);

  async function saveRecord(e) {
    e.preventDefault();
    setError(null);
    try {
      const res = await yiiApi.post('/medical-records', { visit_id: visitId, ...record });
      setMessage('Rekam medis tersimpan.');
    } catch (err) {
      setError(err.message);
    }
  }

  async function addProcedure(procedureId) {
    try {
      const res = await yiiApi.post('/visit-procedures', { visit_id: visitId, medical_procedure_id: procedureId, qty: 1 });
      setVisitProcedures([...visitProcedures, res.data]);
    } catch (err) {
      setError(err.message);
    }
  }

  async function submitPrescription(e) {
    e.preventDefault();
    setError(null);
    try {
      await yiiApi.post('/prescriptions', {
        visit_id: visitId,
        items: prescriptionItems.filter((i) => i.medicine_id),
      });
      setMessage('Resep berhasil dibuat.');
      setPrescriptionItems([{ medicine_id: '', dose: '', frequency: '', qty: 1 }]);
    } catch (err) {
      setError(err.message);
    }
  }

  async function requestLab(testName) {
    try {
      await yiiApi.post('/lab-requests', { visit_id: visitId, test_name: testName });
      setMessage(`Permintaan lab "${testName}" dikirim.`);
    } catch (err) {
      setError(err.message);
    }
  }

  return (
    <div>
      <h1>Pemeriksaan - Kunjungan #{visitId}</h1>
      {message && <p style={{ color: '#198754' }}>{message}</p>}
      {error && <p className="error">{error}</p>}

      <form className="card" onSubmit={saveRecord}>
        <h3>Rekam Medis Elektronik</h3>
        <div className="form-row">
          <div>
            <label>Anamnesis / Keluhan</label>
            <textarea
              value={record.anamnesis}
              onChange={(e) => setRecord({ ...record, anamnesis: e.target.value })}
            />
          </div>
        </div>
        <div className="form-row">
          <div>
            <label>Tekanan Darah</label>
            <input
              value={record.vital_signs.td}
              onChange={(e) => setRecord({ ...record, vital_signs: { ...record.vital_signs, td: e.target.value } })}
              placeholder="120/80"
            />
          </div>
          <div>
            <label>Nadi</label>
            <input
              value={record.vital_signs.nadi}
              onChange={(e) => setRecord({ ...record, vital_signs: { ...record.vital_signs, nadi: e.target.value } })}
            />
          </div>
          <div>
            <label>Suhu</label>
            <input
              value={record.vital_signs.suhu}
              onChange={(e) => setRecord({ ...record, vital_signs: { ...record.vital_signs, suhu: e.target.value } })}
            />
          </div>
          <div>
            <label>RR</label>
            <input
              value={record.vital_signs.rr}
              onChange={(e) => setRecord({ ...record, vital_signs: { ...record.vital_signs, rr: e.target.value } })}
            />
          </div>
        </div>
        <div className="form-row">
          <div>
            <label>Pemeriksaan Fisik</label>
            <textarea
              value={record.physical_exam}
              onChange={(e) => setRecord({ ...record, physical_exam: e.target.value })}
            />
          </div>
        </div>
        <div className="form-row">
          <div>
            <label>Diagnosis Utama</label>
            <input
              value={record.primary_diagnosis}
              onChange={(e) => setRecord({ ...record, primary_diagnosis: e.target.value })}
            />
          </div>
          <div>
            <label>Diagnosis Sekunder</label>
            <input
              value={record.secondary_diagnosis}
              onChange={(e) => setRecord({ ...record, secondary_diagnosis: e.target.value })}
            />
          </div>
        </div>
        <button type="submit">Simpan Rekam Medis</button>
      </form>

      <div className="card">
        <h3>Tindakan Medis</h3>
        <div className="form-row">
          <div>
            <select onChange={(e) => e.target.value && addProcedure(e.target.value)} defaultValue="">
              <option value="" disabled>
                -- Tambah Tindakan --
              </option>
              {procedures.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name} (Rp {Number(p.tariff).toLocaleString('id-ID')})
                </option>
              ))}
            </select>
          </div>
        </div>
        <ul>
          {visitProcedures.map((vp) => (
            <li key={vp.id}>Tindakan #{vp.medical_procedure_id} x{vp.qty}</li>
          ))}
        </ul>
      </div>

      <form className="card" onSubmit={submitPrescription}>
        <h3>Resep Obat</h3>
        {prescriptionItems.map((item, idx) => (
          <div className="form-row" key={idx}>
            <div>
              <label>Obat</label>
              <select
                value={item.medicine_id}
                onChange={(e) => {
                  const next = [...prescriptionItems];
                  next[idx].medicine_id = e.target.value;
                  setPrescriptionItems(next);
                }}
              >
                <option value="">-- Pilih --</option>
                {medicines.map((m) => (
                  <option key={m.id} value={m.id}>
                    {m.name} (stok: {m.stock})
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label>Dosis</label>
              <input
                value={item.dose}
                onChange={(e) => {
                  const next = [...prescriptionItems];
                  next[idx].dose = e.target.value;
                  setPrescriptionItems(next);
                }}
              />
            </div>
            <div>
              <label>Frekuensi</label>
              <input
                value={item.frequency}
                onChange={(e) => {
                  const next = [...prescriptionItems];
                  next[idx].frequency = e.target.value;
                  setPrescriptionItems(next);
                }}
                placeholder="3x sehari"
              />
            </div>
            <div>
              <label>Jumlah</label>
              <input
                type="number"
                min="1"
                value={item.qty}
                onChange={(e) => {
                  const next = [...prescriptionItems];
                  next[idx].qty = Number(e.target.value);
                  setPrescriptionItems(next);
                }}
              />
            </div>
          </div>
        ))}
        <button
          type="button"
          className="secondary"
          onClick={() => setPrescriptionItems([...prescriptionItems, { medicine_id: '', dose: '', frequency: '', qty: 1 }])}
        >
          + Tambah Obat
        </button>{' '}
        <button type="submit">Buat Resep</button>
      </form>

      <div className="card">
        <h3>Permintaan Pemeriksaan Penunjang</h3>
        <div className="form-row">
          <div>
            <button type="button" onClick={() => requestLab('Darah Lengkap')}>
              Minta Lab: Darah Lengkap
            </button>
          </div>
          <div>
            <button type="button" onClick={() => requestLab('Urine Lengkap')}>
              Minta Lab: Urine Lengkap
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
