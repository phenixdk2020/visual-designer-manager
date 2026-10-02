// Converts README.md (+ billeder/) in this folder into a Word document.
// Usage: npm install docx@9 && node byg-word.js [output.docx]
const fs = require('fs');
const path = require('path');
const {
  Document, Packer, Paragraph, TextRun, ImageRun, HeadingLevel, AlignmentType, Table, TableRow, TableCell,
  WidthType, BorderStyle, ShadingType, LevelFormat, Footer, Header, PageNumber, PageBreak,
} = require('docx');

const SRC = __dirname;
const OUT = process.argv[2] || path.join(SRC, 'Brugermanual-Visual-Designer-Manager-2.0.0-rc.9.docx');
const md = fs.readFileSync(path.join(SRC, 'README.md'), 'utf8').replace(/\r/g, '');

// A4 with 2 cm margins → usable width 17 cm.
const PAGE = { width: 11906, height: 16838, margin: 1134 };
const CONTENT_DXA = PAGE.width - 2 * PAGE.margin;           // 9638
const MAX_W_PX = Math.floor(CONTENT_DXA / 1440 * 96);        // ~642 px
const MAX_H_PX = 820;                                        // keep tall screenshots on one page
const BLUE = '1F3A5F', GREY = '5A6270', LIGHT = 'EEF2F7', TIP = 'FFF6E0', TIP_BORDER = 'E0A800';
const FONT = 'Calibri';

function jpegSize(buf) {
  let i = 2;
  while (i < buf.length) {
    if (buf[i] !== 0xff) { i++; continue; }
    const m = buf[i + 1], len = buf.readUInt16BE(i + 2);
    if (m >= 0xc0 && m <= 0xcf && ![0xc4, 0xc8, 0xcc].includes(m)) return { h: buf.readUInt16BE(i + 5), w: buf.readUInt16BE(i + 7) };
    i += 2 + len;
  }
  throw new Error('Not a JPEG');
}

// Inline markdown → TextRuns (bold, italic, code, links → plain text).
function runs(text, base = {}) {
  const out = [];
  const re = /(\*\*[^*]+\*\*|\*[^*\s][^*]*\*|`[^`]+`|\[[^\]]+\]\([^)]+\))/g;
  let last = 0, m;
  const push = (t, o = {}) => { if (t) out.push(new TextRun({ text: t, font: FONT, ...base, ...o })); };
  while ((m = re.exec(text))) {
    push(text.slice(last, m.index));
    const tok = m[0];
    if (tok.startsWith('**')) push(tok.slice(2, -2), { bold: true });
    else if (tok.startsWith('`')) push(tok.slice(1, -1), { font: 'Consolas', size: (base.size || 22) - 2, shading: { type: ShadingType.CLEAR, color: 'auto', fill: 'F1F1F1' } });
    else if (tok.startsWith('[')) push(tok.match(/^\[([^\]]+)\]/)[1]);
    else push(tok.slice(1, -1), { italics: true });
    last = m.index + tok.length;
  }
  push(text.slice(last));
  return out;
}

function image(file, caption, maxW = MAX_W_PX) {
  const buf = fs.readFileSync(path.join(SRC, file));
  const { w, h } = jpegSize(buf);
  let scale = Math.min(1, maxW / w, MAX_H_PX / h);
  const para = [new Paragraph({
    alignment: AlignmentType.CENTER, spacing: { before: 120, after: caption ? 40 : 160 }, keepNext: !!caption,
    children: [new ImageRun({ type: 'jpg', data: buf, transformation: { width: Math.round(w * scale), height: Math.round(h * scale) }, altText: { title: caption || file, description: caption || file, name: path.basename(file) } })],
  })];
  if (caption) para.push(new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 200 }, children: [new TextRun({ text: caption, italics: true, size: 18, color: GREY, font: FONT })] }));
  return para;
}

function callout(text) {
  const isTip = /^\*\*(Tip|Vigtigt)/.test(text);
  return new Paragraph({
    spacing: { before: 120, after: 160 }, indent: { left: 200, right: 200 },
    shading: { type: ShadingType.CLEAR, color: 'auto', fill: isTip ? TIP : LIGHT },
    border: { left: { style: BorderStyle.SINGLE, size: 18, color: isTip ? TIP_BORDER : BLUE, space: 8 } },
    children: runs(text, { size: 21 }),
  });
}

