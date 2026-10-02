# B&S University: Student Registration & Record System

A PHP and MySQL web application for managing a university's student records. It covers student registration, an admin dashboard, and faculty, course and subject management. Built as a course project and run locally with XAMPP.

## Features

- Student registration with unique roll numbers
- Login and logout, with separate admin and student pages
- Admin dashboard with a student ledger, plus an edit modal with dynamic dropdowns
- Faculty, course and subject management
- Student profile page
- Prepared statements for database queries, to protect against SQL injection

## Tech Stack

- HTML, CSS (flexbox and grid), JavaScript
- PHP
- MySQL (through XAMPP)

## Database

The database has six tables: `students`, `admin`, `faculty`, `course`, `subjects` and `enrollment`.

`database.sql` contains the table structure only. It has no real data, so you need to add your own records after importing it.

## Setup (XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Download this project into `C:\xampp\htdocs\login_register`:

   ```bash
   git clone https://github.com/nepalisubasworks/bs-university-student-system.git login_register
   ```

3. Open phpMyAdmin at `http://localhost/phpmyadmin`, create a database named `users_db`, and import `database.sql` into it.
4. Copy `config.example.php` to `config.php` and fill in your database details. A default XAMPP install uses the user `root` with an empty password.
5. Add your first admin account by inserting a row into the `admin` table in phpMyAdmin. Match the way the login code checks the password.
6. Open `http://localhost/login_register/` in your browser.

## Project Structure

| File | Purpose |
|------|---------|
| `index.php` | Entry page |
| `login_register.php` | Login and registration |
| `admin_page.php` | Admin dashboard |
| `student_page.php` | Student profile page |
| `manage_faculty.php` | Faculty management |
| `subjects.php` | Subject management |
| `logout.php` | Ends the session |
| `config.example.php` | Database connection template |
| `database.sql` | Database structure |
| `style.css`, `script.js` | Styling and front-end behaviour |

## Security Notes

- The real `config.php` is listed in `.gitignore`, so database credentials are never committed.
- The repository contains no student or admin data.

## Author

**Subas Nepali**. GitHub: [nepalisubasworks](https://github.com/nepalisubasworks)
