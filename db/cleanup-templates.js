const fs = require('fs');
const path = require('path');

const filePath = path.join(__dirname, '..', 'views', 'attendance', 'mark.ejs');
let content = fs.readFileSync(filePath, 'utf-8');

// Normalize line endings
content = content.replace(/\r\n/g, '\n');

let lines = content.split('\n');
console.log('Original line count:', lines.length);

// Define line ranges to REMOVE (1-indexed, inclusive)
const removals = [
    [83, 111],    // Template switcher section + trailing blank
    [191, 288],   // Forum Board template HTML
    [289, 361],   // Notion Spreadsheet template HTML
    [424, 473],   // Roster Cards Grid template HTML
    [582, 605],   // .tmpl-tab CSS (no longer needed)
    [720, 763],   // Forum CSS + Table CSS + blanks
    [778, 785],   // Cards CSS
    [790, 796],   // TEMPLATE_DESCRIPTIONS const + blank
    [800, 802],   // Template restore from localStorage in DOMContentLoaded
    [871, 897],   // switchTemplate function + trailing blank
    [993, 1009],  // toggleTableSort function + trailing blank
];

// Build a set of 0-indexed line indices to remove
const removeSet = new Set();
for (const [start, end] of removals) {
    for (let i = start - 1; i < end; i++) {
        removeSet.add(i);
    }
}

console.log('Total lines to remove:', removeSet.size);

// Filter out removed lines
let newLines = lines.filter((_, idx) => !removeSet.has(idx));
let newContent = newLines.join('\n');

// === String replacements for Kanban improvements ===

const replacements = [
    // 1. Make Kanban visible by default (remove display:none)
    {
        from: 'id="tmpl-kanban" class="template-view-container" style="display: none;"',
        to:   'id="tmpl-kanban"'
    },
    // 2. Increase Kanban grid gap and min column width
    {
        from: 'grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; align-items: start;',
        to:   'grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; align-items: start;'
    },
    // 3. Increase Kanban column header padding
    {
        from: 'class="kanban-col-header" style="padding: 10px 14px;',
        to:   'class="kanban-col-header" style="padding: 14px 18px;'
    },
    // 4. Increase Kanban cards body spacing
    {
        from: 'style="padding: 10px; display: flex; flex-direction: column; gap: 8px; max-height: 650px; overflow-y: auto;"',
        to:   'style="padding: 14px; display: flex; flex-direction: column; gap: 12px; max-height: 700px; overflow-y: auto;"'
    },
    // 5. Increase Kanban mini-card padding in CSS
    {
        from: '    padding: 8px 10px;\n    box-shadow: 0 1px 2px rgba(0,0,0,0.02);',
        to:   '    padding: 12px 14px;\n    box-shadow: 0 1px 3px rgba(0,0,0,0.04);'
    },
    // 6. Add applySortingAndFiltering() in DOMContentLoaded (replaces removed switchTemplate call)
    {
        from: '    updateLiveCounters();\n\n    // Search input listener',
        to:   '    updateLiveCounters();\n    applySortingAndFiltering();\n\n    // Search input listener'
    },
    // 7. Update CSS section comment
    {
        from: '/* TEMPLATE SWITCHER & SHARED STYLES                            */',
        to:   '/* SHARED STYLES                                                */'
    },
    // 8. Update template section comment
    {
        from: '<!-- TEMPLATE 3: KANBAN DEPARTMENT COLUMNS                        -->',
        to:   '<!-- ATTENDANCE: KANBAN DEPARTMENT COLUMNS                        -->'
    },
];

let replacementCount = 0;
for (const r of replacements) {
    if (newContent.includes(r.from)) {
        newContent = newContent.replace(r.from, r.to);
        replacementCount++;
        console.log(`  ✓ Replacement ${replacementCount}: applied`);
    } else {
        console.log(`  ✗ WARNING: Target not found: "${r.from.substring(0, 60)}..."`);
    }
}

console.log(`\nApplied ${replacementCount}/${replacements.length} replacements`);
console.log('New line count:', newContent.split('\n').length);

fs.writeFileSync(filePath, newContent, 'utf-8');
console.log('\n✅ mark.ejs updated! Only Kanban template remains with improved spacing.');
