const { DatabaseSync } = require('node:sqlite');
const path = require('path');
const fs = require('fs');

const DB_PATH = path.join(__dirname, 'taascor.db');
const SCHEMA_PATH = path.join(__dirname, 'schema.sql');

let rawDb = null;

class WrappedDatabase {
    constructor(db) {
        this.db = db;
    }

    exec(sql) {
        return this.db.exec(sql);
    }

    prepare(sql) {
        const stmt = this.db.prepare(sql);
        return {
            run: (...params) => {
                const res = stmt.run(...params);
                return {
                    changes: res ? res.changes : 0,
                    lastInsertRowid: res && res.lastInsertRowid !== undefined ? Number(res.lastInsertRowid) : 0
                };
            },
            get: (...params) => {
                return stmt.get(...params);
            },
            all: (...params) => {
                return stmt.all(...params);
            }
        };
    }

    transaction(fn) {
        return (...args) => {
            this.db.exec('BEGIN IMMEDIATE');
            try {
                const result = fn(...args);
                this.db.exec('COMMIT');
                return result;
            } catch (err) {
                this.db.exec('ROLLBACK');
                throw err;
            }
        };
    }

    close() {
        if (this.db) {
            this.db.close();
            this.db = null;
        }
    }
}

let wrappedInstance = null;

function getDb() {
    if (!wrappedInstance) {
        rawDb = new DatabaseSync(DB_PATH);
        rawDb.exec('PRAGMA foreign_keys = ON;');
        rawDb.exec('PRAGMA journal_mode = WAL;');
        wrappedInstance = new WrappedDatabase(rawDb);
    }
    return wrappedInstance;
}

function initializeDb() {
    const database = getDb();
    const schema = fs.readFileSync(SCHEMA_PATH, 'utf8');
    database.exec(schema);
    console.log('Database initialized successfully with schema.');
    return database;
}

function runTransaction(fn) {
    const database = getDb();
    return database.transaction(fn)();
}

function closeDb() {
    if (wrappedInstance) {
        wrappedInstance.close();
        wrappedInstance = null;
        rawDb = null;
    }
}

process.on('exit', closeDb);

module.exports = { getDb, initializeDb, runTransaction, closeDb, DB_PATH };