function table(rows) {
  const cells = rows.map(r => r.replace(/^\||\|$/g, '').split('|').map(c => c.trim()));
  const header = cells[0], body = cells.slice(2);
  const n = header.length;
  const widths = n === 2 ? [Math.round(CONTENT_DXA * 0.34), CONTENT_DXA - Math.round(CONTENT_DXA * 0.34)] : Array(n).fill(Math.floor(CONTENT_DXA / n));
  const border = { style: BorderStyle.SINGLE, size: 4, color: 'C9D1DC' };
  const mk = (txt, i, head) => new TableCell({
    width: { size: widths[i], type: WidthType.DXA },
    shading: head ? { type: ShadingType.CLEAR, color: 'auto', fill: BLUE } : undefined,
    margins: { top: 80, bottom: 80, left: 120, right: 120 },
    children: [new Paragraph({ children: runs(txt, head ? { bold: true, color: 'FFFFFF', size: 20 } : { size: 20 }) })],
  });
  return new Table({
    width: { size: CONTENT_DXA, type: WidthType.DXA }, columnWidths: widths,
    borders: { top: border, bottom: border, left: border, right: border, insideHorizontal: border, insideVertical: border },
    rows: [new TableRow({ tableHeader: true, cantSplit: true, children: header.map((c, i) => mk(c, i, true)) }), ...body.map(r => new TableRow({ cantSplit: true, children: r.map((c, i) => mk(c, i, false)) }))],
  });
}

// ---- Parse markdown into blocks ----
const lines = md.split('\n');
const children = [];
let title = 'Brugermanual', subtitle = '';
let i = 0, inContents = false, listCount = 0;
const chapters = [];
const para = t => new Paragraph({ spacing: { after: 140, line: 300 }, children: runs(t) });

while (i < lines.length) {
  const line = lines[i];
  if (line.startsWith('# ')) { title = line.slice(2).trim(); i++; continue; }
  if (!subtitle && line.startsWith('Version')) { subtitle = line.replace(/\*\*/g, ''); i++; continue; }
  if (line.startsWith('## ')) {
    const h = line.slice(3).trim();
    inContents = h === 'Indhold';
    if (inContents) { i++; continue; }                                  // replaced by a real TOC
    children.push(new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun({ text: h, font: FONT })] }));
    i++; continue;
  }
  if (inContents) { const c = line.match(/^\d+\.\s+\[([^\]]+)\]/); if (c) chapters.push(c[1]); i++; continue; }
  if (line.startsWith('### ')) { children.push(new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun({ text: line.slice(4).trim(), font: FONT })] })); i++; continue; }
  if (line.trim() === '---' || line.trim() === '') { i++; continue; }
  let m;
  if ((m = line.match(/^!\[([^\]]*)\]\(([^)]+)\)/))) { children.push(...image(m[2], m[1])); i++; continue; }
  if (line.trim() === '<p>') {                                          // side-by-side phone screenshots
    const imgs = []; i++;
    while (i < lines.length && lines[i].trim() !== '</p>') { const g = lines[i].match(/src="([^"]+)" alt="([^"]*)"/); if (g) imgs.push(g); i++; }
    i++;
    const colW = Math.floor(CONTENT_DXA / imgs.length), none = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
    children.push(new Table({
      width: { size: CONTENT_DXA, type: WidthType.DXA }, columnWidths: imgs.map(() => colW),
      borders: { top: none, bottom: none, left: none, right: none, insideHorizontal: none, insideVertical: none },
      rows: [new TableRow({ children: imgs.map(g => new TableCell({ width: { size: colW, type: WidthType.DXA }, borders: { top: none, bottom: none, left: none, right: none }, children: image(g[1], g[2], 260) })) })],
    }));
    continue;
  }
  if (line.startsWith('>')) {
    const buf = [];
    while (i < lines.length && lines[i].startsWith('>')) { buf.push(lines[i].replace(/^>\s?/, '')); i++; }
    buf.join('\n').split(/\n\s*\n/).forEach(p => children.push(callout(p.replace(/\n/g, ' ').trim())));
    continue;
  }
  if (line.startsWith('|')) {
    const rows = [];
    while (i < lines.length && lines[i].startsWith('|')) rows.push(lines[i++]);
    children.push(table(rows), new Paragraph({ spacing: { after: 120 }, children: [] }));
    continue;
  }
  if ((m = line.match(/^(\s*)(\d+)\.\s+(.*)/)) || (m = line.match(/^(\s*)([-*])\s+(.*)/))) {
    const instance = ++listCount;                                        // each list restarts at 1
    while (i < lines.length && (m = (lines[i].match(/^(\s*)(\d+)\.\s+(.*)/) || lines[i].match(/^(\s*)([-*])\s+(.*)/)))) {
      const level = m[1].length >= 2 ? 1 : 0;
      const isNum = /\d/.test(m[2]);
      children.push(new Paragraph({ numbering: { reference: isNum ? 'num' : 'bullet', level, instance }, spacing: { after: 80 }, children: runs(m[3]) }));
      i++;
    }
    continue;
  }
  // plain paragraph (join soft-wrapped lines)
  const buf = [line];
  i++;
  while (i < lines.length && lines[i].trim() && !/^(#|>|\||!\[|\s*\d+\.\s|\s*[-*]\s|<p>|---)/.test(lines[i])) buf.push(lines[i++]);
  children.push(para(buf.join(' ').trim()));
}

// ---- Front page + TOC ----
const logo = fs.readFileSync(path.join(SRC, 'billeder/30-website-forside.jpg'));
const ls = jpegSize(logo);
const cover = [
  new Paragraph({ spacing: { before: 1800 }, children: [] }),
  new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'Visual Designer Manager', font: FONT, size: 60, bold: true, color: BLUE })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 120 }, children: [new TextRun({ text: 'Brugermanual', font: FONT, size: 40, color: BLUE })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 600 }, children: [new TextRun({ text: subtitle, font: FONT, size: 24, color: GREY })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, children: [new ImageRun({ type: 'jpg', data: logo, transformation: { width: 460, height: Math.round(ls.h * 460 / ls.w * 0.55) }, altText: { title: 'Eksempelsite', description: 'Forsiden af eksempelsitet Nordisk Veteranklub', name: 'forside' } })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 200 }, children: [new TextRun({ text: 'Eksempel: websitet for Nordisk Veteranklub, bygget trin for trin i manualen', italics: true, size: 18, color: GREY, font: FONT })] }),
  new Paragraph({ children: [new PageBreak()] }),
  new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun({ text: 'Indhold', font: FONT })] }),
  ...chapters.map(c => new Paragraph({ spacing: { after: 100 }, border: { bottom: { style: BorderStyle.DOTTED, size: 4, color: 'C9D1DC', space: 4 } }, children: [new TextRun({ text: `${chapters.indexOf(c) + 1}.  ${c}`, font: FONT, size: 24, color: '222222' })] })),
  new Paragraph({ children: [new PageBreak()] }),
];

