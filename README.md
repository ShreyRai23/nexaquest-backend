# 🌟 MindBloom API — Backend Core

![Laravel](https://img.shields.io/badge/Laravel-12.0-red) ![PHP](https://img.shields.io/badge/PHP-8.2-blue) ![MySQL](https://img.shields.io/badge/MySQL-8.0-orange) ![Gemini API](https://img.shields.io/badge/AI-Google_Gemini-purple)

**MindBloom API** is the robust, server-side engine powering the MindBloom AI platform. Built with Laravel 12, it handles real-time gamification logic, stateless JWT authentication, complex psychometric reporting, and secure integration with the Google Gemini AI for personalized mentorship.

## ✨ Key Features
- **🎮 Gamification Engine:** Server-side validation and state management for daily missions, quizzes, dynamic XP awards, and achievement unlocks.
- **🤖 Gemini AI Integration:** Deeply integrated with the Google Gemini API to power "Bloomy" — generating real-time quiz questions, offering empathetic chat responses, and synthesizing comprehensive psychometric reports.
- **🔐 Role-Based Auth (JWT):** Stateless, token-based authentication separating `child` and `parent` roles, ensuring secure API access and data isolation.
- **📊 Reporting System:** Automated PDF generation for parents detailing a child's skill progress, career recommendations, and cognitive strengths.
- **🚀 Cloud-Ready:** Infrastructure-as-Code ready with `render.yaml` for seamless deployment on Render.

## 🛠️ Tech Stack
- **Framework:** [Laravel 12](https://laravel.com/)
- **Language:** PHP 8.2+
- **Database:** MySQL
- **Authentication:** `tymon/jwt-auth`
- **PDF Generation:** `barryvdh/laravel-dompdf`
- **AI Integration:** Direct REST integration with `generativelanguage.googleapis.com`

## 🚀 Getting Started Locally

### Prerequisites
Make sure you have [PHP 8.2+](https://windows.php.net/), [Composer](https://getcomposer.org/), and a MySQL server (like XAMPP) running.

### Installation
1. Clone the repository:
   ```bash
   git clone https://github.com/ShreyRai23/mindbloom-api.git
   cd mindbloom-api
   ```
2. Install dependencies:
   ```bash
   composer install
   ```
3. Set up environment variables:
   Copy the example environment file and generate your application keys:
   ```bash
   cp .env.example .env
   php artisan key:generate
   php artisan jwt:secret
   ```
4. Database setup:
   Create a local MySQL database named `mindbloom_db`. Update your `.env` file with the database credentials, then run the migrations and seeders to populate initial data:
   ```bash
   php artisan migrate:fresh --seed
   ```
5. Configure API Keys:
   In your `.env` file, add your Google Gemini API key:
   ```env
   GEMINI_API_KEY=your_actual_key_here
   FRONTEND_URL=http://localhost:5173
   ```
6. Start the local server:
   ```bash
   php artisan serve
   ```
   The API will be available at `http://localhost:8000/api`.

## 🌐 Production Deployment (Render)

This backend is fully configured for automated deployment on **Render.com**.

1. Connect this GitHub repository to Render as a **New Blueprint**.
2. Render will read the included `render.yaml` file to automatically provision:
   - A PHP Web Service (running `php artisan serve`).
   - A free MySQL database instance.
3. Once provisioned, open the Web Service settings in the Render dashboard and set the remaining environment variables:
   - `FRONTEND_URL` (Your Vercel frontend URL)
   - `GEMINI_API_KEY` (Your private API key)
4. Your API is live!

---
*Built with ❤️ for the future of learning.*
