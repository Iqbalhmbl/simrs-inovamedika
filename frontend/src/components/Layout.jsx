import { NavLink } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

/**
 * Nav items a role can't use are simply hidden here; the real enforcement
 * is server-side (RbacFilter on Yii2, requireRole on Node) - this is only
 * about not showing a dead end.
 */
const NAV_BY_ROLE = {
  admin: ['registration', 'queue', 'pharmacy', 'lab', 'dashboard'],
  petugas_pendaftaran: ['registration', 'queue'],
  dokter: ['queue', 'dashboard'],
  petugas_farmasi: ['pharmacy'],
  petugas_laboratorium: ['lab'],
  kasir: ['queue', 'dashboard'],
};

const LINKS = {
  registration: { to: '/registration', label: 'Pendaftaran' },
  queue: { to: '/queue', label: 'Antrean & Kunjungan' },
  pharmacy: { to: '/pharmacy', label: 'Farmasi' },
  lab: { to: '/lab', label: 'Laboratorium' },
  dashboard: { to: '/dashboard', label: 'Dashboard' },
};

export default function Layout({ children }) {
  const { user, logout } = useAuth();
  const allowed = NAV_BY_ROLE[user?.role] || [];

  return (
    <div className="layout">
      <aside className="sidebar">
        <h2>SIMRS</h2>
        {allowed.map((key) => (
          <NavLink key={key} to={LINKS[key].to}>
            {LINKS[key].label}
          </NavLink>
        ))}
        <a onClick={logout} style={{ cursor: 'pointer', marginTop: 20 }}>
          Logout ({user?.full_name})
        </a>
      </aside>
      <main className="content">{children}</main>
    </div>
  );
}
