import { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { yiiApi } from '../api/client';
import { useAlert } from '../context/AlertContext';

function formatRp(value) {
  return `Rp ${Number(value || 0).toLocaleString('id-ID')}`;
}

export default function ExaminationPage() {
  const { visitId } = useParams();
  const { success, error: alertError } = useAlert();
  const [visit, setVisit] = useState(null);
  const [record, setRecord] = useState({
    anamnesis: '', complaint: '', physical_exam: '',
    primary_diagnosis: '', secondary_diagnosis: '',
    vital_signs: { td: '', nadi: '', suhu: '', rr: '' },
  });
  const [procedures, setProcedures] = useState([]);
  const [medicines, setMedicines] = useState([]);
  const [visitProcedures, setVisitProcedures] = useState([]);
  const [selectedProcedureId, setSelectedProcedureId] = useState('');
  const [prescriptions, setPrescriptions] = useState([]);
  const [prescriptionItems, setPrescriptionItems] = useState([{ medicine_id: '', dose: '', frequency: '', qty: 1 }]);

  const loadVisitProcedures = useCallback(() => {
    yiiApi
      .get('/visit-procedures', { params: { visit_id: visitId } })
      .then((res) => setVisitProcedures(res.data.items || res.data));
  }, [visitId]);

  const loadPrescriptions = useCallback(() => {
    yiiApi
      .get('/prescriptions', { params: { visit_id: visitId } })
      .then((res) => setPrescriptions(res.data.items || res.data));
  }, [visitId]);

  useEffect(() => {
    yiiApi.get(`/visits/${visitId}`).then((res) => setVisit(res.data));
    yiiApi.get('/medical-procedures').then((res) => setProcedures(res.data.items || res.data));
    yiiApi.get('/medicines').then((res) => setMedicines(res.data.items || res.data));
    yiiApi
      .get(`/medical-records/by-visit/${visitId}`)
      .then((res) => setRecord({ ...record, ...res.data, vital_signs: res.data.vital_signs || record.vital_signs }))
      .catch(() => {}); // no record yet is fine
    loadVisitProcedures();
    loadPrescriptions();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visitId]);

  async function saveRecord(e) {
    e.preventDefault();
    try {
      await yiiApi.post('/medical-records', { visit_id: visitId, ...record });
      success('Rekam medis tersimpan.');
    } catch (err) {
      alertError(err);
    }
  }

  async function addProcedure() {
    if (!selectedProcedureId) {
      alertError('Pilih tindakan terlebih dahulu.');
      return;
    }
    try {
      await yiiApi.post('/visit-procedures', {
        visit_id: visitId,
        medical_procedure_id: selectedProcedureId,
        qty: 1,
      });
      setSelectedProcedureId('');
      loadVisitProcedures();
      success('Tindakan medis berhasil ditambahkan.');
    } catch (err) {
      alertError(err);
    }
  }

  async function submitPrescription(e) {
    e.preventDefault();
    const items = prescriptionItems.filter((i) => i.medicine_id);
    if (items.length === 0) {
      alertError('Pilih minimal satu obat sebelum membuat resep.');
      return;
    }
    if (!visit?.doctor_id) {
      alertError('Dokter untuk kunjungan ini tidak ditemukan. Muat ulang halaman.');
      return;
    }
    try {
      await yiiApi.post('/prescriptions', {
        visit_id: visitId,
        doctor_id: visit.doctor_id,
        items,
      });
      setPrescriptionItems([{ medicine_id: '', dose: '', frequency: '', qty: 1 }]);
      loadPrescriptions();
      success('Resep berhasil dibuat.');
    } catch (err) {
      alertError(err);
    }
  }

  async function requestLab(testName) {
    try {
      await yiiApi.post('/lab-requests', { visit_id: visitId, test_name: testName });
      success(`Permintaan lab "${testName}" dikirim.`);
    } catch (err) {
      alertError(err);
    }
  }

  const procedureTotal = visitProcedures.reduce(
    (sum, vp) => sum + Number(vp.tariff_at_time || 0) * Number(vp.qty || 1),
    0
  );

  return (
    <div>
      <h1>Pemeriksaan - Kunjungan #{visitId}</h1>
      {visit && (
        <p style={{ color: '#555', marginTop: -8 }}>
          {visit.patient?.name} ({visit.patient?.mrn}) &middot; {visit.polyclinic?.name} &middot; dr. {visit.doctor?.name}
        </p>
      )}

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
            <label>Pilih Tindakan</label>
            <select value={selectedProcedureId} onChange={(e) => setSelectedProcedureId(e.target.value)}>
              <option value="">-- Pilih Tindakan --</option>
              {procedures.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name} ({formatRp(p.tariff)})
                </option>
              ))}
            </select>
          </div>
          <div style={{ flex: 'none', alignSelf: 'flex-end' }}>
            <button type="button" onClick={addProcedure}>
              + Tambah Tindakan
            </button>
          </div>
        </div>

        <table>
          <thead>
            <tr>
              <th>Tindakan</th>
              <th>Qty</th>
              <th>Tarif</th>
              <th>Subtotal</th>
            </tr>
          </thead>
          <tbody>
            {visitProcedures.map((vp) => (
              <tr key={vp.id}>
                <td>{vp.medical_procedure?.name || `#${vp.medical_procedure_id}`}</td>
                <td>{vp.qty}</td>
                <td>{formatRp(vp.tariff_at_time)}</td>
                <td>{formatRp(Number(vp.tariff_at_time) * Number(vp.qty))}</td>
              </tr>
            ))}
            {visitProcedures.length === 0 && (
              <tr>
                <td colSpan={4}>Belum ada tindakan medis.</td>
              </tr>
            )}
          </tbody>
          {visitProcedures.length > 0 && (
            <tfoot>
              <tr>
                <td colSpan={3}><strong>Total Tindakan</strong></td>
                <td><strong>{formatRp(procedureTotal)}</strong></td>
              </tr>
            </tfoot>
          )}
        </table>
      </div>

      <div className="card">
        <h3>Resep Obat Tersimpan</h3>
        {prescriptions.length === 0 && <p style={{ color: '#888', fontSize: 14 }}>Belum ada resep untuk kunjungan ini.</p>}
        {prescriptions.map((p) => (
          <div key={p.id} style={{ marginBottom: 12 }}>
            <strong>Resep #{p.id}</strong>{' '}
            <span className={`badge ${p.status === 'dispensed' ? 'completed' : 'waiting'}`}>{p.status}</span>
            <table>
              <thead>
                <tr>
                  <th>Obat</th>
                  <th>Dosis</th>
                  <th>Frekuensi</th>
                  <th>Qty</th>
                </tr>
              </thead>
              <tbody>
                {(p.items || []).map((i) => (
                  <tr key={i.id}>
                    <td>{i.medicine?.name || `#${i.medicine_id}`}</td>
                    <td>{i.dose}</td>
                    <td>{i.frequency}</td>
                    <td>{i.qty}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ))}
      </div>

      <form className="card" onSubmit={submitPrescription}>
        <h3>Buat Resep Baru</h3>
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
