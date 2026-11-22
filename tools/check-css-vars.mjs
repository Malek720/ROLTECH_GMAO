#!/usr/bin/env node
/**
 * Verification script: check-css-vars.mjs
 * Fails with exit code 1 if any var(--rtn-...) in assets/ (excluding vendor) or templates/ is undefined.
 */

import fs from 'fs';
import path from 'path';

function findFiles(dir, exts) {
  let results = [];
  if (!fs.existsSync(dir)) return results;
  const list = fs.readdirSync(dir);
  for (const file of list) {
    const full = path.join(dir, file);
    const stat = fs.statSync(full);
    if (stat.isDirectory()) {
      if (file === 'vendor' || file === 'node_modules') continue;
      results = results.concat(findFiles(full, exts));
    } else {
      if (exts.some(ext => file.endsWith(ext))) {
        results.push(full);
      }
    }
  }
  return results;
}

const cssVarDefRegex = /(--rtn-[a-zA-Z0-9_-]+)\s*:/g;
const jsSetPropRegex = /style\.setProperty\(['"](--rtn-[a-zA-Z0-9_-]+)['"]/g;
const cssVarUseRegex = /var\((--rtn-[a-zA-Z0-9_-]+)/g;

const filesToCheck = [
  ...findFiles('assets/styles', ['.css']),
  ...findFiles('assets', ['.js']),
  ...findFiles('templates', ['.twig', '.html'])
];

const definedVars = new Set();
const usedVars = new Map();

for (const f of filesToCheck) {
  const content = fs.readFileSync(f, 'utf8');
  let m;
  while ((m = cssVarDefRegex.exec(content)) !== null) {
    definedVars.add(m[1]);
  }
  while ((m = jsSetPropRegex.exec(content)) !== null) {
    definedVars.add(m[1]);
  }
}

for (const f of filesToCheck) {
  const content = fs.readFileSync(f, 'utf8');
  let m;
  while ((m = cssVarUseRegex.exec(content)) !== null) {
    const v = m[1];
    if (!usedVars.has(v)) usedVars.set(v, new Set());
    usedVars.get(v).add(f);
  }
}

const undefinedVars = [];
for (const [v, locs] of usedVars.entries()) {
  if (!definedVars.has(v)) {
    undefinedVars.push({ varName: v, locations: Array.from(locs).map(p => path.relative('.', p)) });
  }
}

console.log('\n=== RolTech GMAO — CSS Variable Definition Check ===\n');
console.log(`Total --rtn-* variables defined: ${definedVars.size}`);
console.log(`Total --rtn-* variables used:    ${usedVars.size}`);

if (undefinedVars.length > 0) {
  console.error('\nFAIL: Undefined CSS variables found:');
  for (const item of undefinedVars) {
    console.error(`  - ${item.varName} in ${item.locations.join(', ')}`);
  }
  console.log('\n====================================================\n');
  process.exit(1);
} else {
  console.log('\nPASS: All var(--rtn-...) references are properly defined.');
  console.log('\n====================================================\n');
  process.exit(0);
}
