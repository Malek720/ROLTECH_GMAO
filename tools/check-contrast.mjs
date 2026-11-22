#!/usr/bin/env node
/**
 * WCAG AA Contrast Verification Script for RolTech GMAO
 * Parses CSS declarations from assets/styles/rtn-ui.css and assets/styles/login.css,
 * resolves custom properties, and verifies color contrast against WCAG AA criteria.
 */

import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

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

  // Resolve var(--xxx) references up to 5 passes
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

function parseRules(cssPath) {
  let content = readFileSync(cssPath, 'utf-8').replace(/\/\*[\s\S]*?\*\//g, '');
  // Strip @media and @keyframes blocks so base rules are evaluated
  content = content.replace(/@media[^{]*\{([\s\S]*?\}\s*)\}/g, '');
  content = content.replace(/@keyframes[^{]*\{([\s\S]*?\}\s*)\}/g, '');

  const rules = new Map();
  const ruleRegex = /([^{]+)\{([^}]+)\}/g;
  let match;
  while ((match = ruleRegex.exec(content)) !== null) {
    const rawSelectors = match[1].trim();
    const decls = match[2].trim();
    const selectors = rawSelectors.split(',').map(s => s.trim());
    for (const selector of selectors) {
      const declMap = rules.get(selector) || new Map();
      for (const line of decls.split(';')) {
        const parts = line.split(':');
        if (parts.length >= 2) {
          const prop = parts[0].trim();
          const val = parts.slice(1).join(':').replace('!important', '').trim();
          declMap.set(prop, val);
        }
      }
      rules.set(selector, declMap);
    }
  }
  return rules;
}

function resolveValue(val, tokens) {
  if (!val) return null;
  val = val.trim();
  if (val.startsWith('#')) return val;
  const match = val.match(/^var\((--rtn-[\w-]+)(?:,\s*([^)]+))?\)$/);
  if (match) {
    const varName = match[1];
    const fallback = match[2] ? match[2].trim() : null;
    if (tokens.has(varName)) {
      return resolveValue(tokens.get(varName), tokens);
    }
    if (fallback) {
      return resolveValue(fallback, tokens);
    }
    throw new Error(`Referenced undefined variable: ${varName}`);
  }
  return val;
}

