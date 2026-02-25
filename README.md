# 📚 BookCircle — Community Book Sharing WordPress Plugin

A feature-rich WordPress plugin for community book listing, personal libraries, and peer-to-peer book rental requests.

## ✨ Features

- **Central Book Catalog** — Title, Author, Publisher, Genre, Cover, ISBN, Language, Pages
- **Unique Book Codes** — Auto-generated 8-char codes (e.g. `DUNE3F9A`)
- **Author Profiles** — Name, bio, email, nationality, birth date, website, social links (Twitter/Instagram/Facebook)
- **Publisher Profiles** — Name, description, email, phone, address, city, country, founded year, logo
- **Personal Libraries** — Each reader assigns books to their library
- **Public / Private toggle** — Control book visibility per user
- **Search by Unique Code** — See which community members have a book
- **Rental Requests** — Request to borrow, approve/reject, mark returned
- **WordPress REST API** — All frontend operations via AJAX
- **One Shortcode Dashboard** — All tabs in a single `[bookcircle]` shortcode
- **Beautiful Admin UI** — Full CRUD for books, authors, publishers, rentals, members

## 🚀 Installation

### 1. Upload Plugin
Upload the `bookshare-plugin` folder to `/wp-content/plugins/`

### 2. Activate
Go to **WordPress Admin → Plugins** and activate **BookCircle – Community Book Sharing**

> Tables are created automatically on activation.

### 3. Use Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[bookcircle]` | **Full dashboard** — all tabs in one block |
| `[bookshare_catalog]` | Open on Catalog tab |
| `[bookshare_library]` | Open on My Library tab |
| `[bookshare_search]` | Open on Search by Code tab |

## 📁 Directory Structure

```
bookshare-plugin/
├── bookshare.php               ← Main plugin entry
├── composer.json               ← Autoload config
├── vendor/autoload.php         ← PSR-4 autoloader
├── src/
│   ├── Plugin.php              ← Singleton main class
│   ├── Installer.php           ← DB table creation
│   ├── Models/
│   │   ├── Book.php
│   │   ├── Author.php
│   │   ├── Publisher.php
│   │   ├── UserLibrary.php
│   │   └── Rental.php
│   ├── Controllers/
│   │   ├── FrontController.php   ← [bookcircle] shortcode
│   │   ├── BookController.php
│   │   ├── LibraryController.php
│   │   ├── RentalController.php
│   │   └── AdminController.php
│   └── API/
│       └── RestAPI.php
├── assets/
│   ├── js/bookshare.js
│   ├── js/bookshare-admin.js
│   ├── css/bookshare.css
│   └── css/bookshare-admin.css
├── templates/
│   └── dashboard.php
└── admin/
    ├── page-books.php
    ├── page-authors.php
    ├── page-publishers.php
    ├── page-rentals.php
    ├── page-members.php
    └── page-settings.php
```

## 🗄️ Database Tables

| Table | Purpose |
|-------|---------|
| `wp_bs_books` | Central book catalog |
| `wp_bs_authors` | Author profiles |
| `wp_bs_publishers` | Publisher profiles |
| `wp_bs_user_library` | User ↔ Book relationship |
| `wp_bs_rentals` | Rental request tracking |

## 🔌 REST API

Base URL: `/wp-json/bookshare/v1/`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/books` | List catalog (`?search=&genre=`) |
| POST | `/books` | Add book (auth required) |
| GET | `/books/{code}` | Get book by unique code + holders |
| GET | `/authors` | List authors |
| POST | `/authors` | Add author (admin) |
| GET | `/publishers` | List publishers |
| GET | `/library` | My library |
| POST | `/library` | Add book to my library |
| DELETE | `/library` | Remove book |
| POST | `/library/toggle` | Toggle public/private |
| GET | `/library/user/{id}` | Another user's public library |
| GET | `/library/search?code=` | Find holders by unique code |
| POST | `/rentals/request` | Request to rent a book |
| GET | `/rentals/incoming` | My incoming requests |
| GET | `/rentals/outgoing` | My outgoing requests |
| POST | `/rentals/{id}/status` | Update rental status |

## 🔒 Permissions

- **Anyone** — browse catalog and public libraries
- **Logged-in users** — add books, manage library, send rental requests
- **Admins** — full CRUD for all data, manage all rentals
