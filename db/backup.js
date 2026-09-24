require('dotenv').config();
const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');
const { databaseOptions } = require('./database');

async function backupDatabase() {
    const config = databaseOptions();
    const directory = path.join(__dirname, 'backups');
    fs.mkdirSync(directory, { recursive: true });
    const output = path.join(directory, 'taascor-' + new Date().toISOString().replace(/[:.]/g, '-') + '.sql');
    const args = ['--host=' + config.host, '--port=' + config.port, '--user=' + config.user,
        '--single-transaction', '--skip-lock-tables', '--hex-blob', '--default-character-set=utf8mb4',
        '--result-file=' + output, config.database];
    if (config.ssl) throw new Error('For TLS databases, use your provider backup tools or a dump client configured with verified TLS.');
    await new Promise((resolve, reject) => {
        const child = spawn(process.env.MYSQLDUMP_PATH || 'mysqldump', args, {
            shell: false, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'],
            env: { ...process.env, MYSQL_PWD: config.password }
        });
        let errorText = '';
        child.stderr.on('data', chunk => { errorText += chunk.toString(); });
        child.once('error', reject);
        child.once('exit', code => code === 0 ? resolve() : reject(new Error('MySQL backup failed: ' + errorText)));
    });
    console.log('Backup saved to ' + output);
    return output;
}
if (require.main === module) backupDatabase().catch(error => { console.error(error.message); process.exitCode = 1; });
module.exports = { backupDatabase };
