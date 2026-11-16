/**
 * RolTech GMAO — Generic 3D Model Configuration Fallback
 *
 * Used for non-conveyor machines (e.g. HP-2000X hydraulic press, AirMax-300 air compressor)
 * or any newly registered model without a bespoke 3D config.
 * Arranges modules deterministically in a 3-column grid.
 */

const PALETTE = [
    0x5e81ac, // Nord 10 (frost blue)
    0x81a1c1, // Nord 9 (ice blue)
    0x88c0d0, // Nord 8 (light blue)
    0x8fbcbb, // Nord 7 (teal)
    0xa3be8c, // Nord 14 (green)
    0xebcb8b, // Nord 13 (yellow)
    0xd08770, // Nord 12 (orange)
    0xbf616a, // Nord 11 (red)
    0xb48ead, // Nord 15 (purple)
];

export function buildGenericConfig(modules = []) {
    const configModules = {};
    const cols = 3;
    const spacingX = 2.4;
    const spacingZ = 2.4;

    modules.forEach((mod, index) => {
        const col = index % cols;
        const row = Math.floor(index / cols);
        const x = (col - 1) * spacingX;
        const z = row * spacingZ;
        const y = 0.0;

        configModules[mod.code] = {
            shape: 'block',
            label: mod.name || mod.code,
            color: PALETTE[index % PALETTE.length],
            position: [x, y, z],
            explodeAxis: [(col - 1) * 0.4, 1.0, 0.2],
            explodeDistance: 2.2,
            focus: {
                position: [x, 2.0, z + 3.8],
                target: [x, 0.0, z],
            },
        };
    });

    return {
        modelCode: 'GENERIC',
        modules: configModules,
    };
}
