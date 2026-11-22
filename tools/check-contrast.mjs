#!/usr/bin/env node
/**
 * WCAG AA Contrast Verification Script for RolTech GMAO
 * Reads CSS variables from assets/styles/rtn-ui.css and verifies color contrast.
 */

import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Pairs to check: [name, fgVar, bgVar, minRatio, type ('text'|'ui')]
const PAIRS = [
  { name: 'Body text on canvas', fg: '--rtn-text', bg: '--rtn-bg-canvas', min: 4.5, type: 'text' },
  { name: 'Body text on card', fg: '--rtn-text', bg: '--rtn-bg-card', min: 4.5, type: 'text' },
  { name: 'Muted text on card', fg: '--rtn-text-muted', bg: '--rtn-bg-card', min: 4.5, type: 'text' },
  { name: 'Link on card', fg: '--rtn-link', bg: '--rtn-bg-card', min: 4.5, type: 'text' },
  { name: 'Button primary label', fg: '--rtn-btn-primary-text', bg: '--rtn-btn-primary-bg', min: 4.5, type: 'text' },
  { name: 'Button secondary label', fg: '--rtn-btn-secondary-text', bg: '--rtn-btn-secondary-bg', min: 4.5, type: 'text' },
  { name: 'Button ghost label on card', fg: '--rtn-btn-ghost-text', bg: '--rtn-bg-card', min: 4.5, type: 'text' },
  { name: 'Button danger label', fg: '--rtn-btn-danger-text', bg: '--rtn-btn-danger-bg', min: 4.5, type: 'text' },
  { name: 'Badge OK text', fg: '--rtn-badge-ok-text', bg: '--rtn-badge-ok-bg', min: 4.5, type: 'text' },
  { name: 'Badge Proche text', fg: '--rtn-badge-proche-text', bg: '--rtn-badge-proche-bg', min: 4.5, type: 'text' },
  { name: 'Badge Echue text', fg: '--rtn-badge-echue-text', bg: '--rtn-badge-echue-bg', min: 4.5, type: 'text' },
  { name: 'Badge Warning text', fg: '--rtn-badge-warning-text', bg: '--rtn-badge-warning-bg', min: 4.5, type: 'text' },
  { name: 'Status OK text on card', fg: '--rtn-status-ok-text', bg: '--rtn-bg-card', min: 4.5, type: 'text' },
  { name: 'Status Proche text on card', fg: '--rtn-status-proche-text', bg: '--rtn-bg-card', min: 4.5, type: 'text' },
  { name: 'Status Echue text on card', fg: '--rtn-status-echue-text', bg: '--rtn-bg-card', min: 4.5, type: 'text' },
  { name: 'Status Warning text on card', fg: '--rtn-status-warning-text', bg: '--rtn-bg-card', min: 4.5, type: 'text' },
  { name: 'Focus ring on card background', fg: '--rtn-focus-ring', bg: '--rtn-bg-card', min: 3.0, type: 'ui' },
  { name: 'Focus ring on canvas', fg: '--rtn-focus-ring', bg: '--rtn-bg-canvas', min: 3.0, type: 'ui' },
  { name: 'Card border on canvas', fg: '--rtn-border', bg: '--rtn-bg-canvas', min: 3.0, type: 'ui' }
];

function parseCssVariables(cssPath) {
  const content = readFileSync(cssPath, 'utf-8');
  const vars = new Map();
  const regex = /(--rtn-[\w-]+)\s*:\s*([^;]+);/g;
  let match;
  while ((match = regex.exec(content)) !== null) {
    const key = match[1].trim();
    let val = match[2].trim();
    vars.set(key, val);
  }

  // Resolve var(--xxx) references
  for (let i = 0; i < 5; i++) {
    for (const [key, val] of vars.entries()) {
      const varRef = val.match(/^var\((--rtn-[\w-]+)\)$/);
      if (varRef && vars.has(varRef[1])) {
        vars.set(key, vars.get(varRef[1]));
      }
    }
  }

  return vars;
}

function hexToRgb(hex) {
  hex = hex.replace('#', '').trim();
  if (hex.length === 3) {
    hex = hex.split('').map(c => c + c).join('');
  }
  if (hex.length === 8) {
    // Drop alpha channel for basic solid contrast against dark theme
    hex = hex.substring(0, 6);
  }
  const num = parseInt(hex, 16);
  return {
    r: (num >> 16) & 255,
    g: (num >> 8) & 255,
    b: num & 255
  };
}

function getLuminance({ r, g, b }) {
  const a = [r, g, b].map(v => {
    v /= 255;
    return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
  });
  return 0.2126 * a[0] + 0.7152 * a[1] + 0.0722 * a[2];
}

function getContrast(rgb1, rgb2) {
  const l1 = getLuminance(rgb1);
  const l2 = getLuminance(rgb2);
  const lighter = Math.max(l1, l2);
  const darker = Math.min(l1, l2);
  return (lighter + 0.05) / (darker + 0.05);
}

const cssPath = resolve(__dirname, '../assets/styles/rtn-ui.css');
const tokens = parseCssVariables(cssPath);

let hasFailure = false;
console.log('\n=== RolTech GMAO — WCAG Contrast Check ===\n');
console.log('| Pair Name | FG Token (Hex) | BG Token (Hex) | Ratio | Required | Result |');
console.log('|---|---|---|---|---|---|');

for (const pair of PAIRS) {
  const fgVal = tokens.get(pair.fg);
  const bgVal = tokens.get(pair.bg);

  if (!fgVal || !bgVal) {
    console.log(`| ${pair.name} | ${pair.fg} (${fgVal}) | ${pair.bg} (${bgVal}) | N/A | ${pair.min}:1 | MISSING TOKEN |`);
    hasFailure = true;
    continue;
  }

  try {
    const fgRgb = hexToRgb(fgVal);
    const bgRgb = hexToRgb(bgVal);
    const ratio = getContrast(fgRgb, bgRgb);
    const passed = ratio >= pair.min;
    if (!passed) hasFailure = true;

    const status = passed ? 'PASS' : 'FAIL';
    console.log(`| ${pair.name} | \`${fgVal}\` (${pair.fg}) | \`${bgVal}\` (${pair.bg}) | **${ratio.toFixed(2)}:1** | >= ${pair.min}:1 | ${status} |`);
  } catch (err) {
    console.log(`| ${pair.name} | ${fgVal} | ${bgVal} | ERR | ${pair.min}:1 | ERROR: ${err.message} |`);
    hasFailure = true;
  }
}

console.log('\n==========================================\n');

if (hasFailure) {
  console.error('Contrast check FAILED: One or more pairs do not satisfy WCAG AA criteria.');
  process.exit(1);
} else {
  console.log('Contrast check PASSED: All pairs satisfy WCAG AA criteria.');
  process.exit(0);
}
