// Seed / Sync Coordinators and Warehouses from parsed Excel roster
require('dotenv').config();
const bcrypt = require('bcryptjs');
const path = require('path');
const fs = require('fs');
const { getDb, initializeDb, closeDb } = require('../db/database');

async function seedCoordinatorsAndWarehouses() {
    await initializeDb();
    const db = getDb();

    console.log('--- SYNCING WAREHOUSES / AREAS ---');

    // 1. Get or create Admin user ID for assigned_by
    const adminUser = await db.prepare("SELECT id FROM users WHERE role = 'ADMIN' ORDER BY id ASC LIMIT 1").get();
    const adminId = adminUser ? adminUser.id : 1;

    // Load parsed JSON
    const dataPath = path.resolve(__dirname, 'coordinators-data.json');
    if (!fs.existsSync(dataPath)) {
        throw new Error('coordinators-data.json not found. Run parse-coordinators.js first.');
    }
    const data = JSON.parse(fs.readFileSync(dataPath, 'utf8'));

    // Map clients to standard area names and descriptions
    const clientToAreaName = {
        'PHIXC': 'SHOPEE PHIXC',
        'HEAD OFFICE / UNASSIGNED': 'HEAD OFFICE'
    };

    // Ensure all areas exist
    const areaIdMap = new Map(); // clientKey -> areaId

    for (const client of data.clients) {
        const areaName = clientToAreaName[client] || client;
        
        let area = await db.prepare('SELECT id, name FROM areas WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))').get(areaName);
        
        if (!area) {
            // Also check partial match for PHIXC
            if (client === 'PHIXC') {
                area = await db.prepare("SELECT id, name FROM areas WHERE name LIKE '%PHIXC%' LIMIT 1").get();
            }
        }

        if (!area) {
            const desc = client === 'CABUYAO OFFICE' ? 'Cabuyao Main Office & Operations' :
                         client === 'HEAD OFFICE / UNASSIGNED' ? 'Central Management & Support' :
                         `${client} Warehouse & Operations Facility`;
            
            const insertResult = await db.prepare(
                'INSERT INTO areas (name, description, is_active, created_at, created_by) VALUES (?, ?, 1, UTC_TIMESTAMP(), ?)'
            ).run(areaName, desc, adminId);
            
            area = { id: insertResult.lastInsertRowid, name: areaName };
            console.log(`Created new area: [${area.id}] ${areaName}`);
        } else {
            console.log(`Existing area found: [${area.id}] ${area.name}`);
        }

        areaIdMap.set(client, area.id);
        areaIdMap.set(areaName, area.id);
    }

    console.log(`\n--- SYNCING ${data.coordinators.length} COORDINATOR ACCOUNTS ---`);
    const defaultPassword = 'Taascor@2026';
    const passwordHash = bcrypt.hashSync(defaultPassword, 12);

    let createdCount = 0;
    let updatedCount = 0;
    let assignedCount = 0;

    for (const c of data.coordinators) {
        const targetAreaId = areaIdMap.get(c.client) || areaIdMap.get('HEAD OFFICE') || 1;

        // Check if user already exists
        // Match by email, or match by known special names, or match by full name
        let existingUser = await db.prepare('SELECT * FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))').get(c.generatedEmail);

        if (!existingUser) {
            // Try matching full name
            existingUser = await db.prepare('SELECT * FROM users WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?))').get(c.fullName);
        }

        if (!existingUser) {
            // Try matching raw name
            existingUser = await db.prepare('SELECT * FROM users WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?))').get(c.rawName);
        }

        // Special case matches for existing initial DB users
        if (!existingUser) {
            if (c.fullName.includes('JOHN CARL') || c.rawName.includes('JOHN CARL')) {
                existingUser = await db.prepare("SELECT * FROM users WHERE email = 'johncarl@taascor.com' OR full_name = 'JOHN CARL'").get();
            } else if (c.fullName.includes('MARIANNE') || c.rawName.includes('MARIANNE')) {
                existingUser = await db.prepare("SELECT * FROM users WHERE email = 'marianne@taascor.com' OR full_name = 'marianne'").get();
            }
        }

        let userId;

        if (existingUser) {
            userId = existingUser.id;
            // Update user details
            await db.prepare(`
                UPDATE users 
                SET full_name = ?,
                    password_hash = ?,
                    role = 'COORDINATOR',
                    status = 'active',
                    email_verified = 1,
                    updated_at = UTC_TIMESTAMP()
                WHERE id = ?
            `).run(c.fullName, passwordHash, userId);
            
            c.actualEmail = existingUser.email;
            updatedCount++;
        } else {
            // Insert new coordinator user
            const res = await db.prepare(`
                INSERT INTO users (email, password_hash, full_name, role, status, email_verified, must_change_password, created_at, updated_at)
                VALUES (?, ?, ?, 'COORDINATOR', 'active', 1, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            `).run(c.generatedEmail, passwordHash, c.fullName);
            
            userId = res.lastInsertRowid;
            c.actualEmail = c.generatedEmail;
            createdCount++;
        }

        // Now handle warehouse/area assignment in coordinator_area_assignments
        const currentAssign = await db.prepare(
            'SELECT * FROM coordinator_area_assignments WHERE user_id = ? AND is_current = 1'
        ).get(userId);

        if (!currentAssign || currentAssign.area_id !== targetAreaId) {
            // End old assignment
            if (currentAssign) {
                await db.prepare('UPDATE coordinator_area_assignments SET is_current = 0, ended_at = UTC_TIMESTAMP() WHERE user_id = ? AND is_current = 1').run(userId);
            }
            // Insert new active assignment
            await db.prepare(`
                INSERT INTO coordinator_area_assignments (user_id, area_id, assigned_by, assigned_at, is_current, remarks)
                VALUES (?, ?, ?, UTC_TIMESTAMP(), 1, ?)
            `).run(userId, targetAreaId, adminId, `Assigned to ${c.client} from roster`);
            
            assignedCount++;
        }
    }

    console.log(`\nSync complete!`);
    console.log(`Created coordinators: ${createdCount}`);
    console.log(`Updated coordinators: ${updatedCount}`);
    console.log(`Area assignments made/updated: ${assignedCount}`);

    // Query and return all users in system in clean order
    const allUsers = await db.prepare(`
        SELECT u.id, u.email, u.full_name, u.role, u.status,
               a.name as assigned_warehouse,
               caa.assigned_at
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

    console.log(`\nTotal Users in System: ${allUsers.length}`);
    return allUsers;
}

if (require.main === module) {
    seedCoordinatorsAndWarehouses()
        .then(users => {
            console.log('\n================ ALL ACCOUNTS IN SYSTEM (IN ORDER) ================');
            console.table(users.map(u => ({
                ID: u.id,
                Role: u.role,
                Name: u.full_name,
                Email_Username: u.email,
                Assigned_Warehouse: u.assigned_warehouse || 'N/A',
                Status: u.status
            })));
        })
        .catch(err => {
            console.error('Seed error:', err);
            process.exit(1);
        })
        .finally(closeDb);
}

module.exports = { seedCoordinatorsAndWarehouses };
