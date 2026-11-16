/**
 * RolTech GMAO — 3D Viewer Procedural Shape Builder
 *
 * NOTE (AGENTS.md Rule 23):
 * All shapes created here are schematic representations built from geometric
 * primitives (boxes, cylinders, toruses), not actual engineering geometry.
 * Polygon counts are deliberately kept low for smooth mobile performance.
 */

export function makeShape(THREE, kind, opts = {}) {
    const group = new THREE.Group();
    const color = opts.color !== undefined ? opts.color : 0x88c0d0;
    const baseMat = new THREE.MeshStandardMaterial({
        color: color,
        roughness: 0.45,
        metalness: 0.35,
    });
    const accentMat = new THREE.MeshStandardMaterial({
        color: 0x4c566a,
        roughness: 0.6,
        metalness: 0.2,
    });

    switch (kind) {
        case 'rail': {
            // I-beam or tubular profile for conveyor track
            const mainRail = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.18, 0.25), baseMat);
            const flangeTop = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.05, 0.45), accentMat);
            flangeTop.position.y = 0.1;
            const flangeBottom = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.05, 0.45), accentMat);
            flangeBottom.position.y = -0.1;
            group.add(mainRail, flangeTop, flangeBottom);
            break;
        }

        case 'traction': {
            // Segmented traction line (chain or cable) with link elements
            const line = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 2.2, 8), accentMat);
            line.rotation.z = Math.PI / 2;
            for (let i = -0.8; i <= 0.8; i += 0.4) {
                const link = new THREE.Mesh(new THREE.TorusGeometry(0.12, 0.04, 6, 12), baseMat);
                link.position.x = i;
                link.rotation.y = Math.PI / 2;
                group.add(link);
            }
            group.add(line);
            break;
        }

        case 'trolley': {
            // Suspension body with two opposing guide wheels
            const body = new THREE.Mesh(new THREE.BoxGeometry(0.4, 0.6, 0.25), baseMat);
            const wheelGeom = new THREE.CylinderGeometry(0.16, 0.16, 0.08, 12);
            const w1 = new THREE.Mesh(wheelGeom, accentMat);
            w1.rotation.x = Math.PI / 2;
            w1.position.set(-0.15, 0.25, 0.16);
            const w2 = new THREE.Mesh(wheelGeom, accentMat);
            w2.rotation.x = Math.PI / 2;
            w2.position.set(0.15, 0.25, 0.16);
            const hook = new THREE.Mesh(new THREE.TorusGeometry(0.12, 0.03, 6, 12), accentMat);
            hook.position.set(0, -0.35, 0);
            group.add(body, w1, w2, hook);
            break;
        }

        case 'return180': {
            // Half-circle return wheel with curved guard rail
            const wheel = new THREE.Mesh(new THREE.CylinderGeometry(0.9, 0.9, 0.2, 24, 1, false, 0, Math.PI), baseMat);
            wheel.rotation.x = Math.PI / 2;
            const hub = new THREE.Mesh(new THREE.CylinderGeometry(0.25, 0.25, 0.28, 16), accentMat);
            hub.rotation.x = Math.PI / 2;
            const rim = new THREE.Mesh(new THREE.TorusGeometry(0.9, 0.05, 6, 20, Math.PI), accentMat);
            group.add(wheel, hub, rim);
            break;
        }

        case 'return90': {
            // Quarter-circle return corner group
            const wheel = new THREE.Mesh(new THREE.CylinderGeometry(0.85, 0.85, 0.2, 16, 1, false, 0, Math.PI / 2), baseMat);
            wheel.rotation.x = Math.PI / 2;
            const hub = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.22, 0.26, 14), accentMat);
            hub.rotation.x = Math.PI / 2;
            group.add(wheel, hub);
            break;
        }

        case 'motor': {
            // Electric motor block with reducer gear casing and cooling fins
            const motorBody = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.35, 0.9, 16), baseMat);
            motorBody.rotation.z = Math.PI / 2;
            const reducer = new THREE.Mesh(new THREE.BoxGeometry(0.65, 0.7, 0.6), accentMat);
            reducer.position.x = 0.55;
            const terminalBox = new THREE.Mesh(new THREE.BoxGeometry(0.25, 0.18, 0.25), accentMat);
            terminalBox.position.set(-0.1, 0.4, 0);
            const shaft = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 0.35, 12), accentMat);
            shaft.rotation.x = Math.PI / 2;
            shaft.position.set(0.55, 0, 0.35);
            group.add(motorBody, reducer, terminalBox, shaft);
            break;
        }

        case 'tensioner': {
            // Pneumatic/screw tensioning rails with carriage
            const rail1 = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.05, 1.6, 8), accentMat);
            rail1.rotation.z = Math.PI / 2;
            rail1.position.y = 0.2;
            const rail2 = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.05, 1.6, 8), accentMat);
            rail2.rotation.z = Math.PI / 2;
            rail2.position.y = -0.2;
            const carriage = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.55, 0.35), baseMat);
            const spring = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.12, 0.7, 10), accentMat);
            spring.rotation.z = Math.PI / 2;
            spring.position.x = -0.4;
            group.add(rail1, rail2, carriage, spring);
            break;
        }

        case 'pulley': {
            // Belt/groove transmission pulley
            const disc = new THREE.Mesh(new THREE.CylinderGeometry(0.6, 0.6, 0.16, 20), baseMat);
            disc.rotation.x = Math.PI / 2;
            const groove = new THREE.Mesh(new THREE.CylinderGeometry(0.55, 0.55, 0.06, 20), accentMat);
            groove.rotation.x = Math.PI / 2;
            const centerHub = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.18, 0.22, 14), accentMat);
            centerHub.rotation.x = Math.PI / 2;
            group.add(disc, groove, centerHub);
            break;
        }

        case 'carrier': {
            // Load carrier / spool holder / basket hanger
            const hanger = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.9, 0.08), accentMat);
            hanger.position.y = 0.3;
            const cradle = new THREE.Mesh(new THREE.CylinderGeometry(0.45, 0.45, 0.8, 12, 1, true, 0, Math.PI), baseMat);
            cradle.rotation.x = Math.PI / 2;
            cradle.position.y = -0.2;
            group.add(hanger, cradle);
            break;
        }

        case 'dryer': {
            // Tunnel enclosure with ventilating blower cowl
            const tunnel = new THREE.Mesh(new THREE.BoxGeometry(1.6, 1.1, 1.1), baseMat);
            const fanCap = new THREE.Mesh(new THREE.CylinderGeometry(0.3, 0.35, 0.3, 14), accentMat);
            fanCap.position.y = 0.65;
            group.add(tunnel, fanCap);
            break;
        }

        case 'block':
        default: {
            // Generic fallback enclosure with visible framing
            const size = opts.size || [1.0, 1.0, 1.0];
            const box = new THREE.Mesh(new THREE.BoxGeometry(size[0], size[1], size[2]), baseMat);
            const edges = new THREE.Mesh(
                new THREE.BoxGeometry(size[0] * 1.02, size[1] * 0.1, size[2] * 1.02),
                accentMat
            );
            group.add(box, edges);
            break;
        }
    }

    group.userData = {
        kind: kind,
        label: opts.label || kind,
    };

    return group;
}
