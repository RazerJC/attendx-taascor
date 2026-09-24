// List all accounts in the system in order
const { getDb, closeDb } = require('../db/database');

async function listAllAccounts() {
    const db = getDb();
    const accounts = await db.prepare(`
        SELECT u.id, u.role, u.full_name, u.email,
               COALESCE(a.name, 'N/A') as assigned_warehouse,
               u.status
        FROM users u
        LEFT JOIN coordinator_area_assignments caa ON caa.user_id = u.id AND caa.is_current = 1
        LEFT JOIN areas a ON caa.area_id = a.id
        ORDER BY 
            CASE u.role 
                WHEN 'ADMIN' THEN 1 
                WHEN 'HEAD_HR' THEN 2 
                WHEN 'HR' THEN 3 
                WHEN 'COORDINATOR' THEN 4 
                ELSE 5 
            END,
            u.id ASC
    `).all();

    return accounts;
}

if (require.main === module) {
    listAllAccounts()
        .then(accounts => {
            console.log(`\n================ TOTAL ACCOUNTS: ${accounts.length} ================`);
            console.table(accounts);
        })
        .catch(console.error)
        .finally(closeDb);
}

module.exports = { listAllAccounts };