const doc = new Document({
  creator: 'Visual Designer Manager', title: 'Visual Designer Manager – brugermanual', description: subtitle,
  styles: {
    default: { document: { run: { font: FONT, size: 22 } } },
    paragraphStyles: [
      { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { size: 34, bold: true, color: BLUE, font: FONT }, paragraph: { spacing: { before: 480, after: 160 }, outlineLevel: 0, keepNext: true } },
      { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { size: 26, bold: true, color: BLUE, font: FONT }, paragraph: { spacing: { before: 300, after: 120 }, outlineLevel: 1, keepNext: true } },
    ],
  },
  numbering: { config: [
    { reference: 'bullet', levels: [0, 1].map(l => ({ level: l, format: LevelFormat.BULLET, text: l ? '◦' : '•', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 720 + l * 360, hanging: 360 } } } })) },
    { reference: 'num', levels: [0, 1].map(l => ({ level: l, format: l ? LevelFormat.LOWER_LETTER : LevelFormat.DECIMAL, text: l ? '%2)' : '%1.', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 720 + l * 360, hanging: 360 } } } })) },
  ] },
  sections: [{
    properties: { page: { size: { width: PAGE.width, height: PAGE.height }, margin: { top: PAGE.margin, bottom: PAGE.margin, left: PAGE.margin, right: PAGE.margin } }, titlePage: true },
    headers: { default: new Header({ children: [new Paragraph({ alignment: AlignmentType.RIGHT, children: [new TextRun({ text: 'Visual Designer Manager – brugermanual', size: 16, color: GREY, font: FONT })] })] }) },
    footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ children: ['Side ', PageNumber.CURRENT, ' af ', PageNumber.TOTAL_PAGES], size: 16, color: GREY, font: FONT })] })] }) },
    children: [...cover, ...children],
  }],
});

Packer.toBuffer(doc).then(b => { fs.writeFileSync(OUT, b); console.log('wrote', OUT, b.length, 'bytes'); });
