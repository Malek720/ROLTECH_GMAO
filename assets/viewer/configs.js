/**
 * RolTech GMAO — 3D Viewer Configuration Registry
 *
 * Dynamically loads bespoke conveyor configs using literal import paths
 * for AssetMapper compatibility, with automatic fallback to generic grid layout.
 */

import { buildGenericConfig } from './generic.js';

const MODEL_LOADERS = {
    'OC-CA1100': () => import('./models/OC-CA1100.config.js'),
    'HLS-CA1100': () => import('./models/HLS-CA1100.config.js'),
    'HLS-PXL-CA1100': () => import('./models/HLS-PXL-CA1100.config.js'),
    'HLS-PXL-CA1100-01': () => import('./models/HLS-PXL-CA1100-01.config.js'),
    'PPXXL-CA1100': () => import('./models/PPXXL-CA1100.config.js'),
};

export async function loadConfig(modelCode, modules = []) {
    const loader = MODEL_LOADERS[modelCode];
    if (loader) {
        try {
            const moduleExport = await loader();
            const config = moduleExport.default || moduleExport;
            if (config && config.modules) {
                return config;
            }
        } catch (error) {
            console.warn(`[RolTech Viewer] Error loading config for model ${modelCode}:`, error);
        }
    }

    return buildGenericConfig(modules);
}
