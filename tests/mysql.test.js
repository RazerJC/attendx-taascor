const test = require('node:test');
const assert = require('node:assert/strict');
const mysql = require('mysql2/promise');
const { randomUUID } = require('crypto');
const fs = require('fs');
const path = require('path');
const { isExpired } = require('../services/time');

test('UTC token expiry is independent of the machine timezone', () => {
    const now = Date.parse('2026-09-16T03:00:00Z');
    assert.equal(isExpired('2026-09-16 04:00:00', now), false);
    assert.equal(isExpired('2026-09-16 02:00:00', now), true);
    assert.equal(isExpired('invalid', now), true);
});

test('MySQL migration, workflows, HTTP pages, and durable sessions', { skip: !process.env.TEST_DB_HOST }, async t => {
    const name = `taascor_test_${process.pid}_${Date.now()}`;
    assert.match(name, /^taascor_test_\d+_\d+$/);
    const options = {
        host: process.env.TEST_DB_HOST, port: Number(process.env.TEST_DB_PORT || 3306),
        user: process.env.TEST_DB_USER, password: process.env.TEST_DB_PASSWORD || ''
    };
    const control = await mysql.createConnection(options);
    await control.query(`CREATE DATABASE \`${name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`);
    Object.assign(process.env, {
        DB_HOST: options.host, DB_PORT: String(options.port), DB_USER: options.user,
        DB_PASSWORD: options.password, DB_NAME: name, NODE_ENV: 'test',
        SESSION_SECRET: randomUUID() + randomUUID(),
        ADMIN_EMAIL: 'admin@taascor.com', ADMIN_PASSWORD: randomUUID() + 'Aa1!'
    });
    const { initializeDb, getDb, closeDb } = require('../db/database');
    const db = getDb();
    let server, app;
    try {
        await t.test('schema can initialize twice and enforces foreign keys', async () => {
            await initializeDb(); await initializeDb();
            await assert.rejects(db.prepare('INSERT INTO departments (area_id, name) VALUES (?, ?)').run(999, 'invalid'), { code: 'ER_NO_REFERENCED_ROW_2' });
        });
        await t.test('SQLite import preserves IDs, self references and timestamps; repeat import refuses nonempty target', async () => {
            const { DatabaseSync } = require('node:sqlite');
            const dir = fs.mkdtempSync(path.join(require('os').tmpdir(), 'taascor-import-'));
            const fixture = path.join(dir, 'fixture.db');
            const source = new DatabaseSync(fixture);
            source.exec(fs.readFileSync(path.join(__dirname, '../db/schema.sqlite.sql'), 'utf8'));
            source.prepare("INSERT INTO users (id,email,password_hash,full_name,role,status,created_at) VALUES (1,?,'hash','Import Admin','ADMIN','active',?)").run('import@taascor.com', '2026-09-16T03:04:05.000Z');
            source.exec('UPDATE users SET approved_by=1 WHERE id=1');
            source.close();
            const { importSqlite } = require('../db/import-sqlite');
            const counts = await importSqlite(fixture);
            assert.equal(counts.users, 1);
            const user = await db.prepare('SELECT * FROM users WHERE id=1').get();
            assert.equal(user.approved_by, 1);
            assert.equal(user.created_at, '2026-09-16 03:04:05');
            await assert.rejects(importSqlite(fixture), /not empty/);
            await db.exec('UPDATE users SET approved_by=NULL');
            await db.exec('DELETE FROM users');
            await db.exec('ALTER TABLE users AUTO_INCREMENT=1');
            const invalid = new DatabaseSync(fixture);
            invalid.exec("INSERT INTO areas (id,name) VALUES (1,'Import Area')");
            invalid.exec("INSERT INTO employees (employee_id,first_name,last_name,full_name,area_id,employment_start_date) VALUES ('BAD-DATE','Import','Test','Import Test',1,'03/03/0206')");
            invalid.close();
            await assert.rejects(importSqlite(fixture), /employees record 1, employment_start_date/);
            assert.equal((await db.prepare('SELECT COUNT(*) AS count FROM users').get()).count,0);
            await db.exec('ALTER TABLE users AUTO_INCREMENT=1');
            await db.exec('ALTER TABLE areas AUTO_INCREMENT=1');
            for (const suffix of ['', '-wal', '-shm']) {
                if (fs.existsSync(fixture + suffix)) fs.unlinkSync(fixture + suffix);
            }
            fs.rmdirSync(dir);
        });
        await require('../db/seed').createInitialAdmin();
        await require('../db/seed').seedSampleData();
        await t.test('all eight existing business verification scenarios', async () => {
            await require('../verify').runVerification();
        });
        await t.test('failed transaction rolls back its related writes', async () => {
            await assert.rejects(db.transaction(async () => {
                await db.prepare('INSERT INTO areas (name) VALUES (?)').run('rollback-test');
                throw new Error('rollback requested');
            })(), /rollback requested/);
            assert.equal(await db.prepare('SELECT id FROM areas WHERE name=?').get('rollback-test'), undefined);
        });
        await t.test('concurrent absence submissions create one case', async () => {
            const result = await db.prepare("INSERT INTO employees (employee_id,first_name,last_name,full_name,area_id,coordinator_id,employment_start_date) VALUES ('RACE-1','Race','Test','Race Test',1,3,'2026-09-01')").run();
            const { handleEmployeeAbsence } = require('../services/btw');
            const cases = await Promise.all(Array.from({length:4}, () => handleEmployeeAbsence(result.lastInsertRowid, '2026-09-16', 3)));
            assert.equal(new Set(cases.map(c => c.id)).size, 1);
        });
        app = require('../server'); server = await app.start(0);
        let base = `http://127.0.0.1:${server.address().port}`;
        const jar = {};
        async function request(url, init={}) {
            const response = await fetch(base + url, { ...init, redirect:'manual', headers:{...init.headers, ...(jar.cookie ? {cookie:jar.cookie} : {})} });
            const cookies = response.headers.getSetCookie();
            if(cookies.length) jar.cookie=cookies[0].split(';')[0];
            const text=await response.text();
            return {response,text};
        }
        async function login(email,password) {
            jar.cookie='';
            const page = await request('/login');
            assert.equal(page.response.status,200);
            const token=page.text.match(/name="_csrf"\s+value="([^"]+)"/)[1];
            const result=await request('/login',{method:'POST',headers:{'content-type':'application/x-www-form-urlencoded'},body:new URLSearchParams({email,password,_csrf:token}).toString()});
            assert.equal(result.response.status,302);
        }
        await t.test('admin pages and report export render with awaited query results', async () => {
            await login(process.env.ADMIN_EMAIL,process.env.ADMIN_PASSWORD);
            for(const url of ['/dashboard','/employees','/schedules','/attendance','/btw','/concerns','/manpower','/departments','/notifications','/admin/users','/admin/staff/2','/admin/areas','/admin/positions','/admin/audit-logs','/reports','/api/notifications/latest']) {
                const {response,text}=await request(url);
                assert.equal(response.status,200,`${url}: ${text.slice(-700)}`);
            }
            const file=await request('/reports/export');
            assert.equal(file.response.status,200);
        });
        await t.test('the same login survives server and session-store restart', async () => {
            const profilePage=await request('/profile');
            const profileToken=profilePage.text.match(/name="csrf-token" content="([^"]+)"/)[1];
            const avatar='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5xkAAAAASUVORK5CYII=';
            const updated=await request('/profile',{method:'POST',headers:{'content-type':'application/x-www-form-urlencoded','x-csrf-token':profileToken},body:new URLSearchParams({full_name:'System Administrator',profile_photo_data:avatar}).toString()});
            assert.equal(updated.response.status,302);
            assert.equal((await db.prepare('SELECT profile_photo FROM users WHERE id=1').get()).profile_photo,avatar);
            const originalCookie=jar.cookie;
            await new Promise(resolve=>server.close(resolve)); server=null;
            await app.shutdown();
            delete require.cache[require.resolve('../server')];
            app=require('../server'); server=await app.start(0);
            base=`http://127.0.0.1:${server.address().port}`;
            const result=await request('/dashboard');
            assert.equal(result.response.status,200);
            assert.equal(jar.cookie,originalCookie);
            assert.ok((await request('/profile')).text.includes(avatar));
        });
        async function postForm(url, values) {
            const page = await request('/dashboard');
            const token = page.text.match(/name="csrf-token" content="([^"]+)"/)[1];
            return request(url, {method:'POST',headers:{'content-type':'application/x-www-form-urlencoded','x-csrf-token':token},body:new URLSearchParams(values).toString()});
        }
        await t.test('HTTP schedule and attendance saves preserve IDs, versions, statuses, and report data', async () => {
            const emp = await db.prepare('SELECT id FROM employees WHERE area_id=1 LIMIT 1').get();
            const day = '2026-09-17';
            const schedule = {employee_ids:String(emp.id),start_date:day,shift_start:'08:00',shift_end:'17:00'};
            assert.equal((await postForm('/schedules',schedule)).response.status,302);
            const before=await db.prepare('SELECT * FROM employee_schedules WHERE employee_id=? AND work_date=?').get(emp.id,day);
            for(const status of ['present','late','absent','on_leave','rest_day','no_work','leave','sent_home']) {
                const result=await request('/api/attendance/save',{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify({work_date:day,attendance:{[emp.id]:{status,schedule_id:before.id}}})});
                assert.equal(result.response.status,200,result.text);
                assert.equal(JSON.parse(result.text).saved,1);
            }
            assert.equal((await postForm('/schedules',{...schedule,shift_start:'09:00'})).response.status,302);
            const after=await db.prepare('SELECT * FROM employee_schedules WHERE employee_id=? AND work_date=?').get(emp.id,day);
            assert.equal(after.id,before.id); assert.equal(after.version,before.version+1);
            const attendance=await db.prepare('SELECT * FROM employee_attendance WHERE employee_id=? AND work_date=?').get(emp.id,day);
            assert.equal(attendance.schedule_id,before.id); assert.equal(attendance.version,8);
            const csv=await request('/reports/export?format=csv&date_from='+day+'&date_to='+day);
            assert.equal(csv.response.status,200); assert.match(csv.text,/SENT_HOME/);
        });
        await t.test('HR dashboard and detail pages render', async () => {
            await login('hr.santos@taascor.com','HrUser123!');
            for(const url of ['/dashboard','/btw/1','/concerns/1','/manpower/1','/employees/1','/profile','/admin/pending-coordinators']) {
                const result=await request(url); assert.equal(result.response.status,200,url+': '+result.text.slice(-300));
            }
        });
        await t.test('HR attendance and manpower acceptance are recorded and protected', async () => {
            assert.equal((await request('/admin/staff/2')).response.status,403);
            assert.equal((await postForm('/coordinator-attendance/time-in',{})).response.status,302);
            const hr=await db.prepare("SELECT id FROM users WHERE email='hr.santos@taascor.com'").get();
            const record=await db.prepare('SELECT * FROM coordinator_attendance WHERE user_id=? ORDER BY id DESC LIMIT 1').get(hr.id);
            assert.ok(record.time_in);
            await postForm('/coordinator-attendance/time-out',{});
            assert.ok((await db.prepare('SELECT time_out FROM coordinator_attendance WHERE id=?').get(record.id)).time_out);
            assert.equal((await postForm('/coordinator-attendance/approve-correction',{attendance_id:record.id,new_time_in:'07:00'})).response.status,403);
            const claim=await postForm('/manpower/1/claim',{});
            assert.equal(claim.response.status,302);
            assert.equal((await db.prepare('SELECT hr_id FROM manpower_request_handlers WHERE request_id=1').get()).hr_id,hr.id);
            await postForm('/manpower/1/claim',{});
            assert.equal((await db.prepare('SELECT COUNT(*) AS n FROM manpower_request_handlers WHERE request_id=1').get()).n,1);
            assert.match((await request('/manpower/1')).text,/Maria Santos/);
        });
        await t.test('shared status preserves absence dates, HR action history, rejection reason and suspension dates', async () => {
            const employee = await db.prepare('SELECT id FROM employees WHERE area_id=1 LIMIT 1').get();
            const id = employee.id;
            await db.prepare("INSERT INTO employee_attendance (employee_id,work_date,status,recorded_by) VALUES (?,'2026-08-03','absent',2),(?,'2026-08-04','absent',2)").run(id,id);
            await db.prepare("INSERT INTO employee_schedules (employee_id,work_date,is_rest_day,created_by) VALUES (?,'2026-08-04',1,3)").run(id);
            const caseResult = await db.prepare("INSERT INTO btw_cases (case_ref,employee_id,area_id,coordinator_id,status,absence_dates) VALUES ('TEST-STATUS',?,1,3,'pending_hr_review','[\"2026-08-03\"]')").run(id);
            const caseId = caseResult.lastInsertRowid;
            const body = {decision:'not_approved',hr_remarks:'Test HR reason',action_type:'suspension',issued_date:'2026-08-05',suspension_start:'2026-08-06',suspension_end:'2026-08-08',authorized_return_date:'2026-08-09'};
            await postForm('/btw/'+caseId+'/decision',{...body,authorized_return_date:'2026-08-07'});
            assert.equal((await db.prepare('SELECT COUNT(*) AS n FROM btw_hr_actions WHERE case_id=?').get(caseId)).n,0);
            await postForm('/btw/'+caseId+'/decision',body);
            let result = await request('/employees/'+id+'/status?from=2026-08-01&to=2026-08-31');
            let data = JSON.parse(result.text);
            assert.deepEqual(data.absences.map(a=>a.work_date),['2026-08-03']);
            assert.equal(data.actions[0].suspension_end,'2026-08-08');
            assert.equal(data.actions[0].return_date,'2026-08-09');
            assert.equal(data.cases.find(c=>c.id===caseId).status,'not_approved');
            await postForm('/btw/'+caseId+'/decision',{decision:'approved',hr_remarks:'Approved after review',authorized_return_date:'2026-08-09'});
            data=JSON.parse((await request('/employees/'+id+'/status?all=1')).text);
            assert.equal(data.actions.filter(a=>a.case_id===caseId).length,2);
            assert.equal((await request('/employees/'+id+'/status?from=bad')).response.status,400);
            const dashboard = await request('/dashboard?date=2026-08-04');
            assert.equal(dashboard.response.status,200); assert.match(dashboard.text,/Rest Day/); assert.match(dashboard.text,/Unmarked/);
            await login('juan.delacruz@taascor.com','Coord123!');
            data=JSON.parse((await request('/employees/'+id+'/status?all=1')).text);
            assert.equal(data.actions.find(a=>a.action_type==='suspension').summary,'Test HR reason');
            assert.equal((await postForm('/btw/'+caseId+'/decision',body)).response.status,403);
            const other=await db.prepare('SELECT id FROM employees WHERE area_id=2 LIMIT 1').get();
            assert.equal((await request('/employees/'+other.id+'/status')).response.status,403);
        });
        await t.test('attendance form keeps employee IDs and saved records after reload', async () => {
            await login('juan.delacruz@taascor.com','Coord123!');
            const employees = await db.prepare('SELECT id FROM employees WHERE area_id=1 ORDER BY id LIMIT 2').all();
            assert.equal(employees.length, 2);
            const day = require('luxon').DateTime.now().setZone('Asia/Manila').toISODate();
            const form = {work_date:day};
            form[`attendance[e_${employees[0].id}][status]`] = 'present';
            form[`attendance[e_${employees[1].id}][status]`] = 'rest_day';
            assert.equal((await postForm('/attendance', form)).response.status, 302);
            const page = await request('/attendance');
            assert.match(page.text, /Saved attendance for 2 employee/);
            for (const [index, status] of ['present','rest_day'].entries()) {
                const record = await db.prepare('SELECT status FROM employee_attendance WHERE employee_id=? AND work_date=?').get(employees[index].id, day);
                assert.equal(record.status, status);
                assert.ok(page.text.includes(`data-status-input="${employees[index].id}" value="${status}"`));
            }
            assert.match((await request(`/reports?date_from=${day}&date_to=${day}`)).text, /Attendance Records/);
            const other = await db.prepare('SELECT id FROM employees WHERE area_id=2 LIMIT 1').get();
            await postForm('/attendance',{work_date:day,[`attendance[e_${other.id}][status]`]:'present'});
            assert.match((await request('/attendance')).text, /No attendance was saved/);
            // Legacy numeric keys are compacted by the form parser; refuse them instead of writing to another employee.
            const broken = await postForm('/attendance',{work_date:day,'attendance[16][status]':'present'});
            assert.equal(broken.response.status,302);
            assert.match((await request('/attendance')).text,/No attendance data submitted/);
        });
        await t.test('coordinator pages and area restrictions', async () => {
            await login('juan.delacruz@taascor.com','Coord123!');
            for(const url of ['/dashboard','/employees','/schedules','/attendance','/coordinator-attendance','/departments','/reports']) {
                const result=await request(url);
                assert.equal(result.response.status,200,url);
            }
            const other=await db.prepare('SELECT id FROM employees WHERE area_id=2 LIMIT 1').get();
            assert.equal((await request(`/employees/${other.id}`)).response.status,403);
        });
    } finally {
        if(server) await new Promise(resolve=>server.close(resolve));
        if(app) await app.shutdown();
        await closeDb();
        await control.query(`DROP DATABASE \`${name}\``);
        await control.end();
    }
});
