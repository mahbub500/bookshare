# 📖 BookCircle — Community Book Sharing Plugin

A WordPress plugin for community book listing, personal libraries, and peer-to-peer book rental requests.

---

## 🌐 Suggested Website Names

| Name | Domain Idea |
|------|-------------|
| **BookCircle** | bookcircle.community |
| **ReadShare** | readshare.app |
| **LibraTrade** | libratrade.com |
| **PageBridge** | pagebridge.net |
| **BookNest** | booknest.community |
| **ShelfShare** | shelfshare.xyz |

---

## ✨ Features

- 📚 **Central Book Catalog** — Title, Author, Publisher, Genre, Cover
- 🔑 **Unique Book Codes** — Auto-generated (e.g. `DUNE3F9A`)
- 👤 **Personal Libraries** — Each reader assigns books to their library
- 🔒 **Public / Private** — Toggle book visibility per-user
- 🔍 **Search by Unique Code** — See which community members have a book
- 📬 **Rental Requests** — Request to borrow, approve/reject, mark returned
- 🛡️ **WordPress REST API** — All frontend operations done via AJAX

---

## 📁 Directory Structure

```
bookshare-plugin/
├── bookshare.php               ← Main plugin entry
├── composer.json               ← Autoload config
├── src/
│   ├── Plugin.php              ← Singleton main class
│   ├── Installer.php           ← DB table creation
│   ├── Models/
│   │   ├── Book.php            ← Books CRUD
│   │   ├── UserLibrary.php     ← User ↔ Book relationship
│   │   └── Rental.php         ← Rental request management
│   ├── Controllers/
│   │   ├── BookController.php
│   │   ├── LibraryController.php
│   │   └── RentalController.php
│   └── API/
│       └── RestAPI.php         ← All REST endpoints
├── assets/
│   ├── js/bookshare.js         ← Frontend app (vanilla JS)
│   └── css/bookshare.css       ← All styles
├── templates/
│   ├── catalog.php             ← [bookshare_catalog] shortcode
│   ├── library.php             ← [bookshare_library] shortcode
│   └── search.php              ← [bookshare_search] shortcode
└── demo.html                   ← Standalone UI demo
```

---

## 🚀 Installation

### 1. Install via Composer
```bash
cd wp-content/plugins/bookshare-plugin
composer install
```

### 2. Activate Plugin
Go to **WordPress Admin → Plugins** and activate **BookShare Community**.

Tables are created automatically on activation.

### 3. Use Shortcodes

Add to any WordPress page:

| Shortcode | What it shows |
|-----------|--------------|
| `[bookshare_catalog]` | Full book catalog with search + add form |
| `[bookshare_library]` | Logged-in user's personal library + rental requests |
| `[bookshare_search]` | Search by unique book code |

---

## 🔌 REST API Endpoints

All endpoints are under: `/wp-json/bookshare/v1/`

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/books` | List catalog (`?search=`) |
| `POST` | `/books` | Add book (auth required) |
| `GET` | `/books/{code}` | Get book by unique code |
| `GET` | `/library` | My library |
| `POST` | `/library` | Add book to my library |
| `DELETE` | `/library` | Remove book |
| `POST` | `/library/toggle` | Toggle public/private |
| `GET` | `/library/user/{id}` | View another user's public library |
| `GET` | `/library/search?code=` | Find holders by unique code |
| `POST` | `/rentals/request` | Request to rent a book |
| `GET` | `/rentals/incoming` | My incoming requests (as owner) |
| `GET` | `/rentals/outgoing` | My outgoing requests |
| `POST` | `/rentals/{id}/status` | Update rental status |

---

## 🗄️ Database Tables

| Table | Purpose |
|-------|---------|
| `wp_bs_books` | Central book catalog |
| `wp_bs_user_library` | Which users own which books |
| `wp_bs_rentals` | Rental request tracking |

---

## 🔒 Permissions

- Anyone can **browse** the catalog and public libraries
- **Logged-in users** can add books, manage their library, and send rental requests
- Only the **book owner** can approve/reject rental requests

---

## 💡 How Unique Codes Work

Each book gets a unique code like `DUNE3F9A` (title prefix + random suffix).  
Any reader can search this code to find all community members who have that book publicly listed.