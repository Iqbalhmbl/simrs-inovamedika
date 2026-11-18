import axios from 'axios';

const TOKEN_KEY = 'simrs_token';

export const yiiApi = axios.create({
  baseURL: import.meta.env.VITE_YII_API_URL || 'http://localhost:8080/api',
});

export const nodeApi = axios.create({
  baseURL: import.meta.env.VITE_NODE_API_URL || 'http://localhost:4000/api',
});

function attachAuth(instance) {
  instance.interceptors.request.use((config) => {
    const token = localStorage.getItem(TOKEN_KEY);
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  });

  // Both backends use the same {success, data} / {success, error} envelope
  // (see docs/architecture.md), so unwrap it here once for every caller.
  instance.interceptors.response.use(
    (response) => response.data,
    (error) => {
      const payload = error.response?.data;
      const message = payload?.error?.message || error.message || 'Terjadi kesalahan.';
      return Promise.reject({ ...error, message, details: payload?.error?.details });
    }
  );
}

attachAuth(yiiApi);
attachAuth(nodeApi);

export function setToken(token) {
  localStorage.setItem(TOKEN_KEY, token);
}

export function getToken() {
  return localStorage.getItem(TOKEN_KEY);
}

export function clearToken() {
  localStorage.removeItem(TOKEN_KEY);
}
