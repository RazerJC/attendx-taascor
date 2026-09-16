# TAASCOR Attendance Monitoring System — User Guide
**Company**: TAASCOR Management & General Services Corp.  
**System Version**: 1.0.0

---

## 1. System Overview & User Roles

The TAASCOR Attendance Monitoring System is designed to manage employee masterfiles, work scheduling, daily attendance, employee concerns, Back-to-Work clearance workflows, and manpower requests across operational areas and warehouses.

There are **three user roles**:
1. **ADMIN**: Full system control, user accounts, warehouse definitions, positions, and security audit logs.
2. **HR**: Coordinator approvals, warehouse assignments, system-wide attendance monitoring, Back-to-Work clearance decisions, and worker allocations for manpower requests.
3. **COORDINATOR**: Frontline supervisors assigned to a specific warehouse or area. Coordinators manage employee masterfiles, assign schedules, mark daily attendance, log their own work shifts, submit concern reports, and confirm manpower arrivals.

> [!NOTE]
> Employees are managed records and do not have system login credentials.

---

## 2. Getting Started & Account Registration

### Coordinator Registration Flow
1. Navigate to `/register`.
2. Enter your full name, company email ending in **`@taascor.com`**, and a secure system password (minimum 8 characters).
   - *Security Note*: Do not enter your Hostinger mailbox password.
3. Submit the registration form.
4. Check your inbox for the verification email. Click the verification link to confirm email ownership.
5. Your account is now in **Pending** status.
6. The HR Department reviews your account and assigns your designated warehouse or area.
7. Once approved, you can sign in to access your assigned warehouse operations.

---

## 3. Coordinator Guide

### A. Marking Daily Attendance
1. Click **Attendance** in the sidebar or the **Mark Attendance** shortcut on your Dashboard.
2. Select the operational date (defaults to today).
3. The table displays all active employees under your assigned warehouse.
4. Set the attendance status for each worker:
   - **Present**: Worker arrived on time for their scheduled shift.
   - **Late**: Worker arrived after shift start. Time-in is recorded.
   - **Absent**: Worker did not report. **This automatically triggers a Back-to-Work clearance case.**
   - **On Leave**: Pre-approved leave. Does not trigger absence clearance.
   - **Rest Day**: Scheduled day off.
5. Click **Save Attendance Records**.

### B. Back-to-Work Clearance Workflow
When an employee is confirmed absent on a scheduled working day:
1. The system creates a clearance case formatted as `BTW-YYYY-XXXX`.
2. Navigate to **Back-to-Work** under the Requests & Concerns hub.
3. Open the case thread to submit the employee's reason or medical notes.
4. The employee must obtain official HR clearance before being cleared to return.
5. Once HR records an official decision (`Approved` or `Not Approved`), you will receive an in-app notification displaying the decision and authorized return date.

### C. Logging Coordinator Attendance
1. Coordinators must record their own working hours via **My Attendance** or the **Time In / Out** button in the header.
2. Click **Record Time-In** upon starting your shift. A server timestamp is permanently logged.
3. Click **Record Time-Out** at the end of your shift.
4. If an error occurred or an emergency prevented recording, click **Request Correction** and provide the details for HR review.

### D. Submitting Manpower Requests
1. Navigate to **Manpower Requests** → click **New Manpower Request**.
2. Specify the required deployment date, shift schedule, reason, and priority.
3. Input the number of workers needed for each position.
4. When HR assigns candidate names, you will receive a notification.
5. When the worker physically reports to the warehouse, open the request and click **Confirm Arrival**.
6. Set the worker's actual position and shift schedule. The worker is immediately added to your **Active Employee Masterfile** and request fulfillment totals update automatically.

---

## 4. HR Guide

### A. Reviewing Pending Coordinator Registrations
1. Navigate to **Pending Approvals** in the sidebar.
2. Review applicant name, verified email, and registration timestamp.
3. Select the warehouse or area to which the coordinator will be assigned.
4. Click **Approve & Assign**. The coordinator gains immediate operational access.

### B. Monitoring Workforce Attendance
1. Open **Attendance Monitoring** from the dashboard or sidebar.
2. Filter attendance records across all operational warehouses.
3. Monitor daily absences, late arrivals, and unrecorded attendance sheets.

### C. Back-to-Work Clearance Decisions
1. Open **Back-to-Work** in the sidebar.
2. Filter for cases with status `Pending HR Review`.
3. Review the absence dates, coordinator explanation, and attached notes.
4. In the **HR Clearance Decision** box:
   - Select **Approved**, **Request Clarification**, or **Do Not Approve**.
   - If approved, set the **Authorized Return Date**.
   - Enter decision remarks.
5. Click **Record Official Decision**. The assigned coordinator is notified immediately.

### D. Fulfilling Manpower Requests
1. Open **Manpower Requests** in the sidebar.
2. Select a request in `Submitted` status.
3. In the **HR Worker Allocation** section:
   - Choose whether to assign an **Incoming / External Candidate** (enter candidate name) or an **Existing Employee Transfer**.
   - Select the target requested position slot.
   - Click **Assign Candidate to Request**.

---

## 5. Administrator Guide

### A. User Management & Account Privileges
1. Navigate to **User Management** under Intelligence & Admin.
2. View all system accounts, email addresses, roles, and status.
3. Click **Create HR Account** to add new HR personnel.
4. Suspend or reactivate accounts with immediate session revocation.

### B. Warehouse & Position Configuration
1. Navigate to **Warehouses / Areas** to add new facility locations.
2. Navigate to **Positions** to define job titles across the workforce.

### C. Audit Trail
1. Navigate to **Audit Logs**.
2. Review timestamped events, acting users, IP addresses, target entities, and modification details.
