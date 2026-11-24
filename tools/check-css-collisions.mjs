#!/usr/bin/env node
/**
 * Verification script: check-css-collisions.mjs
 * Reads assets/styles/login.css and assets/styles/rtn-ui.css and fails if any class
 * selector is defined in both while both files can be loaded on the same page.
 */

import fs from 'fs';
import path from 'path';

function getDefinedClasses(filePath) {
  const content = fs.readFileSync(filePath, 'utf8');
  const clean = content.replace(/\/\*[\s\S]*?\*\//g, '');
  const classes = new Set();
  const ruleParts = clean.split('{');
  for (let i = 0; i < ruleParts.length - 1; i++) {
    const sel = ruleParts[i].split('}').pop();
    const matches = sel.matchAll(/\.([a-zA-Z0-9_-]+)/g);
    for (const match of matches) {
      classes.add(match[1]);
    }
  }
  return classes;
}

const loginCssPath = path.resolve('assets/styles/login.css');
const rtnUiCssPath = path.resolve('assets/styles/rtn-ui.css');
const loginTemplatePath = path.resolve('templates/security/login.html.twig');

const loginClasses = getDefinedClasses(loginCssPath);
const rtnClasses = getDefinedClasses(rtnUiCssPath);
const commonClasses = Array.from(loginClasses).filter(c => rtnClasses.has(c)).sort();

console.log('\n=== RolTech GMAO — CSS Class Collision Check ===\n');
console.log(`Classes in login.css:  ${loginClasses.size}`);
console.log(`Classes in rtn-ui.css: ${rtnClasses.size}`);
console.log(`Common class selectors: ${commonClasses.length} (${commonClasses.join(', ')})`);

// Check if login template loads rtn-ui.css
const loginTemplate = fs.readFileSync(loginTemplatePath, 'utf8');
const loadsParentStyles = /\{%\s*block\s+stylesheets\s*%\}\s*\{\{\s*parent\(\)\s*\}\}/.test(loginTemplate);
const directlyLoadsRtnUi = loginTemplate.includes('rtn-ui.css');
const bothLoadedOnLoginPage = loadsParentStyles || directlyLoadsRtnUi;

console.log(`Does /login template load rtn-ui.css? ${bothLoadedOnLoginPage ? 'YES (COLLISION RISK)' : 'NO (Isolated by page template)'}`);

if (bothLoadedOnLoginPage && commonClasses.length > 0) {
  console.error('\nFAIL: Class collision detected! Both files are loaded on the login page with colliding classes:');
  for (const c of commonClasses) {
    console.error(`  - .${c}`);
  }
  console.log('\n=================================================\n');
  process.exit(1);
} else {
  console.log('\nPASS: No runtime class collision between login.css and rtn-ui.css.');
  console.log('\n=================================================\n');
  process.exit(0);
}
