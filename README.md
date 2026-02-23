# 📖 BookCircle Community Plugin v2

A WordPress plugin for community book sharing with **Admin-only catalog management**, Custom Post Types, and reader rental requests.

---

## ✨ Key Features

| Feature | Who |
|---------|-----|
| Add/Edit Books, Authors, Publishers | **Admin only** |
| Upload book cover image | **Admin** (Featured Image on CPT) |
| Select Author & Publisher via dropdown | **Admin** |
| Auto-generate unique book code | **Admin** |
| Browse central book catalog | Everyone |
| Add books to personal library | Logged-in readers |
| Toggle library public/private | Readers |
| Request to rent from another reader | Logged-in readers |
| Approve/reject rental requests | Book owner (reader) |
| Request admin to add a missing book | Logged-in readers |
| Approve/reject book listing requests | **Admin** |

---

## 📁 File Structure

```
bookshare-plugin/
├── bookshare.php                    ← Entry point (includes built-in autoloader, no Composer needed)
├── composer.json                    ← Optional Composer config
├── src/
│   ├── Plugin.php                   ← Main singleton, boots everything
│   ├── Installer.php                ← Creates DB tables on activation
│   ├── PostTypes/
│   │   ├── BookCPT.php              ← bs_book custom post type (admin-only)
│   │   ├── AuthorCPT.php            ← bs_author custom post type
│   │   └── PublisherCPT.php         ← bs_publisher custom post type
│   ├── Admin/
│   │   ├── AdminMenu.php            ← BookCircle admin menu + dashboard
│   │   ├── BookMetaBox.php          ← All book meta fields in admin
│   │   └── BookRequestAdmin.php     ← Manage reader requests in admin
│   ├── Models/
│   │   ├── UserLibrary.php          ← User ↔ Book relationships
│   │   ├── Rental.php               ← Rental request management
│   │   └── BookRequest.php          ← Book listing requests
│   ├── Controllers/
│   │   └── ShortcodeController.php  ← Registers all 3 shortcodes
│   └── API/
│       └── RestAPI.php              ← All REST endpoints
├── assets/
│   ├── css/front.css                ← Frontend styles
│   ├── css/admin.css                ← Admin styles
│   ├── js/front.js                  ← Frontend app (all ops via REST)
│   └── js/admin.js                  ← Admin JS (image upload, etc.)
└── templates/
    ├── catalog.php                  ← [bookcircle_catalog] shortcode
    ├── library.php                  ← [bookcircle_library] shortcode
    └── search.php                   ← [bookcircle_search] shortcode
```

---

## 🚀 Installation (No Composer Required!)

1. Upload `bookshare-plugin/` folder to `wp-content/plugins/`
2. Activate via **WordPress Admin → Plugins**
3. Tables are created automatically on activation

> The plugin includes a built-in PSR-4 autoloader. No `composer install` needed!
> If you want to use Composer anyway: `cd bookshare-plugin && composer install`

---

## 🔧 Admin Usage

### Adding a Book (Admin Only)
1. Go to **BookCircle → Books → Add New Book**
2. Enter the **Title** in the title field
3. Fill in the **Book Details** meta box:
   - Unique Code (auto-generated from title — can regenerate)
   - Select **Author** from dropdown (or add new author first)
   - Select **Publisher** from dropdown (or add new publisher first)
   - ISBN, Genre, Publication Year, Language, Pages
4. Upload **Cover Image** using the **Featured Image** box
5. Add description in the main editor
6. Click **Publish**

### Adding Authors
Go to **BookCircle → Authors → Add New Author**
- Name (title), Bio (content), Photo (featured image)

### Adding Publishers
Go to **BookCircle → Publishers → Add New Publisher**
- Name (title), Description (content), Logo (featured image)

### Managing Book Requests
Go to **BookCircle → Book Requests**
- See all pending requests from readers
- Click **Approve** → automatically creates a `bs_book` post
- Click **Reject** → marks as rejected, notifies in reader's library view

---

## 📌 Shortcodes

Add to any WordPress page:

```
[bookcircle_catalog]   — Book catalog with search + request form
[bookcircle_library]   — Personal library + rental management
[bookcircle_search]    — Find book by unique code
```

---

## 🗄️ Database Tables

| Table | Purpose |
|-------|---------|
| `wp_bs_library` | User ↔ CPT book relationships |
| `wp_bs_rentals` | Rental requests between readers |
| `wp_bs_book_requests` | Reader requests to admin to add books |

Book data lives in WordPress `wp_posts` (CPT) and `wp_postmeta`.

---

## 🔌 REST API

Base: `/wp-json/bookshare/v1/`

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/books` | — | Get catalog (`?search=`, `?genre=`) |
| GET | `/books/{id}` | — | Single book |
| GET | `/authors` | — | All authors |
| GET | `/publishers` | — | All publishers |
| GET | `/library` | ✓ | My library |
| POST | `/library` | ✓ | Add book |
| DELETE | `/library` | ✓ | Remove book |
| POST | `/library/toggle` | ✓ | Toggle public/private |
| GET | `/library/search?code=` | — | Find holders by unique code |
| POST | `/book-requests` | ✓ | Submit book listing request |
| GET | `/book-requests` | ✓ | My requests |
| POST | `/rentals/request` | ✓ | Request to rent |
| GET | `/rentals/incoming` | ✓ | Incoming rental requests |
| GET | `/rentals/outgoing` | ✓ | Outgoing rental requests |
| POST | `/rentals/{id}/status` | ✓ | Update rental status |

---

## 🔒 Permissions Summary

- **Admin** (`manage_options`): Full access — create, edit, delete CPT posts
- **Reader** (logged in): Library management, rental requests, book listing requests
- **Guest** (not logged in): Browse catalog and public libraries only