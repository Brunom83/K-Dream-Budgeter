# 🇰🇷 K-Dream Budgeter

> A Gamified Personal Finance & Squad Goals App.
> Built to help Gen-Z track expenses and save for shared dreams (like a trip to Korea).

![K-Dream Dashboard](https://via.placeholder.com/800x400?text=Insert+Dashboard+Screenshot+Here)

## 🚀 About the Project
K-Dream is not just a budget tracker; it's a social financial tool. It allows users to track personal finances while contributing to a shared "Squad Goal".
Developed as a **Full Stack** project running on a **Home Lab (Linux Server)** using **Docker**.

### ✨ Key Features
* **💸 Transaction Tracking:** Income & Expenses with visual feedback.
* **📊 Data Visualization:** Interactive Chart.js graphs (Monthly Cashflow).
* **👥 Squad Mode:** Create or Join squads to track shared financial goals.
* **📻 Team Chat:** Real-time messaging board for squads.
* **🎨 Glassmorphism UI:** Modern, clean aesthetic using TailwindCSS.
* **📱 PWA Ready:** Installable on mobile devices (Android/iOS).
* **🛡️ Secure:** Dockerized environment with automated daily backups.

## 🛠️ Tech Stack
* **Backend:** PHP 8.2 (PDO)
* **Database:** MySQL 8.0
* **Frontend:** HTML5, TailwindCSS, Chart.js
* **Infrastructure:** Docker, Docker Compose, Linux (Ubuntu/Mint)
* **Tools:** Git, VS Code Remote SSH, Cloudflare Tunnel

## 🔧 How to Run (Docker)
1.  **Clone the repo:**
    ```bash
    git clone [https://github.com/Brunom83/K-Dream-Budgeter.git](https://github.com/Brunom83/K-Dream-Budgeter.git)
    ```
2.  **Configure Environment:**
    Rename `includes/db.example.php` to `includes/db.php` and set credentials.
3.  **Start Containers:**
    ```bash
    docker compose up -d --build
    ```
4.  **Access:**
    Open `http://localhost:8080`

## 📸 Screenshots
| Dashboard (Light Mode) | Squad Chat |
|:---:|:---:|
| ![Dash](link_to_image_1) | ![Squad](link_to_image_2) |

---
*Developed by **Vicius** 🏎️*