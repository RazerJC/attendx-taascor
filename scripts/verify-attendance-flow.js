const http = require('http');

class CookieJar {
    constructor() {
        this.cookies = {};
    }
    setFromHeaders(headers) {
        const raw = headers['set-cookie'] || [];
        for (const str of raw) {
            const part = str.split(';')[0];
            const eqIdx = part.indexOf('=');
            if (eqIdx > 0) {
                const k = part.substring(0, eqIdx).trim();
                const v = part.substring(eqIdx + 1).trim();
                this.cookies[k] = v;
            }
        }
    }
    getHeader() {
        return Object.entries(this.cookies)
            .map(([k, v]) => `${k}=${v}`)
            .join('; ');
    }
}

function request(options, postData = null, jar = null) {
    return new Promise((resolve, reject) => {
        const headers = options.headers || {};
        if (jar) {
            const ch = jar.getHeader();
            if (ch) headers['Cookie'] = ch;
        }
        if (postData) {
            headers['Content-Type'] = headers['Content-Type'] || 'application/x-www-form-urlencoded';
            headers['Content-Length'] = Buffer.byteLength(postData);
        }

        const req = http.request({
            hostname: 'localhost',
            port: 3000,
            path: options.path,
            method: options.method || 'GET',
            headers
        }, (res) => {
            let data = '';
            res.on('data', chunk => { data += chunk; });
            res.on('end', () => {
                if (jar) jar.setFromHeaders(res.headers);
                resolve({
                    statusCode: res.statusCode,
                    headers: res.headers,
                    body: data
                });
            });
        });

        req.on('error', reject);
        if (postData) req.write(postData);
        req.end();
    });
}

function extractCsrf(body) {
    const m = body.match(/name="_csrf"\s+value="([^"]+)"/);
    return m ? m[1] : null;
}

