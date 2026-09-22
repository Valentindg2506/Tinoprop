/**
 * CAS — Capa de datos API
 * Habla con /api/*.php, gestiona token, caché offline y cola de pendientes.
 */
const CasAPI = (function () {
    'use strict';

    const TOKEN_KEY  = 'cas_token';
    const QUEUE_KEY  = 'cas_queue';
    const ONLINE_CBS = [];
    let   _online    = navigator.onLine;

    // ── Token ──────────────────────────────────────────────────
    function getToken()    { return localStorage.getItem(TOKEN_KEY); }
    function setToken(t)   { localStorage.setItem(TOKEN_KEY, t); }
    function clearToken()  { localStorage.removeItem(TOKEN_KEY); }
    function isLoggedIn()  { return !!getToken(); }

    // ── Red ────────────────────────────────────────────────────
    function isOnline() { return _online; }

    window.addEventListener('online',  () => {
        _online = true;
        _drainQueue();
        ONLINE_CBS.forEach(cb => cb());
    });
    window.addEventListener('offline', () => { _online = false; });

    function onReconnect(cb) { ONLINE_CBS.push(cb); }

    // ── Cola offline ──────────────────────────────────────────
    function _getQueue() {
        try { return JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]'); } catch(e) { return []; }
    }
    function _saveQueue(q) {
        try { localStorage.setItem(QUEUE_KEY, JSON.stringify(q)); } catch(e) {}
    }
    function _enqueue(op) {
        const q = _getQueue().filter(x => !(x.resource === op.resource && x.id == op.id));
        q.push(op);
        _saveQueue(q);
    }
    function getPendingCount() { return _getQueue().length; }

    async function _drainQueue() {
        const q = _getQueue();
        if (!q.length) return;
        const failed = [];
        for (const op of q) {
            try {
                if (op.method === 'POST') {
                    await _fetch(op.resource + '.php', { method: 'POST', body: JSON.stringify(op.data) });
                } else if (op.method === 'DELETE') {
                    await _fetch(op.resource + '.php?id=' + encodeURIComponent(op.id), { method: 'DELETE' });
                }
            } catch (_) {
                failed.push(op);
            }
        }
        _saveQueue(failed);
        if (failed.length === 0) {
            document.dispatchEvent(new CustomEvent('cas:synced'));
        }
    }

    // ── Fetch base ────────────────────────────────────────────
    async function _fetch(path, opts = {}) {
        const token = getToken();
        const headers = { 'Content-Type': 'application/json', ...(opts.headers || {}) };
        
        let finalPath = path;
        
        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
            // Para hostings que borran el header Authorization, enviamos en URL:
            finalPath += (path.includes('?') ? '&' : '?') + 'token=' + encodeURIComponent(token);
        }
        
        // Evitamos usar URLs absolutas si el dominio tiene subcarpetas
        const baseUrl = 'api/';
        
        const res  = await fetch(baseUrl + finalPath, { ...opts, headers });
        const text = await res.text();
        let json;
        
        try {
            json = JSON.parse(text);
        } catch (err) {
            throw new Error('Error del servidor: ' + text.substring(0, 50));
        }

        if (!json.ok) {
            if (res.status === 401) {
                clearToken();
                document.dispatchEvent(new CustomEvent('cas:unauthorized'));
            }
            throw new Error(json.error || 'Error de API');
        }
        return json.data;
    }

    // ── Auth ──────────────────────────────────────────────────
    async function login(usuario, password) {
        let res;
        try {
            res = await fetch('api/login.php?t=' + Date.now(), {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ usuario, password })
            });
        } catch (err) {
            throw new Error("No hay conexión con el servidor (Network Error)");
        }
        
        let text;
        try {
            text = await res.text();
        } catch(e) {
            throw new Error("Error leyendo respuesta de login");
        }
        
        let json;
        try {
            json = JSON.parse(text);
        } catch(e) {
            throw new Error("Respuesta del servidor inválida:\n" + text.substring(0, 100));
        }
        if (!json.ok) throw new Error(json.error || 'Login fallido');
        setToken(json.token);
        return json;
    }

    async function logout() {
        try { await _fetch('logout.php', { method: 'POST' }); } catch(_) {}
        clearToken();
    }

    // ── CRUD ──────────────────────────────────────────────────
    async function get(recurso) {
        return _fetch(recurso + '.php');
    }

    async function save(recurso, obj) {
        if (!_online) {
            _enqueue({ resource: recurso, method: 'POST', id: obj.id, data: obj });
            return null;
        }
        try {
            return await _fetch(recurso + '.php', { method: 'POST', body: JSON.stringify(obj) });
        } catch (err) {
            // Si falla por red, encolar
            if (err.message && err.message.toLowerCase().includes('failed to fetch')) {
                _enqueue({ resource: recurso, method: 'POST', id: obj.id, data: obj });
                return null;
            }
            throw err;
        }
    }

    async function remove(recurso, id) {
        if (!_online) {
            _enqueue({ resource: recurso, method: 'DELETE', id });
            return null;
        }
        try {
            return await _fetch(recurso + '.php?id=' + encodeURIComponent(id), { method: 'DELETE' });
        } catch (err) {
            if (err.message && err.message.toLowerCase().includes('failed to fetch')) {
                _enqueue({ resource: recurso, method: 'DELETE', id });
                return null;
            }
            throw err;
        }
    }

    // ── Meta de seguimiento ───────────────────────────────────
    async function saveMeta(clave, valor) {
        return save('seguimiento_meta', { clave, valor });
    }

    // ── API pública ───────────────────────────────────────────
    return { login, logout, isLoggedIn, get, save, remove, saveMeta, isOnline, onReconnect, getPendingCount };
})();
