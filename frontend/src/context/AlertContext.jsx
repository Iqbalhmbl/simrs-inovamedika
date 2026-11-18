import { createContext, useContext, useState, useCallback } from 'react';
import Modal from '../components/Modal';

const AlertContext = createContext(null);

function formatDetails(details) {
  if (!details || typeof details !== 'object') return null;
  return Object.entries(details)
    .map(([field, msgs]) => `${field}: ${(Array.isArray(msgs) ? msgs : [msgs]).join(', ')}`)
    .join('\n');
}

/**
 * Every "done/selesai/sukses/error" message in the app goes through this
 * modal instead of an inline <p>, so feedback is always something the user
 * must actively acknowledge rather than text that can be missed or that
 * disappears on the next re-render.
 */
export function AlertProvider({ children }) {
  const [alert, setAlert] = useState(null); // { type: 'success'|'error', title, message }

  const showAlert = useCallback((message, type = 'success', details = null) => {
    const extra = formatDetails(details);
    setAlert({
      type,
      title: type === 'error' ? 'Terjadi Kesalahan' : 'Berhasil',
      message: extra ? `${message}\n${extra}` : message,
    });
  }, []);

  const success = useCallback((message) => showAlert(message, 'success'), [showAlert]);

  // Accepts either a plain string or the error object thrown by api/client.js
  // (which already carries a clean .message and optional .details).
  const error = useCallback(
    (err) => {
      const message = typeof err === 'string' ? err : err?.message || 'Terjadi kesalahan.';
      const details = typeof err === 'object' ? err?.details : null;
      showAlert(message, 'error', details);
    },
    [showAlert]
  );

  const close = useCallback(() => setAlert(null), []);

  return (
    <AlertContext.Provider value={{ showAlert, success, error }}>
      {children}
      {alert && (
        <Modal title={alert.title} onClose={close}>
          <p
            style={{
              whiteSpace: 'pre-line',
              color: alert.type === 'error' ? '#b00020' : '#198754',
              margin: '0 0 16px',
            }}
          >
            {alert.message}
          </p>
          <div style={{ textAlign: 'right' }}>
            <button type="button" onClick={close}>
              OK
            </button>
          </div>
        </Modal>
      )}
    </AlertContext.Provider>
  );
}

export function useAlert() {
  const ctx = useContext(AlertContext);
  if (!ctx) throw new Error('useAlert must be used within an AlertProvider');
  return ctx;
}
