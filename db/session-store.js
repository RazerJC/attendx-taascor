const session = require('express-session');
const MySQLStore = require('express-mysql-session')(session);
const { getPool } = require('./database');

function createSessionStore() {
    return new MySQLStore({
        createDatabaseTable: false,
        endConnectionOnClose: false,
        expiration: 8 * 60 * 60 * 1000,
        checkExpirationInterval: 15 * 60 * 1000
    }, getPool());
}
module.exports = { createSessionStore };
