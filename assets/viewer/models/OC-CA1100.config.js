/**
 * 3D Model Configuration: OC-CA1100
 * Confirmed RolTech model data from AppFixtures.php.
 */
export default {
    modelCode: 'OC-CA1100',
    modules: {
        'M1': {
            shape: 'return180',
            label: 'Groupe de renvoi 180° (GR 180 1100)',
            color: 0x5e81ac,
            position: [-6.0, 0, 0],
            explodeAxis: [0, 1, 0],
            explodeDistance: 2.2,
            focus: { position: [-6.0, 1.6, 3.6], target: [-6.0, 0, 0] },
        },
        'M2': {
            shape: 'return90',
            label: 'Groupe de renvoi 90° (GR 90 1100)',
            color: 0x81a1c1,
            position: [-4.0, 0, 0],
            explodeAxis: [0, -1, 0],
            explodeDistance: 2.0,
            focus: { position: [-4.0, 1.6, 3.6], target: [-4.0, 0, 0] },
        },
        'M3': {
            shape: 'tensioner',
            label: 'Groupe Motorisation Tendeur (GM T 200 1100)',
            color: 0x88c0d0,
            position: [-2.0, 0, 0],
            explodeAxis: [0, 1, 0],
            explodeDistance: 2.5,
            focus: { position: [-2.0, 1.8, 3.8], target: [-2.0, 0, 0] },
        },
        'M4': {
            shape: 'pulley',
            label: 'Module poulie (GM-800 01 1100)',
            color: 0x8fbcbb,
            position: [0.0, 0, 0],
            explodeAxis: [0, -1, 0],
            explodeDistance: 2.2,
            focus: { position: [0.0, 1.5, 3.5], target: [0.0, 0, 0] },
        },
        'M5': {
            shape: 'motor',
            label: 'Module support moteur (GM-700 02 1100)',
            color: 0xd08770,
            position: [2.0, 0, 0],
            explodeAxis: [0, 1, 0],
            explodeDistance: 2.4,
            focus: { position: [2.0, 1.6, 3.6], target: [2.0, 0, 0] },
        },
        'M6': {
            shape: 'rail',
            label: 'Tronçon (section)',
            color: 0xa3be8c,
            position: [4.0, 0, 0],
            explodeAxis: [0, -1, 0],
            explodeDistance: 2.0,
            focus: { position: [4.0, 1.5, 3.5], target: [4.0, 0, 0] },
        },
        'M7': {
            shape: 'trolley',
            label: 'Trolley (T-200-1100)',
            color: 0xebcb8b,
            position: [6.0, 0, 0],
            explodeAxis: [0, 1, 0],
            explodeDistance: 2.3,
            focus: { position: [6.0, 1.5, 3.5], target: [6.0, 0, 0] },
        },
    },
};
