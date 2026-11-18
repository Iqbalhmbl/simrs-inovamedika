import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useAlert } from '../context/AlertContext';

export default function LoginPage() {
  const { login } = useAuth();
  const { error: alertError } = useAlert();
  const navigate = useNavigate();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setLoading(true);
    try {
      await login(username, password);
      navigate('/');
    } catch (err) {
      alertError(err);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="login-page">
      <form className="card login-box" onSubmit={handleSubmit}>
        <h2>SIMRS - Masuk</h2>
        <div className="form-row">
          <div>
            <label>Username</label>
            <input value={username} onChange={(e) => setUsername(e.target.value)} required />
          </div>
        </div>
        <div className="form-row">
          <div>
            <label>Password</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </div>
        </div>
        <button type="submit" disabled={loading} style={{ width: '100%' }}>
          {loading ? 'Memproses...' : 'Masuk'}
        </button>
        <p style={{ fontSize: 12, color: '#777', marginTop: 12 }}>
          Dummy users: admin / pendaftaran1 / dokter1 / farmasi1 / lab1 / kasir1 — password:
          password123
        </p>
      </form>
    </div>
  );
}
