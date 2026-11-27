import { readFileSync } from 'node:fs';

const research = readFileSync('docs/ADMIN-DASHBOARD-DESIGN-RESEARCH.md', 'utf8');
const dashboard = readFileSync('design-system/pesantren-online/pages/admin-dashboard.md', 'utf8');
const master = readFileSync('design-system/pesantren-online/MASTER.md', 'utf8');

const required = [
    ['Linear research', 'https://linear.app/now/behind-the-latest-design-refresh'],
    ['Vercel research', 'https://vercel.com/changelog/dashboard-navigation-redesign-rollout'],
    ['Carbon table guidance', 'https://www.carbondesignsystem.com/building-blocks/core/components/data-table/guidelines'],
    ['Shopify table guidance', 'https://shopify.dev/docs/api/app-home/latest/patterns/compositions/index-table'],
    ['Graphify architecture trace', 'PlatformOverview'],
    ['dashboard page override', 'Ruang kendali'],
    ['master design link', 'pages/admin-dashboard.md'],
];

for (const [label, needle] of required) {
    const sources = label === 'dashboard page override' || label === 'master design link' ? dashboard + master : research;
    if (!sources.includes(needle)) {
        console.error(`Missing ${label}: ${needle}`);
        process.exitCode = 1;
    }
}

if (process.exitCode) process.exit(process.exitCode);
console.log('DASHBOARD_RESEARCH_VERIFIED');
