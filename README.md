# 🚀 Smart Data Import Engine

A **production-ready, scalable data import system** built with Laravel that handles large Excel/CSV uploads with **auto column mapping, validation, error tracking, and queue-based processing**.

---

## 🧠 Overview

Smart Data Import Engine is designed to solve real-world challenges of importing large datasets into applications. It provides a flexible and configurable system to:

* Import **10k+ records efficiently**
* Automatically map columns
* Validate data before insertion
* Track row-level errors
* Process data asynchronously using queues

---

## ✨ Features

### 📂 File Upload

* Supports **CSV & Excel files**
* Handles large datasets

### 🔄 Auto Column Mapping

* Intelligent column matching
* Manual override support

### ✅ Validation Engine

* Field-level validation rules
* Custom validation support

### ❌ Error Reporting

* Row-wise error tracking
* Detailed failure reasons
* Downloadable error reports

### ⚡ Queue-Based Processing

* Background job processing
* Prevents UI blocking
* Scalable for large data imports

### 📊 Progress Tracking

* Real-time import status
* Processed vs total rows

### 🔁 Retry Mechanism

* Retry failed rows only

---

## 🏗️ Tech Stack

* **Backend:** Laravel (PHP)
* **Database:** MySQL
* **Queue:** Laravel Queue (Redis/Database)
* **File Processing:** Laravel Excel
* **Frontend:** Blade / Bootstrap

---

## 📁 Project Structure

```
Smart_Data_Import_Engine/
├── app/
│   ├── Http/Controllers/
│   │   └── ImportController.php
│   ├── Services/
│   │   ├── ImportService.php
│   │   ├── MappingService.php
│   │   └── ValidationService.php
│   ├── Jobs/
│   │   └── ProcessImportJob.php
│   ├── Models/
│   │   ├── ImportJob.php
│   │   └── ImportError.php
│   ├── Imports/
│   │   └── GenericImport.php
│   └── Providers/
│       └── AppServiceProvider.php
├── config/
│   ├── app.php
│   └── database.php
├── database/
│   └── migrations/
│       ├── 2024_01_01_000001_create_import_jobs_table.php
│       └── 2024_01_01_000002_create_import_errors_table.php
├── routes/
│   ├── web.php
│   ├── api.php
│   └── console.php
├── resources/
│   └── views/
│       └── welcome.blade.php
├── storage/
│   ├── app/public/imports/
│   └── app/public/exports/
├── bootstrap/
│   └── app.php
├── public/
│   └── index.php
├── .env.example
├── artisan
└── composer.json
```

---

## 🗄️ Database Schema

### import_jobs

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| file_name | string | Original filename |
| file_path | string | Storage path |
| status | string | pending/processing/completed/failed |
| total_rows | integer | Total rows in file |
| processed_rows | integer | Successfully processed |
| failed_rows | integer | Failed rows count |
| column_mapping | json | Column mapping config |
| validation_rules | json | Custom validation rules |
| module | string | Import module type |
| error_summary | text | Error summary |
| timestamps | timestamps | Created/Updated |

### import_errors

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| job_id | bigint | Foreign key to import_jobs |
| row_number | integer | Row number in file |
| error_message | text | Error details |
| row_data | json | Raw row data |
| is_retried | boolean | Retry status |
| timestamps | timestamps | Created/Updated |

---

## ⚙️ Installation

```bash
# Clone the repository
git clone https://github.com/your-username/Smart_Data_Import_Engine.git

cd Smart_Data_Import_Engine

# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=smart_import
# DB_USERNAME=root
# DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Start server
php artisan serve
```

---

## 🔄 Queue Setup (Important)

```bash
# Run queue worker
php artisan queue:work

# Or with database driver
php artisan queue:work --driver=database
```

---

## 📬 API Endpoints

### 📤 Upload File

```
POST /api/import/upload
Content-Type: multipart/form-data

Parameters:
- file: File (CSV, XLSX, XLS)
- module: string (optional, default: generic)
```

### 🔄 Save Mapping

```
POST /api/import/mapping
Content-Type: application/json

{
  "job_id": 1,
  "mapping": {
    "Full Name": "name",
    "Email Address": "email",
    "Phone No": "phone",
    "DOB": "dob"
  }
}
```

### ▶️ Start Import

```
POST /api/import/start
Content-Type: application/json

{
  "job_id": 1
}
```

### 📊 Check Status

```
GET /api/import/status/{id}
```

### ❌ Get Errors

```
GET /api/import/errors/{id}
```

### 📥 Download Error Report

```
GET /api/import/errors/{id}/download
```

### 🔁 Retry Failed Rows

```
POST /api/import/retry/{id}
```

### 📋 List All Jobs

```
GET /api/import/jobs
```

---

## 📊 Import Flow

```
Upload File → Read Headers → Auto Column Mapping → 
User Confirms Mapping → Queue Job Start → 
Row Processing (Validation + Insert) → Error Logging → Final Report
```

---

## 🧪 Sample CSV

```csv
Full Name,Email Address,Phone No,DOB
Priya Agrawal,priya@gmail.com,9876543210,1995-06-10
Rahul Sharma,rahul@gmail,9876543211,1998-03-21
Anita Verma,anita@gmail.com,,2000-01-15
```

A sample CSV file is included: `sample_data.csv`

---

## 🧩 Advanced Features

* Multi-module imports (Users, Products, etc.)
* Duplicate detection
* Import modes (Insert / Update / Upsert)
* Chunk processing for performance
* Mapping templates reuse
* Real-time progress (AJAX polling)

---

## 🛡️ Error Handling

The system captures:
- Row number
- Error message
- Raw row data

This enables easy debugging and retry of failed rows.

---

## 📈 Performance

The system handles large files using:
- **Chunk processing** - Process rows in batches
- **Queue-based async jobs** - Background processing
- **Memory management** - Prevents memory overload

---

## 💡 Use Cases

* CRM data import
* ERP migration
* Bulk user onboarding
* Product catalog upload

---

## 🏆 Why This Project Stands Out

* Solves **real production problem**
* Built with **scalability in mind**
* Clean architecture with separation of concerns
* Demonstrates backend engineering skills

---

## 📌 Resume Highlight

> Built a scalable data import engine handling large datasets with async processing, validation, and detailed error tracking.

---

## 🤝 Contributing

Pull requests are welcome. For major changes, please open an issue first.

---

## 📜 License

This project is open-source and available under the MIT License.

---

## ⭐ Support

If you like this project, give it a ⭐ on GitHub!
