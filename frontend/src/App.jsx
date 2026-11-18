import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from './context/AuthContext';
import Layout from './components/Layout';
import LoginPage from './pages/LoginPage';
import RegistrationPage from './pages/RegistrationPage';
import QueuePage from './pages/QueuePage';
import SchedulePage from './pages/SchedulePage';
import ExaminationPage from './pages/ExaminationPage';
import PharmacyPage from './pages/PharmacyPage';
import LabPage from './pages/LabPage';
import BillingPage from './pages/BillingPage';
import DashboardPage from './pages/DashboardPage';

function PrivateRoute({ children }) {
  const { isAuthenticated } = useAuth();
  return isAuthenticated ? children : <Navigate to="/login" replace />;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        path="/*"
        element={
          <PrivateRoute>
            <Layout>
              <Routes>
                <Route path="/" element={<Navigate to="/registration" replace />} />
                <Route path="/registration" element={<RegistrationPage />} />
                <Route path="/queue" element={<QueuePage />} />
                <Route path="/schedule" element={<SchedulePage />} />
                <Route path="/examination/:visitId" element={<ExaminationPage />} />
                <Route path="/pharmacy" element={<PharmacyPage />} />
                <Route path="/lab" element={<LabPage />} />
                <Route path="/billing/:visitId" element={<BillingPage />} />
                <Route path="/dashboard" element={<DashboardPage />} />
              </Routes>
            </Layout>
          </PrivateRoute>
        }
      />
    </Routes>
  );
}
