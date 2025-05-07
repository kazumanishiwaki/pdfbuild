import fs from 'fs';
import ejs from 'ejs';
import { exec } from 'child_process';
import { promisify } from 'util';

const execAsync = promisify(exec);

// Load raw ACF data (flat structure)
const data = JSON.parse(fs.readFileSync('content.json', 'utf-8'));

// Convert fixed member fields → array
const members = [];
for (let i = 1; i <= 10; i++) {
  const name = data[`member${i}_name`];
  if (name && name.trim() !== '') {
    members.push({
      name,
      photo: data[`member${i}_photo`] || 'https://placehold.co/380x380.png',
      bio: data[`member${i}_bio`] || ''
    });
  }
}

// Merge into template context
const context = {
  title: data.title || '',
  lead: data.lead || '',
  members
};

// Compile EJS
const tpl = fs.readFileSync('templates/index.ejs', 'utf-8');
const html = ejs.render(tpl, context);

// Write HTML
fs.writeFileSync('index.html', html);
console.log('✅ index.html generated (ACF free build)');

const pdfName = 'booklet.pdf';

// Generate PDF using Vivliostyle
async function generatePDF() {
  try {
    // Tailwind CSSのビルド
    await execAsync('./node_modules/.bin/tailwindcss -i ./src/input.css -o ./dist/output.css');
    console.log('✅ Tailwind CSS built');

    // VivliostyleでPDFを生成（booklet.pdfのみ）
    await execAsync(`./node_modules/.bin/vivliostyle build index.html -o ${pdfName} --no-sandbox --no-default-style`);
    console.log(`✅ PDF generated: ${pdfName}`);
  } catch (error) {
    console.error('Error generating PDF:', error);
    process.exit(1);
  }
}

// PDF生成を実行
generatePDF();
