# TAASCOR Attendance Monitoring System — Deployment & Maintenance Guide
**Company**: TAASCOR Management & General Services Corp.

---

## 1. System Requirements
- **Runtime**: Node.js v20.x or v24.x LTS
- **Package Manager**: npm v10+
- **Database Engine**: Built-in SQLite via Node.js native `node:sqlite` (zero external database server required)
- **Memory**: Minimum 512 MB RAM (1 GB recommended)
- **Disk**: 100 MB for application files + storage for database snapshots

---

## 2. Installation & First-Time Setup

1. **Clone or copy the application files to the production directory**:
   ```bash
   cd /var/www/taascor
   ```

2. **Install production dependencies**:
   ```bash
   npm install --omit=dev
   ```

3. **Configure Environment Variables**:
   Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
   Edit `.env` with your production settings:
   ```ini
   PORT=3000
   NODE_ENV=production
   SESSION_SECRET=generate-a-strong-random-64-character-string-here

   # Initial Admin Credentials (used only on first database initialization)
   ADMIN_EMAIL=admin@taascor.com
   ADMIN_PASSWORD=SecureAdminPassword2026!

   # Hostinger / Production SMTP Mailer Settings
   SMTP_HOST=smtp.hostinger.com
   SMTP_PORT=465
   SMTP_SECURE=true
   SMTP_USER=noreply@taascor.com
   SMTP_PASS=YourStrongMailboxPasswordHere
   SMTP_FROM="TAASCOR System <noreply@taascor.com>"

   APP_URL=https://attendance.taascor.com
   TZ=Asia/Manila
   ```

4. **Initialize Database & Seed Data (Optional)**:
   ```bash
   npm run seed
   ```

---

## 3. Production Process Management with PM2

Install PM2 globally:
```bash
npm install -g pm2
```

Start the application with PM2:
```bash
pm2 start server.js --name "taascor-attendance"
pm2 save
pm2 startup
```

---

## 4. Nginx Reverse Proxy Configuration (with SSL)

Create `/etc/nginx/sites-available/taascor`:
```nginx
server {
    listen 80;
    server_name attendance.taascor.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name attendance.taascor.com;

    ssl_certificate /etc/letsencrypt/live/attendance.taascor.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/attendance.taascor.com/privkey.pem;

    client_max_body_size 10M;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }

    location /images/ {
        alias /var/www/taascor/public/images/;
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }

    location /css/ {
        alias /var/www/taascor/public/css/;
        expires 7d;
    }
}
```

Enable the configuration and reload Nginx:
```bash
ln -s /etc/nginx/sites-available/taascor /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

---

## 5. Hostinger VPS / Shared Hosting Specific Notes

- **Hostinger VPS**: Recommended environment. Follow the standard Node.js + PM2 + Nginx instructions above.
- **Hostinger Cloud / cPanel Node.js Selector**:
  1. In hPanel, open **Advanced** → **Node.js**.
  2. Select Node.js version **20.x or 22.x/24.x**.
  3. Set Application root to `/home/user/public_html/attendance`.
  4. Set Application startup file to `server.js`.
  5. Run `NPM Install` via the cPanel interface.
  6. Add environment variables in the cPanel environment section.

---

## 6. Database Backup & Disaster Recovery

### Creating Backups
The database is stored as a single SQLite file at `db/taascor.db`. To take an instant snapshot without shutting down the application (using WAL checkpointing):
```bash
npm run backup
```
Backups are saved to `db/backups/taascor_backup_YYYY-MM-DDTHH-mm-ss.db`.

### Automated Nightly Backup via Cron
Add this entry to your server crontab (`crontab -e`):
```cron
0 2 * * * cd /var/www/taascor && node db/backup.js >> /var/log/taascor-backup.log 2>&1
```

### Restoring from Backup
In case of server migration or disaster recovery:
1. Stop the application:
   ```bash
   pm2 stop taascor-attendance
   ```
2. Replace `db/taascor.db` with the snapshot:
   ```bash
   cp db/backups/taascor_backup_TARGET.db db/taascor.db
   rm -f db/taascor.db-wal db/taascor.db-shm
   ```
3. Restart the application:
   ```bash
   pm2 restart taascor-attendance
   ```
