/**
 * RolTech GMAO — 3D Viewer Engine (Step 7.4)
 *
 * Rule 22: Three.js is a local AssetMapper asset, no CDN.
 * Rule 7:  No listener setup is gated behind an await. Bootstrap registers
 *          turbo:load synchronously; heavy imports happen inside the async body.
 * Rule 24: Status never shown by color alone — text labels accompany every
 *          color in the legend.
 * Rule 23: Schematic geometry only; no engineering dimensions invented for OC-CA1100.
 */

import { loadConfig } from './configs.js';
import { makeShape } from './builder.js';

// ─── Health colour map (rule 24: text labels added in legend) ──────────────
const HEALTH_COLOR = { ok: 0x4caf50, proche: 0xffa726, echue: 0xef5350 };
const HEALTH_LABEL = { ok: 'OK', proche: 'Proche', echue: 'Échue' };
const HIGHLIGHT_EMISSIVE = 0x2979ff; // blue tint — clearly distinct from greens/oranges/reds

// ─── Tween helper (no dependency) ─────────────────────────────────────────
function lerp(a, b, t) { return a + (b - a) * t; }
function lerpVec3(THREE, from, to, t) {
    return new THREE.Vector3(
        lerp(from.x, to.x, t),
        lerp(from.y, to.y, t),
        lerp(from.z, to.z, t),
    );
}

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

// ─── WebGL support probe ───────────────────────────────────────────────────
function webglSupported() {
    try {
        const canvas = document.createElement('canvas');
        return !!(
            canvas.getContext('webgl2') ||
            canvas.getContext('webgl') ||
            canvas.getContext('experimental-webgl')
        );
    } catch { return false; }
}

/**
 * Mount a 3-D viewer into `container`.
 * @param {HTMLElement} container
 * @param {object}      data        – flat `modules` list from the endpoint (step 7.2)
 * @returns {Promise<void>}
 */
