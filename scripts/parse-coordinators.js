const xlsx = require('xlsx');
const path = require('path');
const fs = require('fs');

const filePath = path.resolve(__dirname, '../node_modules/@types/node/REFERENCES/COORDINATORS-PER-CLIENT_WITH_EMAIL.xlsx');
if (!fs.existsSync(filePath)) {
  console.error('File not found:', filePath);
  process.exit(1);
}

const wb = xlsx.readFile(filePath);
const s1 = wb.Sheets['COOR'];
const d1 = xlsx.utils.sheet_to_json(s1, { header: 1 });
const s2 = wb.Sheets['COOR (2)'];
const d2 = xlsx.utils.sheet_to_json(s2, { header: 1 });

function normalizeName(str) {
  if (!str) return '';
  return str.replace(/^\*\s*/, '')
    .toUpperCase()
    .replace(/[^A-Z\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function formatFullName(raw) {
  let cleaned = raw.replace(/^\*\s*/, '').trim();
  if (cleaned.includes(',')) {
    const parts = cleaned.split(',');
    const last = parts[0].trim();
    const first = parts.slice(1).join(' ').trim();
    // Return capitalized formal name
    return `${first} ${last}`.replace(/\s+/g, ' ').trim();
  }
  return cleaned.replace(/\s+/g, ' ').trim();
}

function generateEmailSlug(fullName) {
  // Convert "Maridie Aquino" -> "maridie.aquino"
  return fullName.toLowerCase()
    .replace(/[^a-z0-9\s]/g, '')
    .trim()
    .split(/\s+/)
    .join('.');
}

const rawList = [];

// Sheet 1: COOR
for (let i = 2; i <= 50; i++) {
  const row = d1[i];
  if (!row || !row[1]) continue;
  const no = row[0];
  const rawName = String(row[1]).trim();
  const client = row[2] ? String(row[2]).trim() : '';
  const email = row[3] ? String(row[3]).trim() : '';
  rawList.push({
    source: 'COOR',
    sheetNo: no,
    rawName,
    client,
    email
  });
}

// Sheet 2: COOR (2)
for (let i = 2; i < d2.length; i++) {
  const row = d2[i];
  if (!row || !row[1]) continue;
  const rawName = String(row[1]).trim();
  const client = row[2] ? String(row[2]).trim() : '';
  const email = row[3] ? String(row[3]).trim() : '';

  rawList.push({
    source: 'COOR (2)',
    sheetNo: null,
    rawName,
    client,
    email
  });
}

// Deduplicate and merge by normalized name
const mergedCoordinators = [];
const seen = new Map();

for (const item of rawList) {
  const norm = normalizeName(item.rawName);
  if (!norm || norm.length < 3) continue;

  if (seen.has(norm)) {
    const existing = seen.get(norm);
    if (!existing.client && item.client) existing.client = item.client;
    if (!existing.email && item.email) existing.email = item.email;
    existing.sources.push(item.source);
  } else {
    const entry = {
      normName: norm,
      rawName: item.rawName,
      fullName: formatFullName(item.rawName),
      client: item.client || 'HEAD OFFICE / UNASSIGNED',
      givenEmail: item.email,
      sources: [item.source]
    };
    seen.set(norm, entry);
    mergedCoordinators.push(entry);
  }
}

console.log('Total unique coordinators found:', mergedCoordinators.length);

// Extract all unique clients / warehouses
const uniqueClients = [...new Set(mergedCoordinators.map(c => c.client).filter(Boolean))].sort();
console.log('\nUnique Clients / Warehouses (' + uniqueClients.length + '):');
uniqueClients.forEach((cl, i) => console.log(` ${i + 1}. ${cl}`));

// Generate emails ensuring uniqueness
const emailCounts = new Map();
for (const c of mergedCoordinators) {
  let slug = generateEmailSlug(c.fullName);
  if (!slug) slug = 'coordinator';
  
  if (!emailCounts.has(slug)) {
    emailCounts.set(slug, 1);
    c.generatedEmail = `${slug}@taascor.com`;
    c.username = slug;
  } else {
    const count = emailCounts.get(slug) + 1;
    emailCounts.set(slug, count);
    c.generatedEmail = `${slug}${count}@taascor.com`;
    c.username = `${slug}${count}`;
  }
  // Standard password
  c.password = 'Taascor@2026';
}

console.log('\nSample Generated Accounts (First 10):');
console.log(JSON.stringify(mergedCoordinators.slice(0, 10), null, 2));

// Export merged data to json for the seeder
fs.writeFileSync(path.resolve(__dirname, 'coordinators-data.json'), JSON.stringify({
  clients: uniqueClients,
  coordinators: mergedCoordinators
}, null, 2));

console.log('\nSaved parsed data to scripts/coordinators-data.json');
