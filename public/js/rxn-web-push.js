/**
 * RxnWebPush — gestión cliente de Web Push.
 *
 * Expone window.RxnWebPush con: getStatus, enable, disable, isSupported.
 * El UI de Mi Perfil (mi_perfil.php) consume esta API para mostrar el botón
 * tri-state (off / on / blocked).
 *
 * Por diseño NUNCA invoca Notification.requestPermission() automáticamente —
 * solo si el usuario clickea explícitamente "Activar". Los browsers castigan
 * los prompts no solicitados y "Block" es persistente.
 */
(function () {
    'use strict';

    const STATUS_URL = '/mi-perfil/web-push/status';
    const SUBSCRIBE_URL = '/mi-perfil/web-push/subscribe';
    const UNSUBSCRIBE_URL = '/mi-perfil/web-push/unsubscribe';

    function isSupported() {
        return typeof window !== 'undefined'
            && 'serviceWorker' in navigator
            && 'PushManager' in window
            && 'Notification' in window;
    }

    function isIos() {
        const ua = navigator.userAgent || '';
        return /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    }

    function permissionState() {
        if (!('Notification' in window)) return 'unsupported';
        return Notification.permission; // 'default' | 'granted' | 'denied'
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = atob(base64);
        const output = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; i++) {
            output[i] = rawData.charCodeAt(i);
        }
        return output;
    }

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content') || '';
        const input = document.querySelector('input[name="csrf_token"]');
        return input ? input.value : '';
    }

    function formBody(fields) {
        return Object.keys(fields)
            .map((k) => encodeURIComponent(k) + '=' + encodeURIComponent(fields[k] == null ? '' : String(fields[k])))
            .join('&');
    }

    async function getStatus() {
        const res = await fetch(STATUS_URL, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('status_failed');
        return res.json();
    }

    async function ensureRegistration() {
        if (!isSupported()) throw new Error('unsupported');
        const reg = await navigator.serviceWorker.register('/sw.js');
        await navigator.serviceWorker.ready;
        return reg;
    }

    async function enable() {
        if (!isSupported()) throw new Error('unsupported');
        const status = await getStatus();
        if (!status.configured || !status.vapid_public_key) {
            throw new Error('vapid_no_configurado');
        }

        const perm = await Notification.requestPermission();
        if (perm !== 'granted') {
            throw new Error('permiso_denegado');
        }

        const reg = await ensureRegistration();
        let sub = await reg.pushManager.getSubscription();
        if (!sub) {
            sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(status.vapid_public_key),
            });
        }

        const subJson = sub.toJSON();
        const res = await fetch(SUBSCRIBE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json',
            },
            body: formBody({
                csrf_token: csrfToken(),
                endpoint: subJson.endpoint,
                p256dh: subJson.keys && subJson.keys.p256dh,
                auth: subJson.keys && subJson.keys.auth,
            }),
        });
        if (!res.ok) throw new Error('subscribe_failed');
        return res.json();
    }

    async function disable() {
        if (!isSupported()) return { ok: true, active: 0 };
        const reg = await navigator.serviceWorker.getRegistration('/sw.js');
        if (!reg) return { ok: true, active: 0 };
        const sub = await reg.pushManager.getSubscription();
        if (!sub) return { ok: true, active: 0 };

        const endpoint = sub.endpoint;
        await sub.unsubscribe();

        const res = await fetch(UNSUBSCRIBE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json',
            },
            body: formBody({ csrf_token: csrfToken(), endpoint }),
        });
        return res.ok ? res.json() : { ok: false };
    }

    window.RxnWebPush = {
        isSupported,
        isIos,
        permissionState,
        getStatus,
        enable,
        disable,
    };
})();
