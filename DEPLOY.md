# Armor Fire HRMS — Git + Live (Subdomain) Setup

## 1) Pehla repository ma add karo (local)

```bash
cd c:\xampp\htdocs\armor_new_hrms

# Agar pehli var git nathi
git init
git add .
git status
git commit -m "Initial Armor Fire HRMS - Core PHP manufacturing HRMS"

# GitHub / GitLab pe empty repo banavi ne:
git remote add origin https://github.com/YOUR_ORG/armor_new_hrms.git
git branch -M main
git push -u origin main
```

Important:
- `.env` git ma **nai** jase (`.gitignore` ma che)
- Repo ma `.env.example` jase — team/live e copy kari ne values bhare

Local `.env` already example jevu:
```
APP_ENV=local
APP_URL=http://localhost/armor_new_hrms
APP_BASE_PATH=/armor_new_hrms
DB_HOST=localhost
DB_NAME=armor_hrms
DB_USER=root
DB_PASS=
```

---

## 2) Live subdomain (example: hrms.armorfire.com)

### A. Hosting / cPanel
1. Subdomain banavo: `hrms.yourdomain.com`
2. Document root = project folder (jya `index.php` che)
3. PHP 8.0+ enable karo
4. MySQL database + user create karo
5. `sql/hrms_database.sql` + `sql/employees.sql` + `sql/masters.sql` import karo  
   (athva phpMyAdmin ma run)  
6. Seed optional: browser/CLI thi `sql/seed_masters.php` (test data)

### B. Code upload
- Git pull on server **or** ZIP upload
- Folder structure same rakho

### C. Live `.env` (server pe)
```
APP_ENV=production
APP_URL=https://hrms.yourdomain.com
APP_BASE_PATH=
DB_HOST=localhost
DB_PORT=3306
DB_NAME=your_live_db
DB_USER=your_live_user
DB_PASS=your_strong_password
APP_TIMEZONE=Asia/Kolkata
```

`APP_BASE_PATH=` empty = subdomain root (recommended).

### D. Permissions
```
assets/uploads/logo/   → writable (755 or 775)
```

### E. Check
- Open `https://hrms.yourdomain.com`
- Login admin
- Dashboard boxes → department → employee list
- Masters CRUD open thay

---

## 3) Local vs Live same code

| Item | Local | Live |
|------|-------|------|
| Code | same git repo | same git repo |
| Config | `.env` | alag `.env` |
| URL base | `/armor_new_hrms` | empty (subdomain) |
| DB | XAMPP root | hosting DB user |

Code change nathi — only `.env` change.

---

## 4) Safe deploy habit
1. Local test
2. `git add` / `commit` / `push`
3. Server pe `git pull`
4. `.env` touch nathi (server ni file as-is)
5. Agar SQL change hoy to migration/sql manually run
