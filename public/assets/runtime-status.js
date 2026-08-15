/**
 * Shared runtime-status fetcher.
 *
 * Four pages used to carry their own near-identical copy of this, which had already
 * drifted apart. Each element declares its own palette so the pages keep their
 * existing look without the script needing to know about any of them.
 *
 * Devices:  data-runtime-device-status="<name>"
 *           data-status-online / data-status-offline  (classes, optional)
 *           optional children [data-status-dot] and [data-status-label]
 * UPS:      data-runtime-ups-badge="<identifier>"   with data-tone-<tone> classes
 *           data-runtime-ups-details="<identifier>"
 */
(() => {
    const UNKNOWN_DEVICE_CLASS = 'bg-slate-400';
    const UNKNOWN_TONE = 'neutral';

    const endpointElement = document.querySelector('[data-runtime-status-endpoint]');
    if (!endpointElement) {
        return;
    }

    const endpoint = endpointElement.dataset.runtimeStatusEndpoint || '';
    const deviceNodes = Array.from(document.querySelectorAll('[data-runtime-device-status]'));
    const upsBadges = Array.from(document.querySelectorAll('[data-runtime-ups-badge]'));
    const upsDetails = Array.from(document.querySelectorAll('[data-runtime-ups-details]'));

    if (endpoint === '' || (deviceNodes.length === 0 && upsBadges.length === 0 && upsDetails.length === 0)) {
        return;
    }

    const collect = (nodes, key) => Array.from(new Set(
        nodes.map((node) => node.dataset[key] || '').filter((value) => value !== '')
    ));

    const deviceNames = collect(deviceNodes, 'runtimeDeviceStatus');
    const upsIdentifiers = Array.from(new Set([
        ...collect(upsBadges, 'runtimeUpsBadge'),
        ...collect(upsDetails, 'runtimeUpsDetails'),
    ]));

    const params = new URLSearchParams();
    deviceNames.forEach((deviceName) => params.append('device_names[]', deviceName));
    upsIdentifiers.forEach((upsIdentifier) => params.append('ups_identifiers[]', upsIdentifier));

    const splitClasses = (value) => (value || '').split(' ').filter((className) => className !== '');

    const applyDeviceStatus = (node, status) => {
        const onlineClasses = splitClasses(node.dataset.statusOnline || 'bg-green-500');
        const offlineClasses = splitClasses(node.dataset.statusOffline || 'bg-red-500');
        const target = node.querySelector('[data-status-dot]') || node;

        target.classList.remove(...onlineClasses, ...offlineClasses, UNKNOWN_DEVICE_CLASS);

        if (status === 'online') {
            target.classList.add(...onlineClasses);
        } else if (status === 'offline') {
            target.classList.add(...offlineClasses);
        } else {
            target.classList.add(UNKNOWN_DEVICE_CLASS);
        }

        const label = node.querySelector('[data-status-label]');
        if (label) {
            label.textContent = status;
        }
    };

    const applyUpsBadge = (badge, runtimeStatus) => {
        const tones = ['success', 'warning', 'neutral'];
        const classesByTone = {};
        tones.forEach((tone) => {
            classesByTone[tone] = splitClasses(badge.dataset['tone' + tone.charAt(0).toUpperCase() + tone.slice(1)]);
        });

        tones.forEach((tone) => badge.classList.remove(...classesByTone[tone]));

        const tone = tones.includes(runtimeStatus.tone) ? runtimeStatus.tone : UNKNOWN_TONE;
        badge.classList.add(...classesByTone[tone]);
        badge.textContent = typeof runtimeStatus.label === 'string' ? runtimeStatus.label : 'Unknown';
    };

    const applyUpsDetails = (details, runtimeStatus) => {
        const level = runtimeStatus.battery_level;
        if (level === null || level === undefined) {
            details.textContent = 'Battery data unavailable';
            return;
        }

        const minutes = runtimeStatus.battery_runtime_minutes;
        details.textContent = minutes === null || minutes === undefined
            ? String(level) + '% charged'
            : String(level) + '% charged - ' + minutes + 'm left';
    };

    fetch(endpoint + '?' + params.toString(), { headers: { Accept: 'application/json' } })
        .then((response) => (response.ok ? response.json() : Promise.reject(new Error('Request failed'))))
        .then((payload) => {
            const deviceStatusByName = payload.device_status_by_name || {};
            const upsStatusByIdentifier = payload.ups_status_by_identifier || {};

            deviceNodes.forEach((node) => {
                const deviceName = node.dataset.runtimeDeviceStatus || '';
                if (deviceName === '') {
                    return;
                }

                const status = typeof deviceStatusByName[deviceName] === 'string'
                    ? deviceStatusByName[deviceName]
                    : 'unknown';
                applyDeviceStatus(node, status);
            });

            upsBadges.forEach((badge) => {
                const upsIdentifier = badge.dataset.runtimeUpsBadge || '';
                if (upsIdentifier !== '') {
                    applyUpsBadge(badge, upsStatusByIdentifier[upsIdentifier] || {});
                }
            });

            upsDetails.forEach((details) => {
                const upsIdentifier = details.dataset.runtimeUpsDetails || '';
                if (upsIdentifier !== '') {
                    applyUpsDetails(details, upsStatusByIdentifier[upsIdentifier] || {});
                }
            });
        })
        .catch(() => {
        });
})();
