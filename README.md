# TAASCOR Attendance Monitoring System
**Company**: TAASCOR Management & General Services Corp.

A full-stack, enterprise attendance and workforce management web application built for TAASCOR operations across assigned warehouses and areas.

---

## Key Features

- **Strict Three-Role Architecture**: `ADMIN`, `HR`, and `COORDINATOR`. Employees are managed records without login accounts.
- **Company Email Authentication**: Coordinator registration strictly restricted to verified `@taascor.com` email addresses, requiring HR approval and warehouse assignment before access is granted.
- **Warehouse-Level Data Isolation**: Coordinators are strictly restricted to their assigned area. Cross-area access is blocked at the database and middleware layers.
- **Employee Masterfile**: Active and Inactive views. Inactivation permanently preserves all historical attendance, schedules, and clearance cases.
- **Dynamic Scheduling**: Individual and group scheduling, overnight shifts crossing midnight, conflict detection, and weekly matrix view.
- **Daily Attendance Marking**: Bulk table marking with status differentiation (Present, Late, Absent, On Leave, Rest Day). Confirmed absences automatically trigger or link Back-to-Work cases.
- **Coordinator Self-Attendance**: Server-timestamped Time-In and Time-Out with HR correction request workflow.
- **Forum-Style Requests & Concerns Hub**:
  - **Back-to-Work Clearance**: Absence linking, thread updates, authorized return date, and HR decision highlights.
  - **Employee Concerns**: Categories, HR directives, and resolution tracking.
  - **Manpower Requests**: Position breakdown, HR worker allocation (external or transfer), coordinator arrival confirmation, and automatic masterfile registration.
- **Excel (.xlsx) & CSV Export**: Streamed report generation with area-level permissions enforced.
- **Real-Time Notification Polling**: Unread badges and direct link navigation.
- **Full Audit Trail**: Immutable logging of system actions with IP tracking.

---

## Technology Stack

- **Backend**: Node.js v20+ / Express.js
- **Database**: SQLite with WAL mode via native `node:sqlite`
- **Security**: bcryptjs password hashing, session cookies, rate-limiting, and CSRF protection
- **Frontend**: Server-rendered EJS with vanilla CSS design system in TAASCOR navy, gold, and red accents
- **Reporting**: ExcelJS for native spreadsheet generation
- **Timezone**: Asia/Manila (luxon)

---

## Quick Start (Local Setup)

1. **Install Dependencies**:
   ```bash
   npm install
   ```

2. **Configure Environment**:
   ```bash
   cp .env.example .env
   ```

3. **Seed Database with Demo Data**:
   ```bash
   npm run seed
   ```

4. **Run Verification Test Suite**:
   ```bash
   node verify.js
   ```

5. **Start Application**:
   ```bash
   npm start
   ```
   Open `http://localhost:3000` in your web browser.

---

## Default Sample Credentials

| Role | Email | Password | Assigned Area |
| :--- | :--- | :--- | :--- |
| **ADMIN** | `admin@taascor.com` | `Admin123!` | System-Wide |
| **HR** | `hr.santos@taascor.com` | `HrUser123!` | System-Wide |
| **COORDINATOR** | `juan.delacruz@taascor.com` | `Coord123!` | Warehouse A - Pasig |
| **COORDINATOR** | `anna.reyes@taascor.com` | `Coord123!` | Warehouse B - Taguig |
| **PENDING** | `mark.garcia@taascor.com` | `Coord123!` | Awaiting HR Approval |

---

## Documentation Links

- [User Guide (ADMIN, HR, COORDINATOR)](docs/user-guide.md)
- [Production Deployment & Disaster Recovery](docs/deployment.md)
