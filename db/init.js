const { initializeDb, closeDb } = require('./database');
const { createInitialAdmin } = require('./seed');
initializeDb().then(createInitialAdmin).then(() => console.log('MySQL schema and initial administrator are ready.'))
    .catch(error => { console.error(error.message); process.exitCode = 1; }).finally(closeDb);