export async function mountViewer(container, data) {
    // Guard: never mount twice on the same container
    if (container._rtnViewerMounted) return;
    container._rtnViewerMounted = true;

    // WebGL check before importing Three (saves the bundle download on unsupported devices)
    if (!webglSupported()) {
        container.dispatchEvent(new CustomEvent('rtn:viewer-unsupported', {
            bubbles: true,
            detail: { reason: 'webgl-unavailable' },
        }));
        return;
    }

    try {
        // ── Lazy imports (rule 7: NO listeners set up here, only inside this fn) ─
        const THREE = await import('three');
        const { OrbitControls } = await import('three/addons/controls/OrbitControls.js');

        // ── Build config ──────────────────────────────────────────────────────
        const modules = data.modules ?? [];
        // API returns data.model.code, not data.modelCode
        const modelCode = data.model?.code ?? data.modelCode ?? '';
        const config = await loadConfig(modelCode, modules);

    // ── Scene ─────────────────────────────────────────────────────────────
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x1b2435);

    // Lights
    scene.add(new THREE.AmbientLight(0xffffff, 0.6));
    const dirLight = new THREE.DirectionalLight(0xffffff, 1.1);
    dirLight.position.set(5, 10, 7);
    scene.add(dirLight);

    // ── Renderer ─────────────────────────────────────────────────────────
    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

    let contextLost = false;
    renderer.domElement.addEventListener('webglcontextlost', (e) => {
        e.preventDefault();
        contextLost = true;
        container.dispatchEvent(new CustomEvent('rtn:viewer-unsupported', {
            bubbles: true,
            detail: { reason: 'context-lost' },
        }));
    }, false);

    // ── Camera ────────────────────────────────────────────────────────────
    const W = container.clientWidth || 400;
    const H = container.clientHeight || 300;
    const camera = new THREE.PerspectiveCamera(45, W / H, 0.01, 1000);
    camera.position.set(0, 4, 10);

    // ── OrbitControls ─────────────────────────────────────────────────────
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.enablePan = true;
    controls.screenSpacePanning = true;

    // ── Build module groups ───────────────────────────────────────────────
    // Map: moduleId → { group, originalPosition, explodeAxis, explodeDistance,
    //                   materials, code, health }
    const moduleMap = new Map();

    for (const mod of modules) {
        const cfg = config.modules?.[mod.code];
        const health = mod.health ?? 'ok';
        const color = HEALTH_COLOR[health] ?? HEALTH_COLOR.ok;

        let group;
        if (cfg) {
            group = makeShape(THREE, cfg.shape ?? 'block', {
                color,
                ...cfg,
            });
        } else {
            // Fallback: simple block
            group = makeShape(THREE, 'block', { color });
        }

        // Position
        if (cfg?.position) {
            group.position.set(cfg.position[0] ?? 0, cfg.position[1] ?? 0, cfg.position[2] ?? 0);
        } else {
            // Fallback grid layout
            const idx = modules.indexOf(mod);
            group.position.set((idx % 5) * 2.5 - 5, 0, Math.floor(idx / 5) * 2.5);
        }

        group.userData = {
            moduleId: mod.id,
            code: mod.code,
            label: cfg?.label ?? mod.name ?? mod.code,
            health,
            originalPosition: group.position.clone(),
            explodeAxis: cfg?.explodeAxis ? new THREE.Vector3(...cfg.explodeAxis) : new THREE.Vector3(1, 0, 0),
            explodeDistance: cfg?.explodeDistance ?? 1.0,
            focusPosition: cfg?.focus?.position ? new THREE.Vector3(...cfg.focus.position) : null,
            focusTarget: cfg?.focus?.target ? new THREE.Vector3(...cfg.focus.target) : null,
        };

        // Collect materials for disposal and highlight
        const materials = [];
        group.traverse((child) => {
            if (child.isMesh && child.material) {
                const mats = Array.isArray(child.material) ? child.material : [child.material];
                mats.forEach((m) => { if (!materials.includes(m)) materials.push(m); });
            }
        });
        group.userData.materials = materials;

        scene.add(group);
        moduleMap.set(mod.id, group);
    }

    // ── Legend DOM ────────────────────────────────────────────────────────
    const legend = document.createElement('div');
    legend.setAttribute('aria-label', 'Légende des états');
    legend.style.cssText = [
        'position:absolute;bottom:12px;left:12px',
        'background:rgba(27,36,53,.85)',
        'backdrop-filter:blur(4px)',
        'border:1px solid #3b465c',
        'border-radius:8px',
        'padding:8px 12px',
        'font-size:.8rem',
        'color:#eceff4',
        'display:flex',
        'gap:12px',
        'flex-wrap:wrap',
        'z-index:10',
    ].join(';');

    const healthEntries = [
        { key: 'ok',     color: '#4caf50', text: 'OK' },
        { key: 'proche', color: '#ffa726', text: 'Proche' },
        { key: 'echue',  color: '#ef5350', text: 'Échue' },
    ];
    for (const { color, text } of healthEntries) {
        const item = document.createElement('span');
        item.style.cssText = 'display:inline-flex;align-items:center;gap:5px';
        item.innerHTML = `<span aria-hidden="true" style="width:11px;height:11px;border-radius:50%;background:${color};display:inline-block;flex-shrink:0"></span><span>${text}</span>`;
        legend.appendChild(item);
    }

    // ── Explode slider ────────────────────────────────────────────────────
    const sliderWrapper = document.createElement('div');
    sliderWrapper.style.cssText = [
        'position:absolute;top:12px;right:12px',
        'background:rgba(27,36,53,.85)',
        'backdrop-filter:blur(4px)',
        'border:1px solid #3b465c',
        'border-radius:8px',
        'padding:8px 12px',
        'font-size:.8rem',
        'color:#eceff4',
        'display:flex',
        'flex-direction:column',
        'gap:4px',
        'z-index:10',
        'min-width:140px',
    ].join(';');
    const sliderId = 'rtn-viewer-explode-' + Math.random().toString(36).slice(2, 8);
    const sliderLabel = document.createElement('label');
    sliderLabel.setAttribute('for', sliderId);
    sliderLabel.textContent = 'Vue éclatée';
    const slider = document.createElement('input');
    slider.type = 'range';
    slider.id = sliderId;
    slider.min = '0';
    slider.max = '100';
    slider.value = '0';
    slider.style.cssText = 'width:100%;accent-color:#88c0d0';
    slider.setAttribute('aria-label', 'Vue éclatée — déplace les modules');
    sliderWrapper.appendChild(sliderLabel);
    sliderWrapper.appendChild(slider);

    // ── Container setup ───────────────────────────────────────────────────
    const prevPosition = container.style.position;
    if (!container.style.position || container.style.position === 'static') {
        container.style.position = 'relative';
    }
    container.style.overflow = 'hidden';

    renderer.setSize(W, H);
    renderer.domElement.style.cssText = 'display:block;width:100%;height:100%';
    container.appendChild(renderer.domElement);
    container.appendChild(legend);
    container.appendChild(sliderWrapper);

    // ── ResizeObserver ────────────────────────────────────────────────────
    let frameRequested = false;
    function requestRender() {
        if (!frameRequested) {
            frameRequested = true;
            rafId = requestAnimationFrame(renderFrame);
        }
    }

    const resizeObserver = new ResizeObserver((entries) => {
        for (const entry of entries) {
            const { width, height } = entry.contentRect;
            if (width > 0 && height > 0) {
                renderer.setSize(width, height);
                camera.aspect = width / height;
                camera.updateProjectionMatrix();
                requestRender();
            }
        }
    });
    resizeObserver.observe(container);

    // ── Selection & picking ───────────────────────────────────────────────
    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2();
    let selectedGroup = null;
    let pointerDownX = 0;
    let pointerDownY = 0;
    let explodeT = 0;

    // Camera tween state
    let tween = null; // { fromCamPos, fromTarget, toCamPos, toTarget, t, duration }

    function startCameraTween(toPos, toTarget) {
        if (prefersReducedMotion()) {
            camera.position.copy(toPos);
            controls.target.copy(toTarget);
            controls.update();
            requestRender();
            return;
        }
        tween = {
            fromCamPos: camera.position.clone(),
            fromTarget: controls.target.clone(),
            toCamPos: toPos.clone(),
            toTarget: toTarget.clone(),
            t: 0,
            duration: 400,
            startTime: performance.now(),
        };
        startAnimationLoop();
    }

    function highlightGroup(group) {
        group.traverse((child) => {
            if (child.isMesh && child.material) {
                const mats = Array.isArray(child.material) ? child.material : [child.material];
                mats.forEach((m) => { m.emissive?.setHex(HIGHLIGHT_EMISSIVE); m.emissiveIntensity = 0.4; });
            }
        });
    }

    function unhighlightGroup(group) {
        group.traverse((child) => {
            if (child.isMesh && child.material) {
                const mats = Array.isArray(child.material) ? child.material : [child.material];
                mats.forEach((m) => { m.emissive?.setHex(0x000000); m.emissiveIntensity = 0; });
            }
        });
    }

    function selectGroup(group) {
        if (selectedGroup && selectedGroup !== group) unhighlightGroup(selectedGroup);
        selectedGroup = group;
        if (group) {
            highlightGroup(group);
            const ud = group.userData;
            const toPos = ud.focusPosition ?? group.position.clone().add(new THREE.Vector3(0, 2, 5));
            const toTarget = ud.focusTarget ?? group.position.clone();
            startCameraTween(toPos, toTarget);
            container.dispatchEvent(new CustomEvent('rtn:module-selected', {
                bubbles: true,
                detail: { moduleId: ud.moduleId, code: ud.code },
            }));
        } else {
            container.dispatchEvent(new CustomEvent('rtn:module-selected', {
                bubbles: true,
                detail: { moduleId: null, code: null },
            }));
        }
        requestRender();
    }

    function getCanvasPointer(event) {
        const rect = renderer.domElement.getBoundingClientRect();
        const clientX = event.clientX ?? event.touches?.[0]?.clientX ?? 0;
        const clientY = event.clientY ?? event.touches?.[0]?.clientY ?? 0;
        return {
            x: ((clientX - rect.left) / rect.width) * 2 - 1,
            y: -((clientY - rect.top) / rect.height) * 2 + 1,
        };
    }

    function onPointerDown(event) {
        pointerDownX = event.clientX ?? event.touches?.[0]?.clientX ?? 0;
        pointerDownY = event.clientY ?? event.touches?.[0]?.clientY ?? 0;
    }

    function onPointerUp(event) {
        const upX = event.clientX ?? event.changedTouches?.[0]?.clientX ?? 0;
        const upY = event.clientY ?? event.changedTouches?.[0]?.clientY ?? 0;
        const dx = upX - pointerDownX;
        const dy = upY - pointerDownY;
        if (Math.sqrt(dx * dx + dy * dy) > 5) return; // was a drag

        const p = getCanvasPointer(event.changedTouches?.[0] ?? event);
        pointer.set(p.x, p.y);
        raycaster.setFromCamera(pointer, camera);

        const meshes = [];
        scene.traverse((child) => { if (child.isMesh) meshes.push(child); });
        const hits = raycaster.intersectObjects(meshes, false);

        if (hits.length === 0) {
            if (selectedGroup) { unhighlightGroup(selectedGroup); selectedGroup = null; selectGroup(null); }
            return;
        }

        // Walk up to find the top-level group that is a direct scene child
        let hit = hits[0].object;
        while (hit.parent && hit.parent !== scene) hit = hit.parent;
        if (hit.userData?.moduleId !== undefined) selectGroup(hit);
    }

    renderer.domElement.addEventListener('pointerdown', onPointerDown);
    renderer.domElement.addEventListener('pointerup', onPointerUp);
    renderer.domElement.addEventListener('touchstart', onPointerDown, { passive: true });
    renderer.domElement.addEventListener('touchend', onPointerUp, { passive: true });

    // ── Explode slider handler ────────────────────────────────────────────
    slider.addEventListener('input', () => {
        explodeT = slider.value / 100;
        applyExplode(explodeT);
        requestRender();
    });

    function applyExplode(t) {
        for (const group of moduleMap.values()) {
            const ud = group.userData;
            const offset = ud.explodeAxis.clone().multiplyScalar(ud.explodeDistance * t);
            group.position.copy(ud.originalPosition).add(offset);
        }
    }

    // ── Render loop ───────────────────────────────────────────────────────
    let rafId = null;
    let animLoopActive = false;

    function renderFrame(now) {
        rafId = null;
        frameRequested = false;
        if (contextLost) return;

        // Advance camera tween
        if (tween) {
            const elapsed = now - tween.startTime;
            tween.t = Math.min(elapsed / tween.duration, 1);
            const easedT = 1 - Math.pow(1 - tween.t, 3); // ease-out cubic
            camera.position.copy(lerpVec3(THREE, tween.fromCamPos, tween.toCamPos, easedT));
            controls.target.copy(lerpVec3(THREE, tween.fromTarget, tween.toTarget, easedT));
            controls.update();
            if (tween.t < 1) {
                frameRequested = true;
                rafId = requestAnimationFrame(renderFrame);
            } else {
                tween = null;
                animLoopActive = false;
            }
        }

        if (controls.enableDamping) controls.update();
        renderer.render(scene, camera);
    }

    function startAnimationLoop() {
        if (!animLoopActive && !rafId) {
            animLoopActive = true;
            frameRequested = true;
            rafId = requestAnimationFrame(renderFrame);
        }
    }

    // Controls change → re-render
    controls.addEventListener('change', requestRender);

    // Initial render
    requestRender();

    // ── Visibility — stop rendering when hidden ───────────────────────────
    const intersectionObserver = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) requestRender();
        }
    }, { threshold: 0 });
    intersectionObserver.observe(container);

    // ── Public API ────────────────────────────────────────────────────────
    function destroy() {
        if (!container._rtnViewerMounted) return;
        container._rtnViewerMounted = false;

        if (rafId !== null) { cancelAnimationFrame(rafId); rafId = null; }
        resizeObserver.disconnect();
        intersectionObserver.disconnect();
        controls.dispose();
        renderer.domElement.removeEventListener('pointerdown', onPointerDown);
        renderer.domElement.removeEventListener('pointerup', onPointerUp);
        renderer.domElement.removeEventListener('touchstart', onPointerDown);
        renderer.domElement.removeEventListener('touchend', onPointerUp);
        slider.removeEventListener('input', () => {});

        // Dispose all geometries and materials
        scene.traverse((child) => {
            if (child.isMesh) {
                child.geometry?.dispose();
                const mats = Array.isArray(child.material) ? child.material : [child.material];
                mats.forEach((m) => {
                    m?.map?.dispose();
                    m?.dispose();
                });
            }
        });

        renderer.dispose();
        renderer.forceContextLoss();
        renderer.domElement.remove();
        legend.remove();
        sliderWrapper.remove();
        container.style.position = prevPosition;

        delete container.rtnViewer;
    }

    container.rtnViewer = {
        selectModule(id) {
            const group = moduleMap.get(id);
            if (group) selectGroup(group);
        },
        clearSelection() {
            if (selectedGroup) { unhighlightGroup(selectedGroup); selectedGroup = null; }
            selectGroup(null);
        },
        setExplode(t) {
            explodeT = Math.max(0, Math.min(1, t));
            slider.value = String(Math.round(explodeT * 100));
            applyExplode(explodeT);
            requestRender();
        },
        destroy,
    };

    container.dispatchEvent(new CustomEvent('rtn:viewer-ready', { bubbles: true }));
    } catch (err) {
        console.error('[RolTech Viewer] Error mounting 3D viewer:', err);
        container.dispatchEvent(new CustomEvent('rtn:viewer-unsupported', {
            bubbles: true,
            detail: { reason: 'scene-init-failed', error: err?.message },
        }));
        container._rtnViewerMounted = false;
    }
}
