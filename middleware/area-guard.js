// Area guard middleware - ensures coordinators can only access their assigned area's data
const { getDb } = require('../db/database');

function getCoordinatorAreaId(userId) {
    const db = getDb();
    const assignment = db.prepare(
        'SELECT area_id FROM coordinator_area_assignments WHERE user_id = ? AND is_current = 1'
    ).get(userId);
    return assignment ? assignment.area_id : null;
}

// Middleware: attach current area to request for coordinators
function attachArea(req, res, next) {
    if (!req.session.user) return next();
    
    if (req.session.user.role === 'COORDINATOR') {
        const areaId = getCoordinatorAreaId(req.session.user.id);
        req.userAreaId = areaId;
        res.locals.userAreaId = areaId;
        
        if (areaId) {
            const db = getDb();
            const area = db.prepare('SELECT * FROM areas WHERE id = ?').get(areaId);
            req.userArea = area;
            res.locals.userArea = area;
        }
    } else {
        // HR and ADMIN can see all areas
        req.userAreaId = null;
        res.locals.userAreaId = null;
    }
    
    next();
}

// Middleware: require coordinator to have an assigned area
function requireArea(req, res, next) {
    if (req.session.user.role === 'COORDINATOR') {
        if (!req.userAreaId) {
            return res.status(403).render('error', {
                title: 'No Area Assigned',
                message: 'Your account does not have an assigned area yet. Please contact HR.',
                code: 403
            });
        }
    }
    next();
}

// Validate that an entity belongs to the coordinator's area
function validateAreaAccess(areaId, req) {
    if (req.session.user.role === 'COORDINATOR') {
        return parseInt(areaId) === parseInt(req.userAreaId);
    }
    // HR and ADMIN can access any area
    return true;
}

// Get area filter for queries - returns area_id for coordinators, null for HR/ADMIN
function getAreaFilter(req) {
    if (req.session.user.role === 'COORDINATOR') {
        return req.userAreaId;
    }
    return null;
}

module.exports = { attachArea, requireArea, validateAreaAccess, getAreaFilter, getCoordinatorAreaId };