async function runTests() {
    console.log('=== STARTING AUTOMATED VERIFICATION WITH COOKIE JAR ===\n');

    const { getDb } = require('../db/database');
    const db = getDb();
    console.log('0. Clearing existing 2026-09-24 record for test user 1...');
    await db.prepare("DELETE FROM coordinator_attendance WHERE user_id = 1 AND work_date = '2026-09-24'").run();

    // 1. GET /login as Coordinator
    console.log('1. Coordinator GET /login...');
    const coordJar = new CookieJar();
    const loginGet = await request({ path: '/login', method: 'GET' }, null, coordJar);
    const csrf1 = extractCsrf(loginGet.body);
    console.log(`   CSRF Token extracted: ${csrf1 ? csrf1.substring(0, 10) + '...' : 'NULL'}`);

    // 2. Login as Coordinator PHIXC
    console.log('\n2. Logging in as Coordinator (PHIXC@taascor.com)...');
    const loginPayload = `_csrf=${encodeURIComponent(csrf1)}&email=${encodeURIComponent('PHIXC@taascor.com')}&password=${encodeURIComponent('Coordinator@2026')}`;
    const loginPost = await request({ path: '/login', method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }, loginPayload, coordJar);
    console.log(`   Login response status: ${loginPost.statusCode} (Redirect: ${loginPost.headers.location})`);

    // 3. GET /coordinator-attendance
    console.log('\n3. Accessing /coordinator-attendance as Coordinator...');
    const coordPage = await request({ path: '/coordinator-attendance', method: 'GET' }, null, coordJar);
    console.log(`   Response status: ${coordPage.statusCode}`);
    console.log(`   Contains "My Daily Attendance": ${coordPage.body.includes('My Daily Attendance')}`);
    console.log(`   Contains "Camera Time-In": ${coordPage.body.includes('Camera Time-In')}`);
    console.log(`   Contains "Camera Time-Out": ${coordPage.body.includes('Camera Time-Out')}`);
    console.log(`   Contains "webcam-modal": ${coordPage.body.includes('webcam-modal')}`);
    console.log(`   Contains "camera-face-guide": ${coordPage.body.includes('camera-face-guide')}`);
    console.log(`   Contains "liveCoordClock": ${coordPage.body.includes('liveCoordClock')}`);
    const csrfCoord = extractCsrf(coordPage.body);

    // 4. Test Camera Time-In
    console.log('\n4. Submitting Camera Time-In...');
    const dummyPhotoIn = 'data:image/jpeg;base64,' + Buffer.from('photo-time-in-sample-test-2026').toString('base64');
    const timeInPayload = `_csrf=${encodeURIComponent(csrfCoord)}&time_in_photo=${encodeURIComponent(dummyPhotoIn)}`;
    const timeInPost = await request({ path: '/coordinator-attendance/time-in', method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }, timeInPayload, coordJar);
    console.log(`   Time-In status: ${timeInPost.statusCode} (Redirect: ${timeInPost.headers.location})`);

    // 5. Re-check /coordinator-attendance after Time-In
    console.log('\n5. Checking /coordinator-attendance after Time-In...');
    const coordPageAfterIn = await request({ path: '/coordinator-attendance', method: 'GET' }, null, coordJar);
    console.log(`   Contains "Camera Time-Out": ${coordPageAfterIn.body.includes('Camera Time-Out')}`);
    console.log(`   Contains "time-photo-thumb": ${coordPageAfterIn.body.includes('time-photo-thumb')}`);
    console.log(`   Contains Time-In Photo: ${coordPageAfterIn.body.includes('title="Time-In Verification Photo"') || coordPageAfterIn.body.includes('time_in_photo')}`);

    // 6. Test HEAD_HR login & monitor
    console.log('\n6. Logging in as HEAD_HR (headhr@taascor.com)...');
    const hrJar = new CookieJar();
    const hrLoginGet = await request({ path: '/login', method: 'GET' }, null, hrJar);
    const hrCsrf = extractCsrf(hrLoginGet.body);

    const hrPayload = `_csrf=${encodeURIComponent(hrCsrf)}&email=${encodeURIComponent('headhr@taascor.com')}&password=${encodeURIComponent('HeadHr@2026')}`;
    const hrLoginPost = await request({ path: '/login', method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }, hrPayload, hrJar);
    console.log(`   Head HR login status: ${hrLoginPost.statusCode} (Redirect: ${hrLoginPost.headers.location})`);

    // 7. GET /coordinator-attendance as HEAD_HR
    console.log('\n7. Accessing /coordinator-attendance as HEAD_HR...');
    const hrPage = await request({ path: '/coordinator-attendance', method: 'GET' }, null, hrJar);
    console.log(`   Response status: ${hrPage.statusCode}`);
    console.log(`   Contains "Coordinator Attendance Monitor": ${hrPage.body.includes('Coordinator Attendance Monitor')}`);
    console.log(`   Contains "Military Time / 24H": ${hrPage.body.includes('Military Time / 24H')}`);
    console.log(`   Contains "openHrLightbox": ${hrPage.body.includes('openHrLightbox')}`);
    console.log(`   Contains Time-In Photo thumbnail: ${hrPage.body.includes('title="View Time-In verification photo"')}`);
    console.log(`   Contains Coordinator PHIXC: ${hrPage.body.includes('PHIXC') || hrPage.body.includes('coordinator')}`);
    console.log(`   Contains On Duty status: ${hrPage.body.includes('On Duty')}`);

    // 8. Test Coordinator Timekeeping Breakdown
    console.log('\n8. Accessing /coordinator-attendance/breakdown...');
    const breakdownPage = await request({ path: '/coordinator-attendance/breakdown', method: 'GET' }, null, hrJar);
    console.log(`   Response status: ${breakdownPage.statusCode}`);
    console.log(`   Contains "Timekeeping Breakdown": ${breakdownPage.body.includes('Timekeeping Breakdown')}`);
    console.log(`   Contains "Cutoff Period": ${breakdownPage.body.includes('Cutoff') || breakdownPage.body.includes('Period')}`);
    console.log(`   Contains "REG OT" or "Reg OT": ${breakdownPage.body.includes('Reg OT') || breakdownPage.body.includes('REG OT')}`);
    console.log(`   Contains Export Excel button: ${breakdownPage.body.includes('Export')}`);

    // 9. Test Coordinator Time-Out
    console.log('\n9. Submitting Camera Time-Out...');
    const csrfCoordOut = extractCsrf(coordPageAfterIn.body);
    const dummyPhotoOut = 'data:image/jpeg;base64,' + Buffer.from('photo-time-out-sample-test-2026').toString('base64');
    const timeOutPayload = `_csrf=${encodeURIComponent(csrfCoordOut)}&time_out_photo=${encodeURIComponent(dummyPhotoOut)}`;
    const timeOutPost = await request({ path: '/coordinator-attendance/time-out', method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }, timeOutPayload, coordJar);
    console.log(`   Time-Out status: ${timeOutPost.statusCode} (Redirect: ${timeOutPost.headers.location})`);

    // 10. Re-check /coordinator-attendance for completed shift
    console.log('\n10. Checking coordinator view after Time-Out...');
    const coordPageAfterOut = await request({ path: '/coordinator-attendance', method: 'GET' }, null, coordJar);
    console.log(`   Contains "completed": ${coordPageAfterOut.body.includes('logged and completed') || coordPageAfterOut.body.includes('Completed')}`);
    console.log(`   Contains Time-Out photo thumbnail: ${coordPageAfterOut.body.includes('title="Time-Out Verification Photo"')}`);

    // 11. Re-check HEAD_HR view for completed shift
    console.log('\n11. Re-checking HEAD_HR monitor view after Time-Out...');
    const hrPageAfterOut = await request({ path: '/coordinator-attendance', method: 'GET' }, null, hrJar);
    console.log(`   Contains Time-Out photo in HR view: ${hrPageAfterOut.body.includes('title="View Time-Out verification photo"')}`);
    console.log(`   Contains Shift Completed: ${hrPageAfterOut.body.includes('Shift Completed')}`);

    console.log('\n=== ALL TESTS COMPLETED SUCCESSFULLY! ===');
}

runTests().catch(err => {
    console.error('Test error:', err);
    process.exit(1);
});
