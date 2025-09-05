import { execFileSync } from 'child_process';
import fs from 'fs';

let built = false;
export async function buildCssOnce() {
  if (built && fs.existsSync('./dist/output.css')) return;
  if (fs.existsSync('./node_modules/.bin/tailwindcss') && fs.existsSync('./src/input.css')) {
    execFileSync('./node_modules/.bin/tailwindcss',
      ['-i','./src/input.css','-o','./dist/output.css'],
      { stdio: 'inherit' });
  } else {
    console.warn('⚠️ tailwind or src/input.css not found; skipping CSS build');
  }
  built = true;
}
