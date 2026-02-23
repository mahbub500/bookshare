<?php defined('ABSPATH') || exit; ?>
<div class="bs-wrap" data-bs-catalog>

  <div class="bs-form-header">
    <h2>📚 Book Catalog</h2>
    <?php if (is_user_logged_in()): ?>
    <button class="bs-btn bs-btn--primary js-toggle-add-form">+ Add Book</button>
    <?php endif; ?>
  </div>

  <?php if (is_user_logged_in()): ?>
  <form class="bs-add-form js-add-book-form">
    <div class="bs-form-grid">
      <input class="bs-input" name="title"       placeholder="Book Title *"  required>
      <input class="bs-input" name="author"      placeholder="Author *"      required>
      <input class="bs-input" name="publisher"   placeholder="Publisher">
      <input class="bs-input" name="isbn"        placeholder="ISBN">
      <input class="bs-input" name="genre"       placeholder="Genre">
      <input class="bs-input" name="cover_url"   placeholder="Cover Image URL">
      <textarea class="bs-input" name="description" placeholder="Description" rows="3" style="grid-column:1/-1"></textarea>
      <button class="bs-btn bs-btn--accent bs-btn--full" type="submit">Add to Catalog</button>
    </div>
  </form>
  <?php endif; ?>

  <div class="bs-search-bar">
    <input class="bs-input" type="search" placeholder="Search books, authors, publishers…" style="max-width:340px;" js-catalog-search>
  </div>
  <div class="bs-search-bar">
    <input class="bs-input js-catalog-search" type="search" placeholder="Search books, authors, publishers…" style="max-width:340px;">
  </div>

  <div class="bs-grid js-catalog-grid"></div>
</div>