// Create HR account with username: hr, password: hr
const bcrypt = require('bcryptjs');
require('dotenv').config();
const { initializeDb, getDb, closeDb } = require('../db/database');

(async () => {
    await initializeDb();
    const db = getDb();
    
    const hash = bcrypt.hashSync('hr', 12);
    
    // Check if account already exists
    const existing = await db.prepare("SELECT id FROM users WHERE email = ?").get('hr');
    
    if (existing) {
        await db.prepare("UPDATE users SET password_hash = ?, role = 'HR', status = 'active' WHERE email = ?").run(hash, 'hr');
        console.log('HR account UPDATED successfully!');
    } else {
        await db.prepare(
            `INSERT INTO users (email, password_hash, full_name, role, status, email_verified, created_at, updated_at)
             VALUES (?, ?, 'HR Staff', 'HR', 'active', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())`
        ).run('hr', hash);
        console.log('HR account CREATED successfully!');
    }
    
    console.log('');
    console.log('  Username: hr');
    console.log('  Password: hr');
    console.log('  Role:     HR');
    console.log('');
    
    await closeDb();
})().catch(e => {
    console.error('Error:', e.message);
    process.exit(1);
});
