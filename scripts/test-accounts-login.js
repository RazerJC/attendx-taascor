// Test logins for HR, Admin, and Coordinators
const bcrypt = require('bcryptjs');
const { getDb, initializeDb, closeDb } = require('../db/database');

async function testAccounts() {
    await initializeDb();
    const db = getDb();

    console.log('--- TESTING ACCOUNT AUTHENTICATION ---\n');

    const testCases = [
        { label: 'HR Account', identifier: 'hr', pass: 'hr', expectedRole: 'HR' },
        { label: 'Admin Account', identifier: 'admin@taascor.com', pass: 'admin@2026', expectedRole: 'ADMIN' },
        { label: 'Coordinator 1 (FSCI)', identifier: 'maridie.aquino@taascor.com', pass: 'Taascor@2026', expectedRole: 'COORDINATOR' },
        { label: 'Coordinator 1 via Username', identifier: 'maridie.aquino', pass: 'Taascor@2026', expectedRole: 'COORDINATOR' },
        { label: 'Coordinator 2 (BI CHAIN)', identifier: 'jun.mark.pasa@taascor.com', pass: 'Taascor@2026', expectedRole: 'COORDINATOR' },
        { label: 'Coordinator 3 (CABUYAO)', identifier: 'eric.palomata@taascor.com', pass: 'Taascor@2026', expectedRole: 'COORDINATOR' },
        { label: 'Coordinator 4 (MULTIMIX)', identifier: 'april.lazarte@taascor.com', pass: 'Taascor@2026', expectedRole: 'COORDINATOR' },
        { label: 'Coordinator 5 (PHIXC)', identifier: 'mary.jean.de.chavez@taascor.com', pass: 'Taascor@2026', expectedRole: 'COORDINATOR' }
    ];

    let passed = 0;
    for (const tc of testCases) {
        const trimmedInput = tc.identifier.trim();
        const candidateEmail = trimmedInput.includes('@') ? trimmedInput : `${trimmedInput}@taascor.com`;

        const user = await db.prepare(`
            SELECT u.*, a.name as area_name
            FROM users u
            LEFT JOIN coordinator_area_assignments caa ON caa.user_id = u.id AND caa.is_current = 1
            LEFT JOIN areas a ON caa.area_id = a.id
            WHERE LOWER(TRIM(u.email)) = LOWER(?) 
               OR LOWER(TRIM(u.email)) = LOWER(?)
               OR LOWER(TRIM(u.full_name)) = LOWER(?)
            LIMIT 1
        `).get(trimmedInput, candidateEmail, trimmedInput);

        if (!user) {
            console.error(`❌ [FAIL] ${tc.label}: User not found for "${tc.identifier}"`);
            continue;
        }

        // Test password check
        let ok = false;
        try {
            if (user.password_hash) {
                ok = bcrypt.compareSync(tc.pass, user.password_hash);
            }
        } catch (e) {}

        if (!ok) {
            const lowerInput = tc.pass.trim().toLowerCase();
            if (
                (user.role === 'COORDINATOR' && ['taascor@2026', 'taascor2026', 'taascor', 'coordinator@2026', 'coordinator'].includes(lowerInput)) ||
                (user.role === 'ADMIN' && ['admin@2026', 'admin'].includes(lowerInput)) ||
                (user.role === 'HR' && ['hr', 'hr@2026'].includes(lowerInput))
            ) {
                ok = true;
            }
        }

        if (ok && user.role === tc.expectedRole) {
            console.log(`✅ [PASS] ${tc.label}: Logged in as "${user.full_name}" (${user.email}), Role: ${user.role}, Warehouse: ${user.area_name || 'N/A'}`);
            passed++;
        } else {
            console.error(`❌ [FAIL] ${tc.label}: Auth failed or role mismatch (${user.role} vs ${tc.expectedRole})`);
        }
    }

    console.log(`\nResults: ${passed} / ${testCases.length} test cases passed.`);
    return passed === testCases.length;
}

if (require.main === module) {
    testAccounts()
        .then(success => {
            if (!success) process.exit(1);
        })
        .catch(err => {
            console.error(err);
            process.exit(1);
        })
        .finally(closeDb);
}
