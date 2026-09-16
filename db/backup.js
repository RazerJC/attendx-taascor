const fs = require('fs');
const path = require('path');
const { DB_PATH } = require('./database');

function backupDatabase() {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const backupDir = path.join(__dirname, 'backups');
    
    if (!fs.existsSync(backupDir)) {
        fs.mkdirSync(backupDir, { recursive: true });
    }

    const backupFile = path.join(backupDir, `taascor_backup_${timestamp}.db`);
    
    try {
        fs.copyFileSync(DB_PATH, backupFile);
        console.log(`Database backup created successfully at: ${backupFile}`);
        return backupFile;
    } catch (err) {
        console.error('Failed to create database backup:', err.message);
        throw err;
    }
}

if (require.main === module) {
    backupDatabase();
}

module.exports = { backupDatabase };