function hexToRgb(hex) {
  hex = hex.replace('#', '').trim();
  if (hex.length === 3) {
    hex = hex.split('').map(c => c + c).join('');
  }
  if (hex.length === 8) {
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

const rtnUiPath = resolve(__dirname, '../assets/styles/rtn-ui.css');
const loginCssPath = resolve(__dirname, '../assets/styles/login.css');

const tokens = parseCssVariables(rtnUiPath);
const rtnRules = parseRules(rtnUiPath);
const loginRules = parseRules(loginCssPath);

function getRuleProp(rules, selectorSubstring, prop) {
  for (const [sel, decls] of rules.entries()) {
    if (sel.includes(selectorSubstring) && decls.has(prop)) {
      return decls.get(prop);
    }
  }
  return null;
}

const PAIRS = [
  // Base theme tokens
  { name: 'Body text on canvas', fg: resolveValue(tokens.get('--rtn-text'), tokens), bg: resolveValue(tokens.get('--rtn-bg-canvas'), tokens), min: 4.5, type: 'text' },
  { name: 'Body text on card', fg: resolveValue(tokens.get('--rtn-text'), tokens), bg: resolveValue(tokens.get('--rtn-bg-card'), tokens), min: 4.5, type: 'text' },
  { name: 'Muted text on card', fg: resolveValue(tokens.get('--rtn-text-muted'), tokens), bg: resolveValue(tokens.get('--rtn-bg-card'), tokens), min: 4.5, type: 'text' },
  { name: 'Link on card', fg: resolveValue(tokens.get('--rtn-link'), tokens), bg: resolveValue(tokens.get('--rtn-bg-card'), tokens), min: 4.5, type: 'text' },
  { name: 'Button primary label', fg: resolveValue(tokens.get('--rtn-btn-primary-text'), tokens), bg: resolveValue(tokens.get('--rtn-btn-primary-bg'), tokens), min: 4.5, type: 'text' },
  { name: 'Button secondary label', fg: resolveValue(tokens.get('--rtn-btn-secondary-text'), tokens), bg: resolveValue(tokens.get('--rtn-btn-secondary-bg'), tokens), min: 4.5, type: 'text' },
  { name: 'Button ghost label on card', fg: resolveValue(tokens.get('--rtn-btn-ghost-text'), tokens), bg: resolveValue(tokens.get('--rtn-bg-card'), tokens), min: 4.5, type: 'text' },
  { name: 'Button danger label', fg: resolveValue(tokens.get('--rtn-btn-danger-text'), tokens), bg: resolveValue(tokens.get('--rtn-btn-danger-bg'), tokens), min: 4.5, type: 'text' },

  // Badge variants (parsed from rtn-ui.css rules)
  {
    name: 'Badge OK text',
    fg: resolveValue(getRuleProp(rtnRules, '.rtn-badge-ok', 'color'), tokens),
    bg: resolveValue(getRuleProp(rtnRules, '.rtn-badge-ok', 'background'), tokens),
    min: 4.5, type: 'text'
  },
  {
    name: 'Badge Proche text',
    fg: resolveValue(getRuleProp(rtnRules, '.rtn-badge-proche', 'color'), tokens),
    bg: resolveValue(getRuleProp(rtnRules, '.rtn-badge-proche', 'background'), tokens),
    min: 4.5, type: 'text'
  },
  {
    name: 'Badge Echue text',
    fg: resolveValue(getRuleProp(rtnRules, '.rtn-badge-echue', 'color'), tokens),
    bg: resolveValue(getRuleProp(rtnRules, '.rtn-badge-echue', 'background'), tokens),
    min: 4.5, type: 'text'
  },
  {
    name: 'Badge Warning text',
    fg: resolveValue(getRuleProp(rtnRules, '.rtn-badge-warning', 'color'), tokens),
    bg: resolveValue(getRuleProp(rtnRules, '.rtn-badge-warning', 'background'), tokens),
    min: 4.5, type: 'text'
  },
  {
    name: 'Badge Info text',
    fg: resolveValue(getRuleProp(rtnRules, '.rtn-badge-info', 'color'), tokens),
    bg: resolveValue(getRuleProp(rtnRules, '.rtn-badge-info', 'background'), tokens),
    min: 4.5, type: 'text'
  },
  {
    name: 'Badge Confirmed (Data Status)',
    fg: resolveValue(getRuleProp(rtnRules, '.rtn-badge-confirmed', 'color'), tokens),
    bg: resolveValue(getRuleProp(rtnRules, '.rtn-badge-confirmed', 'background'), tokens),
    min: 4.5, type: 'text'
  },
  {
    name: 'Badge Demo (Data Status)',
    fg: resolveValue(getRuleProp(rtnRules, '.rtn-badge-demo', 'color'), tokens),
    bg: resolveValue(getRuleProp(rtnRules, '.rtn-badge-demo', 'background'), tokens),
    min: 4.5, type: 'text'
  },

  // Map popup rules
  {
    name: 'Map popup text on card bg',
    fg: resolveValue(getRuleProp(rtnRules, '.leaflet-popup-content-wrapper', 'color'), tokens),
    bg: resolveValue(getRuleProp(rtnRules, '.leaflet-popup-content-wrapper', 'background'), tokens),
    min: 4.5, type: 'text'
  },
  {
    name: 'Map popup link on card bg',
    fg: resolveValue(getRuleProp(rtnRules, '.leaflet-container a', 'color'), tokens),
    bg: resolveValue(getRuleProp(rtnRules, '.leaflet-popup-content-wrapper', 'background'), tokens),
    min: 4.5, type: 'text'
  },
  {
    name: 'Map popup close button on card bg',
    fg: resolveValue(getRuleProp(rtnRules, '.leaflet-popup-close-button', 'color'), tokens),
    bg: resolveValue(getRuleProp(rtnRules, '.leaflet-popup-content-wrapper', 'background'), tokens),
    min: 4.5, type: 'text'
  },
  {
    name: 'Map popup action button label',
    fg: resolveValue(getRuleProp(rtnRules, '.rtn-map-popup .rtn-button', 'color'), tokens),
    bg: resolveValue(getRuleProp(rtnRules, '.rtn-map-popup .rtn-button', 'background'), tokens),
    min: 4.5, type: 'text'
  },
  {
    name: 'Map popup action button on popup bg',
    fg: resolveValue(getRuleProp(rtnRules, '.rtn-map-popup .rtn-button', 'background'), tokens),
    bg: resolveValue(getRuleProp(rtnRules, '.leaflet-popup-content-wrapper', 'background'), tokens),
    min: 3.0, type: 'ui'
  },

  // Login page rules (parsed from login.css)
  {
    name: 'Login submit button label (Technicien)',
    fg: resolveValue(getRuleProp(loginRules, '.rtn-submit-btn', 'color'), tokens),
    bg: '#88c0d0', // Technicien role accent
    min: 4.5, type: 'text'
  },
  {
    name: 'Login submit button label (Responsable)',
    fg: resolveValue(getRuleProp(loginRules, '.rtn-submit-btn', 'color'), tokens),
    bg: '#d08770', // Responsable role accent
    min: 4.5, type: 'text'
  },
  {
    name: 'Login submit button label (Admin)',
    fg: resolveValue(getRuleProp(loginRules, '.rtn-submit-btn', 'color'), tokens),
    bg: '#b48ead', // Admin role accent
    min: 4.5, type: 'text'
  },
  {
    name: 'Login eyebrow on card bg',
    fg: resolveValue(getRuleProp(loginRules, '.rtn-eyebrow', 'color'), tokens),
    bg: '#ffffff',
    min: 4.5, type: 'text'
  },
  {
    name: 'Login placeholder on input bg',
    fg: resolveValue(getRuleProp(loginRules, '.rtn-field input::placeholder', 'color'), tokens),
    bg: '#fbfcfe',
    min: 4.5, type: 'text'
  },
  {
    name: 'Login footer text on sidebar bg',
    fg: resolveValue(getRuleProp(loginRules, '.rtn-sidebar-footer', 'color'), tokens),
    bg: '#f7f9fc',
    min: 4.5, type: 'text'
  },
  {
    name: 'Login role picker item on sidebar bg',
    fg: resolveValue(getRuleProp(loginRules, '.rtn-nav-item', 'color'), tokens),
    bg: '#f7f9fc',
    min: 4.5, type: 'text'
  },
  {
    name: 'Login role picker icon on icon bg',
    fg: resolveValue(getRuleProp(loginRules, '.rtn-nav-icon', 'color'), tokens),
    bg: resolveValue(getRuleProp(loginRules, '.rtn-nav-icon', 'background'), tokens) || '#e5e9f0',
    min: 3.0, type: 'ui'
  }
];

let hasFailure = false;
console.log('\n=== RolTech GMAO — WCAG Contrast Check ===\n');
console.log('| Pair Name | FG Color | BG Color | Ratio | Required | Result |');
console.log('|---|---|---|---|---|---|');

for (const pair of PAIRS) {
  if (!pair.fg || !pair.bg) {
    console.log(`| ${pair.name} | ${pair.fg} | ${pair.bg} | N/A | ${pair.min}:1 | MISSING COLOR |`);
    hasFailure = true;
    continue;
  }

  try {
    const fgRgb = hexToRgb(pair.fg);
    const bgRgb = hexToRgb(pair.bg);
    const ratio = getContrast(fgRgb, bgRgb);
    const passed = ratio >= pair.min;
    if (!passed) hasFailure = true;

    const status = passed ? 'PASS' : 'FAIL';
    console.log(`| ${pair.name} | \`${pair.fg}\` | \`${pair.bg}\` | **${ratio.toFixed(2)}:1** | >= ${pair.min}:1 | ${status} |`);
  } catch (err) {
    console.log(`| ${pair.name} | ${pair.fg} | ${pair.bg} | ERR | ${pair.min}:1 | ERROR: ${err.message} |`);
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
