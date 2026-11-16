/**
 * 3D Model Configuration: HLS-PXL-CA1100-01
 * Demo model data from AppFixtures.php.
 */
export default {
    modelCode: 'HLS-PXL-CA1100-01',
    modules: {
        'M1': {
            shape: 'return180',
            label: 'Groupe de renvoi 180°',
            color: 0x5e81ac,
            position: [-6.0, 0, 0],
            explodeAxis: [0, 1, 0],
            explodeDistance: 2.2,
            focus: { position: [-6.0, 1.6, 3.6], target: [-6.0, 0, 0] },
        },
        'M2': {
            shape: 'return90',
            label: 'Groupe de renvoi 90°',
            color: 0x81a1c1,
            position: [-4.0, 0, 0],
            explodeAxis: [0, -1, 0],
            explodeDistance: 2.0,
            focus: { position: [-4.0, 1.6, 3.6], target: [-4.0, 0, 0] },
        },
        'M3': {
            shape: 'motor',
            label: 'Groupe motorisation tendeur',
            color: 0xd08770,
            position: [-2.0, 0, 0],
            explodeAxis: [0, 1, 0],
            explodeDistance: 2.5,
            focus: { position: [-2.0, 1.8, 3.8], target: [-2.0, 0, 0] },
        },
        'M4': {
            shape: 'pulley',
            label: 'Module poulie',
            color: 0x8fbcbb,
            position: [0.0, 0, 0],
            explodeAxis: [0, -1, 0],
            explodeDistance: 2.2,
            focus: { position: [0.0, 1.5, 3.5], target: [0.0, 0, 0] },
        },
        'M5': {
            shape: 'rail',
            label: 'Tronçon',
            color: 0xa3be8c,
            position: [2.0, 0, 0],
            explodeAxis: [0, 1, 0],
            explodeDistance: 2.0,
            focus: { position: [2.0, 1.5, 3.5], target: [2.0, 0, 0] },
        },
        'M6': {
            shape: 'trolley',
            label: 'Trolley',
            color: 0xebcb8b,
            position: [4.0, 0, 0],
            explodeAxis: [0, -1, 0],
            explodeDistance: 2.3,
            focus: { position: [4.0, 1.5, 3.5], target: [4.0, 0, 0] },
        },
        'M7': {
            shape: 'carrier',
            label: 'Porteur de charge',
            color: 0xb48ead,
            position: [6.0, 0, 0],
            explodeAxis: [0, 1, 0],
            explodeDistance: 2.4,
            focus: { position: [6.0, 1.6, 3.6], target: [6.0, 0, 0] },
        },
    },
};
