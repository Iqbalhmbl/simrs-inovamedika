import { createContext, useContext, useState, useCallback } from 'react';
import { yiiApi, setToken, clearToken, getToken } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    const raw = localStorage.getItem('simrs_user');
    return raw ? JSON.parse(raw) : null;
  });

  const login = useCallback(async (username, password) => {
    const res = await yiiApi.post('/auth/login', { username, password });
    setToken(res.data.token);
    localStorage.setItem('simrs_user', JSON.stringify(res.data.user));
    setUser(res.data.user);
    return res.data.user;
  }, []);

  const logout = useCallback(() => {
    clearToken();
    localStorage.removeItem('simrs_user');
    setUser(null);
  }, []);

  return (
    <AuthContext.Provider value={{ user, login, logout, isAuthenticated: !!getToken() }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
